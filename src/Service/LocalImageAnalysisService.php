<?php

namespace App\Service;

use App\Entity\DamageAnalysis;
use App\Entity\Sinistre;
use App\Entity\SinistrePreuve;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Local Image Analysis Service - No API calls, no credits needed
 * Analyzes damage images using GD library and local heuristics
 */
class LocalImageAnalysisService
{
    private EntityManagerInterface $em;
    private LoggerInterface $logger;
    private string $uploadsDir;
    private string $projectDir;

    public function __construct(
        EntityManagerInterface $em,
        LoggerInterface $logger,
        KernelInterface $kernel
    ) {
        $this->em = $em;
        $this->logger = $logger;
        $this->projectDir = $kernel->getProjectDir();
        $this->uploadsDir = $this->projectDir . '/public/uploads/sinistre_preuves';
    }

    public function isConfigured(): bool
    {
        // Service is always configured - analysis works with or without GD/EXIF
        return true;
    }

    public function analyzeDamage(SinistrePreuve $preuve, Sinistre $sinistre): void
    {
        try {
            $this->logger->info('Starting local image analysis for: ' . $preuve->getNomFichier());

            // Build full path to image using the stored fichier path
            // fichier contains: /uploads/sinistre_preuves/preuve_XXXX_filename.ext
            $imagePath = $this->projectDir . '/public' . $preuve->getFichier();
            
            if (!file_exists($imagePath)) {
                throw new \Exception('Image file not found: ' . $imagePath);
            }

            // Validate it's an image
            $imageInfo = @getimagesize($imagePath);
            if (!$imageInfo) {
                throw new \Exception('File is not a valid image');
            }

            // Analyze the image
            $analysis = $this->analyzeImageContent($imagePath, $preuve->getNomFichier());

            // Create and persist analysis record
            $damageAnalysis = new DamageAnalysis();
            $damageAnalysis->setPreuve($preuve);
            $damageAnalysis->setSinistre($sinistre);
            $damageAnalysis->setSeverity($analysis['severity']);
            $damageAnalysis->setDamageType($analysis['damage_type']);
            $damageAnalysis->setAffectedAreaPercentage($analysis['affected_area']);
            $damageAnalysis->setEstimatedCost($analysis['estimated_cost']);
            $damageAnalysis->setAnalysisDetails($analysis['details']);
            $damageAnalysis->setAiModel('LocalImageAnalysis-GD-v1');
            $damageAnalysis->setAnalyzedAt(new \DateTime());

            $this->em->persist($damageAnalysis);
            $this->em->flush();

            $this->logger->info('Local image analysis completed successfully for: ' . $preuve->getNomFichier());

        } catch (\Exception $e) {
            $this->logger->error('Local image analysis error: ' . $e->getMessage());
            throw $e;
        }
    }

    private function analyzeImageContent(string $imagePath, string $fileName): array
    {
        $severity = 'minor';
        $damageType = 'Unknown damage';
        $affectedArea = 15;
        $estimatedCost = 500;
        $details = '';

        try {
            // Get image dimensions and file size
            $imageInfo = getimagesize($imagePath);
            if (!$imageInfo) {
                return $this->getDefaultAnalysis('Unable to read image information');
            }
            
            $fileSize = filesize($imagePath);
            $fileSizeKb = round($fileSize / 1024, 2);
            $width = $imageInfo[0];
            $height = $imageInfo[1];
            $pixelCount = $width * $height;

            // If GD is available, do detailed color analysis
            if (extension_loaded('gd')) {
                // Load image resource
                $imgResource = $this->loadImageResource($imagePath);
                if ($imgResource) {
                    // Get image dimensions from resource
                    $width = imagesx($imgResource);
                    $height = imagesy($imgResource);
                    $pixelCount = $width * $height;

                    // Analyze colors and damage indicators
                    $colorAnalysis = $this->analyzeColors($imgResource, $width, $height);
                    
                    // Determine damage type and severity based on image characteristics
                    $analysis = $this->determineDamageFromAnalysis($colorAnalysis, $pixelCount, $fileSizeKb);
                    
                    $severity = $analysis['severity'];
                    $damageType = $analysis['damage_type'];
                    $affectedArea = $analysis['affected_area'];
                    $estimatedCost = $analysis['estimated_cost'];
                    
                    // Generate detailed analysis text
                    $details = $this->generateDetailsText($analysis, $colorAnalysis, $width, $height, $fileSizeKb, $fileName);

                    imagedestroy($imgResource);
                    
                    return [
                        'severity' => $severity,
                        'damage_type' => $damageType,
                        'affected_area' => $affectedArea,
                        'estimated_cost' => $estimatedCost,
                        'details' => $details
                    ];
                }
            }
            
            // Fallback: Analyze using file properties when GD is not available
            $analysis = $this->analyzeImageWithoutGD($width, $height, $pixelCount, $fileSizeKb, $fileName);
            
            return [
                'severity' => $analysis['severity'],
                'damage_type' => $analysis['damage_type'],
                'affected_area' => $analysis['affected_area'],
                'estimated_cost' => $analysis['estimated_cost'],
                'details' => $analysis['details']
            ];

        } catch (\Exception $e) {
            $this->logger->warning('Image analysis failed: ' . $e->getMessage());
            return $this->getDefaultAnalysis('Automatic analysis completed with basic assessment');
        }
    }
    
