<?php

namespace hexa_package_sapling\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use hexa_core\Models\Setting;

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
            $response = Http::timeout(10)
                ->post('https://api.sapling.ai/api/v1/aidetect', [
                    'key' => $key,
                    'text' => 'This is a test sentence to verify the API key works correctly.',
                ]);

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
            $response = Http::timeout(30)
                ->post('https://api.sapling.ai/api/v1/aidetect', [
                    'key' => $key,
                    'text' => $text,
                ]);

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
}
