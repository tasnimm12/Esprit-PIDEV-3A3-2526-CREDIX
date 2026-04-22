<?php

namespace App\Service\DamageDetection;

use Psr\Log\LoggerInterface;

/**
 * Rule-Based Severity Classification Engine
 * 
 * Takes ONLY raw image analysis data and applies strict, consistent rules
 * to determine insurance damage severity level.
 * 
 * Rules are based on damage percentage ONLY:
 * - MINOR: 0-35% visible damage
 * - MEDIUM: 35-70% visible damage
 * - MAJOR: 70-100% visible damage
 * 
 * This separation ensures:
 * 1. Severity is determined by consistent rules, not arbitrary algorithms
 * 2. Severity can be audited and explained
 * 3. Cost estimates follow from severity, not vice versa
 * 4. Impossible outputs (MINOR with 80% damage) cannot occur
 */
class RuleBasedSeverityEngine
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Classify severity based on raw damage percentage
     * 
     * @param array $analysisData Raw data from RawImageAnalysisService
     * @return array {
     *     'severity': string (MINOR|MEDIUM|MAJOR),
     *     'damage_percentage': int (0-100),
     *     'confidence_score': float (0-1),
     *     'classification_rule': string (human readable rule applied),
     *     'confidence_reason': string (why this confidence level),
     * }
     */
    public function classifySeverity(array $analysisData): array
    {
        $damagePercentage = $analysisData['damage_percentage'];
        $confidenceScore = $analysisData['confidence_score'];

        // Apply strict thresholds based on damage percentage
        $severity = $this->applySeverityThresholds($damagePercentage);

        // Adjust confidence based on image quality
        $adjustedConfidence = $this->adjustConfidenceByQuality($confidenceScore, $analysisData);

        // Generate explanation of which rule was applied
        $rule = $this->getRuleExplanation($severity, $damagePercentage);

        // Generate confidence explanation
        $confidenceReason = $this->getConfidenceExplanation($analysisData);

        $this->logger->info(
            "Severity classified: $severity ({$damagePercentage}% damage, confidence: " . 
            round($adjustedConfidence, 2) . ")"
        );

        return [
            'severity' => $severity,
            'damage_percentage' => $damagePercentage,
            'confidence_score' => $adjustedConfidence,
            'classification_rule' => $rule,
            'confidence_reason' => $confidenceReason,
        ];
    }

    /**
     * Apply strict severity thresholds
     * 
     * RULE 1: damage_percentage 0-35 → MINOR
     * RULE 2: damage_percentage 35-70 → MEDIUM
     * RULE 3: damage_percentage 70-100 → MAJOR
     * 
     * These are the ONLY rules. No exceptions, no adjustments.
     */
    private function applySeverityThresholds(int $damagePercentage): string
    {
        if ($damagePercentage < 35) {
            return 'MINOR';
        } elseif ($damagePercentage < 70) {
            return 'MEDIUM';
        } else {
            return 'MAJOR';
        }
    }

    /**
     * Adjust confidence score based on image quality and feature detection
     */
    private function adjustConfidenceByQuality(float $baseConfidence, array $analysisData): float
    {
        $adjustedConfidence = $baseConfidence;

        // Poor image quality reduces confidence
        $imageQuality = $analysisData['image_quality'] ?? 1.0;
        $adjustedConfidence *= $imageQuality;

        // More detected features increases confidence (more evidence)
        $featureCount = count($analysisData['detected_features'] ?? []);
        if ($featureCount >= 3) {
            $adjustedConfidence = min(1.0, $adjustedConfidence + 0.1);
        } elseif ($featureCount == 0) {
            $adjustedConfidence *= 0.8; // Low confidence if no features detected
        }

        return round($adjustedConfidence, 3);
    }

    /**
     * Get human-readable explanation of which rule determined severity
     */
    private function getRuleExplanation(string $severity, int $damagePercentage): string
    {
        $rules = [
            'MINOR' => "Damage percentage is {$damagePercentage}% (threshold: 0-34%) → MINOR severity",
            'MEDIUM' => "Damage percentage is {$damagePercentage}% (threshold: 35-69%) → MEDIUM severity",
            'MAJOR' => "Damage percentage is {$damagePercentage}% (threshold: 70-100%) → MAJOR severity",
        ];

        return $rules[$severity] ?? "Unknown severity: $severity";
    }

    /**
     * Get explanation of confidence score
     */
    private function getConfidenceExplanation(array $analysisData): string
    {
        $quality = $analysisData['image_quality'] ?? 1.0;
        $features = count($analysisData['detected_features'] ?? []);

        $reasons = [];

        if ($quality >= 0.8) {
            $reasons[] = "High quality image (excellent detail)";
        } elseif ($quality >= 0.6) {
            $reasons[] = "Good quality image";
        } else {
            $reasons[] = "Lower quality image (some detail loss)";
        }

        if ($features >= 4) {
            $reasons[] = "Multiple damage indicators detected";
        } elseif ($features >= 2) {
            $reasons[] = "Some damage indicators detected";
        } else {
            $reasons[] = "Few damage indicators detected";
        }

        return implode("; ", $reasons);
    }
}
