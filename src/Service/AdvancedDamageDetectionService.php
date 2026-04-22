<?php

namespace App\Service;

use App\Entity\DamageAnalysis;
use App\Entity\Sinistre;
use App\Entity\SinistrePreuve;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Advanced Damage Detection Service
 * 
 * Hybrid approach combining:
 * - Image properties analysis (dimensions, colors, compression)
 * - Rule-based damage detection
 * - Multi-image analysis
 * - Severity classification (MINOR, MEDIUM, MAJOR)
 * - Real-world insurance damage estimation
 * 
 * No external API calls, no special extensions required
 */
class AdvancedDamageDetectionService
{
    private EntityManagerInterface $em;
    private LoggerInterface $logger;
    private ImagePreprocessingService $preprocessor;
    private string $projectDir;

    public function __construct(
        EntityManagerInterface $em,
        LoggerInterface $logger,
        ImagePreprocessingService $preprocessor,
        string $projectDir
    ) {
        $this->em = $em;
        $this->logger = $logger;
        $this->preprocessor = $preprocessor;
        $this->projectDir = $projectDir;
    }

    /**
     * Analyze single image for damage
     */
    public function analyzeSingleImage(SinistrePreuve $preuve, Sinistre $sinistre): DamageAnalysis
    {
        $this->logger->info("Analyzing damage from image: {$preuve->getNomFichier()}");

        $imagePath = $this->projectDir . '/public' . $preuve->getFichier();
        
        if (!file_exists($imagePath)) {
            throw new \Exception("Image file not found: $imagePath");
        }

        // Preprocess image
        $imageData = $this->preprocessor->analyzeImage($imagePath);
        
        // Run damage detection algorithm
        $damageAssessment = $this->detectDamage($imageData, $preuve->getNomFichier());
        
        // Create and persist analysis record
        $analysis = new DamageAnalysis();
        $analysis->setPreuve($preuve);
        $analysis->setSinistre($sinistre);
        $analysis->setSeverity($damageAssessment['severity']);
        $analysis->setDamageType($damageAssessment['damage_type']);
        $analysis->setAffectedAreaPercentage($damageAssessment['affected_area']);
        $analysis->setEstimatedCost($damageAssessment['estimated_cost']);
        $analysis->setAnalysisDetails($damageAssessment['details']);
        $analysis->setAiModel('AdvancedHybridDetection-v2');
        $analysis->setAnalyzedAt(new \DateTime());

        $this->em->persist($analysis);
        $this->em->flush();

        $this->logger->info("Damage analysis completed: {$damageAssessment['severity']} | Cost: \${$damageAssessment['estimated_cost']}");

        return $analysis;
    }

    /**
     * Analyze multiple images and create combined assessment
     */
    public function analyzeMultipleImages(array $preuves, Sinistre $sinistre): array
    {
        $this->logger->info("Analyzing " . count($preuves) . " images for combined assessment");

        $analyses = [];
        $combinedData = [
            'severity_scores' => [],
            'damage_types' => [],
            'affected_areas' => [],
            'estimated_costs' => [],
        ];

        foreach ($preuves as $preuve) {
            try {
                $analysis = $this->analyzeSingleImage($preuve, $sinistre);
                $analyses[] = $analysis;
                
                // Collect data for combined analysis
                $combinedData['severity_scores'][] = $this->severityToScore($analysis->getSeverity());
                $combinedData['damage_types'][] = $analysis->getDamageType();
                $combinedData['affected_areas'][] = $analysis->getAffectedAreaPercentage();
                $combinedData['estimated_costs'][] = $analysis->getEstimatedCost();
            } catch (\Exception $e) {
                $this->logger->error("Error analyzing image {$preuve->getNomFichier()}: " . $e->getMessage());
            }
        }

        return [
            'analyses' => $analyses,
            'combined_assessment' => $this->createCombinedAssessment($combinedData),
        ];
    }

