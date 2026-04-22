<?php

namespace App\Controller\API;

use App\Entity\Sinistre;
use App\Entity\SinistrePreuve;
use App\Service\AdvancedDamageDetectionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/damage-analysis', name: 'api_damage_')]
class DamageAnalysisController extends AbstractController
{
    #[Route('/analyze/{sinistreId}', name: 'analyze_single', methods: ['POST'])]
    public function analyzeSingle(
        int $sinistreId,
        Request $request,
        EntityManagerInterface $em,
        AdvancedDamageDetectionService $analysisService
    ): JsonResponse {
        // Get the sinistre
        $sinistre = $em->getRepository(Sinistre::class)->find($sinistreId);
        if (!$sinistre) {
            return $this->json(['error' => 'Sinistre not found'], Response::HTTP_NOT_FOUND);
        }

        // Check authorization
        if ($sinistre->getUtilisateur()->getId() !== $this->getUser()?->getId() && 
            strtolower($this->getUser()?->getRole() ?? '') !== 'admin') {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_FORBIDDEN);
        }

        try {
            // Analyze all unanalyzed preuves
            $preuves = $sinistre->getPreuves();
            $analyzedCount = 0;

            foreach ($preuves as $preuve) {
                // Check if already analyzed
                $existingAnalysis = $em->getRepository(\App\Entity\DamageAnalysis::class)
                    ->findBy(['preuve' => $preuve]);
                
                if (empty($existingAnalysis)) {
                    try {
                        $analysisService->analyzeSingleImage($preuve, $sinistre);
                        $analyzedCount++;
                    } catch (\Exception $e) {
                        // Continue with other images
                    }
                }
            }

            // Get all analyses for this sinistre
            $analyses = $sinistre->getDamageAnalyses();
            
            return $this->json([
                'success' => true,
                'message' => "Analyzed $analyzedCount images",
                'total_analyses' => count($analyses),
                'analyses' => $this->formatAnalysesForResponse($analyses),
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/reanalyze/{sinistreId}', name: 'reanalyze', methods: ['POST'])]
    public function reanalyze(
        int $sinistreId,
        Request $request,
        EntityManagerInterface $em,
        AdvancedDamageDetectionService $analysisService
    ): JsonResponse {
        // Get the sinistre
        $sinistre = $em->getRepository(Sinistre::class)->find($sinistreId);
        if (!$sinistre) {
            return $this->json(['error' => 'Sinistre not found'], Response::HTTP_NOT_FOUND);
        }

        // Check authorization
        if ($sinistre->getUtilisateur()->getId() !== $this->getUser()?->getId() && 
            strtolower($this->getUser()?->getRole() ?? '') !== 'admin') {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_FORBIDDEN);
        }

        try {
            // Delete existing analyses
            $existingAnalyses = $sinistre->getDamageAnalyses();
            foreach ($existingAnalyses as $analysis) {
                $em->remove($analysis);
            }
            $em->flush();

            // Reanalyze all preuves
            $preuves = $sinistre->getPreuves();
            $analyzedCount = 0;

            foreach ($preuves as $preuve) {
                try {
                    $analysisService->analyzeSingleImage($preuve, $sinistre);
                    $analyzedCount++;
                } catch (\Exception $e) {
                    // Continue with other images
                }
            }

            // Get all new analyses
            $analyses = $sinistre->getDamageAnalyses();
            
            return $this->json([
                'success' => true,
                'message' => "Re-analyzed $analyzedCount images",
                'total_analyses' => count($analyses),
                'analyses' => $this->formatAnalysesForResponse($analyses),
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/upload/{sinistreId}', name: 'upload_analyze', methods: ['POST'])]
    public function uploadAndAnalyze(
        int $sinistreId,
        Request $request,
        EntityManagerInterface $em,
        AdvancedDamageDetectionService $analysisService
    ): JsonResponse {
        $sinistre = $em->getRepository(Sinistre::class)->find($sinistreId);
        if (!$sinistre) {
            return $this->json(['error' => 'Sinistre not found'], Response::HTTP_NOT_FOUND);
        }

        // Check authorization
        if ($sinistre->getUtilisateur()->getId() !== $this->getUser()?->getId() && 
            strtolower($this->getUser()?->getRole() ?? '') !== 'admin') {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_FORBIDDEN);
        }

        $uploadedFiles = $request->files->get('images');
        if (!$uploadedFiles) {
            return $this->json(['error' => 'No images provided'], Response::HTTP_BAD_REQUEST);
        }

        if (!is_array($uploadedFiles)) {
            $uploadedFiles = [$uploadedFiles];
        }

        try {
            $projectDir = $this->getParameter('kernel.project_dir');
            $uploadDir = $projectDir . '/public/uploads/sinistre_preuves/';
            
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $uploadedCount = 0;
            $analyzedCount = 0;
            $errors = [];

            foreach ($uploadedFiles as $file) {
                if ($file->getSize() === 0) {
                    continue;
                }

                $fileExtension = strtolower($file->getClientOriginalExtension());
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                if (!in_array($fileExtension, $allowedExtensions)) {
                    $errors[] = $file->getClientOriginalName() . ': Invalid file type';
                    continue;
                }

                try {
                    // Move uploaded file
                    $fileName = uniqid('preuve_') . '_' . $file->getClientOriginalName();
                    $file->move($uploadDir, $fileName);

                    // Create SinistrePreuve
                    $preuve = new SinistrePreuve();
                    $preuve->setSinistre($sinistre);
                    $preuve->setFichier('/uploads/sinistre_preuves/' . $fileName);
                    $preuve->setTypeFichier('image/' . $fileExtension);
                    $preuve->setNomFichier($file->getClientOriginalName());

                    $em->persist($preuve);
                    $em->flush();

                    // Analyze damage
                    try {
                        $analysisService->analyzeSingleImage($preuve, $sinistre);
                        $analyzedCount++;
                    } catch (\Exception $e) {
                        $errors[] = $file->getClientOriginalName() . ': Analysis failed - ' . $e->getMessage();
                    }

                    $uploadedCount++;
                } catch (\Exception $e) {
                    $errors[] = $file->getClientOriginalName() . ': ' . $e->getMessage();
                }
            }

            $analyses = $sinistre->getDamageAnalyses();

            return $this->json([
                'success' => true,
                'uploaded' => $uploadedCount,
                'analyzed' => $analyzedCount,
                'total_analyses' => count($analyses),
                'errors' => $errors,
                'analyses' => $this->formatAnalysesForResponse($analyses),
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/summary/{sinistreId}', name: 'summary', methods: ['GET'])]
    public function getSummary(
        int $sinistreId,
        EntityManagerInterface $em
    ): JsonResponse {
        $sinistre = $em->getRepository(Sinistre::class)->find($sinistreId);
        if (!$sinistre) {
            return $this->json(['error' => 'Sinistre not found'], Response::HTTP_NOT_FOUND);
        }

        // Check authorization
        if ($sinistre->getUtilisateur()->getId() !== $this->getUser()?->getId() && 
            strtolower($this->getUser()?->getRole() ?? '') !== 'admin') {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_FORBIDDEN);
        }

        $analyses = $sinistre->getDamageAnalyses();
        
        if (empty($analyses)) {
            return $this->json([
                'sinistre_id' => $sinistreId,
                'analyses_count' => 0,
                'summary' => 'No damage analyses available',
            ]);
        }

        // Calculate summary statistics
        $severities = [];
        $totalCost = 0;
        $damageTypes = [];
        $affectedAreas = [];

        foreach ($analyses as $analysis) {
            $severity = $analysis->getSeverity();
            $severities[$severity] = ($severities[$severity] ?? 0) + 1;
            $totalCost += $analysis->getEstimatedCost();
            $damageTypes[] = $analysis->getDamageType();
            $affectedAreas[] = $analysis->getAffectedAreaPercentage();
        }

        // Find most common severity
        arsort($severities);
        $commonestSeverity = array_key_first($severities);

        return $this->json([
            'sinistre_id' => $sinistreId,
            'analyses_count' => count($analyses),
            'overall_severity' => $commonestSeverity,
            'severity_breakdown' => $severities,
            'total_estimated_cost' => $totalCost,
            'average_affected_area' => round(array_sum($affectedAreas) / count($affectedAreas), 1),
            'unique_damage_types' => array_unique($damageTypes),
        ]);
    }

    /**
     * Format damage analyses for JSON response
     */
    private function formatAnalysesForResponse($analyses): array
    {
        $formatted = [];
        foreach ($analyses as $analysis) {
            $formatted[] = [
                'id' => $analysis->getId(),
                'severity' => $analysis->getSeverity(),
                'damage_type' => $analysis->getDamageType(),
                'affected_area' => $analysis->getAffectedAreaPercentage(),
                'estimated_cost' => $analysis->getEstimatedCost(),
                'ai_model' => $analysis->getAiModel(),
                'analyzed_at' => $analysis->getAnalyzedAt()->format('Y-m-d H:i:s'),
                'evidence_file' => $analysis->getPreuve()?->getNomFichier(),
                'details_preview' => substr($analysis->getAnalysisDetails(), 0, 200) . '...',
            ];
        }
        return $formatted;
    }
}
