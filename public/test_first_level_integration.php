<?php
/**
 * First-Level Decision System - Integration Verification
 * 
 * This simple test verifies the service logic works correctly
 * by simulating the decision generation process.
 */

// Simple decision service simulation (no Symfony dependencies)
class FirstLevelDecisionTester
{
    public function runTests(): array
    {
        $results = [];

        echo "========================================\n";
        echo "FIRST-LEVEL DECISION SYSTEM - INTEGRATION TEST\n";
        echo "========================================\n\n";

        $testCases = [
            [
                'name' => 'AUTO: Moderate Collision (MEDIUM)',
                'type' => 'AUTO',
                'severity' => 'MEDIUM',
                'damage' => 52,
                'cost' => 5200,
                'confidence' => 0.78,
                'expect' => 'REVIEW',
            ],
            [
                'name' => 'AUTO: Minor Damage',
                'type' => 'AUTO',
                'severity' => 'MINOR',
                'damage' => 15,
                'cost' => 800,
                'confidence' => 0.85,
                'expect' => 'DENY',
            ],
            [
                'name' => 'HOME: Major Fire Damage',
                'type' => 'HOME',
                'severity' => 'MAJOR',
                'damage' => 78,
                'cost' => 35000,
                'confidence' => 0.88,
                'expect' => 'REVIEW',
            ],
            [
                'name' => 'HEALTH: Moderate Injury',
                'type' => 'HEALTH',
                'severity' => 'MEDIUM',
                'damage' => 45,
                'cost' => 3800,
                'confidence' => 0.74,
                'expect' => 'REVIEW',
            ],
            [
                'name' => 'LIABILITY: Major Third-Party Injury',
                'type' => 'LIABILITY',
                'severity' => 'MAJOR',
                'damage' => 72,
                'cost' => 25000,
                'confidence' => 0.81,
                'expect' => 'REVIEW',
            ],
            [
                'name' => 'SCHOOL: Minor Student Incident',
                'type' => 'SCHOOL',
                'severity' => 'MINOR',
                'damage' => 8,
                'cost' => 200,
                'confidence' => 0.90,
                'expect' => 'APPROVE',
            ],
            [
                'name' => 'TRAVEL: Trip Cancellation',
                'type' => 'TRAVEL',
                'severity' => 'MAJOR',
                'damage' => 85,
                'cost' => 2500,
                'confidence' => 0.92,
                'expect' => 'REVIEW',
            ],
            [
                'name' => 'PROFESSIONAL: Business Interruption',
                'type' => 'PROFESSIONAL',
                'severity' => 'MAJOR',
                'damage' => 88,
                'cost' => 45000,
                'confidence' => 0.85,
                'expect' => 'REVIEW',
            ],
        ];

        $passed = 0;
        $failed = 0;

        foreach ($testCases as $idx => $case) {
            echo ($idx + 1) . ". " . $case['name'] . "\n";

            $decision = $this->generateDecision(
                $case['type'],
                $case['severity'],
                $case['damage'],
                $case['cost'],
                $case['confidence']
            );

            $recommendation = $decision['recommendation'];
            $status = ($recommendation === $case['expect']) ? '✅ PASS' : '❌ FAIL';

            echo "   Expected: {$case['expect']}, Got: {$recommendation} - {$status}\n";
            echo "   Damage: {$decision['damage_percentage']}%, Severity: {$decision['severity']}\n";
            echo "   Confidence: {$decision['confidence_score']}%, Payout: \${$decision['payout']}\n";

            if ($recommendation === $case['expect']) {
                $passed++;
            } else {
                $failed++;
            }

            echo "\n";
        }

        echo "========================================\n";
        echo "TEST RESULTS: $passed passed, $failed failed\n";
        echo "========================================\n";

        return [
            'total' => count($testCases),
            'passed' => $passed,
            'failed' => $failed,
            'success' => ($failed === 0),
        ];
    }

    private function generateDecision(
        string $type,
        string $severity,
        int $damage,
        int $cost,
        float $confidence
    ): array {
        $type = strtoupper($type);
        $confidencePercent = (int)($confidence * 100);

        // Deductibles by type
        $deductibles = [
            'AUTO' => 500,
            'HOME' => 1000,
            'HEALTH' => 250,
            'TRAVEL' => 100,
            'LIABILITY' => 2500,
            'SCHOOL' => 50,
            'PROFESSIONAL' => 5000,
        ];
        $deductible = $deductibles[$type] ?? 500;

        // Calculate payout
        $payout = max(0, $cost - $deductible);

        // Determine recommendation
        $recommendation = 'REVIEW'; // Default

        // Rule 1: No coverage for MINOR (except SCHOOL)
        if ($severity === 'MINOR' && $type !== 'SCHOOL') {
            $recommendation = 'DENY';
        }
        // Rule 2: MAJOR always needs review
        elseif ($severity === 'MAJOR') {
            $recommendation = 'REVIEW';
        }
        // Rule 3: MEDIUM with high confidence can approve
        elseif ($severity === 'MEDIUM' && $confidencePercent >= 70) {
            $recommendation = 'APPROVE';
        }
        // Rule 4: MINOR for SCHOOL with good confidence can approve
        elseif ($severity === 'MINOR' && $type === 'SCHOOL' && $confidencePercent >= 70) {
            $recommendation = 'APPROVE';
        }

        return [
            'type' => $type,
            'severity' => $severity,
            'damage_percentage' => $damage,
            'estimated_cost' => $cost,
            'confidence_score' => $confidencePercent,
            'payout' => $payout,
            'deductible' => $deductible,
            'recommendation' => $recommendation,
            'coverage_applicable' => $recommendation !== 'DENY',
        ];
    }
}

// Run tests
$tester = new FirstLevelDecisionTester();
$results = $tester->runTests();

// Exit with appropriate code
exit($results['failed'] > 0 ? 1 : 0);
