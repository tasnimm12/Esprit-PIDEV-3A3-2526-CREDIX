<?php
/**
 * Insurance Claim Analysis System
 * 
 * Structured analysis for admin review workflow
 * SAFETY-CRITICAL: Not a decision maker, requires human review
 */

class InsuranceClaimAnalyzer
{
    /**
     * Analyze insurance claim with strict type-based rules
     * Returns structured data for admin review only
     */
    public static function analyze($insuranceType, $claimData)
    {
        $type = strtoupper($insuranceType);
        
        // Validate insurance type
        $validTypes = ['AUTO', 'HOME', 'HEALTH', 'TRAVEL', 'LIABILITY'];
        if (!in_array($type, $validTypes)) {
            return [
                'error' => "Invalid insurance type: $type",
                'valid_types' => $validTypes
            ];
        }

        // Extract analysis data
        $damagePercent = $claimData['damage_percentage'] ?? 50;
        $cost = $claimData['estimated_cost'] ?? 5000;
        $confidence = $claimData['confidence_score'] ?? 65;
        $features = $claimData['detected_features'] ?? [];

        // Determine severity STRICTLY by insurance type
        $severity = self::determineSeverityByType($type, $damagePercent, $features, $confidence);

        // Build type-specific reasoning
        $reasoning = self::buildTypeSpecificReasoning($type, $severity, $damagePercent, $cost, $confidence);

        // Check consistency
        $consistencyCheck = self::validateConsistency($severity, $confidence, $damagePercent);

        // Handle low confidence - mark as unreliable
        if ($confidence < 50) {
            $severity = 'UNRELIABLE';
            $consistencyCheck = false;
        }

        return [
            'insurance_type' => $type,
            'detected_damage_features' => array_keys(array_filter($features)),
            'damage_percentage' => $damagePercent,
            'estimated_cost' => $cost,
            'recommended_severity' => $severity,
            'confidence_score' => $confidence,
            'consistency_check' => $consistencyCheck,
            'reasoning' => $reasoning,
            'admin_review_required' => true,
            'final_decision_authority' => 'HUMAN_ADMIN_ONLY',
        ];
    }

    /**
     * Determine severity EXCLUSIVELY by insurance type
     * This is the critical safety rule - type MUST be considered
     */
    private static function determineSeverityByType($type, $damagePercent, $features, $confidence)
    {
        // Safety rule: Low confidence cannot justify MAJOR
        if ($confidence < 60 && $damagePercent < 70) {
            return 'MEDIUM';
        }

        switch ($type) {
            case 'AUTO':
                // AUTO: Only vehicle collision/damage matters
                if ($damagePercent >= 70) return 'MAJOR';
                if ($damagePercent >= 40) return 'MEDIUM';
                return 'MINOR';

            case 'HOME':
                // HOME: Only structural/property damage matters
                if ($damagePercent >= 70) return 'MAJOR';
                if ($damagePercent >= 40) return 'MEDIUM';
                return 'MINOR';

            case 'HEALTH':
                // HEALTH: Only injury severity matters
                if (isset($features['serious_injury']) && $features['serious_injury']) {
                    return 'MAJOR';
                }
                if (isset($features['moderate_injury']) && $features['moderate_injury']) {
                    return 'MEDIUM';
                }
                return 'MINOR';

            case 'TRAVEL':
                // TRAVEL: Only trip disruption matters
                if ($damagePercent >= 80) return 'MAJOR';
                if ($damagePercent >= 40) return 'MEDIUM';
                return 'MINOR';

            case 'LIABILITY':
                // LIABILITY: Only legal risk matters
                if (isset($features['third_party_serious_injury']) && $features['third_party_serious_injury']) {
                    return 'MAJOR';
                }
                return 'MEDIUM';

            default:
                return 'MEDIUM';
        }
    }

    /**
     * Build reasoning that explicitly mentions insurance type
     * Required for transparency and audit trail
     */
    private static function buildTypeSpecificReasoning($type, $severity, $damagePercent, $cost, $confidence)
    {
        $base = "INSURANCE_TYPE: $type. ";
        
        switch ($type) {
            case 'AUTO':
                return $base . "Analysis strictly evaluates vehicle collision/damage. " .
                       "Damage assessment: $damagePercent%. Severity: $severity. " .
                       "Estimated repair: \$$cost. Confidence: $confidence%. " .
                       "This analysis evaluates ONLY vehicle-related factors per AUTO insurance scope. " .
                       "ADMIN REVIEW REQUIRED before any decision.";

            case 'HOME':
                return $base . "Analysis strictly evaluates property/structural damage. " .
                       "Damage assessment: $damagePercent%. Severity: $severity. " .
                       "Estimated repair: \$$cost. Confidence: $confidence%. " .
                       "This analysis evaluates ONLY property-related factors per HOME insurance scope. " .
                       "ADMIN REVIEW REQUIRED before any decision.";

            case 'HEALTH':
                return $base . "Analysis strictly evaluates injury severity and medical impact. " .
                       "Severity: $severity. Estimated treatment: \$$cost. Confidence: $confidence%. " .
                       "This analysis evaluates ONLY health/injury factors per HEALTH insurance scope. " .
                       "ADMIN REVIEW REQUIRED before any decision.";

            case 'TRAVEL':
                return $base . "Analysis strictly evaluates trip disruption and loss. " .
                       "Disruption level: $damagePercent%. Severity: $severity. " .
                       "Estimated loss: \$$cost. Confidence: $confidence%. " .
                       "This analysis evaluates ONLY trip-related factors per TRAVEL insurance scope. " .
                       "ADMIN REVIEW REQUIRED before any decision.";

            case 'LIABILITY':
                return $base . "Analysis strictly evaluates legal risk and third-party exposure. " .
                       "Severity: $severity. Estimated exposure: \$$cost. Confidence: $confidence%. " .
                       "This analysis evaluates ONLY liability-related factors per LIABILITY insurance scope. " .
                       "ADMIN REVIEW REQUIRED before any decision.";

            default:
                return "Insurance analysis complete. Severity: $severity. Confidence: $confidence%. " .
                       "ADMIN REVIEW REQUIRED before any decision.";
        }
    }

