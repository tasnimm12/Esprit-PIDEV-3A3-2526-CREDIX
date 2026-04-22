<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * Hugging Face computer vision service for claim image analysis.
 *
 * Computes damage metrics from real model output only.
 */
class HuggingFaceImageAnalysisService
{
    private const HF_API_BASE = 'https://api-inference.huggingface.co/models';

    private const DAMAGE_KEYWORDS = [
        'damage' => 1.00,
        'damaged' => 1.00,
        'wreck' => 1.00,
        'crash' => 0.95,
        'collision' => 0.95,
        'broken' => 0.90,
        'dent' => 0.85,
        'scratched' => 0.80,
        'scratch' => 0.80,
        'cracked' => 0.80,
        'debris' => 0.75,
        'smoke' => 0.70,
        'fire' => 0.95,
        'flood' => 0.95,
        'water' => 0.60,
        'injury' => 0.90,
        'ambulance' => 0.70,
        'accident' => 0.95,
    ];

    private const VEHICLE_PART_KEYWORDS = [
        'car',
        'truck',
        'bus',
        'vehicle',
        'wheel',
        'window',
        'door',
        'bumper',
        'hood',
        'motorcycle',
        'bicycle',
    ];

    public function __construct(
        private LoggerInterface $logger,
        private string $huggingFaceToken,
        private string $objectDetectionModel,
        private string $imageClassificationModel
    ) {
    }

    public function isConfigured(): bool
    {
        return trim($this->huggingFaceToken) !== '';
    }

    /**
     * Analyze insurance claim image using Hugging Face endpoints.
     */
    public function analyzeClaimImage(string $imagePath, string $insuranceType = 'AUTO'): array
    {
        try {
            if (!$this->isConfigured()) {
                return $this->insufficientData('Hugging Face token not configured');
            }

            if (!file_exists($imagePath)) {
                return $this->insufficientData('Image file not found');
            }

            $imageData = file_get_contents($imagePath);
            if ($imageData === false || $imageData === '') {
                return $this->insufficientData('Cannot read image file');
            }

            $detectionResults = $this->runObjectDetection($imageData);
            $classificationResults = $this->runImageClassification($imageData);

            return $this->computeDamageAnalysis(
                $detectionResults,
                $classificationResults,
                strtoupper($insuranceType)
            );
        } catch (\Throwable $e) {
            $this->logger->error('Hugging Face analysis error', ['error' => $e->getMessage()]);

            return $this->insufficientData('Unexpected analysis error: ' . $e->getMessage());
        }
    }

    private function runObjectDetection(string $imageData): array
    {
        return $this->callBinaryModel($this->objectDetectionModel, $imageData, [
            'wait_for_model' => true,
        ]);
    }

    private function runImageClassification(string $imageData): array
    {
        return $this->callBinaryModel($this->imageClassificationModel, $imageData, [
            'wait_for_model' => true,
        ]);
    }

