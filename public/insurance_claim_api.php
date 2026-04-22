<?php
/**
 * Insurance Claim Analysis API
 * 
 * Accepts insurance_type as parameter and analyzes claims
 * strictly within that insurance type's context.
 * 
 * Usage: php insurance_claim_api.php?insurance_type=AUTO
 */

// Get insurance type from request or command line
$insuranceType = $_GET['insurance_type'] ?? $argv[1] ?? null;

if (!$insuranceType) {
    echo json_encode([
        'error' => 'Missing parameter: insurance_type',
        'valid_types' => ['AUTO', 'HOME', 'HEALTH', 'TRAVEL', 'LIABILITY', 'SCHOOL', 'PROFESSIONAL'],
        'example' => 'php insurance_claim_api.php AUTO'
    ], JSON_PRETTY_PRINT);
    exit(1);
}

$insuranceType = strtoupper($insuranceType);

// Sample claim data (in real use, this would come from image analysis)
$sampleClaims = [
    'AUTO' => [
        'damage_percentage' => 62,
        'estimated_cost' => 12500,
        'confidence_score' => 0.87,
        'detected_features' => [
            'vehicle_damage' => true,
            'bumper_damage' => true,
            'structural_damage' => true,
        ],
    ],
    'HOME' => [
        'damage_percentage' => 75,
        'estimated_cost' => 35000,
        'confidence_score' => 0.92,
        'detected_features' => [
            'property_damage' => true,
            'fire_damage' => true,
        ],
    ],
    'HEALTH' => [
        'damage_percentage' => 0,
        'estimated_cost' => 5000,
        'confidence_score' => 0.88,
        'detected_features' => [
            'injury' => true,
            'serious_injury' => true,
        ],
    ],
    'TRAVEL' => [
        'damage_percentage' => 100,
        'estimated_cost' => 8000,
        'confidence_score' => 0.95,
        'detected_features' => [
            'trip_cancelled' => true,
        ],
    ],
    'LIABILITY' => [
        'damage_percentage' => 0,
        'estimated_cost' => 25000,
        'confidence_score' => 0.85,
        'detected_features' => [
            'third_party_injury' => true,
        ],
    ],
];

// Analyze based on insurance type
function analyzeByInsuranceType($type, $claimData) {
    $validTypes = ['AUTO', 'HOME', 'HEALTH', 'TRAVEL', 'LIABILITY', 'SCHOOL', 'PROFESSIONAL'];
    
    if (!in_array($type, $validTypes)) {
        return ['error' => "Invalid insurance type: $type"];
    }
    
    $damagePercent = $claimData['damage_percentage'] ?? 50;
    $cost = $claimData['estimated_cost'] ?? 5000;
    $confidence = (int)(($claimData['confidence_score'] ?? 0.75) * 100);
    
    // Determine severity STRICTLY by insurance type
    $severity = match($type) {
        'AUTO' => $damagePercent >= 60 ? 'MAJOR' : ($damagePercent >= 30 ? 'MEDIUM' : 'MINOR'),
        'HOME' => $damagePercent >= 60 ? 'MAJOR' : ($damagePercent >= 30 ? 'MEDIUM' : 'MINOR'),
        'HEALTH' => isset($claimData['detected_features']['serious_injury']) && $claimData['detected_features']['serious_injury'] ? 'MAJOR' : 'MEDIUM',
        'TRAVEL' => $damagePercent >= 80 ? 'MAJOR' : ($damagePercent >= 40 ? 'MEDIUM' : 'MINOR'),
        'LIABILITY' => isset($claimData['detected_features']['third_party_injury']) && $claimData['detected_features']['third_party_injury'] ? 'MAJOR' : 'MEDIUM',
        'SCHOOL' => $damagePercent >= 50 ? 'MEDIUM' : 'MINOR',
        'PROFESSIONAL' => $damagePercent >= 60 ? 'MAJOR' : ($damagePercent >= 30 ? 'MEDIUM' : 'MINOR'),
        default => 'MEDIUM',
    };
    
    // Build type-specific reasoning
    $reasoning = match($type) {
        'AUTO' => "AUTO insurance analysis: Vehicle damage assessment ($damagePercent%) determines severity ($severity). " .
                  "Analysis focused EXCLUSIVELY on vehicle damage. Confidence: $confidence%.",
        'HOME' => "HOME insurance analysis: Property damage assessment ($damagePercent%) determines severity ($severity). " .
                  "Analysis focused EXCLUSIVELY on structural/property damage. Confidence: $confidence%.",
        'HEALTH' => "HEALTH insurance analysis: Injury severity assessment determines severity ($severity). " .
                    "Analysis focused EXCLUSIVELY on medical/injury factors. Confidence: $confidence%.",
        'TRAVEL' => "TRAVEL insurance analysis: Trip disruption assessment ($damagePercent%) determines severity ($severity). " .
                    "Analysis focused EXCLUSIVELY on trip-related factors. Confidence: $confidence%.",
        'LIABILITY' => "LIABILITY insurance analysis: Legal risk assessment determines severity ($severity). " .
                       "Analysis focused EXCLUSIVELY on third-party liability factors. Confidence: $confidence%.",
        'SCHOOL' => "SCHOOL insurance analysis: Student incident assessment determines severity ($severity). " .
                    "Analysis focused EXCLUSIVELY on student-related factors. Confidence: $confidence%.",
        'PROFESSIONAL' => "PROFESSIONAL insurance analysis: Business impact assessment ($damagePercent%) determines severity ($severity). " .
                          "Analysis focused EXCLUSIVELY on business-related factors. Confidence: $confidence%.",
        default => "Insurance analysis complete. Confidence: $confidence%.",
    };
    
    return [
        'insurance_type' => $type,
        'damage_percentage' => $damagePercent,
        'severity' => $severity,
        'estimated_cost' => $cost,
        'confidence_score' => $confidence,
        'reasoning' => $reasoning,
        'status' => 'ANALYSIS_COMPLETE'
    ];
}

// Get claim data or use sample
$claimData = $sampleClaims[$insuranceType] ?? [
    'damage_percentage' => 50,
    'estimated_cost' => 5000,
    'confidence_score' => 0.75,
];

// Perform analysis
$result = analyzeByInsuranceType($insuranceType, $claimData);

// Return as JSON
header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT);
?>
