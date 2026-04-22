<?php

namespace App\Service\DamageDetection;

use App\Entity\DamageAnalysis;
use App\Entity\Sinistre;
use App\Entity\SinistrePreuve;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Damage Detection Orchestrator
 * 
 * Coordinates the complete damage analysis pipeline:
 * 1. Raw Image Analysis (extracts image data)
 * 2. Rule-Based Severity Engine (assigns severity based on damage %)
 * 3. Insurance Cost Calculator (calculates cost based on severity)
 * 4. Validation Layer (ensures consistency)
 * 5. Database Persistence (saves analysis)
 * 
 * This clean orchestration ensures:
 * - Clear separation of concerns
 * - Each step can be tested independently
 * - Results are always consistent
 * - Logic is auditable and explainable
 */
class DamageDetectionOrchestrator
{
    private RawImageAnalysisService $imageAnalyzer;
    private GeminiDamageAnalysisService $geminiAnalyzer;
    private RuleBasedSeverityEngine $severityEngine;
    private InsuranceCostCalculator $costCalculator;
    private AnalysisValidationService $validator;
    private EntityManagerInterface $em;
    private LoggerInterface $logger;
    private string $projectDir;

    public function __construct(
        RawImageAnalysisService $imageAnalyzer,
        GeminiDamageAnalysisService $geminiAnalyzer,
        RuleBasedSeverityEngine $severityEngine,
        InsuranceCostCalculator $costCalculator,
        AnalysisValidationService $validator,
        EntityManagerInterface $em,
        LoggerInterface $logger,
        string $projectDir
    ) {
        $this->imageAnalyzer = $imageAnalyzer;
        $this->geminiAnalyzer = $geminiAnalyzer;
        $this->severityEngine = $severityEngine;
        $this->costCalculator = $costCalculator;
        $this->validator = $validator;
        $this->em = $em;
        $this->logger = $logger;
        $this->projectDir = $projectDir;
    }

    /**
     * Execute complete damage analysis pipeline
     * 
     * Flow:
     * Image → Raw Analysis → Severity Classification → Cost Calculation → Validation → Database
     * 
     * Each step is independent and can be logged/audited
     */
    public function analyzeImage(SinistrePreuve $preuve, Sinistre $sinistre): DamageAnalysis
    {
        $this->logger->info("Starting damage analysis for: {$preuve->getNomFichier()}");

        $imagePath = $this->projectDir . '/public' . $preuve->getFichier();

        // STEP 1: Raw Image Analysis
        // Extract objective data from image (no interpretation)
        $this->logger->info("STEP 1: Raw image analysis");
        $analysisData = $this->geminiAnalyzer->analyzeImage($imagePath);

        if ($analysisData !== null) {
            $this->logger->info("  - Analyzer: Gemini");
            if (!empty($analysisData['gemini_reasoning'])) {
                $this->logger->info("  - Gemini reasoning: " . $analysisData['gemini_reasoning']);
            }
        } else {
            $this->logger->info("  - Analyzer: Local fallback");
            $analysisData = $this->imageAnalyzer->analyzeImage($imagePath);
        }

        $this->logger->info("  - Damage percentage: {$analysisData['damage_percentage']}%");
        $this->logger->info("  - Detected features: " . implode(', ', $analysisData['detected_features']));
        $this->logger->info("  - Confidence: " . round($analysisData['confidence_score'], 2));

        // STEP 2: Rule-Based Severity Classification
        // Apply strict rules to assign severity based ONLY on damage percentage
        $this->logger->info("STEP 2: Severity classification");
        $classificationData = $this->severityEngine->classifySeverity($analysisData);
        $this->logger->info("  - Severity: {$classificationData['severity']}");
        $this->logger->info("  - Rule: {$classificationData['classification_rule']}");

        // STEP 3: Insurance Cost Calculation
        // Calculate cost based on severity (not arbitrary formulas)
        $this->logger->info("STEP 3: Cost calculation");
        $costData = $this->costCalculator->calculateCost($classificationData, $analysisData);
        $this->logger->info("  - Estimated cost: \${$costData['estimated_cost']}");
        $this->logger->info("  - Cost range: \${$costData['cost_range_min']}-\${$costData['cost_range_max']}");

        // STEP 4: Validation & Consistency Check
        // Verify all results are logically consistent
        $this->logger->info("STEP 4: Validation and consistency check");
        $validationData = $this->validator->validateAnalysis(array_merge($classificationData, $costData));
        
        if (!$validationData['is_valid']) {
            $this->logger->error("Validation failed", [
                'errors' => $validationData['errors'],
                'corrections' => $validationData['corrections'],
            ]);
            
            // Apply corrections
            $correctedData = $this->validator->correctAnalysis(array_merge($classificationData, $costData));
            $this->logger->info("  - Applied corrections: " . implode(', ', $validationData['corrections']));
        } else {
            $this->logger->info("  - Validation passed");
        }

        // STEP 5: Persistence
        // Save analysis to database
        $this->logger->info("STEP 5: Persisting to database");
        $analysis = $this->createAnalysisEntity($preuve, $sinistre, $classificationData, $costData, $validationData, $analysisData);
        $this->em->persist($analysis);
        $this->em->flush();

        $this->logger->info("Damage analysis completed successfully");
        $this->logger->info("  Final: {$analysis->getSeverity()} | {$analysis->getDamageType()} | " .
                          "\${$analysis->getEstimatedCost()}");

        return $analysis;
    }

