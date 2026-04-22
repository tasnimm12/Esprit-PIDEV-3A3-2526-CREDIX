<?php
/**
 * Insurance Claim Analysis - Web Endpoint
 * 
 * Demonstrates complete analysis workflow
 * Resolves {INSURANCE_TYPE} template with query parameter
 */

header('Content-Type: application/json');

// Get insurance type from query or use AUTO as default
$insuranceType = $_GET['type'] ?? $_POST['type'] ?? 'AUTO';
$action = $_GET['action'] ?? 'demo';

class InsuranceAnalysisEngine
{
    public static function analyze($type, $claimData)
    {
        $type = strtoupper($type);
        $validTypes = ['AUTO', 'HOME', 'HEALTH', 'TRAVEL', 'LIABILITY'];
        
        if (!in_array($type, $validTypes)) {
            return ['error' => "Invalid type: $type. Valid: " . implode(', ', $validTypes)];
        }

        $damage = (int)($claimData['damage_percentage'] ?? 50);
        $cost = (int)($claimData['estimated_cost'] ?? 5000);
        $confidence = (int)($claimData['confidence_score'] ?? 65);
        $features = $claimData['detected_features'] ?? [];

        $severity = self::getSeverity($type, $damage, $features, $confidence);
        $reasoning = self::getReasoning($type, $severity, $damage, $cost, $confidence);
        $consistent = self::isConsistent($severity, $confidence, $damage);

        if ($confidence < 50) {
            $severity = 'UNRELIABLE';
            $consistent = false;
        }

        return [
            'insurance_type' => $type,
            'detected_damage_features' => array_keys(array_filter($features)),
            'damage_percentage' => $damage,
            'estimated_cost' => $cost,
            'recommended_severity' => $severity,
            'confidence_score' => $confidence,
            'consistency_check' => $consistent,
            'reasoning' => $reasoning,
            'admin_review_required' => true,
            'final_decision_authority' => 'HUMAN_ADMIN_ONLY',
            'timestamp' => date('c')
        ];
    }

    private static function getSeverity($type, $damage, $features, $confidence)
    {
        if ($confidence < 60 && $damage < 70) return 'MEDIUM';
        
        switch ($type) {
            case 'AUTO': return $damage >= 70 ? 'MAJOR' : ($damage >= 40 ? 'MEDIUM' : 'MINOR');
            case 'HOME': return $damage >= 70 ? 'MAJOR' : ($damage >= 40 ? 'MEDIUM' : 'MINOR');
            case 'HEALTH': return ($features['serious_injury'] ?? false) ? 'MAJOR' : (($features['moderate_injury'] ?? false) ? 'MEDIUM' : 'MINOR');
            case 'TRAVEL': return $damage >= 80 ? 'MAJOR' : ($damage >= 40 ? 'MEDIUM' : 'MINOR');
            case 'LIABILITY': return ($features['third_party_serious_injury'] ?? false) ? 'MAJOR' : 'MEDIUM';
            default: return 'MEDIUM';
        }
    }

    private static function getReasoning($type, $severity, $damage, $cost, $confidence)
    {
        $msg = "INSURANCE_TYPE: $type";
        
        switch ($type) {
            case 'AUTO': return "$msg (vehicle damage). Damage: $damage%. Severity: $severity. Cost: \$$cost. Confidence: $confidence%. Type-specific evaluation: vehicle collision/structural integrity. ADMIN REVIEW REQUIRED.";
            case 'HOME': return "$msg (property damage). Damage: $damage%. Severity: $severity. Cost: \$$cost. Confidence: $confidence%. Type-specific evaluation: structural/property loss. ADMIN REVIEW REQUIRED.";
            case 'HEALTH': return "$msg (injury/medical). Severity: $severity. Cost: \$$cost. Confidence: $confidence%. Type-specific evaluation: medical necessity/injury severity. ADMIN REVIEW REQUIRED.";
            case 'TRAVEL': return "$msg (trip disruption). Disruption: $damage%. Severity: $severity. Cost: \$$cost. Confidence: $confidence%. Type-specific evaluation: trip loss/cancellation. ADMIN REVIEW REQUIRED.";
            case 'LIABILITY': return "$msg (legal risk). Severity: $severity. Cost: \$$cost. Confidence: $confidence%. Type-specific evaluation: third-party exposure/liability. ADMIN REVIEW REQUIRED.";
            default: return "Analysis complete. Confidence: $confidence%. ADMIN REVIEW REQUIRED.";
        }
    }

