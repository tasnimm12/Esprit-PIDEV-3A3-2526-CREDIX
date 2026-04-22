<?php

namespace App\Service\DamageDetection;

use Psr\Log\LoggerInterface;

/**
 * Insurance Cost Estimation Service
 * 
 * Calculates repair costs based ONLY on final severity classification.
 * 
 * Cost is determined by:
 * 1. Severity level (MINOR/MEDIUM/MAJOR) - primary factor
 * 2. Confidence score - affects estimate reliability but not base cost
 * 3. Number of damage features - secondary adjustment
 * 
 * Base costs (in USD):
 * - MINOR (0-35% damage): $300-$1500
 * - MEDIUM (35-70% damage): $2000-$8000
 * - MAJOR (70-100% damage): $10000-$30000
 * 
 * This approach ensures:
 * - Cost always increases with severity
 * - No impossible combinations (MINOR with $20000 cost)
 * - Costs are realistic for insurance claims
 * - Relationship between severity and cost is transparent
 */
class InsuranceCostCalculator
{
    private LoggerInterface $logger;

    // Cost ranges by severity (realistic insurance claim values in USD)
    private const COST_RANGES = [
        'MINOR' => [
            'base' => 800,      // Base cost for minor damage
            'min' => 300,       // Minimum possible cost
            'max' => 1500,      // Maximum possible cost
        ],
        'MEDIUM' => [
            'base' => 4500,     // Base cost for medium damage
            'min' => 2000,      // Minimum possible cost
            'max' => 8000,      // Maximum possible cost
        ],
        'MAJOR' => [
            'base' => 18000,    // Base cost for major damage
            'min' => 10000,     // Minimum possible cost
            'max' => 30000,     // Maximum possible cost
        ],
    ];

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Calculate insurance cost estimate based on severity
     * 
     * @param array $classificationData Output from RuleBasedSeverityEngine
     * @param array $analysisData Raw analysis data for secondary adjustments
     * @return array {
     *     'estimated_cost': int (cost in USD),
     *     'cost_range_min': int,
     *     'cost_range_max': int,
     *     'severity': string,
     *     'confidence_score': float,
     *     'cost_calculation': string (explanation of how cost was calculated),
     * }
     */
    public function calculateCost(array $classificationData, array $analysisData): array
    {
        $severity = $classificationData['severity'];
        $confidenceScore = $classificationData['confidence_score'];
        $damagePercentage = $classificationData['damage_percentage'];

        // Get base cost for severity
        $costRange = self::COST_RANGES[$severity] ?? self::COST_RANGES['MINOR'];
        $baseCost = $costRange['base'];

        // Adjust cost within the severity's range based on damage percentage
        // More damage within the severity level = higher end of range
        $costAdjustment = $this->calculateSeverityAdjustment($severity, $damagePercentage);
        
        $estimatedCost = (int)($baseCost * $costAdjustment);

        // Ensure cost stays within severity's valid range
        $estimatedCost = max($costRange['min'], min($costRange['max'], $estimatedCost));

        // Apply confidence adjustment (affects cost range reliability, not base estimate)
        if ($confidenceScore < 0.6) {
            // Low confidence: expand the range
            $rangeMin = $costRange['min'];
            $rangeMax = $costRange['max'];
        } else {
            // Higher confidence: narrow the range
            $rangeMin = (int)($costRange['min'] + ($costRange['base'] - $costRange['min']) * 0.2);
            $rangeMax = (int)($costRange['max'] - ($costRange['max'] - $costRange['base']) * 0.2);
        }

        // Adjust ranges based on feature count
        $featureCount = count($analysisData['detected_features'] ?? []);
        if ($featureCount >= 4) {
            // Multiple damage types detected - likely higher repair complexity
            $estimatedCost = min($costRange['max'], (int)($estimatedCost * 1.1));
        }

        $calculation = $this->getCalculationExplanation($severity, $damagePercentage, $confidenceScore, $estimatedCost);

        $this->logger->info(
            "Cost calculated for $severity: \${$estimatedCost} (range: \${$rangeMin}-\${$rangeMax})"
        );

        return [
            'estimated_cost' => $estimatedCost,
            'cost_range_min' => $rangeMin,
            'cost_range_max' => $rangeMax,
            'severity' => $severity,
            'confidence_score' => $confidenceScore,
            'damage_percentage' => $damagePercentage,
            'cost_calculation' => $calculation,
        ];
    }

    /**
     * Calculate cost multiplier within severity range
     * 
     * For MINOR (0-35%): cost ranges from min to base
     * For MEDIUM (35-70%): cost ranges from base to max
     * For MAJOR (70-100%): cost ranges from base to max
     */
    private function calculateSeverityAdjustment(string $severity, int $damagePercentage): float
    {
        // Get thresholds for this severity
        $thresholds = $this->getSeverityThresholds($severity);
        $rangeStart = $thresholds['start'];
        $rangeEnd = $thresholds['end'];
        
        // Normalize damage percentage within this severity's range (0-1)
        $normalizedDamage = ($damagePercentage - $rangeStart) / ($rangeEnd - $rangeStart);
        $normalizedDamage = max(0, min(1, $normalizedDamage)); // Clamp 0-1

        // Cost multiplier: 0.8x at start of range, 1.0x at middle, 1.2x at end
        // This gives variation within severity while keeping range boundaries
        if ($normalizedDamage < 0.5) {
            // First half: 0.8x to 1.0x
            return 0.8 + ($normalizedDamage * 2 * 0.2);
        } else {
            // Second half: 1.0x to 1.2x
            return 1.0 + (($normalizedDamage - 0.5) * 2 * 0.2);
        }
    }

    /**
     * Get damage percentage thresholds for severity level
     */
    private function getSeverityThresholds(string $severity): array
    {
        $thresholds = [
            'MINOR' => ['start' => 0, 'end' => 34],
            'MEDIUM' => ['start' => 35, 'end' => 69],
            'MAJOR' => ['start' => 70, 'end' => 100],
        ];

        return $thresholds[$severity] ?? $thresholds['MINOR'];
    }

    /**
     * Get explanation of cost calculation
     */
    private function getCalculationExplanation(
        string $severity,
        int $damagePercentage,
        float $confidence,
        int $estimatedCost
    ): string {
        $costRange = self::COST_RANGES[$severity];
        
        return sprintf(
            "%s severity (%d%% damage): Base cost \$%d. " .
            "Estimated repair cost \$%d (range: \$%d-\$%d). " .
            "Confidence: %.0f%%",
            $severity,
            $damagePercentage,
            $costRange['base'],
            $estimatedCost,
            $costRange['min'],
            $costRange['max'],
            $confidence * 100
        );
    }
}
