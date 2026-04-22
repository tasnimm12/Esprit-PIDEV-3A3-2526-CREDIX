<?php

namespace App\Examples;

use App\Service\DamageDetection\FirstLevelDecisionService;

/**
 * Real-World Examples for First-Level Decision System
 * 
 * This file shows practical examples of using the FirstLevelDecisionService
 * for different insurance types and claim scenarios.
 */

class FirstLevelDecisionExamples
{
    /**
     * Example 1: AUTO - Car Accident (Moderate Damage)
     */
    public static function exampleAutoClaim(FirstLevelDecisionService $service): array
    {
        $analysisResult = [
            'severity' => 'MEDIUM',
            'damage_percentage' => 52,
            'estimated_cost' => 5200,
            'detected_features' => ['impact_damage', 'edge_complexity', 'dent'],
            'confidence_score' => 0.78,
        ];

        $context = [
            'vehicle_type' => 'sedan',
            'third_party' => false,
            'safety_critical' => false,
        ];

        $decision = $service->generateDecision($analysisResult, 'AUTO', $context);

        /*
        Expected output:
        {
            "recommendation": "REVIEW",
            "damage_percentage": 52,
            "estimated_cost": 5200,
            "recommended_severity": "MEDIUM",
            "confidence_score": 78,
            "estimated_payout": 4700,  // $5200 - $500 deductible
            "coverage_applicable": true,
            "reasoning": "First-level assessment for AUTO claim: MEDIUM damage (52% damage percentage).
                          Vehicle damage, collision severity, repair cost. Repair complexity is moderate.
                          Vehicle operational but requires professional repair. Coverage applies under Collision.
                          Estimated payout after $500 deductible: $4700.",
            "flags_for_review": ["None - routine processing"]
        }
        */

        return $decision;
    }

    /**
     * Example 2: HOME - Fire Damage (Major)
     */
    public static function exampleHomeClaim(FirstLevelDecisionService $service): array
    {
        $analysisResult = [
            'severity' => 'MAJOR',
            'damage_percentage' => 78,
            'estimated_cost' => 35000,
            'detected_features' => ['fire_damage', 'structural', 'charring'],
            'confidence_score' => 0.88,
        ];

        $context = [
            'structural_damage' => true,
            'habitability' => 'uninhabitable',
        ];

        $decision = $service->generateDecision($analysisResult, 'HOME', $context);

        /*
        Expected output:
        {
            "recommendation": "REVIEW",  // Always REVIEW for MAJOR
            "damage_percentage": 78,
            "estimated_cost": 35000,
            "recommended_severity": "MAJOR",
            "confidence_score": 88,
            "estimated_payout": 34000,  // $35000 - $1000 deductible
            "coverage_applicable": true,
            "reasoning": "First-level assessment for HOME claim: MAJOR damage (78% damage percentage).
                          Structural/property damage assessment. Property uninhabitable.
                          Coverage applies under Dwelling. Estimated payout after $1000 deductible: $34000.",
            "flags_for_review": [
                "Major severity - Requires adjuster inspection",
                "Property uninhabitable - Emergency assistance may be needed"
            ]
        }
        */

        return $decision;
    }

    /**
     * Example 3: HEALTH - Injury Claim (Moderate)
     */
    public static function exampleHealthClaim(FirstLevelDecisionService $service): array
    {
        $analysisResult = [
            'severity' => 'MEDIUM',
            'damage_percentage' => 45,  // Represents injury severity
            'estimated_cost' => 3800,
            'detected_features' => [],  // N/A for health
            'confidence_score' => 0.74,
        ];

        $context = [
            'injury_type' => 'fracture',
            'hospitalization_required' => true,
        ];

        $decision = $service->generateDecision($analysisResult, 'HEALTH', $context);

        /*
        Expected output:
        {
            "recommendation": "REVIEW",  // Low confidence < 70%
            "damage_percentage": 45,
            "estimated_cost": 3800,
            "recommended_severity": "MEDIUM",
            "confidence_score": 74,
            "estimated_payout": 3550,  // $3800 - $250 deductible
            "coverage_applicable": true,
            "reasoning": "First-level assessment for HEALTH claim: MEDIUM damage (45% damage percentage).
                          Injury severity and treatment need. Moderate injury requiring professional medical treatment.
                          Coverage applies under Medical. Estimated payout after $250 deductible: $3550.",
            "flags_for_review": ["Low confidence analysis (74%) - Recommend manual inspection"]
        }
        */

        return $decision;
    }