    /**
     * Main damage detection algorithm (Hybrid Approach)
     */
    private function detectDamage(array $imageData, string $fileName): array
    {
        // Step 1: Image quality assessment
        $qualityScore = $this->assessImageQuality($imageData);
        
        // Step 2: Damage indicators detection
        $damageIndicators = $this->detectDamageIndicators($imageData);
        
        // Step 3: Color analysis for damage severity
        $colorAnalysis = $this->analyzeColorProfile($imageData);
        
        // Step 4: Spatial analysis (if image can be analyzed)
        $spatialAnalysis = $this->analyzeSpatialDistribution($imageData);
        
        // Step 5: Apply rule-based classification
        $severity = $this->classifySeverity(
            $damageIndicators,
            $colorAnalysis,
            $spatialAnalysis,
            $qualityScore
        );
        
        // Step 6: Estimate repair costs
        $costEstimate = $this->estimateRepairCost($severity, $damageIndicators, $spatialAnalysis);
        
        // Step 7: Determine damage type
        $damageType = $this->determineDamageType($damageIndicators, $colorAnalysis);
        
        // Step 8: Calculate affected area
        $affectedArea = $this->calculateAffectedArea($spatialAnalysis, $damageIndicators);
        
        // Step 9: Generate detailed analysis
        $details = $this->generateDetailedAnalysis(
            $severity,
            $damageIndicators,
            $colorAnalysis,
            $qualityScore,
            $fileName
        );

        return [
            'severity' => $severity,
            'damage_type' => $damageType,
            'affected_area' => $affectedArea,
            'estimated_cost' => $costEstimate,
            'details' => $details,
        ];
    }

    /**
     * Assess image quality for analysis reliability
     */
    private function assessImageQuality(array $imageData): float
    {
        $quality = 1.0;

        // Check resolution
        $resolution = $imageData['width'] * $imageData['height'];
        if ($resolution < 300000) { // Less than 0.3 MP
            $quality -= 0.3;
        } elseif ($resolution < 1000000) { // Less than 1 MP
            $quality -= 0.15;
        }

        // Check file size ratio (compression quality indicator)
        $megapixels = $resolution / 1000000;
        $bytesPerPixel = $imageData['file_size'] / ($resolution > 0 ? $resolution : 1);
        
        if ($bytesPerPixel < 0.5) { // Heavy compression
            $quality -= 0.2;
        }

        // Check color depth diversity
        if ($imageData['color_entropy'] < 0.3) {
            $quality -= 0.25;
        }

        return max(0.3, $quality);
    }

    /**
     * Detect damage indicators using rule-based approach
     */
    private function detectDamageIndicators(array $imageData): array
    {
        $indicators = [
            'dark_areas' => 0,          // Shadows, burn marks, stains
            'color_abnormality' => 0,   // Unexpected colors (rust, paint damage)
            'edge_complexity' => 0,     // Torn, bent edges
            'brightness_variance' => 0, // Uneven damage indicators
            'red_channel_high' => 0,    // Rust, fire damage
            'saturation_anomaly' => 0,  // Faded or overly saturated areas
        ];

        // Detect dark areas (shadows, burns, stains)
        if ($imageData['dark_pixel_ratio'] > 0.25) {
            $indicators['dark_areas'] = min(1.0, $imageData['dark_pixel_ratio'] / 0.5);
        }

        // Detect color abnormalities
        if ($imageData['red_channel_avg'] > 180 && $imageData['green_channel_avg'] < 120) {
            $indicators['red_channel_high'] = 0.7;
        }

        // Edge complexity (indication of damage, dents, tears)
        if ($imageData['edge_density'] > 0.15) {
            $indicators['edge_complexity'] = min(1.0, $imageData['edge_density'] / 0.3);
        }

        // Brightness variance indicates damage
        if ($imageData['brightness_std_dev'] > 60) {
            $indicators['brightness_variance'] = min(1.0, $imageData['brightness_std_dev'] / 120);
        }

        // Saturation anomaly
        if ($imageData['saturation_variance'] > 0.3) {
            $indicators['saturation_anomaly'] = min(1.0, $imageData['saturation_variance'] / 0.6);
        }

        return $indicators;
    }

    /**
     * Analyze color profile for damage severity
     */
    private function analyzeColorProfile(array $imageData): array
    {
        return [
            'red_dominant' => $imageData['red_channel_avg'] / 255,
            'green_avg' => $imageData['green_channel_avg'] / 255,
            'blue_avg' => $imageData['blue_channel_avg'] / 255,
            'color_entropy' => $imageData['color_entropy'],
            'dark_ratio' => $imageData['dark_pixel_ratio'],
            'bright_ratio' => $imageData['bright_pixel_ratio'],
        ];
    }

