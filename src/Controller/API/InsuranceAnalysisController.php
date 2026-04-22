<?php

namespace App\Controller\API;

use App\Service\HuggingFaceImageAnalysisService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Insurance Claim Analysis Controller
 * 
 * REST API for admin review workflow
 * Accepts insurance_type parameter and claim data
 * Returns structured analysis for human review only
 */
#[Route('/api/insurance-analysis')]
class InsuranceAnalysisController extends AbstractController
{
    public function __construct(
        private HuggingFaceImageAnalysisService $huggingFaceAnalysisService
    ) {
    }

    /**
     * Analyze insurance claim
     * 
     * POST /api/insurance-analysis/analyze
     * 
     * Body:
     * {
     *     "insurance_type": "AUTO|HOME|HEALTH|TRAVEL|LIABILITY",
     *     "damage_percentage": 0-100,
     *     "estimated_cost": number,
     *     "confidence_score": 0-100,
     *     "detected_features": { "feature_name": true/false, ... }
     * }
     */
    #[Route('/analyze', name: 'api_insurance_analyze', methods: ['POST'])]
    public function analyze(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            // Validate required parameter
            $insuranceType = $data['insurance_type'] ?? null;
            if (!$insuranceType) {
                return $this->json([
                    'error' => 'Missing required parameter: insurance_type',
                    'valid_types' => ['AUTO', 'HOME', 'HEALTH', 'TRAVEL', 'LIABILITY'],
                ], 400);
            }

            // Call analysis
            $analysis = $this->performAnalysis($insuranceType, $data);
            
            return $this->json($analysis);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Analyze a claim image and return insurance-ready assessment.
     *
     * POST /api/insurance-analysis/analyze-image
     *
     * Body (multipart/form-data):
     * - image: uploaded image (required)
     * - insurance_type: AUTO|HOME|HEALTH|TRAVEL|LIABILITY (optional, default AUTO)
     */
    #[Route('/analyze-image', name: 'api_insurance_analyze_image', methods: ['POST'])]
    public function analyzeImage(Request $request): JsonResponse
    {
        $file = $request->files->get('image');
        if (!$file) {
            return $this->json(['error' => 'No image file provided'], Response::HTTP_BAD_REQUEST);
        }

        $insuranceType = strtoupper((string) $request->request->get('insurance_type', 'AUTO'));
        $validTypes = ['AUTO', 'HOME', 'HEALTH', 'TRAVEL', 'LIABILITY'];
        if (!in_array($insuranceType, $validTypes, true)) {
            return $this->json([
                'error' => "Invalid insurance type: {$insuranceType}",
                'valid_types' => $validTypes,
            ], Response::HTTP_BAD_REQUEST);
        }

        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $tempPath = sys_get_temp_dir() . '/' . uniqid('claim_image_', true) . '.' . $extension;

        try {
            $file->move(dirname($tempPath), basename($tempPath));
            $hfAnalysis = $this->huggingFaceAnalysisService->analyzeClaimImage($tempPath, $insuranceType);

            if (($hfAnalysis['status'] ?? null) === 'INSUFFICIENT_DATA') {
                return $this->json([
                    'insurance_type' => $insuranceType,
                    'hf_analysis' => $hfAnalysis,
                    'admin_review_required' => true,
                    'final_decision_authority' => 'HUMAN_ADMIN_ONLY',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $damagePercentage = (int) ($hfAnalysis['damage_percentage'] ?? 0);
            $confidenceScore = (int) ($hfAnalysis['confidence_score'] ?? 0);
            $estimatedCost = $this->estimateCost($insuranceType, $damagePercentage);
            $detectedFeatures = $this->toFeatureMap($hfAnalysis['detected_damage_features'] ?? []);

            $insuranceAnalysis = $this->performAnalysis($insuranceType, [
                'insurance_type' => $insuranceType,
                'damage_percentage' => $damagePercentage,
                'estimated_cost' => $estimatedCost,
                'confidence_score' => $confidenceScore,
                'detected_features' => $detectedFeatures,
            ]);

            return $this->json([
                'insurance_type' => $insuranceType,
                'hf_analysis' => $hfAnalysis,
                'insurance_assessment' => $insuranceAnalysis,
                'analysis_mode' => 'hugging_face_inference',
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => 'Image analysis failed: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        } finally {
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    /**
     * Perform type-strict insurance analysis
     */
    private function performAnalysis(string $insuranceType, array $data): array
    {
        $type = strtoupper($insuranceType);
        
        // Validate type
        $validTypes = ['AUTO', 'HOME', 'HEALTH', 'TRAVEL', 'LIABILITY'];
        if (!in_array($type, $validTypes)) {
            return ['error' => "Invalid insurance type: $type"];
        }

        // Extract data
        $damagePercent = (int)($data['damage_percentage'] ?? 50);
        $cost = (int)($data['estimated_cost'] ?? 5000);
        $confidence = (int)($data['confidence_score'] ?? 65);
        $features = $data['detected_features'] ?? [];

        // Determine severity STRICTLY by type
        $severity = $this->determineSeverity($type, $damagePercent, $features, $confidence);

        // Build reasoning
        $reasoning = $this->buildReasoning($type, $severity, $damagePercent, $cost, $confidence);

        // Validate consistency
        $consistencyCheck = $this->validateConsistency($severity, $confidence, $damagePercent);

        // Safety: Low confidence = UNRELIABLE
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
            'timestamp' => date('c'),
        ];
    }

    /**
     * Determine severity STRICTLY by insurance type
     * CRITICAL RULE: Type MUST determine severity logic
     */
    private function determineSeverity(string $type, int $damagePercent, array $features, int $confidence): string
    {
        // Safety: Low confidence cannot justify MAJOR
        if ($confidence < 60 && $damagePercent < 70) {
            return 'MEDIUM';
        }

        return match($type) {
            'AUTO' => $damagePercent >= 70 ? 'MAJOR' : ($damagePercent >= 40 ? 'MEDIUM' : 'MINOR'),
            'HOME' => $damagePercent >= 70 ? 'MAJOR' : ($damagePercent >= 40 ? 'MEDIUM' : 'MINOR'),
            'HEALTH' => isset($features['serious_injury']) && $features['serious_injury'] ? 'MAJOR' : ($features['moderate_injury'] ?? false ? 'MEDIUM' : 'MINOR'),
            'TRAVEL' => $damagePercent >= 80 ? 'MAJOR' : ($damagePercent >= 40 ? 'MEDIUM' : 'MINOR'),
            'LIABILITY' => isset($features['third_party_serious_injury']) && $features['third_party_serious_injury'] ? 'MAJOR' : 'MEDIUM',
            default => 'MEDIUM',
        };
    }

    /**
     * Build reasoning mentioning insurance type
     */
    private function buildReasoning(string $type, string $severity, int $damage, int $cost, int $confidence): string
    {
        return match($type) {
            'AUTO' => "INSURANCE_TYPE: $type (vehicle damage only). Damage: $damage%. Severity: $severity. " .
                      "Cost: \$$cost. Confidence: $confidence%. Analysis evaluates ONLY vehicle damage factors. " .
                      "ADMIN REVIEW REQUIRED.",
            'HOME' => "INSURANCE_TYPE: $type (property damage only). Damage: $damage%. Severity: $severity. " .
                      "Cost: \$$cost. Confidence: $confidence%. Analysis evaluates ONLY property damage factors. " .
                      "ADMIN REVIEW REQUIRED.",
            'HEALTH' => "INSURANCE_TYPE: $type (injury only). Severity: $severity. Cost: \$$cost. " .
                        "Confidence: $confidence%. Analysis evaluates ONLY injury factors. ADMIN REVIEW REQUIRED.",
            'TRAVEL' => "INSURANCE_TYPE: $type (trip disruption only). Disruption: $damage%. Severity: $severity. " .
                        "Cost: \$$cost. Confidence: $confidence%. Analysis evaluates ONLY trip factors. ADMIN REVIEW REQUIRED.",
            'LIABILITY' => "INSURANCE_TYPE: $type (legal risk only). Severity: $severity. Cost: \$$cost. " .
                           "Confidence: $confidence%. Analysis evaluates ONLY liability factors. ADMIN REVIEW REQUIRED.",
            default => "Analysis complete. Confidence: $confidence%. ADMIN REVIEW REQUIRED.",
        };
    }

    /**
     * Validate logical consistency
     */
    private function validateConsistency(string $severity, int $confidence, int $damage): bool
    {
        if ($severity === 'UNRELIABLE' && $confidence >= 50) return false;
        if ($severity === 'MAJOR' && $confidence < 60) return false;
        if ($damage >= 70 && $severity === 'MINOR') return false;
        return true;
    }

    private function toFeatureMap(array $features): array
    {
        $featureMap = [];
        foreach ($features as $feature) {
            if (!is_string($feature) || $feature === '') {
                continue;
            }

            $normalized = strtolower(trim($feature));
            $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized);
            $normalized = trim((string) $normalized, '_');

            if ($normalized !== '') {
                $featureMap[$normalized] = true;
            }
        }

        return $featureMap;
    }

    private function estimateCost(string $insuranceType, int $damagePercentage): int
    {
        $baseCosts = [
            'AUTO' => 750,
            'HOME' => 1000,
            'HEALTH' => 1200,
            'TRAVEL' => 400,
            'LIABILITY' => 1500,
        ];

        $base = $baseCosts[$insuranceType] ?? 800;
        $multiplier = 1 + ($damagePercentage / 100) * 2.5;

        return (int) round($base * $multiplier);
    }
}