    /**
     * Example 4: LIABILITY - Third-Party Injury (Major)
     */
    public static function exampleLiabilityClaim(FirstLevelDecisionService $service): array
    {
        $analysisResult = [
            'severity' => 'MAJOR',
            'damage_percentage' => 72,  // Severity of third-party injury
            'estimated_cost' => 25000,
            'detected_features' => [],
            'confidence_score' => 0.81,
        ];

        $context = [
            'third_party' => true,
            'witnesses' => 2,
            'police_report' => true,
            'injury_to_third_party' => true,
        ];

        $decision = $service->generateDecision($analysisResult, 'LIABILITY', $context);

        /*
        Expected output:
        {
            "recommendation": "REVIEW",  // MAJOR severity + third-party
            "damage_percentage": 72,
            "estimated_cost": 25000,
            "recommended_severity": "MAJOR",
            "confidence_score": 81,
            "estimated_payout": 22500,  // $25000 - $2500 deductible
            "coverage_applicable": true,
            "reasoning": "First-level assessment for LIABILITY claim: MAJOR damage (72% damage percentage).
                          Risk and exposure assessment. Third-party involvement present.
                          Coverage applies under General Liability. Estimated payout after $2500 deductible: $22500.",
            "flags_for_review": [
                "Major severity - Requires adjuster inspection",
                "Third-party involvement - Legal review recommended"
            ]
        }
        */

        return $decision;
    }

    /**
     * Example 5: SCHOOL - Student Incident (Minor)
     */
    public static function exampleSchoolClaim(FirstLevelDecisionService $service): array
    {
        $analysisResult = [
            'severity' => 'MINOR',
            'damage_percentage' => 8,  // Very low severity
            'estimated_cost' => 200,
            'detected_features' => [],
            'confidence_score' => 0.90,
        ];

        $context = [
            'incident_type' => 'playground_fall',
            'school_liability' => 'low',
        ];

        $decision = $service->generateDecision($analysisResult, 'SCHOOL', $context);

        /*
        Expected output:
        {
            "recommendation": "APPROVE",  // MINOR is OK for SCHOOL
            "damage_percentage": 8,
            "estimated_cost": 200,
            "recommended_severity": "MINOR",
            "confidence_score": 90,
            "estimated_payout": 150,  // $200 - $50 deductible
            "coverage_applicable": true,
            "reasoning": "First-level assessment for SCHOOL claim: MINOR damage (8% damage percentage).
                          Minor incident severity. Minor injury, may require basic first aid.
                          Coverage applies under Accident Coverage. Estimated payout after $50 deductible: $150.",
            "flags_for_review": ["None - routine processing"]
        }
        */

        return $decision;
    }

    /**
     * Example 6: TRAVEL - Trip Cancellation (Major)
     */
    public static function exampleTravelClaim(FirstLevelDecisionService $service): array
    {
        $analysisResult = [
            'severity' => 'MAJOR',
            'damage_percentage' => 85,  // Trip completely disrupted
            'estimated_cost' => 2500,
            'detected_features' => [],
            'confidence_score' => 0.92,
        ];

        $context = [
            'incident_type' => 'airline_bankruptcy',
            'trip_days_remaining' => 7,
            'non_refundable_cost' => 2500,
        ];

        $decision = $service->generateDecision($analysisResult, 'TRAVEL', $context);

        /*
        Expected output:
        {
            "recommendation": "REVIEW",  // MAJOR always requires review
            "damage_percentage": 85,
            "estimated_cost": 2500,
            "recommended_severity": "MAJOR",
            "confidence_score": 92,
            "estimated_payout": 2400,  // $2500 - $100 deductible
            "coverage_applicable": true,
            "reasoning": "First-level assessment for TRAVEL claim: MAJOR damage (85% damage percentage).
                          Incident impact and trip disruption. Trip cancelled.
                          Coverage applies under Trip Cancellation. Estimated payout after $100 deductible: $2400.",
            "flags_for_review": ["Major severity - Requires adjuster inspection"]
        }
        */

        return $decision;
    }