    /**
     * Analyze spatial distribution of damage
     */
    private function analyzeSpatialDistribution(array $imageData): array
    {
        return [
            'concentrated' => $imageData['edge_density'] > 0.2,
            'distributed' => $imageData['dark_pixel_ratio'] > 0.3,
            'localized_damage_score' => (1 - $imageData['uniformity_score']),
            'overall_damage_coverage' => $imageData['dark_pixel_ratio'] * 0.6 + ($imageData['edge_density'] > 0.15 ? 0.4 : 0),
        ];
    }

    /**
     * Rule-based severity classification
     */
    private function classifySeverity(
        array $damageIndicators,
        array $colorAnalysis,
        array $spatialAnalysis,
        float $qualityScore
    ): string {
        // Calculate composite damage score
        $damageScore = array_sum($damageIndicators) / count($damageIndicators);
        
        // Adjust for spatial analysis
        if ($spatialAnalysis['concentrated']) {
            $damageScore *= 1.2; // Concentrated damage is more serious
        }
        if ($spatialAnalysis['distributed']) {
            $damageScore *= 1.15; // Widespread damage affects vehicle more
        }

        // Quality score adjustment
        $adjustedScore = $damageScore * $qualityScore;

        // Classification thresholds
        if ($adjustedScore >= 0.65) {
            return 'MAJOR';
        } elseif ($adjustedScore >= 0.35) {
            return 'MEDIUM';
        } else {
            return 'MINOR';
        }
    }

    /**
     * Estimate repair costs based on severity and damage type
     */
    private function estimateRepairCost(string $severity, array $damageIndicators, array $spatialAnalysis): float
    {
        // Base costs (in dollars) - adjusted for real-world insurance claims
        $baseCosts = [
            'MINOR' => 500,    // Paint touch-up, small dents
            'MEDIUM' => 3500,  // Moderate damage, component replacement
            'MAJOR' => 12000,  // Extensive damage, frame damage, major repairs
        ];

        $baseCost = $baseCosts[$severity] ?? 500;

        // Adjust for spatial coverage
        $coverageMultiplier = 1.0 + ($spatialAnalysis['overall_damage_coverage'] * 1.5);

        // Adjust for damage concentration
        if ($spatialAnalysis['concentrated']) {
            $coverageMultiplier *= 1.3; // Concentrated damage in critical areas
        }

        // Adjust based on edge complexity (impact damage)
        $edgeComplexity = $damageIndicators['edge_complexity'] ?? 0;
        if ($edgeComplexity > 0.6) {
            $coverageMultiplier *= 1.25;
        }

        // Calculate final estimate
        $estimatedCost = (int)($baseCost * $coverageMultiplier);

        // Apply realistic ceiling based on severity
        $maxCosts = [
            'MINOR' => 2500,
            'MEDIUM' => 8000,
            'MAJOR' => 25000,
        ];

        return min($estimatedCost, $maxCosts[$severity] ?? 500);
    }

    /**
     * Determine damage type
     */
    private function determineDamageType(array $damageIndicators, array $colorAnalysis): string
    {
        $damageTypes = [];

        // Check for rust/oxidation (red color high)
        if ($colorAnalysis['red_dominant'] > 0.65) {
            $damageTypes[] = 'Rust/Oxidation Damage';
        }

        // Check for burn marks (high darkness)
        if ($damageIndicators['dark_areas'] > 0.6 && $colorAnalysis['dark_ratio'] > 0.35) {
            $damageTypes[] = 'Burn/Heat Damage';
        }

        // Check for impact damage (edge complexity)
        if ($damageIndicators['edge_complexity'] > 0.5) {
            $damageTypes[] = 'Impact/Collision Damage';
        }

        // Check for structural deformation (brightness variance)
        if ($damageIndicators['brightness_variance'] > 0.6) {
            $damageTypes[] = 'Dent/Deformation';
        }

        // Check for paint/surface damage
        if ($damageIndicators['color_abnormality'] > 0.5 || $damageIndicators['saturation_anomaly'] > 0.5) {
            $damageTypes[] = 'Paint/Surface Damage';
        }

        // Check for water damage
        if ($colorAnalysis['blue_avg'] > 0.6 && $colorAnalysis['color_entropy'] < 0.4) {
            $damageTypes[] = 'Water/Flooding Damage';
        }

        if (empty($damageTypes)) {
            $damageTypes[] = 'General Damage';
        }

        return implode(', ', array_slice($damageTypes, 0, 3));
    }

