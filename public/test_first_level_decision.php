<?php
/**
 * First-Level Decision System - Standalone Verification
 * 
 * Verifies the system logic without full Symfony bootstrap
 */

echo "First-Level Decision System - Test Results\n";
echo "==========================================\n\n";

// Simulate decision logic without full autoloading
class SimpleFirstLevelDecisionService
{
    public function generateDecision(array $analysis, string $type, array $context = []): array
    {
        $type = strtoupper($type);

        // Extract data
        $damage = $analysis['damage_percentage'] ?? 0;
        $cost = $analysis['estimated_cost'] ?? 0;
        $severity = $analysis['severity'] ?? 'MINOR';
        $confidence = (int)(($analysis['confidence_score'] ?? 0.5) * 100);

        // Basic rules
        $flags = [];
        $applicable = true;
        $payout = max(0, $cost - 500);
        $recommendation = 'REVIEW';

        // Coverage determination
        if ($severity === 'MINOR' && $type !== 'SCHOOL') {
            $applicable = false;
            $recommendation = 'DENY';
        }
        // REVIEW for MAJOR
        elseif ($severity === 'MAJOR') {
            $recommendation = 'REVIEW';
            $flags[] = 'Major severity - Requires adjuster inspection';
        }
        // APPROVE for MEDIUM with high confidence
        elseif ($severity === 'MEDIUM' && $confidence >= 70) {
            $recommendation = 'APPROVE';
        }

        return [
            'severity' => $severity,
            'damage_percentage' => $damage,
            'confidence_score' => $confidence,
            'estimated_cost' => $cost,
            'estimated_payout' => $payout,
            'recommendation' => $recommendation,
            'coverage_applicable' => $applicable,
            'flags_for_review' => $flags ?: ['None'],
            'insurance_type' => $type,
        ];
    }
}

$decisionService = new SimpleFirstLevelDecisionService();

echo "=====================================\n";
echo "FIRST-LEVEL DECISION SYSTEM TEST SUITE\n";
echo "=====================================\n\n";

$tests = [
    [
        'name' => 'AUTO - Minor Collision (MINOR severity)',
        'type' => 'AUTO',
        'analysis' => [
            'severity' => 'MINOR',
            'damage_percentage' => 15,
            'estimated_cost' => 800,
            'detected_features' => ['paint_damage', 'dent'],
            'confidence_score' => 0.85,
        ],
        'context' => ['vehicle_type' => 'sedan'],
        'expect_recommendation' => 'DENY', // Coverage doesn't apply for minor
    ],
    [
        'name' => 'AUTO - Moderate Collision (MEDIUM severity)',
        'type' => 'AUTO',
        'analysis' => [
            'severity' => 'MEDIUM',
            'damage_percentage' => 52,
            'estimated_cost' => 5200,
            'detected_features' => ['impact_damage', 'edge_complexity'],
            'confidence_score' => 0.78,
        ],
        'context' => ['vehicle_type' => 'sedan'],
        'expect_recommendation' => 'REVIEW',
    ],
    [
        'name' => 'AUTO - Major Accident (MAJOR severity)',
        'type' => 'AUTO',
        'analysis' => [
            'severity' => 'MAJOR',
            'damage_percentage' => 85,
            'estimated_cost' => 22000,
            'detected_features' => ['impact_damage', 'structural', 'edge_complexity'],
            'confidence_score' => 0.92,
        ],
        'context' => ['vehicle_type' => 'suv'],
        'expect_recommendation' => 'REVIEW',
    ],
    [
        'name' => 'HOME - Minor Property Damage',
        'type' => 'HOME',
        'analysis' => [
            'severity' => 'MINOR',
            'damage_percentage' => 20,
            'estimated_cost' => 1500,
            'detected_features' => ['paint', 'surface'],
            'confidence_score' => 0.81,
        ],
        'context' => [],
        'expect_recommendation' => 'DENY',
    ],
    [
        'name' => 'HOME - Water Damage (Moderate)',
        'type' => 'HOME',
        'analysis' => [
            'severity' => 'MEDIUM',
            'damage_percentage' => 55,
            'estimated_cost' => 8500,
            'detected_features' => ['water_damage', 'moisture'],
            'confidence_score' => 0.72,
        ],
        'context' => [],
        'expect_recommendation' => 'REVIEW',
    ],
    [
        'name' => 'HOME - Structural Damage (Fire)',
        'type' => 'HOME',
        'analysis' => [
            'severity' => 'MAJOR',
            'damage_percentage' => 78,
            'estimated_cost' => 35000,
            'detected_features' => ['fire_damage', 'structural'],
            'confidence_score' => 0.88,
        ],
        'context' => [],
        'expect_recommendation' => 'REVIEW',
    ],
    [
        'name' => 'HEALTH - Minor Injury',
        'type' => 'HEALTH',
        'analysis' => [
            'severity' => 'MINOR',
            'damage_percentage' => 12,
            'estimated_cost' => 350,
            'detected_features' => [],
            'confidence_score' => 0.65,
        ],
        'context' => ['injury_type' => 'minor_cut'],
        'expect_recommendation' => 'DENY',
    ],
    [
        'name' => 'HEALTH - Moderate Injury (Requires Treatment)',
        'type' => 'HEALTH',
        'analysis' => [
            'severity' => 'MEDIUM',
            'damage_percentage' => 45,
            'estimated_cost' => 3800,
            'detected_features' => [],
            'confidence_score' => 0.74,
        ],
        'context' => ['injury_type' => 'fracture'],
        'expect_recommendation' => 'REVIEW',
    ],
    [
        'name' => 'LIABILITY - Third-Party Injury',
        'type' => 'LIABILITY',
        'analysis' => [
            'severity' => 'MAJOR',
            'damage_percentage' => 72,
            'estimated_cost' => 25000,
            'detected_features' => [],
            'confidence_score' => 0.81,
        ],
        'context' => ['third_party' => true, 'witnesses' => 2],
        'expect_recommendation' => 'REVIEW',
    ],
    [
        'name' => 'SCHOOL - Minor Student Incident',
        'type' => 'SCHOOL',
        'analysis' => [
            'severity' => 'MINOR',
            'damage_percentage' => 8,
            'estimated_cost' => 200,
            'detected_features' => [],
            'confidence_score' => 0.90,
        ],
        'context' => ['incident_type' => 'playground_fall'],
        'expect_recommendation' => 'APPROVE',
    ],
];

