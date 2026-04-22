<?php

namespace App\Controller\API;

use App\Entity\Sinistre;
use App\Service\DamageDetection\DamageDetectionOrchestrator;
use App\Service\DamageDetection\FirstLevelDecisionService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * First-Level Decision API
 * 
 * Provides first-level insurance claim assessment recommendations
 * based on damage analysis and insurance type context.
 * 
 * All decisions are recommendations only - final approval by admin.
 */
#[Route('/api/first-level-decision')]
class FirstLevelDecisionController extends AbstractController
{
    private DamageDetectionOrchestrator $orchestrator;
    private FirstLevelDecisionService $decisionService;
    private EntityManagerInterface $em;
    private LoggerInterface $logger;

    public function __construct(
        DamageDetectionOrchestrator $orchestrator,
        FirstLevelDecisionService $decisionService,
        EntityManagerInterface $em,
        LoggerInterface $logger
    ) {
        $this->orchestrator = $orchestrator;
        $this->decisionService = $decisionService;
        $this->em = $em;
        $this->logger = $logger;
    }

    /**
     * Analyze claim and generate first-level decision
     * 
     * POST /api/first-level-decision/analyze/{sinistreId}
     * 
     * Body:
     * {
     *     "insurance_type": "AUTO|HOME|HEALTH|TRAVEL|LIABILITY|SCHOOL|PROFESSIONAL",
     *     "context": {
     *         "vehicle_type": "sedan" (optional),
     *         "injury_type": "fracture" (optional),
     *         "third_party": true (optional)
     *     }
     * }
     */
    #[Route('/analyze/{sinistreId}', name: 'api_first_level_decision_analyze', methods: ['POST'])]
    public function analyzeClaim(
        int $sinistreId,
        Request $request
    ): JsonResponse {
        try {
            // Get claim
            $sinistre = $this->em->getRepository(Sinistre::class)->find($sinistreId);
            if (!$sinistre) {
                return new JsonResponse(['error' => 'Claim not found'], 404);
            }

            // Check authorization
            if ($sinistre->getUtilisateur()->getId() !== $this->getUser()?->getId() &&
                strtolower($this->getUser()?->getRole() ?? '') !== 'admin') {
                return new JsonResponse(['error' => 'Unauthorized'], 403);
            }

            // Get request data
            $data = json_decode($request->getContent(), true);
            $insuranceType = $data['insurance_type'] ?? 'AUTO';
            $context = $data['context'] ?? [];

            // Get latest damage analysis for claim
            $analyses = $sinistre->getDamageAnalyses();
            if ($analyses->isEmpty()) {
                return new JsonResponse(['error' => 'No analysis available for this claim'], 400);
            }

            // Use most recent analysis
            $latestAnalysis = $analyses->last();

            // Build analysis result array from DamageAnalysis entity
            $analysisResult = [
                'severity' => $latestAnalysis->getSeverity(),
                'damage_percentage' => $this->extractDamagePercentage($latestAnalysis),
                'estimated_cost' => $latestAnalysis->getEstimatedCost(),
                'detected_features' => $this->extractDetectedFeatures($latestAnalysis),
                'confidence_score' => $this->extractConfidenceScore($latestAnalysis),
                'ai_model' => $latestAnalysis->getAiModel(),
            ];

            // Generate first-level decision
            $decision = $this->decisionService->generateDecision(
                $analysisResult,
                $insuranceType,
                $context
            );

            $this->logger->info('First-level decision generated', [
                'sinistre_id' => $sinistreId,
                'recommendation' => $decision['recommendation'],
                'insurance_type' => $insuranceType,
            ]);

            return new JsonResponse([
                'success' => true,
                'claim_id' => $sinistreId,
                'decision' => $decision,
                'note' => 'This is a first-level recommendation. Final approval authority rests with admin.',
            ]);
        } catch (\Exception $e) {
            $this->logger->error('First-level decision generation failed', [
                'error' => $e->getMessage(),
                'sinistre_id' => $sinistreId,
            ]);

            return new JsonResponse([
                'error' => 'Decision generation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get decision history for a claim
     * 
     * GET /api/first-level-decision/history/{sinistreId}
     */
    #[Route('/history/{sinistreId}', name: 'api_first_level_decision_history', methods: ['GET'])]
    public function getDecisionHistory(int $sinistreId): JsonResponse
    {
        try {
            $sinistre = $this->em->getRepository(Sinistre::class)->find($sinistreId);
            if (!$sinistre) {
                return new JsonResponse(['error' => 'Claim not found'], 404);
            }

            // Check authorization
            if ($sinistre->getUtilisateur()->getId() !== $this->getUser()?->getId() &&
                strtolower($this->getUser()?->getRole() ?? '') !== 'admin') {
                return new JsonResponse(['error' => 'Unauthorized'], 403);
            }

            // Get all analyses with their respective decisions
            $analyses = $sinistre->getDamageAnalyses();

            $history = [];
            foreach ($analyses as $analysis) {
                $history[] = [
                    'analyzed_at' => $analysis->getAnalyzedAt()?->format('Y-m-d H:i:s'),
                    'ai_model' => $analysis->getAiModel(),
                    'severity' => $analysis->getSeverity(),
                    'estimated_cost' => $analysis->getEstimatedCost(),
                    'affected_area' => $analysis->getAffectedAreaPercentage(),
                ];
            }

            return new JsonResponse([
                'success' => true,
                'claim_id' => $sinistreId,
                'analysis_history' => $history,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Extract damage percentage from analysis details
     */
    private function extractDamagePercentage($analysis): int
    {
        // Try to get from analysis_details JSON
        $details = $analysis->getAnalysisDetails();
        if (preg_match('/Damage Percentage:\s*(\d+)%/', $details, $matches)) {
            return (int)$matches[1];
        }

        // Fallback to affected area
        return $analysis->getAffectedAreaPercentage() ?? 0;
    }

    /**
     * Extract detected features from analysis details
     */
    private function extractDetectedFeatures($analysis): array
    {
        $details = $analysis->getAnalysisDetails();
        $features = [];

        // Try to extract from report
        if (preg_match('/Detected Features:\s*(.+?)(?:\n|$)/', $details, $matches)) {
            $featureStr = $matches[1];
            $features = array_map('trim', explode(',', $featureStr));
        }

        return $features ?: ['general_damage'];
    }

    /**
     * Extract confidence score from analysis details
     */
    private function extractConfidenceScore($analysis): float
    {
        $details = $analysis->getAnalysisDetails();

        // Try to extract from report
        if (preg_match('/Confidence:\s*([\d.]+)%?/', $details, $matches)) {
            $score = (float)$matches[1];
            return $score > 1 ? $score / 100 : $score;
        }

        // Default to moderate confidence
        return 0.65;
    }
}
