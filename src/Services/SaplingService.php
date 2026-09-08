<?php

namespace hexa_package_sapling\Services;

use hexa_core\AI\Contracts\AiTransactionRecorder;
use hexa_core\Models\Setting;
use hexa_core\Security\Http\OutboundHttpException;
use hexa_core\Security\Http\OutboundHttpResponse;
use hexa_core\Security\Http\SafeOutboundHttpClient;
use hexa_core\Services\CredentialService;
use Illuminate\Support\Facades\Log;
use Throwable;

class SaplingService
{
    private const ENDPOINT = 'https://api.sapling.ai/api/v1/aidetect';

    private const PROBE_TIMEOUT_SECONDS = 10;

    private const DETECTION_TIMEOUT_SECONDS = 30;

    private const MAX_RESPONSE_BYTES = 4 * 1024 * 1024;

    public function __construct(
        private readonly SafeOutboundHttpClient $http,
        private readonly CredentialService $credentials,
    ) {}

    private function getApiKey(): ?string
    {
        return $this->credentials->get('sapling', 'api_key')
            ?: Setting::getValue('sapling_api_key');
    }

    /** @return array{success: bool, message: string} */
    public function testApiKey(?string $apiKey = null): array
    {
        $key = $this->validApiKey($apiKey ?? $this->getApiKey());
        if ($key === null) {
            return ['success' => false, 'message' => 'No Sapling API key configured.'];
        }

        try {
            $response = $this->requestDetection(
                $key,
                'This is a test sentence to verify the API key works correctly.',
                self::PROBE_TIMEOUT_SECONDS,
                'detector.connection_test',
            );
        } catch (Throwable $exception) {
            $this->logTransportFailure('connection_test', $exception);

            return ['success' => false, 'message' => 'Sapling API key validation failed safely.'];
        }

        if ($response->successful()) {
            return ['success' => true, 'message' => 'Sapling API key is valid.'];
        }
        if (in_array($response->status, [401, 403], true)) {
            return ['success' => false, 'message' => 'Invalid API key.'];
        }

        return ['success' => false, 'message' => "Sapling returned HTTP {$response->status}."];
    }

    /** @return array{success: bool, message: string, data: array|null} */
    public function detect(string $text): array
    {
        $key = $this->validApiKey($this->getApiKey());
        if ($key === null) {
            return ['success' => false, 'message' => 'No Sapling API key configured.', 'data' => null];
        }

        if (strlen($text) < 50) {
            return ['success' => false, 'message' => 'Text too short for AI detection (minimum ~50 characters).', 'data' => null];
        }

        try {
            $response = $this->requestDetection($key, $text, self::DETECTION_TIMEOUT_SECONDS, 'detector.scan');
        } catch (Throwable $exception) {
            $this->logTransportFailure('scan', $exception);

            return ['success' => false, 'message' => 'Sapling request failed safely.', 'data' => null];
        }

        if (! $response->successful()) {
            return ['success' => false, 'message' => "Sapling returned HTTP {$response->status}.", 'data' => null];
        }

        $data = $this->jsonObject($response);
        $score = is_numeric($data['score'] ?? null) ? (float) $data['score'] * 100 : 0.0;
        $sentences = is_array($data['sentence_scores'] ?? null) ? $data['sentence_scores'] : [];

        return [
            'success' => true,
            'message' => 'AI detection complete. Score: '.number_format($score, 1).'%.',
            'data' => [
                'score' => round($score, 2),
                'sentence_scores' => $sentences,
            ],
        ];
    }

    private function requestDetection(string $apiKey, string $text, int $timeout, string $operation): OutboundHttpResponse
    {
        $units = ['characters' => mb_strlen($text), 'words' => str_word_count($text)];
        $span = app(AiTransactionRecorder::class)->start([
            'provider' => 'sapling',
            'package' => 'hexawebsystems/laravel-hexa-package-sapling',
            'model' => 'sapling-ai-detector-v1',
            'operation' => $operation,
            'endpoint' => '/api/v1/aidetect',
            'request_metadata' => array_merge($units, ['timeout_seconds' => $timeout]),
        ]);

        try {
            $response = $this->http->request('POST', self::ENDPOINT, [
                'headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
                'body' => json_encode(['key' => $apiKey, 'text' => $text], JSON_THROW_ON_ERROR),
                'timeout' => $timeout,
                'max_bytes' => self::MAX_RESPONSE_BYTES,
                'max_redirects' => 0,
            ]);
            $payload = $this->jsonObject($response);
            $attributes = [
                'provider_request_id' => $response->headerValues('x-request-id')[0] ?? null,
                'http_status' => $response->status,
                'usage' => $units,
                'response_metadata' => [
                    'score' => $payload['score'] ?? null,
                    'sentence_score_count' => count(is_array($payload['sentence_scores'] ?? null) ? $payload['sentence_scores'] : []),
                ],
            ];

            if ($response->successful()) {
                $span->succeed($attributes);
            } else {
                $span->fail('Sapling request failed.', array_merge($attributes, [
                    'error_type' => 'sapling_http_error',
                ]));
            }

            return $response;
        } catch (Throwable $exception) {
            $span->fail(
                $exception instanceof OutboundHttpException ? $exception : 'Sapling request failed.',
                ['usage' => $units],
            );

            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    private function jsonObject(OutboundHttpResponse $response): array
    {
        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    private function validApiKey(?string $apiKey): ?string
    {
        $apiKey = trim((string) $apiKey);

        return $apiKey !== ''
            && strlen($apiKey) <= 4096
            && preg_match('/[\x00-\x1f\x7f]/', $apiKey) !== 1
                ? $apiKey
                : null;
    }

    private function logTransportFailure(string $operation, Throwable $exception): void
    {
        Log::warning('Sapling request failed safely', [
            'operation' => $operation,
            'failure_code' => $exception instanceof OutboundHttpException
                ? $exception->failureCode()
                : 'request_failed',
        ]);
    }
}
