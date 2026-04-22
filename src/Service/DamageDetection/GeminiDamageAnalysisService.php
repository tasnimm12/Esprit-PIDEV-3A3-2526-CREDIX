<?php

namespace App\Service\DamageDetection;

use Psr\Log\LoggerInterface;

class GeminiDamageAnalysisService
{
    private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models';
    private const INLINE_IMAGE_MAX_BYTES = 18_000_000;
    private const MAX_RETRIES = 3;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $apiKey,
        private readonly string $model = 'gemini-2.5-flash'
    ) {
    }

    public function isConfigured(): bool
    {
        return trim($this->apiKey) !== '';
    }

    public function analyzeImage(string $imagePath): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        if (!is_file($imagePath)) {
            $this->logger->warning('Gemini analysis skipped: file not found', ['path' => $imagePath]);
            return null;
        }

        $fileSize = filesize($imagePath);
        if ($fileSize === false || $fileSize <= 0) {
            return null;
        }

        if ($fileSize > self::INLINE_IMAGE_MAX_BYTES) {
            $this->logger->warning('Gemini analysis skipped: image too large for inline request', [
                'path' => $imagePath,
                'bytes' => $fileSize,
            ]);

            return null;
        }

        $mimeType = $this->detectMimeType($imagePath);
        if ($mimeType === null) {
            $this->logger->warning('Gemini analysis skipped: unsupported mime type', ['path' => $imagePath]);
            return null;
        }

        $imageData = file_get_contents($imagePath);
        if ($imageData === false || $imageData === '') {
            return null;
        }

        $payload = [
            'contents' => [[
                'parts' => [
                    [
                        'text' => $this->buildPrompt(),
                    ],
                    [
                        'inline_data' => [
                            'mime_type' => $mimeType,
                            'data' => base64_encode($imageData),
                        ],
                    ],
                ],
            ]],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
            ],
        ];

        try {
            $response = $this->callGeminiWithRetry($payload);
            $text = $this->extractResponseText($response);

            if ($text === null) {
                return null;
            }

            $parsed = $this->parseJsonResponse($text);
            if ($parsed === null) {
                $this->logger->warning('Gemini response could not be parsed as JSON', ['text' => $text]);
                return null;
            }

            return $this->normalizeAnalysis($parsed);
        } catch (\Throwable $e) {
            $this->logger->error('Gemini image analysis failed', [
                'path' => $imagePath,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function callGeminiWithRetry(array $payload): array
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < self::MAX_RETRIES) {
            try {
                return $this->callGemini($payload);
            } catch (\RuntimeException $e) {
                $lastException = $e;
                $attempt++;

                if (!$this->shouldRetry($e, $attempt)) {
                    throw $e;
                }

                usleep($attempt * 400000);
            }
        }

        throw $lastException ?? new \RuntimeException('Gemini request failed.');
    }

    private function callGemini(array $payload): array
    {
        $url = sprintf(
            '%s/%s:generateContent',
            self::API_BASE,
            rawurlencode($this->model)
        );

        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('Unable to initialize cURL for Gemini request.');
        }

        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-goog-api-key: ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => $body,
        ]);

        $rawResponse = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($rawResponse === false) {
            throw new \RuntimeException('Gemini cURL error: ' . ($error ?: 'unknown error'));
        }

        $decoded = json_decode($rawResponse, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Gemini returned a non-JSON response.');
        }

        if ($statusCode >= 400) {
            $message = $decoded['error']['message'] ?? ('HTTP ' . $statusCode);
            throw new \RuntimeException('Gemini API error: ' . $message, $statusCode);
        }

        return $decoded;
    }

    private function shouldRetry(\RuntimeException $exception, int $attempt): bool
    {
        if ($attempt >= self::MAX_RETRIES) {
            return false;
        }

        $message = strtolower($exception->getMessage());
        $statusCode = $exception->getCode();

        if (in_array($statusCode, [429, 500, 502, 503, 504], true)) {
            return true;
        }

        return str_contains($message, 'high demand')
            || str_contains($message, 'rate limit')
            || str_contains($message, 'temporar')
            || str_contains($message, 'unavailable');
    }

    private function extractResponseText(array $response): ?string
    {
        $parts = $response['candidates'][0]['content']['parts'] ?? null;
        if (!is_array($parts)) {
            return null;
        }

        $textParts = [];
        foreach ($parts as $part) {
            if (isset($part['text']) && is_string($part['text'])) {
                $textParts[] = $part['text'];
            }
        }

        if ($textParts === []) {
            return null;
        }

        return trim(implode("\n", $textParts));
    }

    private function parseJsonResponse(string $text): ?array
    {
        $trimmed = trim($text);
        $trimmed = preg_replace('/^```json\s*/i', '', $trimmed) ?? $trimmed;
        $trimmed = preg_replace('/^```\s*/', '', $trimmed) ?? $trimmed;
        $trimmed = preg_replace('/\s*```$/', '', $trimmed) ?? $trimmed;

        $decoded = json_decode($trimmed, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function normalizeAnalysis(array $data): array
    {
        $damagePercentage = (int) max(0, min(100, (int) ($data['damage_percentage'] ?? 0)));
        $confidencePercent = (float) ($data['confidence_score'] ?? 0);
        if ($confidencePercent > 1) {
            $confidencePercent /= 100;
        }

        $features = $data['detected_features'] ?? [];
        if (!is_array($features)) {
            $features = [];
        }

        $features = array_values(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            $features
        )));

        if ($features === []) {
            $features = ['gemini_visual_assessment'];
        }

        return [
            'damage_percentage' => $damagePercentage,
            'detected_features' => $features,
            'confidence_score' => max(0.0, min(1.0, $confidencePercent)),
            'image_quality' => max(0.4, min(1.0, (float) ($data['image_quality'] ?? 0.85))),
            'color_analysis' => [
                'red_avg' => 0,
                'green_avg' => 0,
                'blue_avg' => 0,
                'dark_ratio' => 0,
            ],
            'edge_complexity' => max(0.0, min(1.0, (float) ($data['edge_complexity'] ?? ($damagePercentage / 100)))),
            'brightness_variance' => max(0.0, min(1.0, (float) ($data['brightness_variance'] ?? ($damagePercentage / 100)))),
            'dark_area_ratio' => max(0.0, min(1.0, (float) ($data['dark_area_ratio'] ?? ($damagePercentage / 100)))),
            'saturation_variance' => max(0.0, min(1.0, (float) ($data['saturation_variance'] ?? 0.3))),
            'gemini_reasoning' => (string) ($data['reasoning'] ?? ''),
        ];
    }

    private function detectMimeType(string $imagePath): ?string
    {
        $extension = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => null,
        };
    }

    private function buildPrompt(): string
    {
        return <<<PROMPT
Analyze this insurance claim evidence image and estimate visible damage.

Return JSON only with this exact shape:
{
  "damage_percentage": 0,
  "confidence_score": 0.0,
  "detected_features": ["feature"],
  "image_quality": 0.0,
  "edge_complexity": 0.0,
  "brightness_variance": 0.0,
  "dark_area_ratio": 0.0,
  "saturation_variance": 0.0,
  "reasoning": "short explanation"
}

Rules:
- damage_percentage must be an integer from 0 to 100 representing visible damaged area.
- confidence_score must be a number from 0.0 to 1.0.
- image_quality, edge_complexity, brightness_variance, dark_area_ratio, saturation_variance must be numbers from 0.0 to 1.0.
- detected_features must be short snake_case labels such as dent, crack, scratch, shattered_glass, rust, paint_damage, broken_part, flood_damage, fire_damage, structural_damage, impact_damage, minor_damage.
- If the image does not clearly show damage, return a low damage_percentage and explain uncertainty.
- Do not include markdown or code fences.
PROMPT;
    }
}
