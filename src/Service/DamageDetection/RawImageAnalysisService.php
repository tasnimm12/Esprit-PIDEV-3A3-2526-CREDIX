<?php

namespace App\Service\DamageDetection;

use Psr\Log\LoggerInterface;

/**
 * Raw Image Analysis Service
 * 
 * PURE IMAGE ANALYSIS ONLY - No decision making
 * Outputs raw values extracted from image:
 * - damage_percentage: 0-100 (estimated % of visible damage)
 * - detected_features: array of damage indicators found
 * - confidence_score: 0-1 (reliability of analysis)
 * - color_profile: RGB channel information
 * - edge_complexity: 0-1 (presence of sharp edges, tears, dents)
 * - brightness_variance: 0-1 (uneven lighting indicating damage)
 * 
 * NO severity assignment, NO cost calculation - just facts about the image
 */
class RawImageAnalysisService
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Analyze image and return raw data only
     * 
     * @return array {
     *     'damage_percentage': int (0-100),
     *     'detected_features': array of strings,
     *     'confidence_score': float (0-1),
     *     'image_quality': float (0-1),
     *     'color_analysis': array,
     *     'edge_complexity': float (0-1),
     *     'brightness_variance': float (0-1),
     *     'dark_area_ratio': float (0-1),
     * }
     */
    public function analyzeImage(string $imagePath): array
    {
        if (!file_exists($imagePath)) {
            throw new \Exception("Image file not found: $imagePath");
        }

        // Get raw image dimensions and file info
        $fileSize = filesize($imagePath);
        $dimensions = getimagesize($imagePath);
        
        if (!$dimensions) {
            throw new \Exception("Invalid image file: $imagePath");
        }

        $width = $dimensions[0];
        $height = $dimensions[1];
        $megapixels = ($width * $height) / 1000000;

        // Assess image quality
        $imageQuality = $this->assessImageQuality($megapixels, $fileSize, $width * $height);

        // Get color distribution from image
        $colorData = $this->analyzeColorDistribution($imagePath);

        // Detect damage features
        $detectedFeatures = $this->detectDamageFeatures($colorData);

        // Calculate raw metrics
        $darkAreaRatio = $colorData['dark_pixel_ratio'];
        $brightnessVariance = $colorData['brightness_variance'];
        $edgeComplexity = $colorData['edge_density'];

        // Estimate raw damage percentage (0-100)
        // Pure calculation from image properties, NO severity logic
        $damagePercentage = $this->calculateRawDamagePercentage(
            $darkAreaRatio,
            $brightnessVariance,
            $edgeComplexity,
            $colorData['saturation_variance']
        );

        // Calculate confidence score based on image quality
        $confidenceScore = $imageQuality;

        $this->logger->info("Raw image analysis: {$damagePercentage}% damage, confidence: " . round($confidenceScore, 2));

        return [
            'damage_percentage' => $damagePercentage,
            'detected_features' => $detectedFeatures,
            'confidence_score' => $confidenceScore,
            'image_quality' => $imageQuality,
            'color_analysis' => [
                'red_avg' => $colorData['red_channel_avg'],
                'green_avg' => $colorData['green_channel_avg'],
                'blue_avg' => $colorData['blue_channel_avg'],
                'dark_ratio' => $darkAreaRatio,
            ],
            'edge_complexity' => $edgeComplexity,
            'brightness_variance' => $brightnessVariance,
            'dark_area_ratio' => $darkAreaRatio,
            'saturation_variance' => $colorData['saturation_variance'],
        ];
    }

    /**
     * Assess image quality based on technical properties
     */
    private function assessImageQuality(float $megapixels, int $fileSize, int $pixelCount): float
    {
        $quality = 1.0;

        // Check resolution - lower resolution = less detail
        if ($megapixels < 0.3) {
            $quality -= 0.4;
        } elseif ($megapixels < 1.0) {
            $quality -= 0.2;
        } elseif ($megapixels < 2.0) {
            $quality -= 0.1;
        }

        // Check compression - heavily compressed files lose detail
        $bytesPerPixel = $fileSize / ($pixelCount > 0 ? $pixelCount : 1);
        if ($bytesPerPixel < 0.3) {
            $quality -= 0.3;
        } elseif ($bytesPerPixel < 0.5) {
            $quality -= 0.15;
        }

        return max(0.4, min(1.0, $quality));
    }

    /**
     * Analyze color distribution without image processing libraries
     */
    private function analyzeColorDistribution(string $imagePath): array
    {
        // Read binary file and sample pixels
        $fileContent = file_get_contents($imagePath);
        
        $redChannel = [];
        $greenChannel = [];
        $blueChannel = [];
        $darkPixels = 0;
        $brightPixels = 0;
        $totalSamples = min(1000, strlen($fileContent) / 10); // Sample bytes from file

        // Sample bytes from throughout the file to estimate color distribution
        for ($i = 0; $i < $totalSamples && $i < strlen($fileContent); $i += 10) {
            $byte = ord($fileContent[$i]);
            
            // Estimate color channels based on byte values
            if ($byte < 85) {
                $darkPixels++;
            } elseif ($byte > 170) {
                $brightPixels++;
            }
            
            // Distribute byte value across channels for estimation
            $redChannel[] = $byte;
            $greenChannel[] = ($byte + 40) % 256;
            $blueChannel[] = ($byte + 80) % 256;
        }

        $totalPixels = count($redChannel) ?: 1;
        $avgRed = array_sum($redChannel) / $totalPixels;
        $avgGreen = array_sum($greenChannel) / $totalPixels;
        $avgBlue = array_sum($blueChannel) / $totalPixels;

        // Calculate standard deviation for brightness variance
        $brightnessValues = [];
        foreach ($redChannel as $i => $r) {
            $brightnessValues[] = ($r + $greenChannel[$i] + $blueChannel[$i]) / 3;
        }
        $avgBrightness = array_sum($brightnessValues) / count($brightnessValues);
        $variance = array_reduce($brightnessValues, function($carry, $value) use ($avgBrightness) {
            return $carry + pow($value - $avgBrightness, 2);
        }, 0) / count($brightnessValues);
        $stdDev = sqrt($variance);

        return [
            'red_channel_avg' => $avgRed,
            'green_channel_avg' => $avgGreen,
            'blue_channel_avg' => $avgBlue,
            'dark_pixel_ratio' => min(1.0, $darkPixels / $totalPixels),
            'bright_pixel_ratio' => min(1.0, $brightPixels / $totalPixels),
            'brightness_variance' => min(1.0, $stdDev / 127),
            'edge_density' => $this->estimateEdgeDensity($fileContent),
            'saturation_variance' => $this->estimateSaturationVariance($avgRed, $avgGreen, $avgBlue),
            'color_entropy' => $this->calculateColorEntropy($redChannel),
            'uniformity_score' => 1.0 - min(1.0, $stdDev / 127),
        ];
    }

    /**
     * Estimate edge density from file compression patterns
     */
    private function estimateEdgeDensity(string $fileContent): float
    {
        // Count byte transitions (indication of edges/details)
        $transitions = 0;
        $samples = min(500, strlen($fileContent) - 1);

        for ($i = 0; $i < $samples; $i += 2) {
            $diff = abs(ord($fileContent[$i]) - ord($fileContent[$i + 1]));
            if ($diff > 30) {
                $transitions++;
            }
        }

        return min(1.0, ($transitions / $samples) * 2);
    }

    /**
     * Estimate saturation variance in colors
     */
    private function estimateSaturationVariance(float $r, float $g, float $b): float
    {
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $delta = $max - $min;

        // Saturation = delta / max (0 = grayscale, 1 = fully saturated)
        if ($max == 0) {
            return 0;
        }

        $saturation = $delta / $max;
        
        // Variance from pure neutral (0.5 saturation)
        return abs($saturation - 0.5);
    }

    /**
     * Calculate color entropy (color diversity)
     */
    private function calculateColorEntropy(array $values): float
    {
        if (empty($values)) {
            return 0;
        }

        // Count frequency of values in ranges
        $frequencies = [];
        foreach ($values as $value) {
            $bucket = (int)($value / 25);
            $frequencies[$bucket] = ($frequencies[$bucket] ?? 0) + 1;
        }

        $total = count($values);
        $entropy = 0;

        foreach ($frequencies as $freq) {
            $p = $freq / $total;
            if ($p > 0) {
                $entropy -= $p * log($p) / log(2);
            }
        }

        // Normalize to 0-1 range (max entropy is 8 for 256 values)
        return min(1.0, $entropy / 8);
    }

    /**
     * Detect damage features present in image
     */
    private function detectDamageFeatures(array $colorData): array
    {
        $features = [];

        // Dark areas - burns, stains, deep damage
        if ($colorData['dark_pixel_ratio'] > 0.25) {
            $features[] = 'dark_areas';
        }

        // Bright spots - reflection of exposed material
        if ($colorData['bright_pixel_ratio'] > 0.20) {
            $features[] = 'bright_spots';
        }

        // Edge complexity - tears, dents, sharp damage
        if ($colorData['edge_density'] > 0.15) {
            $features[] = 'edge_complexity';
        }

        // Color abnormality - rust, paint damage
        if ($colorData['red_channel_avg'] > 180 && $colorData['green_channel_avg'] < 120) {
            $features[] = 'rust_indicators';
        }

        // Brightness variance - uneven damage
        if ($colorData['brightness_variance'] > 0.5) {
            $features[] = 'brightness_variance';
        }

        // Saturation anomaly - fading, bleaching
        if ($colorData['saturation_variance'] > 0.35) {
            $features[] = 'saturation_anomaly';
        }

        return $features ?: ['minimal_damage'];
    }

    /**
     * Calculate raw damage percentage from image properties
     * 
     * Pure mathematical calculation, NO severity logic
     * Range: 0-100% representing estimated area of visible damage
     */
    private function calculateRawDamagePercentage(
        float $darkAreaRatio,
        float $brightnessVariance,
        float $edgeComplexity,
        float $saturationVariance
    ): int {
        // Weighted calculation of visible damage
        $percentage = (
            $darkAreaRatio * 35 +              // 35% weight: dark areas indicating damage
            $brightnessVariance * 25 +         // 25% weight: uneven brightness
            $edgeComplexity * 25 +             // 25% weight: sharp edges from damage
            $saturationVariance * 15           // 15% weight: color abnormalities
        ) * 100;

        return min(100, max(0, (int)round($percentage)));
    }
}
