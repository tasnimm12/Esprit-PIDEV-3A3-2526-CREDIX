<?php

namespace App\Service\DamageDetection;

use Psr\Log\LoggerInterface;

/**
 * Analysis Validation & Consistency Layer
 * 
 * Validates that analysis results are logically consistent:
 * 1. Severity matches damage percentage
 * 2. Cost matches severity level
 * 3. Confidence score is justified
 * 4. All values are within valid ranges
 * 5. No impossible combinations exist
 * 
 * If validation fails, suggests corrections or flags for manual review.
 */
class AnalysisValidationService
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Validate complete analysis result
     * 
     * @return array {
     *     'is_valid': bool,
     *     'errors': array of validation errors,
     *     'warnings': array of warnings,
     *     'corrections': array of suggested corrections,
     *     'confidence_level': string (HIGH|MEDIUM|LOW|REVIEW_REQUIRED),
     * }
     */
    public function validateAnalysis(array $analysis): array
    {
        $errors = [];
        $warnings = [];
        $corrections = [];

        // Validate severity vs damage percentage
        $severityValidation = $this->validateSeverityVsDamagePercentage(
            $analysis['severity'] ?? null,
            $analysis['damage_percentage'] ?? null
        );
        if (!$severityValidation['valid']) {
            $errors[] = $severityValidation['error'];
            $corrections[] = $severityValidation['correction'];
        }

        // Validate cost matches severity
        $costValidation = $this->validateCostVsSeverity(
            $analysis['severity'] ?? null,
            $analysis['estimated_cost'] ?? null
        );
        if (!$costValidation['valid']) {
            $errors[] = $costValidation['error'];
            $corrections[] = $costValidation['correction'];
        }

        // Validate confidence score
        $confidenceValidation = $this->validateConfidenceScore(
            $analysis['confidence_score'] ?? 0
        );
        if (!$confidenceValidation['valid']) {
            $errors[] = $confidenceValidation['error'];
        } elseif ($confidenceValidation['warning']) {
            $warnings[] = $confidenceValidation['warning'];
        }

        // Validate damage percentage range
        $damageValidation = $this->validateDamagePercentage(
            $analysis['damage_percentage'] ?? null
        );
        if (!$damageValidation['valid']) {
            $errors[] = $damageValidation['error'];
        }

        // Validate affected area is reasonable
        $areaValidation = $this->validateAffectedArea(
            $analysis['affected_area_percentage'] ?? null,
            $analysis['damage_percentage'] ?? null
        );
        if (!$areaValidation['valid']) {
            $warnings[] = $areaValidation['warning'];
        }

        // Determine final confidence level
        $confidenceLevel = $this->determineConfidenceLevel(
            empty($errors),
            empty($warnings),
            $analysis['confidence_score'] ?? 0,
            count($analysis['detected_features'] ?? [])
        );

        $isValid = empty($errors);

        if (!$isValid) {
            $this->logger->error("Analysis validation failed", [
                'errors' => $errors,
                'severity' => $analysis['severity'] ?? 'UNKNOWN',
                'damage_percentage' => $analysis['damage_percentage'] ?? 'UNKNOWN',
            ]);
        } elseif (!empty($warnings)) {
            $this->logger->warning("Analysis has warnings", [
                'warnings' => $warnings,
                'severity' => $analysis['severity'] ?? 'UNKNOWN',
            ]);
        }

        return [
            'is_valid' => $isValid,
            'errors' => $errors,
            'warnings' => $warnings,
            'corrections' => $corrections,
            'confidence_level' => $confidenceLevel,
        ];
    }

    /**
     * Validate that severity classification matches damage percentage
     */
    private function validateSeverityVsDamagePercentage(?string $severity, ?int $damagePercentage): array
    {
        if ($severity === null || $damagePercentage === null) {
            return ['valid' => false, 'error' => 'Missing severity or damage_percentage'];
        }

        $expectedSeverity = $this->calculateExpectedSeverity($damagePercentage);

        if ($severity !== $expectedSeverity) {
            return [
                'valid' => false,
                'error' => "Severity '$severity' does not match damage percentage $damagePercentage% " .
                          "(expected: '$expectedSeverity')",
                'correction' => "Change severity to '$expectedSeverity'",
            ];
        }

        return ['valid' => true];
    }

    /**
     * Calculate what severity SHOULD be for given damage percentage
     */
    private function calculateExpectedSeverity(int $damagePercentage): string
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
     * Validate that cost is appropriate for severity
     */
    private function validateCostVsSeverity(?string $severity, ?int $cost): array
    {
        if ($severity === null || $cost === null) {
            return ['valid' => true]; // Skip if missing
        }

        $validRanges = [
            'MINOR' => ['min' => 300, 'max' => 1500],
            'MEDIUM' => ['min' => 2000, 'max' => 8000],
            'MAJOR' => ['min' => 10000, 'max' => 30000],
        ];

        $range = $validRanges[$severity] ?? null;
        if (!$range) {
            return ['valid' => false, 'error' => "Unknown severity: $severity"];
        }

        if ($cost < $range['min'] || $cost > $range['max']) {
            return [
                'valid' => false,
                'error' => "$severity severity cost \$$cost is outside valid range " .
                          "(\${$range['min']}-\${$range['max']})",
                'correction' => "Adjust cost to \${$range['min']}-\${$range['max']} range",
            ];
        }

        return ['valid' => true];
    }

    /**
     * Validate confidence score is in valid range
     */
    private function validateConfidenceScore(float $score): array
    {
        if ($score < 0 || $score > 1) {
            return [
                'valid' => false,
                'error' => "Confidence score $score is outside valid range (0-1)",
            ];
        }

        if ($score < 0.5) {
            return [
                'valid' => true,
                'warning' => 'Confidence score is below 50% - results may be unreliable',
            ];
        }

        return ['valid' => true, 'warning' => null];
    }

    /**
     * Validate damage percentage is in range 0-100
     */
    private function validateDamagePercentage(?int $percentage): array
    {
        if ($percentage === null) {
            return ['valid' => false, 'error' => 'Missing damage_percentage'];
        }

        if ($percentage < 0 || $percentage > 100) {
            return [
                'valid' => false,
                'error' => "Damage percentage $percentage is outside valid range (0-100)",
            ];
        }

        return ['valid' => true];
    }

    /**
     * Validate affected area is reasonable relative to damage percentage
     */
    private function validateAffectedArea(?int $affectedArea, ?int $damagePercentage): array
    {
        if ($affectedArea === null || $damagePercentage === null) {
            return ['valid' => true, 'warning' => null]; // Skip if missing
        }

        // Affected area should generally be close to damage percentage
        // But allow some variance (affected area could be smaller if damage is deep but localized)
        $difference = abs($affectedArea - $damagePercentage);

        if ($difference > 40) {
            return [
                'valid' => true,
                'warning' => "Affected area ($affectedArea%) differs significantly from " .
                            "damage percentage ($damagePercentage%) - may indicate localized damage",
            ];
        }

        return ['valid' => true, 'warning' => null];
    }

    /**
     * Determine overall confidence level for the analysis
     */
    private function determineConfidenceLevel(
        bool $noErrors,
        bool $noWarnings,
        float $scoreConfidence,
        int $featureCount
    ): string {
        if (!$noErrors) {
            return 'REVIEW_REQUIRED';
        }

        if ($scoreConfidence >= 0.8 && $featureCount >= 3) {
            return 'HIGH';
        } elseif ($scoreConfidence >= 0.6 && $featureCount >= 2) {
            return 'MEDIUM';
        } elseif (!$noWarnings) {
            return 'LOW';
        } else {
            return 'MEDIUM';
        }
    }

    /**
     * Get a corrected analysis if validation found issues
     */
    public function correctAnalysis(array $analysis): array
    {
        $corrected = $analysis;

        // Fix severity to match damage percentage
        if (isset($corrected['damage_percentage'])) {
            $corrected['severity'] = $this->calculateExpectedSeverity($corrected['damage_percentage']);
        }

        // Ensure cost is within valid range for severity
        if (isset($corrected['severity']) && isset($corrected['estimated_cost'])) {
            $ranges = [
                'MINOR' => ['min' => 300, 'max' => 1500],
                'MEDIUM' => ['min' => 2000, 'max' => 8000],
                'MAJOR' => ['min' => 10000, 'max' => 30000],
            ];

            $range = $ranges[$corrected['severity']] ?? $ranges['MINOR'];
            $corrected['estimated_cost'] = max(
                $range['min'],
                min($range['max'], $corrected['estimated_cost'])
            );
        }

        return $corrected;
    }
}