    private static function isConsistent($severity, $confidence, $damage)
    {
        if ($severity === 'UNRELIABLE' && $confidence >= 50) return false;
        if ($severity === 'MAJOR' && $confidence < 60) return false;
        if ($damage >= 70 && $severity === 'MINOR') return false;
        return true;
    }
}

// Route 1: Analyze specific claim
if ($action === 'analyze') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    echo json_encode(InsuranceAnalysisEngine::analyze($insuranceType, $data), JSON_PRETTY_PRINT);
    exit;
}

// Route 2: Demo with example claim
if ($action === 'demo') {
    $examples = [
        'AUTO' => [
            'damage_percentage' => 68,
            'estimated_cost' => 15400,
            'confidence_score' => 87,
            'detected_features' => ['bumper_damage' => true, 'hood_deformation' => true, 'frame_damage' => true]
        ],
        'HOME' => [
            'damage_percentage' => 52,
            'estimated_cost' => 22800,
            'confidence_score' => 79,
            'detected_features' => ['roof_damage' => true, 'water_intrusion' => true]
        ],
        'HEALTH' => [
            'damage_percentage' => 0,
            'estimated_cost' => 12500,
            'confidence_score' => 82,
            'detected_features' => ['serious_injury' => true, 'surgery_required' => true]
        ],
        'TRAVEL' => [
            'damage_percentage' => 85,
            'estimated_cost' => 8500,
            'confidence_score' => 88,
            'detected_features' => ['trip_cancelled' => true, 'non_refundable_loss' => true]
        ],
        'LIABILITY' => [
            'damage_percentage' => 0,
            'estimated_cost' => 45000,
            'confidence_score' => 91,
            'detected_features' => ['third_party_serious_injury' => true, 'medical_bills_high' => true]
        ]
    ];

    echo json_encode([
        'system' => 'Insurance Claim Analysis - Admin Review Workflow',
        'requested_type' => $insuranceType,
        'analysis' => InsuranceAnalysisEngine::analyze($insuranceType, $examples[$insuranceType] ?? []),
        'instructions' => [
            'POST to /api/insurance-analysis with insurance_type and claim data',
            'Example: POST {insurance_type: "AUTO", damage_percentage: 65, ...}',
            'System returns: damage features, percentage, cost, severity, confidence, consistency, reasoning',
            'All outputs are for ADMIN REVIEW ONLY - no automatic decisions'
        ]
    ], JSON_PRETTY_PRINT);
    exit;
}

// Route 3: List all supported types
if ($action === 'types') {
    echo json_encode([
        'supported_types' => [
            'AUTO' => 'Vehicle collision and damage analysis',
            'HOME' => 'Property and structural damage analysis',
            'HEALTH' => 'Injury and medical necessity analysis',
            'TRAVEL' => 'Trip disruption and loss analysis',
            'LIABILITY' => 'Legal risk and third-party exposure analysis'
        ],
        'usage' => '?type=AUTO&action=demo',
        'safety_rules' => [
            'If confidence_score < 50, severity marked as UNRELIABLE',
            'Cannot force MAJOR severity with low confidence',
            'Consistency check validates logical coherence',
            'Insurance type MUST be explicitly considered',
            'Final decisions reserved for human admin review'
        ]
    ], JSON_PRETTY_PRINT);
    exit;
}

// Default: Show info
echo json_encode([
    'message' => 'Insurance Analysis System Ready',
    'insurance_type_placeholder' => $insuranceType,
    'endpoints' => [
        '?type=AUTO&action=types' => 'List all insurance types',
        '?type=AUTO&action=demo' => 'Run demo analysis for AUTO',
        '?type=HOME&action=demo' => 'Run demo analysis for HOME',
        '?type=HEALTH&action=demo' => 'Run demo analysis for HEALTH',
        '?type=TRAVEL&action=demo' => 'Run demo analysis for TRAVEL',
        '?type=LIABILITY&action=demo' => 'Run demo analysis for LIABILITY'
    ],
    'post_analyze' => 'POST with JSON body: {insurance_type, damage_percentage, estimated_cost, confidence_score, detected_features}'
], JSON_PRETTY_PRINT);
?>
