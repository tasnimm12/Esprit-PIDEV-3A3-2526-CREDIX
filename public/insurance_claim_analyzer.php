<?php
/**
 * Insurance Claim Analysis Function
 * 
 * Analyzes insurance claims strictly by insurance type
 * Returns analysis in the required format
 */

function analyzeInsuranceClaim($insuranceType, $claimData) {
    $type = strtoupper($insuranceType);
    
    // Validate insurance type
    $validTypes = ['AUTO', 'HOME', 'HEALTH', 'TRAVEL', 'LIABILITY', 'SCHOOL', 'PROFESSIONAL'];
    if (!in_array($type, $validTypes)) {
        return ['error' => "Invalid insurance type. Must be one of: " . implode(', ', $validTypes)];
    }
    
    // Extract claim data
    $damagePercent = $claimData['damage_percentage'] ?? 50;
    $cost = $claimData['estimated_cost'] ?? 5000;
    $confidence = $claimData['confidence_score'] ?? 0.75;
    $detectedFeatures = $claimData['detected_features'] ?? [];
    
    // Determine severity based on INSURANCE_TYPE ONLY
    $severity = determineSeverity($type, $damagePercent, $detectedFeatures);
    
    // Build type-specific reasoning
    $reasoning = buildTypeSpecificReasoning($type, $severity, $damagePercent, $cost, $confidence, $detectedFeatures);
    
    // Determine if applicable to this insurance type
    $applicable = isApplicableToInsuranceType($type, $detectedFeatures);
    
    return [
        'insurance_type' => $type,
        'damage_percentage' => $damagePercent,
        'severity' => $severity,
        'estimated_cost' => $applicable ? $cost : 0,
        'confidence_score' => (int)($confidence * 100),
        'reasoning' => $reasoning,
        'applicable' => $applicable,
    ];
}

function determineSeverity($insuranceType, $damagePercent, $features) {
    switch ($insuranceType) {
        case 'AUTO':
            // AUTO: Only vehicle structural/safety damage matters
            if ($damagePercent >= 60) return 'MAJOR';
            if ($damagePercent >= 30) return 'MEDIUM';
            return 'MINOR';
            
        case 'HOME':
            // HOME: Only structural/habitability damage matters
            if ($damagePercent >= 60) return 'MAJOR';
            if ($damagePercent >= 30) return 'MEDIUM';
            return 'MINOR';
            
        case 'HEALTH':
            // HEALTH: Only injury severity matters
            if (isset($features['hospitalization_required']) && $features['hospitalization_required']) {
                return 'MAJOR';
            }
            if (isset($features['serious_injury']) && $features['serious_injury']) {
                return 'MEDIUM';
            }
            return 'MINOR';
            
        case 'TRAVEL':
            // TRAVEL: Only trip disruption/loss matters
            if ($damagePercent >= 80) return 'MAJOR';
            if ($damagePercent >= 40) return 'MEDIUM';
            return 'MINOR';
            
        case 'LIABILITY':
            // LIABILITY: Only legal risk/third-party injury matters
            if (isset($features['third_party_injury']) && $features['third_party_injury']) {
                return 'MAJOR';
            }
            return 'MEDIUM';
            
        case 'SCHOOL':
            // SCHOOL: Only student incident severity matters
            if ($damagePercent >= 50) return 'MEDIUM';
            return 'MINOR';
            
        case 'PROFESSIONAL':
            // PROFESSIONAL: Only business interruption matters
            if ($damagePercent >= 60) return 'MAJOR';
            if ($damagePercent >= 30) return 'MEDIUM';
            return 'MINOR';
            
        default:
            return 'MEDIUM';
    }
}