    /**
     * Calls a Hugging Face model endpoint with binary image input.
     */
    private function callBinaryModel(string $model, string $imageData, array $options = []): array
    {
        try {
            $url = self::HF_API_BASE . '/' . $model;
            $waitForModel = ($options['wait_for_model'] ?? true) ? 'true' : 'false';
            $url .= '?wait_for_model=' . $waitForModel;

            $ch = curl_init($url);
            if ($ch === false) {
                return ['error' => 'Unable to initialize cURL'];
            }

            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 45,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->huggingFaceToken,
                    'Content-Type: application/octet-stream',
                ],
                CURLOPT_POSTFIELDS => $imageData,
            ]);

            $rawResponse = curl_exec($ch);
            $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($rawResponse === false) {
                return [
                    'error' => 'cURL request failed: ' . ($curlError ?: 'unknown error'),
                    'status_code' => $statusCode,
                ];
            }

            $payload = json_decode($rawResponse, true);

            if ($statusCode !== 200 || !is_array($payload)) {
                return [
                    'error' => 'Model request failed',
                    'status_code' => $statusCode,
                    'payload' => $payload,
                ];
            }

            if (isset($payload['error'])) {
                return [
                    'error' => $payload['error'],
                    'status_code' => $statusCode,
                    'payload' => $payload,
                ];
            }

            return $payload;
        } catch (\Throwable $e) {
            $this->logger->warning('Hugging Face model call failed', [
                'model' => $model,
                'error' => $e->getMessage(),
            ]);

            return ['error' => $e->getMessage()];
        }
    }

    private function computeDamageAnalysis(
        array $detectionResults,
        array $classificationResults,
        string $insuranceType
    ): array {
        if (isset($detectionResults['error']) && isset($classificationResults['error'])) {
            return $this->insufficientData('Both Hugging Face model calls failed');
        }

        $detectionMetrics = $this->extractDetectionMetrics($detectionResults);
        $classificationMetrics = $this->extractClassificationMetrics($classificationResults);

        if ($detectionMetrics['usable_predictions'] === 0 && $classificationMetrics['usable_predictions'] === 0) {
            return $this->insufficientData('No usable predictions returned by Hugging Face models');
        }

        $rawDamageScore =
            ($detectionMetrics['damage_signal'] * 0.55) +
            ($detectionMetrics['area_signal'] * 0.20) +
            ($classificationMetrics['damage_signal'] * 0.25);

        $damagePercentage = (int) round(min(1.0, max(0.0, $rawDamageScore)) * 100);

        $confidenceValues = array_merge(
            $detectionMetrics['confidence_values'],
            $classificationMetrics['confidence_values']
        );

        $overallConfidence = !empty($confidenceValues)
            ? (int) round((array_sum($confidenceValues) / count($confidenceValues)) * 100)
            : 0;

        $severity = $this->determineSeverity($insuranceType, $damagePercentage);

        $featureSet = array_values(array_unique(array_merge(
            $detectionMetrics['damage_features'],
            $classificationMetrics['damage_features']
        )));

        if (empty($featureSet)) {
            $featureSet = ['no-explicit-damage-keyword'];
        }

        return [
            'status' => 'OK',
            'damage_percentage' => $damagePercentage,
            'detected_damage_features' => $featureSet,
            'confidence_score' => $overallConfidence,
            'severity' => $severity,
            'reasoning' => $this->buildReasoning(
                $insuranceType,
                $damagePercentage,
                $overallConfidence,
                $detectionMetrics,
                $classificationMetrics
            ),
            'model_data' => [
                'models_used' => [
                    'object_detection' => $this->objectDetectionModel,
                    'image_classification' => $this->imageClassificationModel,
                ],
                'object_detection' => $detectionMetrics,
                'classification' => $classificationMetrics,
            ],
            'data_source' => 'Hugging Face model inference',
        ];
    }

    private function extractDetectionMetrics(array $detections): array
    {
        $damageSignalWeighted = 0.0;
        $confidenceValues = [];
        $damageFeatures = [];
        $totalWeight = 0.0;
        $vehicleBoxesAreaRatio = 0.0;
        $usablePredictions = 0;

        foreach ($detections as $item) {
            if (!is_array($item) || !isset($item['label'], $item['score'])) {
                continue;
            }

            $label = strtolower((string) $item['label']);
            $score = (float) $item['score'];
            if ($score <= 0) {
                continue;
            }

            $usablePredictions++;
            $confidenceValues[] = $score;

            $damageWeight = $this->keywordDamageWeight($label);
            if ($damageWeight > 0) {
                $damageFeatures[] = $label;
            }

            $weight = max($score, 0.05);
            $damageSignalWeighted += $damageWeight * $weight;
            $totalWeight += $weight;

            if ($this->isVehiclePartLabel($label)) {
                $vehicleBoxesAreaRatio += $this->extractBoxAreaRatio($item);
            }
        }

        $damageSignal = $totalWeight > 0 ? ($damageSignalWeighted / $totalWeight) : 0.0;

        return [
            'usable_predictions' => $usablePredictions,
            'damage_signal' => min(1.0, $damageSignal),
            'area_signal' => min(1.0, $vehicleBoxesAreaRatio),
            'confidence_values' => $confidenceValues,
            'damage_features' => $damageFeatures,
        ];
    }

    private function extractClassificationMetrics(array $classes): array
    {
        $damageSignalWeighted = 0.0;
        $confidenceValues = [];
        $damageFeatures = [];
        $totalWeight = 0.0;
        $usablePredictions = 0;

        foreach ($classes as $item) {
            if (!is_array($item) || !isset($item['label'], $item['score'])) {
                continue;
            }

            $label = strtolower((string) $item['label']);
            $score = (float) $item['score'];
            if ($score <= 0) {
                continue;
            }

            $usablePredictions++;
            $confidenceValues[] = $score;

            $damageWeight = $this->keywordDamageWeight($label);
            if ($damageWeight > 0) {
                $damageFeatures[] = $label;
            }

            $weight = max($score, 0.05);
            $damageSignalWeighted += $damageWeight * $weight;
            $totalWeight += $weight;
        }

        $damageSignal = $totalWeight > 0 ? ($damageSignalWeighted / $totalWeight) : 0.0;

        return [
            'usable_predictions' => $usablePredictions,
            'damage_signal' => min(1.0, $damageSignal),
            'confidence_values' => $confidenceValues,
            'damage_features' => $damageFeatures,
        ];
    }

    private function keywordDamageWeight(string $label): float
    {
        foreach (self::DAMAGE_KEYWORDS as $keyword => $weight) {
            if (str_contains($label, $keyword)) {
                return $weight;
            }
        }

        return 0.0;
    }

    private function isVehiclePartLabel(string $label): bool
    {
        foreach (self::VEHICLE_PART_KEYWORDS as $keyword) {
            if (str_contains($label, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Estimate the normalized area ratio from DETR bounding boxes.
     */
    private function extractBoxAreaRatio(array $prediction): float
    {
        if (!isset($prediction['box']) || !is_array($prediction['box'])) {
            return 0.0;
        }

        $box = $prediction['box'];
        if (!isset($box['xmin'], $box['xmax'], $box['ymin'], $box['ymax'])) {
            return 0.0;
        }

        $width = max(0.0, (float) $box['xmax'] - (float) $box['xmin']);
        $height = max(0.0, (float) $box['ymax'] - (float) $box['ymin']);
        $area = $width * $height;

        // Normalize with a practical fixed image canvas approximation.
        // DETR outputs typically assume around 800x800 when resized.
        return min(1.0, $area / (800.0 * 800.0));
    }

    private function determineSeverity(string $insuranceType, int $damage): string
    {
        if ($insuranceType === 'HEALTH') {
            if ($damage >= 60) {
                return 'MAJOR';
            }
            if ($damage >= 30) {
                return 'MEDIUM';
            }

            return 'MINOR';
        }

        if ($damage >= 70) {
            return 'MAJOR';
        }
        if ($damage >= 35) {
            return 'MEDIUM';
        }

        return 'MINOR';
    }

    private function buildReasoning(
        string $insuranceType,
        int $damagePercentage,
        int $confidence,
        array $detectionMetrics,
        array $classificationMetrics
    ): string {
        return sprintf(
            'HF inference for %s: damage=%d%%, confidence=%d%%, detection_signal=%.2f, area_signal=%.2f, classification_signal=%.2f.',
            $insuranceType,
            $damagePercentage,
            $confidence,
            $detectionMetrics['damage_signal'],
            $detectionMetrics['area_signal'],
            $classificationMetrics['damage_signal']
        );
    }

    private function insufficientData(string $reason): array
    {
        return [
            'status' => 'INSUFFICIENT_DATA',
            'reason' => $reason,
            'damage_percentage' => null,
            'detected_damage_features' => [],
            'confidence_score' => 0,
            'severity' => null,
            'message' => 'Cannot compute reliable damage analysis from model output.',
            'data_source' => 'Hugging Face model inference',
        ];
    }
}
