<?php

namespace App\Service;

use App\Entity\DamageAnalysis;
use App\Entity\Sinistre;
use App\Entity\SinistrePreuve;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Psr\Log\LoggerInterface;

class ClaudeDamageAnalysisService
{
    private $apiKey;
    private $em;
    private $kernel;
    private $logger;

    public function __construct(
        EntityManagerInterface $em,
        KernelInterface $kernel,
        LoggerInterface $logger
    ) {
        $this->em = $em;
        $this->kernel = $kernel;
        $this->logger = $logger;
        
        // Try to get API key from environment
        $this->apiKey = $_ENV['CLAUDE_API_KEY'] ?? '';
        
        // If not found in $_ENV, try to load from .env file directly
        if (empty($this->apiKey)) {
            $envFile = $this->kernel->getProjectDir() . '/.env.local';
            if (!file_exists($envFile)) {
                $envFile = $this->kernel->getProjectDir() . '/.env';
            }
            
            $this->logger->debug('Reading .env from: ' . $envFile . ' (exists: ' . (file_exists($envFile) ? 'YES' : 'NO') . ')');
            
            if (file_exists($envFile)) {
                $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $this->logger->debug('Total lines in .env: ' . count($lines));
                foreach ($lines as $line) {
                    if (strpos($line, 'CLAUDE_API_KEY=') === 0) {
                        $this->apiKey = substr($line, strlen('CLAUDE_API_KEY='));
                        $this->logger->debug('Found CLAUDE_API_KEY in .env, length: ' . strlen($this->apiKey));
                        break;
                    }
                }
            }
        }
        
        $this->logger->info('ClaudeDamageAnalysisService initialized - API key configured: ' . (!empty($this->apiKey) ? 'YES' : 'NO'));
    }

    /**
     * Analyze damage in an image using Claude Vision API
     */
    public function analyzeDamage(SinistrePreuve $preuve, Sinistre $sinistre): ?DamageAnalysis
    {
        if (!$this->apiKey) {
            $this->logger->error('Claude API key not configured. Set CLAUDE_API_KEY in .env file');
            return null;
        }

        // Only analyze images
        if (!$this->isImageFile($preuve->getNomFichier())) {
            $this->logger->debug('File is not an image: ' . $preuve->getNomFichier());
            return null;
        }

        // Get image file path
        $imagePath = $this->kernel->getProjectDir() . '/public' . $preuve->getFichier();
        
        if (!file_exists($imagePath)) {
            $this->logger->error('Image file not found: ' . $imagePath);
            throw new \Exception('Image file not found: ' . $imagePath);
        }

        $this->logger->info('Starting damage analysis for: ' . $preuve->getNomFichier());

        // Read image and convert to base64
        $imageData = file_get_contents($imagePath);
        $base64Image = base64_encode($imageData);
        $mimeType = $preuve->getTypeFichier();

        try {
            // Call Claude API
            $analysis = $this->callClaudeAPI($base64Image, $mimeType);

            if (!$analysis) {
                $this->logger->error('Claude API returned null analysis');
                return null;
            }

            // Parse the response and create DamageAnalysis entity
            $damageAnalysis = new DamageAnalysis();
            $damageAnalysis->setPreuve($preuve);
            $damageAnalysis->setSinistre($sinistre);
            $damageAnalysis->setSeverity($analysis['severity']);
            $damageAnalysis->setDamageType($analysis['damage_type']);
            $damageAnalysis->setAffectedAreaPercentage($analysis['affected_area_percentage']);
            $damageAnalysis->setEstimatedCost($analysis['estimated_cost'] ?? null);
            $damageAnalysis->setAnalysisDetails($analysis['details']);
            $damageAnalysis->setAiModel('claude-3-5-sonnet');

            // Save to database
            $this->em->persist($damageAnalysis);
            $this->em->flush();

            $this->logger->info('Damage analysis completed successfully. Severity: ' . $analysis['severity']);
            return $damageAnalysis;
        } catch (\Exception $e) {
            $this->logger->error('Claude API analysis error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Call Claude API with vision capabilities
     */
    private function callClaudeAPI(string $base64Image, string $mimeType): ?array
    {
        $url = 'https://api.anthropic.com/v1/messages';

        $payload = [
            'model' => 'claude-3-5-sonnet-20241022',
            'max_tokens' => 1024,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'image',
                            'source' => [
                                'type' => 'base64',
                                'media_type' => $mimeType,
                                'data' => $base64Image,
                            ],
                        ],
                        [
                            'type' => 'text',
                            'text' => $this->getAnalysisPrompt(),
                        ],
                    ],
                ],
            ],
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'x-api-key: ' . $this->apiKey,
            'anthropic-version: 2023-06-01',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $this->logger->error('Claude API error: HTTP ' . $httpCode . ' - ' . substr($response, 0, 500));
            throw new \Exception('Claude API error: HTTP ' . $httpCode . ' - ' . $response);
        }