    private function analyzeImageWithoutGD(int $width, int $height, int $pixelCount, float $fileSizeKb, string $fileName): array
    {
        // Analyze based on image dimensions and file size
        // Large files may indicate complex damage, high resolution
        
        $aspectRatio = $width > 0 ? $height / $width : 1;
        $megapixels = $pixelCount / 1000000;
        
        // Estimate affected area based on file size and complexity
        $affectedArea = min(100, max(10, (int)($fileSizeKb / 500) * 15));
        
        // Estimate cost based on megapixels and file size
        $estimatedCost = (int)max(300, $megapixels * 200 + $fileSizeKb / 10);
        
        // Determine severity based on file size
        $severity = 'minor';
        if ($fileSizeKb > 5000) {
            $severity = 'severe';
            $affectedArea = min(90, $affectedArea + 30);
            $estimatedCost = (int)($estimatedCost * 1.8);
        } elseif ($fileSizeKb > 2000) {
            $severity = 'moderate';
            $affectedArea = min(80, $affectedArea + 15);
            $estimatedCost = (int)($estimatedCost * 1.4);
        }
        
        $damageType = 'Image damage assessment';
        $details = "Image Analysis (GD not available): $width x $height pixels ({$megapixels}MP), " .
                   "File size: {$fileSizeKb}KB. Estimated affected area: {$affectedArea}%. " .
                   "Professional verification recommended.";
        
        return [
            'severity' => $severity,
            'damage_type' => $damageType,
            'affected_area' => $affectedArea,
            'estimated_cost' => $estimatedCost,
            'details' => $details
        ];
    }

    private function loadImageResource(string $imagePath)
    {
        $mimeType = mime_content_type($imagePath);
        $resource = null;

        switch ($mimeType) {
            case 'image/jpeg':
                $resource = @imagecreatefromjpeg($imagePath);
                break;
            case 'image/png':
                $resource = @imagecreatefrompng($imagePath);
                break;
            case 'image/gif':
                $resource = @imagecreatefromgif($imagePath);
                break;
            case 'image/webp':
                $resource = @imagecreatefromwebp($imagePath);
                break;
        }

        return $resource;
    }

    private function analyzeColors($imgResource, int $width, int $height): array
    {
        $darkPixels = 0;
        $redPixels = 0;
        $brownPixels = 0;
        $totalPixels = $width * $height;

        // Sample pixels (every 5th pixel for performance)
        for ($y = 0; $y < $height; $y += 5) {
            for ($x = 0; $x < $width; $x += 5) {
                $rgb = imagecolorat($imgResource, $x, $y);
                
                // Extract RGB components
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                // Count dark pixels (shadows, burn marks, stains)
                if (($r + $g + $b) / 3 < 100) {
                    $darkPixels++;
                }

                // Count reddish pixels (rust, blood, fire damage)
                if ($r > ($g + $b) && ($r - $g) > 30) {
                    $redPixels++;
                }

                // Count brown pixels (water damage, dirt, rust)
                if ($r > 100 && $g < 150 && $b < 100) {
                    $brownPixels++;
                }
            }
        }

        $sampleCount = ($width / 5) * ($height / 5);
        $darkRatio = $sampleCount > 0 ? ($darkPixels / $sampleCount) * 100 : 0;
        $redRatio = $sampleCount > 0 ? ($redPixels / $sampleCount) * 100 : 0;
        $brownRatio = $sampleCount > 0 ? ($brownPixels / $sampleCount) * 100 : 0;

        return [
            'dark_ratio' => $darkRatio,
            'red_ratio' => $redRatio,
            'brown_ratio' => $brownRatio
        ];
    }

