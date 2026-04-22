<?php
/**
 * Test: Safety Rule Validation
 * Low confidence should be marked as UNRELIABLE
 */

class QuickAnalyzer {
    public static function analyze($type, $damage, $cost, $confidence, $features) {
        $type = strtoupper($type);
        
        // Determine severity
        $severity = $confidence < 60 && $damage < 70 ? 'MEDIUM' : 
                   ($damage >= 70 ? 'MAJOR' : ($damage >= 40 ? 'MEDIUM' : 'MINOR'));
        
        // Consistency check
        $consistent = !($severity === 'UNRELIABLE' && $confidence >= 50) &&
                     !($severity === 'MAJOR' && $confidence < 60) &&
                     !($damage >= 70 && $severity === 'MINOR');
        
        // SAFETY: Low confidence = UNRELIABLE
        if ($confidence < 50) {
            $severity = 'UNRELIABLE';
            $consistent = false;
        }
        
        return [
            'insurance_type' => $type,
            'damage_percentage' => $damage,
            'estimated_cost' => $cost,
            'confidence_score' => $confidence,
            'recommended_severity' => $severity,
            'consistency_check' => $consistent,
            'safety_rule_triggered' => $confidence < 50,
            'reasoning' => "INSURANCE_TYPE: $type. Damage: $damage%. Severity: $severity. Confidence: $confidence%. " .
                          ($confidence < 50 ? "⚠️ LOW CONFIDENCE - SEVERITY MARKED AS UNRELIABLE" : "✅ Confidence acceptable")
        ];
    }
}

// Test Case: Low Confidence (35%)
$result = QuickAnalyzer::analyze('AUTO', 75, 18000, 35, ['possible' => true]);
echo json_encode($result, JSON_PRETTY_PRINT);
?>