    /**
     * Example 7: PROFESSIONAL - Business Interruption (Major)
     */
    public static function exampleProfessionalClaim(FirstLevelDecisionService $service): array
    {
        $analysisResult = [
            'severity' => 'MAJOR',
            'damage_percentage' => 88,
            'estimated_cost' => 45000,
            'detected_features' => ['equipment_damage', 'facility_damage'],
            'confidence_score' => 0.85,
        ];

        $context = [
            'business_type' => 'manufacturing',
            'facility_closure_days' => 14,
            'employees_affected' => 45,
            'equipment_loss' => 35000,
        ];

        $decision = $service->generateDecision($analysisResult, 'PROFESSIONAL', $context);

        /*
        Expected output:
        {
            "recommendation": "REVIEW",  // MAJOR + high cost
            "damage_percentage": 88,
            "estimated_cost": 45000,
            "recommended_severity": "MAJOR",
            "confidence_score": 85,
            "estimated_payout": 40000,  // $45000 - $5000 deductible
            "coverage_applicable": true,
            "reasoning": "First-level assessment for PROFESSIONAL claim: MAJOR damage (88% damage percentage).
                          Business impact assessment. Facility closure and significant revenue loss.
                          Coverage applies under Business Interruption. Estimated payout after $5000 deductible: $40000.",
            "flags_for_review": [
                "Major severity - Requires adjuster inspection",
                "Extreme damage (>90%) - Consider total loss assessment"
            ]
        }
        */

        return $decision;
    }

    /**
     * Example 8: Denied Claim (Coverage Not Applicable)
     */
    public static function exampleDeniedClaim(FirstLevelDecisionService $service): array
    {
        // Intentional attempt to claim for excluded damage
        $analysisResult = [
            'severity' => 'MINOR',
            'damage_percentage' => 15,
            'estimated_cost' => 750,
            'detected_features' => ['minor_scratches'],
            'confidence_score' => 0.85,
        ];

        $context = [
            'damage_type' => 'cosmetic_only',
            'policy_exclusion' => 'cosmetic_damage',
        ];

        $decision = $service->generateDecision($analysisResult, 'AUTO', $context);

        /*
        Expected output:
        {
            "recommendation": "DENY",  // MINOR severity + no coverage
            "damage_percentage": 15,
            "estimated_cost": 750,
            "recommended_severity": "MINOR",
            "confidence_score": 85,
            "estimated_payout": 0,  // No payout
            "coverage_applicable": false,  // Key - coverage doesn't apply
            "reasoning": "First-level assessment for AUTO claim: MINOR damage (15% damage percentage).
                          Vehicle damage, collision severity, repair cost. Cosmetic damage. Vehicle fully operational.
                          Coverage does not apply.",
            "flags_for_review": []
        }
        */

        return $decision;
    }

    /**
     * Integration Example: Processing a Claim in Controller
     */
    public static function processClaimInController(
        FirstLevelDecisionService $service,
        $damageAnalysis,
        $claim
    ): void {
        // Extract data from analysis
        $analysisData = [
            'severity' => $damageAnalysis->getSeverity(),
            'damage_percentage' => $damageAnalysis->getAffectedAreaPercentage(),
            'estimated_cost' => $damageAnalysis->getEstimatedCost(),
            'detected_features' => $this->extractFeatures($damageAnalysis),
            'confidence_score' => 0.75,
        ];

        // Determine insurance type from contract
        $insuranceType = $claim->getContrat()->getType();  // AUTO, HOME, HEALTH, etc.

        // Optional context
        $context = [
            'third_party' => $claim->getHasThirdParty(),
            'witnesses' => $claim->getWitnessCount(),
        ];

        // Generate decision
        $decision = $service->generateDecision($analysisData, $insuranceType, $context);

        // Store in claim
        $claim->setFirstLevelRecommendation($decision['recommendation']);
        $claim->setFirstLevelConfidence($decision['confidence_score']);
        $claim->setEstimatedPayout($decision['estimated_payout']);

        // Route based on recommendation
        switch ($decision['recommendation']) {
            case 'APPROVE':
                // Can proceed to payment (may still require verification)
                $claim->setStatus('APPROVED_FOR_PAYMENT');
                break;

            case 'REVIEW':
                // Needs adjuster attention
                $claim->setStatus('PENDING_ADJUSTER_REVIEW');
                $claim->setAdminNotes($decision['admin_notes']);
                break;

            case 'DENY':
                // Not covered
                $claim->setStatus('DENIED');
                $claim->setDenialReason('Coverage does not apply');
                break;
        }

        // Save claim with decision
        $em->persist($claim);
        $em->flush();
    }
}