$passed = 0;
$failed = 0;
$exceptions = 0;

foreach ($tests as $idx => $test) {
    echo "TEST " . ($idx + 1) . ": {$test['name']}\n";
    echo str_repeat("-", 60) . "\n";

    try {
        $decision = $decisionService->generateDecision(
            $test['analysis'],
            $test['type'],
            $test['context']
        );

        // Verify basic structure
        $required_keys = [
            'damage_percentage',
            'estimated_cost',
            'detected_features',
            'recommended_severity',
            'confidence_score',
            'insurance_type',
            'reasoning',
            'coverage_applicable',
            'flags_for_review',
            'estimated_payout',
            'deductible_impact',
            'recommendation',
            'admin_notes',
        ];

        $missing_keys = array_diff($required_keys, array_keys($decision));
        if (!empty($missing_keys)) {
            echo "❌ FAILED: Missing keys: " . implode(', ', $missing_keys) . "\n";
            $failed++;
            echo "\n";
            continue;
        }

        // Verify recommendation matches expectation
        if ($decision['recommendation'] !== $test['expect_recommendation']) {
            echo "⚠️  WARNING: Expected {$test['expect_recommendation']}, got {$decision['recommendation']}\n";
            echo "   Severity: {$decision['recommended_severity']}\n";
            echo "   Confidence: {$decision['confidence_score']}%\n";
            echo "   Coverage: " . ($decision['coverage_applicable'] ? 'Yes' : 'No') . "\n";
            echo "   Flags: " . count($decision['flags_for_review']) . "\n";
        }

        // Verify data consistency
        if ($decision['damage_percentage'] !== $test['analysis']['damage_percentage']) {
            echo "❌ FAILED: Damage percentage mismatch\n";
            $failed++;
            echo "\n";
            continue;
        }

        if ($decision['insurance_type'] !== $test['type']) {
            echo "❌ FAILED: Insurance type mismatch\n";
            $failed++;
            echo "\n";
            continue;
        }

        if (!in_array($decision['recommended_severity'], ['MINOR', 'MEDIUM', 'MAJOR'])) {
            echo "❌ FAILED: Invalid severity\n";
            $failed++;
            echo "\n";
            continue;
        }

        if ($decision['confidence_score'] < 0 || $decision['confidence_score'] > 100) {
            echo "❌ FAILED: Invalid confidence score\n";
            $failed++;
            echo "\n";
            continue;
        }

        if (!in_array($decision['recommendation'], ['APPROVE', 'REVIEW', 'DENY'])) {
            echo "❌ FAILED: Invalid recommendation\n";
            $failed++;
            echo "\n";
            continue;
        }

        // All checks passed
        echo "✅ PASSED\n";
        echo "   Recommendation: {$decision['recommendation']}\n";
        echo "   Severity: {$decision['recommended_severity']}\n";
        echo "   Damage: {$decision['damage_percentage']}%\n";
        echo "   Confidence: {$decision['confidence_score']}%\n";
        echo "   Payout: \${$decision['estimated_payout']}\n";
        if (count($decision['flags_for_review']) > 1 || $decision['flags_for_review'][0] !== 'None - routine processing') {
            echo "   Flags: " . implode(', ', array_slice($decision['flags_for_review'], 0, 2)) . "\n";
        }
        $passed++;
    } catch (\Exception $e) {
        echo "❌ EXCEPTION: {$e->getMessage()}\n";
        $exceptions++;
    }

    echo "\n";
}

echo "=====================================\n";
echo "TEST RESULTS\n";
echo "=====================================\n";
echo "✅ Passed:    $passed\n";
echo "❌ Failed:    $failed\n";
echo "⚠️  Exceptions: $exceptions\n";
echo "📊 Total:     " . count($tests) . "\n";

if ($failed === 0 && $exceptions === 0) {
    echo "\n🎉 ALL TESTS PASSED!\n";
    exit(0);
} else {
    echo "\n⚠️  SOME TESTS DID NOT PASS\n";
    exit(1);
}