        $data = json_decode($response, true);

        if (!$data || !isset($data['content'][0]['text'])) {
            $this->logger->error('Invalid Claude API response: ' . substr($response, 0, 500));
            throw new \Exception('Invalid Claude API response');
        }
        
        $this->logger->debug('Claude API response received and parsed successfully');

        // Parse the AI response
        return $this->parseAnalysisResponse($data['content'][0]['text']);
    }

    /**
     * Get the analysis prompt for Claude
     */
    private function getAnalysisPrompt(): string
    {
        return <<<'PROMPT'
You are an insurance damage assessment expert. Analyze this damage image and provide a detailed assessment in JSON format.

Return ONLY valid JSON with this exact structure (no markdown, no extra text):
{
  "severity": "minor|moderate|severe|critical",
  "damage_type": "describe the main type of damage (e.g., fire damage, water damage, structural damage, etc.)",
  "affected_area_percentage": 10,
  "estimated_cost": 5000,
  "details": "detailed description of the damage, location, extent, and any observations"
}

Severity levels:
- minor: Small, easily fixed damage (damage < 5% of area)
- moderate: Significant damage requiring repairs (damage 5-25% of area)
- severe: Extensive damage (damage 25-75% of area)
- critical: Total loss or near-total damage (damage > 75% of area)

Estimated cost should be in USD. If you cannot estimate, set to null.
PROMPT;
    }

    /**
     * Parse the Claude API response and extract analysis data
     */
    private function parseAnalysisResponse(string $response): ?array
    {
        try {
            // Try to extract JSON from the response
            $jsonMatch = [];
            if (preg_match('/\{[\s\S]*\}/', $response, $jsonMatch)) {
                $analysis = json_decode($jsonMatch[0], true);
                
                if ($analysis && isset($analysis['severity'])) {
                    // Validate severity
                    if (!in_array($analysis['severity'], ['minor', 'moderate', 'severe', 'critical'])) {
                        $analysis['severity'] = 'moderate';
                    }

                    // Ensure affected_area_percentage is valid
                    $area = (int)($analysis['affected_area_percentage'] ?? 0);
                    $analysis['affected_area_percentage'] = max(0, min(100, $area));

                    return $analysis;
                }
            }
        } catch (\Exception $e) {
            // If parsing fails, return default analysis
        }

        // Default analysis if parsing fails
        return [
            'severity' => 'moderate',
            'damage_type' => 'Unable to determine damage type from image',
            'affected_area_percentage' => 0,
            'estimated_cost' => null,
            'details' => 'Image analysis was not completed. Please provide a clearer damage image.',
        ];
    }

    /**
     * Check if file is an image
     */
    private function isImageFile(string $filename): bool
    {
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($ext, $imageExtensions);
    }

    /**
     * Check if Claude API is configured
     */
    public function isConfigured(): bool
    {
        $configured = !empty($this->apiKey);
        $this->logger->debug('Claude API isConfigured check - apiKey length: ' . strlen($this->apiKey) . ', configured: ' . ($configured ? 'YES' : 'NO'));
        return $configured;
    }
}
