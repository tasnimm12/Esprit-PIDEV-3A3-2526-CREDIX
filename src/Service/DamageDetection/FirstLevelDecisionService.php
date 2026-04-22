<?php

namespace App\Service\DamageDetection;

use Psr\Log\LoggerInterface;

/**
 * First-Level Insurance Decision Service
 * 
 * Acts as initial claim assessment system:
 * - Analyzes damage analysis data
 * - Applies insurance-type-specific rules
 * - Generates structured recommendation
 * - Flags for admin review
 * 
 * This is a RECOMMENDATION ONLY, not final approval.
 * All decisions must be reviewed by human administrators.
 */
class FirstLevelDecisionService
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Generate first-level decision recommendation
     * 
     * @param array $analysisResult Complete analysis from DamageDetectionOrchestrator
     * @param string $insuranceType Type: AUTO|HOME|HEALTH|TRAVEL|LIABILITY|SCHOOL|PROFESSIONAL
     * @param array $claimContext Additional context (vehicle type, location, etc)
     * 
     * @return array {
     *     'damage_percentage': int (0-100),
     *     'estimated_cost': int,
     *     'detected_features': array,
     *     'recommended_severity': string (MINOR|MEDIUM|MAJOR),
     *     'confidence_score': int (0-100),
     *     'insurance_type': string,
     *     'reasoning': string,
     *     'coverage_applicable': bool,
     *     'flags_for_review': array,
     *     'estimated_payout': int (estimated insurance payout),
     *     'deductible_impact': int,
     *     'recommendation': string (APPROVE|REVIEW|DENY),
     *     'admin_notes': string,
     * }
     */
    public function generateDecision(
        array $analysisResult,
        string $insuranceType,
        array $claimContext = []
    ): array {
        $insuranceType = strtoupper($insuranceType);

        $this->logger->info("Generating first-level decision", [
            'severity' => $analysisResult['severity'] ?? 'UNKNOWN',
            'insurance_type' => $insuranceType,
        ]);

        // Get insurance-specific rules
        $typeRules = $this->getInsuranceTypeRules($insuranceType);

        // Extract analysis data
        $damagePercentage = $analysisResult['damage_percentage'] ?? 0;
        $estimatedCost = $analysisResult['estimated_cost'] ?? 0;
        $severity = $analysisResult['severity'] ?? 'MINOR';
        $confidenceScore = (int)(($analysisResult['confidence_score'] ?? 0.5) * 100);
        $detectedFeatures = $analysisResult['detected_features'] ?? [];

        // Apply type-specific assessment
        $typeAssessment = $this->assessByInsuranceType(
            $insuranceType,
            $severity,
            $damagePercentage,
            $estimatedCost,
            $detectedFeatures,
            $claimContext
        );

        // Determine coverage and payout
        $coverageResult = $this->determineCoverage(
            $insuranceType,
            $severity,
            $typeAssessment
        );

        // Identify flags for admin review
        $flags = $this->identifyReviewFlags(
            $insuranceType,
            $severity,
            $confidenceScore,
            $typeAssessment,
            $damagePercentage
        );

        // Generate recommendation
        $recommendation = $this->generateRecommendation(
            $severity,
            $confidenceScore,
            $coverageResult['applicable'],
            count($flags)
        );

        // Build reasoning
        $reasoning = $this->buildReasoning(
            $insuranceType,
            $severity,
            $damagePercentage,
            $typeAssessment,
            $coverageResult
        );

        // Build admin notes
        $adminNotes = $this->buildAdminNotes(
            $flags,
            $typeAssessment,
            $coverageResult
        );

        $decision = [
            'damage_percentage' => $damagePercentage,
            'estimated_cost' => $estimatedCost,
            'detected_features' => $detectedFeatures,
            'recommended_severity' => $severity,
            'confidence_score' => $confidenceScore,
            'insurance_type' => $insuranceType,
            'reasoning' => $reasoning,
            'coverage_applicable' => $coverageResult['applicable'],
            'flags_for_review' => $flags,
            'estimated_payout' => $coverageResult['estimated_payout'],
            'deductible_impact' => $coverageResult['deductible'],
            'recommendation' => $recommendation,
            'admin_notes' => $adminNotes,
        ];

        $this->logger->info("First-level decision generated", [
            'recommendation' => $recommendation,
            'severity' => $severity,
            'flags_count' => count($flags),
        ]);

        return $decision;
    }

    /**
     * Get rules and thresholds for insurance type
     */
    private function getInsuranceTypeRules(string $type): array
    {
        $rules = [
            'AUTO' => [
                'description' => 'Vehicle damage, collision severity, repair cost',
                'coverage_types' => ['Collision', 'Comprehensive', 'Liability'],
                'typical_deductible' => 500,
                'damage_type_keywords' => ['collision', 'impact', 'dent', 'paint'],
            ],
            'HOME' => [
                'description' => 'Structural/property damage assessment',
                'coverage_types' => ['Dwelling', 'Personal Property', 'Liability'],
                'typical_deductible' => 1000,
                'damage_type_keywords' => ['water', 'fire', 'structural', 'roof'],
            ],
            'HEALTH' => [
                'description' => 'Injury severity and treatment need',
                'coverage_types' => ['Medical', 'Disability'],
                'typical_deductible' => 250,
                'damage_type_keywords' => ['injury', 'medical', 'trauma'],
            ],
            'TRAVEL' => [
                'description' => 'Incident impact and trip disruption',
                'coverage_types' => ['Trip Cancellation', 'Medical', 'Baggage'],
                'typical_deductible' => 100,
                'damage_type_keywords' => ['cancellation', 'delay', 'loss'],
            ],
            'LIABILITY' => [
                'description' => 'Risk and exposure assessment',
                'coverage_types' => ['General Liability', 'Professional Liability'],
                'typical_deductible' => 2500,
                'damage_type_keywords' => ['injury to third party', 'property damage'],
            ],
            'SCHOOL' => [
                'description' => 'Minor incident severity',
                'coverage_types' => ['Accident Coverage', 'Liability'],
                'typical_deductible' => 50,
                'damage_type_keywords' => ['minor injury', 'incident'],
            ],
            'PROFESSIONAL' => [
                'description' => 'Business impact and loss',
                'coverage_types' => ['Business Interruption', 'Equipment', 'Liability'],
                'typical_deductible' => 5000,
                'damage_type_keywords' => ['business loss', 'equipment damage'],
            ],
        ];

        return $rules[$type] ?? $rules['AUTO'];
    }

    /**
     * Apply insurance-type-specific assessment rules
     */
    private function assessByInsuranceType(
        string $type,
        string $severity,
        int $damagePercentage,
        int $estimatedCost,
        array $detectedFeatures,
        array $context
    ): array {
        switch ($type) {
            case 'AUTO':
                return $this->assessAuto($severity, $damagePercentage, $estimatedCost, $detectedFeatures, $context);
            case 'HOME':
                return $this->assessHome($severity, $damagePercentage, $estimatedCost, $detectedFeatures, $context);
            case 'HEALTH':
                return $this->assessHealth($severity, $damagePercentage, $estimatedCost, $context);
            case 'TRAVEL':
                return $this->assessTravel($severity, $damagePercentage, $estimatedCost, $context);
            case 'LIABILITY':
                return $this->assessLiability($severity, $damagePercentage, $estimatedCost, $context);
            case 'SCHOOL':
                return $this->assessSchool($severity, $damagePercentage, $context);
            case 'PROFESSIONAL':
                return $this->assessProfessional($severity, $damagePercentage, $estimatedCost, $context);
            default:
                return ['assessment' => 'Default assessment', 'score' => 0.5];
        }
    }

    private function assessAuto(string $severity, int $damage, int $cost, array $features, array $context): array
    {
        $assessment = [
            'type_assessment' => 'Vehicle damage analysis',
            'damage_assessment' => '',
            'repair_complexity' => 'standard',
            'safety_risk' => 'low',
            'score' => 0,
        ];

        // Assess collision severity
        if (in_array('impact_damage', $features) || in_array('edge_complexity', $features)) {
            $assessment['repair_complexity'] = 'high';
            $assessment['score'] += 0.3;
        }

        // Assess safety implications
        if ($damage > 70 && in_array('structural', $context)) {
            $assessment['safety_risk'] = 'high';
            $assessment['score'] += 0.4;
        }

        // Assess drivability
        if ($severity === 'MAJOR') {
            $assessment['damage_assessment'] = 'Vehicle likely not drivable. Major structural/mechanical damage.';
        } elseif ($severity === 'MEDIUM') {
            $assessment['damage_assessment'] = 'Vehicle operational but requires professional repair.';
        } else {
            $assessment['damage_assessment'] = 'Cosmetic damage. Vehicle fully operational.';
        }

        return $assessment;
    }

    private function assessHome(string $severity, int $damage, int $cost, array $features, array $context): array
    {
        $assessment = [
            'type_assessment' => 'Property/structural damage analysis',
            'affected_areas' => [],
            'habitability' => 'habitable',
            'score' => 0,
        ];

        // Check damage type
        if (in_array('water', $features)) {
            $assessment['affected_areas'][] = 'Water damage';
            if ($damage > 50) {
                $assessment['habitability'] = 'partially_habitable';
                $assessment['score'] += 0.4;
            }
        }

        if (in_array('structural', $features)) {
            $assessment['affected_areas'][] = 'Structural damage';
            $assessment['habitability'] = 'uninhabitable';
            $assessment['score'] += 0.6;
        }

        if (in_array('fire', $features)) {
            $assessment['affected_areas'][] = 'Fire damage';
            $assessment['habitability'] = 'uninhabitable';
            $assessment['score'] += 0.7;
        }

        return $assessment;
    }

    private function assessHealth(string $severity, int $damage, int $cost, array $context): array
    {
        // For health, "damage" represents injury severity
        $assessment = [
            'type_assessment' => 'Injury severity assessment',
            'injury_type' => $context['injury_type'] ?? 'unspecified',
            'hospitalization_required' => $damage > 60,
            'score' => 0,
        ];

        if ($severity === 'MAJOR') {
            $assessment['assessment'] = 'Serious injury requiring immediate medical attention';
            $assessment['score'] = 0.8;
        } elseif ($severity === 'MEDIUM') {
            $assessment['assessment'] = 'Moderate injury requiring professional medical treatment';
            $assessment['score'] = 0.5;
        } else {
            $assessment['assessment'] = 'Minor injury, may require basic first aid';
            $assessment['score'] = 0.2;
        }

        return $assessment;
    }

    private function assessTravel(string $severity, int $damage, int $cost, array $context): array
    {
        $assessment = [
            'type_assessment' => 'Travel incident impact assessment',
            'disruption_level' => 'low',
            'score' => 0,
        ];

        if ($severity === 'MAJOR') {
            $assessment['disruption_level'] = 'trip_cancelled';
            $assessment['score'] = 0.9;
        } elseif ($severity === 'MEDIUM') {
            $assessment['disruption_level'] = 'significant_delay';
            $assessment['score'] = 0.5;
        } else {
            $assessment['disruption_level'] = 'minor_delay';
            $assessment['score'] = 0.2;
        }

        return $assessment;
    }

    private function assessLiability(string $severity, int $damage, int $cost, array $context): array
    {
        $assessment = [
            'type_assessment' => 'Risk and exposure assessment',
            'third_party_involvement' => $context['third_party'] ?? false,
            'witness_count' => $context['witnesses'] ?? 0,
            'dispute_likelihood' => 'low',
            'score' => 0,
        ];

        if ($assessment['third_party_involvement']) {
            $assessment['score'] += 0.3;
            $assessment['dispute_likelihood'] = 'medium';
        }

        if ($severity === 'MAJOR') {
            $assessment['dispute_likelihood'] = 'high';
            $assessment['score'] += 0.5;
        }

        return $assessment;
    }

    private function assessSchool(string $severity, int $damage, array $context): array
    {
        $assessment = [
            'type_assessment' => 'Minor incident severity',
            'incident_type' => $context['incident_type'] ?? 'unspecified',
            'school_liability' => 'low',
            'score' => 0,
        ];

        // School incidents rarely rise above MEDIUM
        if ($severity === 'MEDIUM') {
            $assessment['score'] = 0.4;
            $assessment['school_liability'] = 'medium';
        } else {
            $assessment['score'] = 0.1;
        }

        return $assessment;
    }

    private function assessProfessional(string $severity, int $damage, int $cost, array $context): array
    {
        $assessment = [
            'type_assessment' => 'Business impact assessment',
            'business_interruption' => $cost > 5000,
            'equipment_loss' => false,
            'score' => 0,
        ];

        if ($damage > 70) {
            $assessment['business_interruption'] = true;
            $assessment['score'] += 0.6;
        }

        if ($cost > 10000) {
            $assessment['equipment_loss'] = true;
            $assessment['score'] += 0.4;
        }

        return $assessment;
    }

    /**
     * Determine coverage applicability and estimated payout
     */
    private function determineCoverage(string $type, string $severity, array $assessment): array
    {
        $rules = $this->getInsuranceTypeRules($type);
        $deductible = $rules['typical_deductible'];

        // Determine if coverage applies
        $applicable = true;
        if ($severity === 'MINOR' && $type !== 'SCHOOL') {
            // Most types don't cover minor damage
            $applicable = false;
        }

        // Calculate estimated payout (after deductible)
        $estimatedCost = $assessment['estimated_cost'] ?? 0;
        $payout = max(0, $estimatedCost - $deductible);

        // Cap payout based on severity
        $payout_caps = [
            'MINOR' => 2500,
            'MEDIUM' => 15000,
            'MAJOR' => 50000,
        ];

        $payout = min($payout, $payout_caps[$severity] ?? 50000);

        return [
            'applicable' => $applicable,
            'estimated_payout' => (int)$payout,
            'deductible' => $deductible,
            'coverage_type' => $rules['coverage_types'][0] ?? 'Standard',
        ];
    }

    /**
     * Identify flags that require admin review
     */
    private function identifyReviewFlags(
        string $type,
        string $severity,
        int $confidence,
        array $assessment,
        int $damagePercentage
    ): array {
        $flags = [];

        // Low confidence
        if ($confidence < 60) {
            $flags[] = 'Low confidence analysis (' . $confidence . '%) - Recommend manual inspection';
        }

        // High severity
        if ($severity === 'MAJOR') {
            $flags[] = 'Major severity - Requires adjuster inspection';
        }

        // Type-specific flags
        if ($type === 'HOME' && isset($assessment['habitability'])) {
            if ($assessment['habitability'] === 'uninhabitable') {
                $flags[] = 'Property uninhabitable - Emergency assistance may be needed';
            }
        }

        if ($type === 'LIABILITY' && ($assessment['third_party_involvement'] ?? false)) {
            $flags[] = 'Third-party involvement - Legal review recommended';
        }

        if ($type === 'AUTO' && isset($assessment['safety_risk'])) {
            if ($assessment['safety_risk'] === 'high') {
                $flags[] = 'Safety risk high - Vehicle inspection required';
            }
        }

        // Extremely high damage
        if ($damagePercentage > 90) {
            $flags[] = 'Extreme damage (>90%) - Consider total loss assessment';
        }

        // Coverage uncertainty
        if ($damagePercentage < 35 && $type !== 'SCHOOL') {
            $flags[] = 'Minor damage - May not exceed deductible';
        }

        return $flags ?: ['None - routine processing'];
    }

    /**
     * Generate recommendation for admin
     */
    private function generateRecommendation(
        string $severity,
        int $confidence,
        bool $coverageApplicable,
        int $flagCount
    ): string {
        // DENY if coverage doesn't apply
        if (!$coverageApplicable) {
            return 'DENY';
        }

        // REVIEW if low confidence or many flags
        if ($confidence < 60 || $flagCount > 2) {
            return 'REVIEW';
        }

        // REVIEW if MAJOR severity
        if ($severity === 'MAJOR') {
            return 'REVIEW';
        }

        // APPROVE for MEDIUM with good confidence
        if ($severity === 'MEDIUM' && $confidence >= 70) {
            return 'APPROVE';
        }

        // Default to REVIEW for safety
        return 'REVIEW';
    }

    /**
     * Build human-readable reasoning
     */
    private function buildReasoning(
        string $type,
        string $severity,
        int $damagePercentage,
        array $assessment,
        array $coverage
    ): string {
        $typeRules = $this->getInsuranceTypeRules($type);

        $reasoning = sprintf(
            "First-level assessment for %s claim: %s damage (%d%% damage percentage). " .
            "%s. ",
            $type,
            $severity,
            $damagePercentage,
            $typeRules['description']
        );

        // Add type-specific assessment
        if (isset($assessment['assessment'])) {
            $reasoning .= $assessment['assessment'] . '. ';
        }

        if (isset($assessment['damage_assessment'])) {
            $reasoning .= $assessment['damage_assessment'] . '. ';
        }

        // Add coverage info
        if (!$coverage['applicable']) {
            $reasoning .= 'Coverage does not apply. ';
        } else {
            $reasoning .= sprintf(
                'Coverage applies under %s. Estimated payout after $%d deductible: $%d. ',
                $coverage['coverage_type'],
                $coverage['deductible'],
                $coverage['estimated_payout']
            );
        }

        $reasoning .= 'This is a first-level recommendation and requires admin review for final approval.';

        return $reasoning;
    }

    /**
     * Build admin notes with detailed information
     */
    private function buildAdminNotes(array $flags, array $assessment, array $coverage): string
    {
        $notes = "ADMIN REVIEW NOTES\n";
        $notes .= "==================\n\n";

        if (!empty($flags) && $flags[0] !== 'None - routine processing') {
            $notes .= "Review Flags:\n";
            foreach ($flags as $flag) {
                $notes .= "  • $flag\n";
            }
            $notes .= "\n";
        }

        $notes .= "Assessment Details:\n";
        foreach ($assessment as $key => $value) {
            if (is_array($value)) {
                $notes .= "  $key: " . implode(', ', $value) . "\n";
            } elseif (is_bool($value)) {
                $notes .= "  $key: " . ($value ? 'Yes' : 'No') . "\n";
            } else {
                $notes .= "  $key: $value\n";
            }
        }

        $notes .= "\nCoverage Information:\n";
        $notes .= "  Applicable: " . ($coverage['applicable'] ? 'Yes' : 'No') . "\n";
        $notes .= "  Coverage Type: " . $coverage['coverage_type'] . "\n";
        $notes .= "  Deductible: $" . $coverage['deductible'] . "\n";
        $notes .= "  Estimated Payout: $" . $coverage['estimated_payout'] . "\n";

        $notes .= "\nIMPORTANT: This is a first-level recommendation only.\n";
        $notes .= "Final approval authority rests with admin/claims adjuster.\n";

        return $notes;
    }
}