function buildTypeSpecificReasoning($type, $severity, $damagePercent, $cost, $confidence, $features) {
    $confidencePercent = (int)($confidence * 100);
    
    switch ($type) {
        case 'AUTO':
            return "AUTO insurance analysis: Vehicle damage assessment shows $damagePercent% structural/mechanical damage. " .
                   "Severity classification ($severity) is based EXCLUSIVELY on vehicle damage extent. " .
                   "Estimated repair cost \$$cost. Analysis confidence: $confidencePercent%. " .
                   "Claim directly relevant to AUTO insurance vehicle coverage.";
            
        case 'HOME':
            return "HOME insurance analysis: Property/structural damage assessment shows $damagePercent% damage extent. " .
                   "Severity classification ($severity) is based EXCLUSIVELY on structural/habitability impact. " .
                   "Estimated repair cost \$$cost. Analysis confidence: $confidencePercent%. " .
                   "Claim directly relevant to HOME insurance property coverage.";
            
        case 'HEALTH':
            return "HEALTH insurance analysis: Injury severity assessment ($severity) is based EXCLUSIVELY on medical impact. " .
                   "Estimated treatment cost \$$cost. Analysis confidence: $confidencePercent%. " .
                   "Claim directly relevant to HEALTH insurance medical coverage.";
            
        case 'TRAVEL':
            return "TRAVEL insurance analysis: Trip disruption/loss assessment shows $damagePercent% impact. " .
                   "Severity classification ($severity) is based EXCLUSIVELY on trip disruption extent. " .
                   "Estimated loss \$$cost. Analysis confidence: $confidencePercent%. " .
                   "Claim directly relevant to TRAVEL insurance trip coverage.";
            
        case 'LIABILITY':
            return "LIABILITY insurance analysis: Third-party legal risk assessment ($severity). " .
                   "Analysis is based EXCLUSIVELY on legal liability/third-party injury factors. " .
                   "Estimated exposure \$$cost. Analysis confidence: $confidencePercent%. " .
                   "Claim directly relevant to LIABILITY insurance coverage.";
            
        case 'SCHOOL':
            return "SCHOOL insurance analysis: Student incident severity assessment ($severity). " .
                   "Analysis is based EXCLUSIVELY on student-related incident factors. " .
                   "Estimated cost \$$cost. Analysis confidence: $confidencePercent%. " .
                   "Claim directly relevant to SCHOOL insurance student incident coverage.";
            
        case 'PROFESSIONAL':
            return "PROFESSIONAL insurance analysis: Business interruption assessment shows $damagePercent% impact. " .
                   "Severity classification ($severity) is based EXCLUSIVELY on business interruption extent. " .
                   "Estimated loss \$$cost. Analysis confidence: $confidencePercent%. " .
                   "Claim directly relevant to PROFESSIONAL insurance business coverage.";
            
        default:
            return "Insurance analysis complete. Severity: $severity. Confidence: $confidencePercent%.";
    }
}

function isApplicableToInsuranceType($type, $features) {
    switch ($type) {
        case 'AUTO':
            return isset($features['vehicle_damage']) ? $features['vehicle_damage'] : true;
        case 'HOME':
            return isset($features['property_damage']) ? $features['property_damage'] : true;
        case 'HEALTH':
            return isset($features['injury']) ? $features['injury'] : true;
        case 'TRAVEL':
            return isset($features['trip_related']) ? $features['trip_related'] : true;
        case 'LIABILITY':
            return isset($features['third_party']) ? $features['third_party'] : true;
        case 'SCHOOL':
            return isset($features['student_related']) ? $features['student_related'] : true;
        case 'PROFESSIONAL':
            return isset($features['business_related']) ? $features['business_related'] : true;
        default:
            return true;
    }
}

// EXAMPLE USAGE
echo "Insurance Claim Analysis System\n";
echo str_repeat("=", 50) . "\n\n";

// Example 1: AUTO claim
echo "EXAMPLE 1: AUTO Insurance Claim\n";
echo str_repeat("-", 50) . "\n";
$autoClaim = [
    'damage_percentage' => 62,
    'estimated_cost' => 12500,
    'confidence_score' => 0.87,
    'detected_features' => [
        'vehicle_damage' => true,
        'front_bumper_damage' => true,
        'hood_deformation' => true,
    ],
];
$autoResult = analyzeInsuranceClaim('AUTO', $autoClaim);
echo json_encode($autoResult, JSON_PRETTY_PRINT) . "\n\n";

// Example 2: HOME claim
echo "EXAMPLE 2: HOME Insurance Claim\n";
echo str_repeat("-", 50) . "\n";
$homeClaim = [
    'damage_percentage' => 75,
    'estimated_cost' => 35000,
    'confidence_score' => 0.92,
    'detected_features' => [
        'property_damage' => true,
        'fire_damage' => true,
        'structural_damage' => true,
    ],
];
$homeResult = analyzeInsuranceClaim('HOME', $homeClaim);
echo json_encode($homeResult, JSON_PRETTY_PRINT) . "\n\n";

// Example 3: HEALTH claim
echo "EXAMPLE 3: HEALTH Insurance Claim\n";
echo str_repeat("-", 50) . "\n";
$healthClaim = [
    'damage_percentage' => 0,
    'estimated_cost' => 5000,
    'confidence_score' => 0.88,
    'detected_features' => [
        'injury' => true,
        'serious_injury' => true,
    ],
];
$healthResult = analyzeInsuranceClaim('HEALTH', $healthClaim);
echo json_encode($healthResult, JSON_PRETTY_PRINT) . "\n\n";

echo "Analysis complete. Functions ready for use.\n";
?>