    /**
     * Validate consistency between inputs and outputs
     * Safety check to catch logical inconsistencies
     */
    private static function validateConsistency($severity, $confidence, $damagePercent)
    {
        // Consistency rules:
        // 1. High severity should have reasonable confidence
        // 2. High damage should correlate with appropriate severity
        // 3. UNRELIABLE severity should have low confidence

        if ($severity === 'UNRELIABLE' && $confidence >= 50) {
            return false; // Inconsistent
        }

        if ($severity === 'MAJOR' && $confidence < 60) {
            return false; // Cannot justify MAJOR with low confidence
        }

        if ($damagePercent >= 70 && $severity === 'MINOR') {
            return false; // High damage but low severity - inconsistent
        }

        return true; // Consistent
    }
}

// EXAMPLE USAGE - Demonstrate system working with different insurance types

echo str_repeat("=", 100) . "\n";
echo "INSURANCE CLAIM ANALYSIS SYSTEM - ADMIN REVIEW WORKFLOW\n";
echo str_repeat("=", 100) . "\n\n";

// Example 1: AUTO claim - HIGH CONFIDENCE
echo "EXAMPLE 1: AUTO Insurance - Vehicle Collision\n";
echo str_repeat("-", 100) . "\n";
$autoClaim = [
    'damage_percentage' => 65,
    'estimated_cost' => 12500,
    'confidence_score' => 88,
    'detected_features' => [
        'bumper_damage' => true,
        'hood_deformation' => true,
        'frame_damage' => true,
    ],
];
$autoResult = InsuranceClaimAnalyzer::analyze('AUTO', $autoClaim);
echo json_encode($autoResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

// Example 2: HOME claim - MEDIUM CONFIDENCE
echo "EXAMPLE 2: HOME Insurance - Property Damage\n";
echo str_repeat("-", 100) . "\n";
$homeClaim = [
    'damage_percentage' => 55,
    'estimated_cost' => 18000,
    'confidence_score' => 72,
    'detected_features' => [
        'roof_damage' => true,
        'water_damage' => true,
    ],
];
$homeResult = InsuranceClaimAnalyzer::analyze('HOME', $homeClaim);
echo json_encode($homeResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

// Example 3: HEALTH claim - LOW CONFIDENCE (SAFETY TEST)
echo "EXAMPLE 3: HEALTH Insurance - Low Confidence (SAFETY VALIDATION)\n";
echo str_repeat("-", 100) . "\n";
$healthClaim = [
    'damage_percentage' => 0,
    'estimated_cost' => 8000,
    'confidence_score' => 45, // Below 50 - should trigger UNRELIABLE
    'detected_features' => [
        'injury' => true,
    ],
];
$healthResult = InsuranceClaimAnalyzer::analyze('HEALTH', $healthClaim);
echo json_encode($healthResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

// Example 4: LIABILITY claim - HIGH CONFIDENCE
echo "EXAMPLE 4: LIABILITY Insurance - Third-Party Risk\n";
echo str_repeat("-", 100) . "\n";
$liabilityClaim = [
    'damage_percentage' => 0,
    'estimated_cost' => 35000,
    'confidence_score' => 92,
    'detected_features' => [
        'third_party_serious_injury' => true,
    ],
];
$liabilityResult = InsuranceClaimAnalyzer::analyze('LIABILITY', $liabilityClaim);
echo json_encode($liabilityResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

// Example 5: TRAVEL claim - INCONSISTENT DATA (SAFETY TEST)
echo "EXAMPLE 5: TRAVEL Insurance - Inconsistent Data (SAFETY VALIDATION)\n";
echo str_repeat("-", 100) . "\n";
$travelClaim = [
    'damage_percentage' => 90,
    'estimated_cost' => 5000,
    'confidence_score' => 35, // Low confidence but high damage
    'detected_features' => [
        'trip_cancelled' => true,
    ],
];
$travelResult = InsuranceClaimAnalyzer::analyze('TRAVEL', $travelClaim);
echo json_encode($travelResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

echo str_repeat("=", 100) . "\n";
echo "KEY SAFETY FEATURES DEMONSTRATED:\n";
echo str_repeat("=", 100) . "\n";
echo "✅ Insurance type strictly considered for each analysis\n";
echo "✅ Low confidence (<50) automatically marked as UNRELIABLE\n";
echo "✅ Consistency checks validate logical coherence\n";
echo "✅ Reasoning explicitly mentions insurance type\n";
echo "✅ All outputs are for ADMIN REVIEW ONLY\n";
echo "✅ No direct financial decisions made by system\n";
echo "✅ Safety rules prevent forcing MAJOR severity with low confidence\n";
echo "\n";
?>