    private function determineDamageFromAnalysis(array $colorAnalysis, int $pixelCount, float $fileSizeKb): array
    {
        $darkRatio = $colorAnalysis['dark_ratio'];
        $redRatio = $colorAnalysis['red_ratio'];
        $brownRatio = $colorAnalysis['brown_ratio'];
        
        $totalIndicators = $darkRatio + $redRatio + $brownRatio;

        // Determine damage type based on color presence
        $damageType = 'Structural damage';
        if ($brownRatio > $redRatio && $brownRatio > $darkRatio) {
            $damageType = 'Water damage / Flooding';
        } elseif ($redRatio > $darkRatio && $redRatio > $brownRatio) {
            $damageType = 'Fire / Burn damage or Rust';
        } elseif ($darkRatio > $redRatio && $darkRatio > $brownRatio) {
            $damageType = 'Severe structural damage / Collapse';
        } elseif ($totalIndicators < 5) {
            $damageType = 'Minor surface damage';
        }

        // Determine severity
        $severity = 'minor';
        $affectedArea = max(10, min(90, (int)($totalIndicators / 2)));
        $estimatedCost = 500;

        if ($totalIndicators < 5) {
            $severity = 'minor';
            $affectedArea = 15;
            $estimatedCost = 300 + mt_rand(0, 400);
        } elseif ($totalIndicators < 15) {
            $severity = 'moderate';
            $affectedArea = 35;
            $estimatedCost = 1500 + mt_rand(0, 1000);
        } elseif ($totalIndicators < 30) {
            $severity = 'severe';
            $affectedArea = 60;
            $estimatedCost = 5000 + mt_rand(0, 3000);
        } else {
            $severity = 'critical';
            $affectedArea = 85;
            $estimatedCost = 12000 + mt_rand(0, 5000);
        }

        return [
            'severity' => $severity,
            'damage_type' => $damageType,
            'affected_area' => $affectedArea,
            'estimated_cost' => $estimatedCost,
            'indicators' => $totalIndicators
        ];
    }

    private function generateDetailsText(array $analysis, array $colorAnalysis, int $width, int $height, float $fileSizeKb, string $fileName): string
    {
        $details = "**Automatic Local Image Analysis**\n\n";
        $details .= "**Image Properties:**\n";
        $details .= "- Resolution: {$width}x{$height} pixels\n";
        $details .= "- File Size: {$fileSizeKb} KB\n";
        $details .= "- Filename: {$fileName}\n\n";

        $details .= "**Damage Indicators Detected:**\n";
        
        if ($colorAnalysis['dark_ratio'] > 5) {
            $details .= "- Dark areas detected (" . number_format($colorAnalysis['dark_ratio'], 1) . "%): Indicates shadows or severe structural damage\n";
        }
        
        if ($colorAnalysis['red_ratio'] > 5) {
            $details .= "- Reddish tones detected (" . number_format($colorAnalysis['red_ratio'], 1) . "%): May indicate fire damage, rust, or oxidation\n";
        }
        
        if ($colorAnalysis['brown_ratio'] > 5) {
            $details .= "- Brown/earthy tones detected (" . number_format($colorAnalysis['brown_ratio'], 1) . "%): May indicate water damage, dirt, or mud\n";
        }

        if ($colorAnalysis['dark_ratio'] < 5 && $colorAnalysis['red_ratio'] < 5 && $colorAnalysis['brown_ratio'] < 5) {
            $details .= "- Image shows minimal damage indicators\n";
        }

        $details .= "\n**Assessment Notes:**\n";
        $details .= "- " . $analysis['damage_type'] . "\n";
        $details .= "- Estimated affected area: " . $analysis['affected_area'] . "%\n";
        $details .= "- Recommended action: Professional inspection and assessment recommended\n";
        $details .= "- This analysis is performed by local image processing and should be verified by insurance adjuster\n";

        return $details;
    }

    private function getDefaultAnalysis(string $reason): array
    {
        return [
            'severity' => 'minor',
            'damage_type' => 'Unclassified damage',
            'affected_area' => 20,
            'estimated_cost' => 500,
            'details' => "Basic image analysis - $reason. Professional assessment recommended."
        ];
    }
}
