<?php

namespace App\Service;

use App\Entity\Sinistre;
use App\Entity\DamageAnalysis;
use Doctrine\ORM\EntityManagerInterface;

class ClaimCoverageAnalysisService
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * Analyze insurance coverage based on damage analysis results
     */
    public function analyzeCoverage(Sinistre $sinistre): array
    {
        $damageAnalyses = $sinistre->getDamageAnalyses();
        $contrat = $sinistre->getContrat();
        
        $result = [
            'hasDamageAnalysis' => false,
            'hasInsurance' => false,
            'damageAnalyses' => [],
            'estimatedCost' => 0,
            'coverageDetails' => [],
            'message' => ''
        ];

        // Check if there's damage analysis
        if ($damageAnalyses->isEmpty()) {
            $result['message'] = 'No damage analysis available. Please upload damage images.';
            return $result;
        }

        $result['hasDamageAnalysis'] = true;
        $totalEstimatedCost = 0;

        // Collect damage analyses
        foreach ($damageAnalyses as $analysis) {
            $result['damageAnalyses'][] = [
                'severity' => $analysis->getSeverity(),
                'damageType' => $analysis->getDamageType(),
                'affectedArea' => $analysis->getAffectedAreaPercentage(),
                'estimatedCost' => $analysis->getEstimatedCost(),
                'analyzed' => $analysis->getAnalyzedAt()
            ];
            $totalEstimatedCost += $analysis->getEstimatedCost();
        }

        $result['estimatedCost'] = $totalEstimatedCost;

        // Check insurance coverage
        if (!$contrat) {
            $result['message'] = 'No insurance contract found for this claim.';
            return $result;
        }

        $result['hasInsurance'] = true;
        $assurance = $contrat->getAssurance();

        if (!$assurance) {
            $result['message'] = 'Insurance policy not found.';
            return $result;
        }

        // Get insurance details
        $coverageLimit = $assurance->getMontantCouverture() ?? 0;
        $deductible = $assurance->getFranchise() ?? 0;
        $annualCeiling = $contrat->getPlafondAnnuel() ?? $coverageLimit;
        $reimbursementRate = ($contrat->getTauxRemboursement() ?? 100) / 100;

        // Calculate coverage
        $amountAfterDeductible = max(0, $totalEstimatedCost - $deductible);
        $amountBeforeCeiling = $amountAfterDeductible * $reimbursementRate;
        $amountCovered = min($amountBeforeCeiling, $annualCeiling);
        $shortfall = max(0, $totalEstimatedCost - $amountCovered - $deductible);

        $result['coverageDetails'] = [
            'estimatedCost' => $totalEstimatedCost,
            'deductible' => $deductible,
            'coverageLimit' => $coverageLimit,
            'annualCeiling' => $annualCeiling,
            'reimbursementRate' => $contrat->getTauxRemboursement() ?? 100,
            'amountCovered' => $amountCovered,
            'shortfall' => $shortfall
        ];

        // Generate message
        if ($totalEstimatedCost <= $deductible) {
            $result['coverageStatus'] = 'not_covered';
            $result['message'] = "Damage cost (€" . number_format($totalEstimatedCost, 2) . ") is below deductible (€" . number_format($deductible, 2) . "). Not covered.";
        } elseif ($amountCovered >= $amountBeforeCeiling) {
            $result['coverageStatus'] = 'fully_covered';
            $result['message'] = "Damage is fully covered! You'll receive €" . number_format($amountCovered, 2) . " after deductible of €" . number_format($deductible, 2) . ".";
        } else {
            $result['coverageStatus'] = 'partially_covered';
            $result['message'] = "Damage is partially covered. You'll receive €" . number_format($amountCovered, 2) . ", but there's a shortfall of €" . number_format($shortfall, 2) . ".";
        }

        return $result;
    }
}