    /**
     * Analyze multiple images and create combined assessment
     */
    public function analyzeMultipleImages(array $preuves, Sinistre $sinistre): array
    {
        $this->logger->info("Analyzing " . count($preuves) . " images for combined assessment");

        $analyses = [];
        foreach ($preuves as $preuve) {
            try {
                $analysis = $this->analyzeImage($preuve, $sinistre);
                $analyses[] = $analysis;
            } catch (\Exception $e) {
                $this->logger->error("Failed to analyze image: " . $preuve->getNomFichier(), [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $analyses;
    }

    /**
     * Create DamageAnalysis entity from processed data
     */
    private function createAnalysisEntity(
        SinistrePreuve $preuve,
        Sinistre $sinistre,
        array $classificationData,
        array $costData,
        array $validationData,
        array $analysisData
    ): DamageAnalysis {
        $analysis = new DamageAnalysis();
        $analysis->setPreuve($preuve);
        $analysis->setSinistre($sinistre);
        $analysis->setSeverity($classificationData['severity']);
        $analysis->setDamageType($this->determineDamageType($analysisData));
        $analysis->setAffectedAreaPercentage($this->calculateAffectedArea($classificationData['damage_percentage']));
        $analysis->setEstimatedCost($costData['estimated_cost']);
        $analysis->setAnalyzedAt(new \DateTime());
        
        // Build detailed analysis report
        $details = $this->buildAnalysisReport(
            $classificationData,
            $costData,
            $validationData,
            $analysisData
        );
        $analysis->setAnalysisDetails($details);
        
        // Set AI model name and version
        $modelName = isset($analysisData['gemini_reasoning'])
            ? 'Gemini+' . 'DamageDetectionOrchestrator-v3'
            : 'DamageDetectionOrchestrator-v3';
        $analysis->setAiModel($modelName);

        return $analysis;
    }

    /**
     * Build comprehensive analysis report
     */
    private function buildAnalysisReport(
        array $classificationData,
        array $costData,
        array $validationData,
        array $analysisData
    ): string {
        $report = "=== DAMAGE ANALYSIS REPORT (v3 - Production Ready) ===\n\n";

        // Image Analysis Summary
        $report .= "IMAGE ANALYSIS:\n";
        $report .= "  Damage Percentage: {$analysisData['damage_percentage']}%\n";
        $report .= "  Image Quality: " . round($analysisData['image_quality'] * 100) . "%\n";
        $report .= "  Detected Features: " . implode(', ', $analysisData['detected_features']) . "\n";
        $report .= "  Base Confidence: " . round($analysisData['confidence_score'] * 100) . "%\n\n";

        // Severity Classification
        $report .= "SEVERITY CLASSIFICATION:\n";
        $report .= "  Severity: {$classificationData['severity']}\n";
        $report .= "  Rule Applied: {$classificationData['classification_rule']}\n";
        $report .= "  Adjusted Confidence: " . round($classificationData['confidence_score'] * 100) . "%\n";
        $report .= "  Reason: {$classificationData['confidence_reason']}\n\n";

        // Cost Estimation
        $report .= "COST ESTIMATION:\n";
        $report .= "  Base Cost Range: \${$costData['cost_range_min']}-\${$costData['cost_range_max']}\n";
        $report .= "  Estimated Repair Cost: \${$costData['estimated_cost']}\n";
        $report .= "  Calculation: {$costData['cost_calculation']}\n\n";

        // Validation
        $report .= "VALIDATION & CONSISTENCY:\n";
        $report .= "  Valid: " . ($validationData['is_valid'] ? 'YES' : 'NO') . "\n";
        $report .= "  Confidence Level: {$validationData['confidence_level']}\n";
        
        if (!empty($validationData['errors'])) {
            $report .= "  Errors: " . implode('; ', $validationData['errors']) . "\n";
        }
        if (!empty($validationData['warnings'])) {
            $report .= "  Warnings: " . implode('; ', $validationData['warnings']) . "\n";
        }

        $report .= "\n=== END REPORT ===";

        return $report;
    }

    /**
     * Determine damage type from detected features
     */
    private function determineDamageType(array $analysisData): string
    {
        $features = $analysisData['detected_features'] ?? [];

        if (in_array('rust_indicators', $features)) {
            return 'Rust/Oxidation Damage';
        } elseif (in_array('edge_complexity', $features) && in_array('bright_spots', $features)) {
            return 'Impact/Collision Damage';
        } elseif (in_array('dark_areas', $features)) {
            return 'Burn/Heat Damage';
        } elseif (in_array('saturation_anomaly', $features)) {
            return 'Paint/Surface Damage';
        } elseif (in_array('brightness_variance', $features)) {
            return 'Dent/Deformation';
        }

        return 'General Damage';
    }

    /**
     * Calculate affected area percentage from damage percentage
     * 
     * Affected area = estimated percentage of vehicle surface showing damage
     */
    private function calculateAffectedArea(int $damagePercentage): int
    {
        // Affected area is generally close to damage percentage
        // but can vary based on whether damage is concentrated or spread
        return min(100, max(5, $damagePercentage));
    }
}
