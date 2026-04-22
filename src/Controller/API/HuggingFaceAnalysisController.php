<?php

namespace App\Controller\API;

use App\Service\HuggingFaceImageAnalysisService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Hugging Face Image Analysis Controller
 * 
 * REST API endpoints for insurance claim image analysis using Hugging Face models
 * CRITICAL: All outputs strictly based on model inference, no guessing or hallucination
 */
#[Route('/api/huggingface-analysis')]
class HuggingFaceAnalysisController extends AbstractController
{
    public function __construct(
        private HuggingFaceImageAnalysisService $hfAnalysisService
    ) {}

    /**
     * Analyze uploaded insurance claim image
     * 
     * POST /api/huggingface-analysis/analyze-image
     * 
     * Body (multipart/form-data):
     * - image: image file (JPEG, PNG)
     * - insurance_type: AUTO|HOME|HEALTH|TRAVEL|LIABILITY
     * 
     * Response: JSON with damage_percentage, confidence, features, reasoning
     * Or: INSUFFICIENT_DATA if model cannot analyze
     */
    #[Route('/analyze-image', name: 'api_hf_analyze_image', methods: ['POST'])]
    public function analyzeImage(Request $request): JsonResponse
    {
        try {
            // Get uploaded file
            $file = $request->files->get('image');
            if (!$file) {
                return $this->json(['error' => 'No image file provided'], 400);
            }

            // Get insurance type
            $insuranceType = $request->request->get('insurance_type', 'AUTO');

            // Validate insurance type
            $validTypes = ['AUTO', 'HOME', 'HEALTH', 'TRAVEL', 'LIABILITY'];
            if (!in_array(strtoupper($insuranceType), $validTypes)) {
                return $this->json(['error' => "Invalid insurance type: $insuranceType"], 400);
            }

            // Save uploaded file temporarily
            $tempPath = sys_get_temp_dir() . '/' . uniqid('claim_') . '.' . $file->getClientOriginalExtension();
            $file->move(dirname($tempPath), basename($tempPath));

            // Analyze with Hugging Face model
            $analysis = $this->hfAnalysisService->analyzeClaimImage($tempPath, strtoupper($insuranceType));

            // Clean up temp file
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }

            return $this->json($analysis);

        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Analyze image from URL
     * 
     * POST /api/huggingface-analysis/analyze-url
     * 
     * Body (JSON):
     * {
     *     "image_url": "https://example.com/image.jpg",
     *     "insurance_type": "AUTO"
     * }
     */
    #[Route('/analyze-url', name: 'api_hf_analyze_url', methods: ['POST'])]
    public function analyzeUrl(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['image_url'])) {
                return $this->json(['error' => 'Missing image_url parameter'], 400);
            }

            $imageUrl = $data['image_url'];
            $insuranceType = $data['insurance_type'] ?? 'AUTO';

            // Download image
            $imageData = @file_get_contents($imageUrl);
            if (!$imageData) {
                return $this->json(['error' => 'Cannot download image from URL'], 400);
            }

            // Save to temp file
            $tempPath = sys_get_temp_dir() . '/' . uniqid('claim_') . '.jpg';
            file_put_contents($tempPath, $imageData);

            // Analyze
            $analysis = $this->hfAnalysisService->analyzeClaimImage($tempPath, strtoupper($insuranceType));

            // Cleanup
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }

            return $this->json($analysis);

        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
}
