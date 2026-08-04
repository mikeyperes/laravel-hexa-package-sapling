<?php

namespace hexa_package_sapling\Services;

use hexa_core\AI\Contracts\AiTransactionRecorder;
use hexa_core\Models\Setting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SaplingService
{
    /**
     * @return string|null
     */
    private function getApiKey(): ?string
    {
        return Setting::getValue('sapling_api_key');
    }

    /**
     * Test the API key.
     *
     * @param string|null $apiKey Override key to test.
     * @return array{success: bool, message: string}
     */
    public function testApiKey(?string $apiKey = null): array
    {
        $key = $apiKey ?? $this->getApiKey();
        if (!$key) {
            return ['success' => false, 'message' => 'No Sapling API key configured.'];
        }

        try {
            $response = $this->requestDetection(
                $key,
                'This is a test sentence to verify the API key works correctly.',
                10,
                'detector.connection_test',
            );

            if ($response->successful()) {
                return ['success' => true, 'message' => 'Sapling API key is valid.'];
            }
            if ($response->status() === 401 || $response->status() === 403) {
                return ['success' => false, 'message' => 'Invalid API key.'];
            }
            return ['success' => false, 'message' => "Sapling returned HTTP {$response->status()}."];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Detect AI-generated content.
     *
     * @param string $text The text to analyze.
     * @return array{success: bool, message: string, data: array|null}
     */
    public function detect(string $text): array
    {
        $key = $this->getApiKey();
        if (!$key) {
            return ['success' => false, 'message' => 'No Sapling API key configured.', 'data' => null];
        }

        if (strlen($text) < 50) {
            return ['success' => false, 'message' => 'Text too short for AI detection (minimum ~50 characters).', 'data' => null];
        }

        try {
            $response = $this->requestDetection($key, $text, 30, 'detector.scan');

            if ($response->successful()) {
                $data = $response->json();
                $score = ($data['score'] ?? 0) * 100;
                $sentences = $data['sentence_scores'] ?? [];

                return [
                    'success' => true,
                    'message' => 'AI detection complete. Score: ' . number_format($score, 1) . '%.',
                    'data' => [
                        'score' => round($score, 2),
                        'sentence_scores' => $sentences,
                    ],
                ];
            }

            $error = $response->json();
            return ['success' => false, 'message' => 'Sapling error: ' . ($error['msg'] ?? "HTTP {$response->status()}"), 'data' => null];
        } catch (\Exception $e) {
            Log::error('SaplingService::detect error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => null];
        }
    }

    private function requestDetection(string $apiKey, string $text, int $timeout, string $operation): Response
    {
        $endpoint = 'https://api.sapling.ai/api/v1/aidetect';
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
            $response = Http::timeout($timeout)->post($endpoint, [
                'key' => $apiKey,
                'text' => $text,
            ]);
            $attributes = [
                'provider_request_id' => $response->header('x-request-id'),
                'http_status' => $response->status(),
                'usage' => $units,
                'response_metadata' => [
                    'score' => $response->json('score'),
                    'sentence_score_count' => count((array) $response->json('sentence_scores', [])),
                ],
            ];

            if ($response->successful()) {
                $span->succeed($attributes);
            } else {
                $span->fail((string) ($response->json('msg') ?? 'Sapling request failed.'), array_merge($attributes, [
                    'error_type' => 'sapling_http_error',
                ]));
            }

            return $response;
        } catch (\Throwable $e) {
            $span->fail($e, ['usage' => $units]);

            throw $e;
        }
    }
}