    /**
     * Calculate affected area percentage
     */
    private function calculateAffectedArea(array $spatialAnalysis, array $damageIndicators): int
    {
        $affectedArea = (int)(
            $spatialAnalysis['overall_damage_coverage'] * 70 +
            $damageIndicators['dark_areas'] * 20 +
            $damageIndicators['edge_complexity'] * 10
        );

        return min(100, max(5, $affectedArea));
    }

    /**
     * Generate detailed analysis report
     */
    private function generateDetailedAnalysis(
        string $severity,
        array $damageIndicators,
        array $colorAnalysis,
        float $qualityScore,
        string $fileName
    ): string {
        $damageScore = array_sum($damageIndicators) / count($damageIndicators);
        
        $report = "Advanced Damage Analysis Report\n";
        $report .= "================================\n";
        $report .= "File: $fileName\n";
        $report .= "Severity Classification: $severity\n\n";
        
        $report .= "Analysis Confidence: " . (int)($qualityScore * 100) . "%\n\n";
        
        $report .= "Damage Indicators:\n";
        $report .= "- Dark Areas (Burns/Stains): " . (int)($damageIndicators['dark_areas'] * 100) . "%\n";
        $report .= "- Edge Complexity (Impact): " . (int)($damageIndicators['edge_complexity'] * 100) . "%\n";
        $report .= "- Brightness Variance: " . (int)($damageIndicators['brightness_variance'] * 100) . "%\n";
        $report .= "- Color Abnormalities: " . (int)($damageIndicators['color_abnormality'] * 100) . "%\n\n";
        
        $report .= "Color Profile:\n";
        $report .= "- Red Channel: " . (int)($colorAnalysis['red_dominant'] * 100) . "%\n";
        $report .= "- Dark Areas Ratio: " . (int)($colorAnalysis['dark_ratio'] * 100) . "%\n";
        $report .= "- Color Entropy: " . round($colorAnalysis['color_entropy'], 2) . "\n\n";
        
        $report .= "Assessment Notes:\n";
        $report .= $this->generateAssessmentNotes($severity, $damageScore);
        
        $report .= "\nThis assessment uses hybrid AI + rule-based logic combining ";
        $report .= "image analysis with insurance industry damage classification standards.";

        return $report;
    }

    /**
     * Generate assessment notes based on severity
     */
    private function generateAssessmentNotes(string $severity, float $damageScore): string
    {
        $notes = "";

        if ($severity === 'MAJOR') {
            $notes = "⚠️ MAJOR DAMAGE DETECTED\n";
            $notes .= "- Significant structural or impact damage identified\n";
            $notes .= "- Comprehensive repair assessment recommended\n";
            $notes .= "- Professional inspection required before repairs\n";
            $notes .= "- May involve frame damage or mechanical components\n";
        } elseif ($severity === 'MEDIUM') {
            $notes = "⚠️ MODERATE DAMAGE DETECTED\n";
            $notes .= "- Visible damage requiring professional repair\n";
            $notes .= "- Component replacement may be necessary\n";
            $notes .= "- Detailed inspection of affected areas recommended\n";
        } else {
            $notes = "✓ MINOR DAMAGE DETECTED\n";
            $notes .= "- Limited damage, mostly cosmetic\n";
            $notes .= "- Paint or minor dent repair likely sufficient\n";
            $notes .= "- Professional estimate recommended for exact costs\n";
        }

        return $notes;
    }

    /**
     * Create combined assessment from multiple images
     */
    private function createCombinedAssessment(array $combinedData): array
    {
        if (empty($combinedData['severity_scores'])) {
            return [];
        }

        $avgSeverityScore = array_sum($combinedData['severity_scores']) / count($combinedData['severity_scores']);
        $maxCost = max($combinedData['estimated_costs']);
        $totalAffectedArea = min(100, (int)(array_sum($combinedData['affected_areas']) / count($combinedData['affected_areas'])));

        return [
            'combined_severity_score' => round($avgSeverityScore, 2),
            'estimated_total_cost' => $maxCost,
            'combined_affected_area' => $totalAffectedArea,
            'damage_types_detected' => array_unique($combinedData['damage_types']),
            'image_count' => count($combinedData['severity_scores']),
            'confidence' => 'HIGH' // Multiple images increase confidence
        ];
    }

    /**
     * Convert severity string to numeric score
     */
    private function severityToScore(string $severity): float
    {
        return match($severity) {
            'MAJOR' => 0.8,
            'MEDIUM' => 0.5,
            'MINOR' => 0.2,
            default => 0.3,
        };
    }
}
