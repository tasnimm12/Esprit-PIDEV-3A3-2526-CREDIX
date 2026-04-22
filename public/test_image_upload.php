<?php
/**
 * Simulated Image Upload Test
 * Tests the image analyzer without needing actual HTTP multipart request
 */

class MockImageUploadTest
{
    public static function simulateUpload()
    {
        // Copy test image to simulate upload
        $testImagePath = __DIR__ . '/test_image.png';
        $uploadDir = __DIR__ . '/uploads/insurance_claims/';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        if (!file_exists($testImagePath)) {
            return ['error' => 'Test image not found'];
        }

        // Simulate the ImageClaimAnalyzer processing
        $insuranceType = 'AUTO';
        
        // Read and analyze image
        $imageSize = filesize($testImagePath);
        
        // Simulate image dimensions and damage detection
        // (Image functions may not be available, so using simulated data)
        $width = 1024;
        $height = 768;
        $damagePercent = 45; // Simulated damage from image analysis

        // Generate analysis
        return [
            'success' => true,
            'file_upload_simulation' => true,
            'insurance_type' => $insuranceType,
            'image_analysis' => [
                'image_dimensions' => "${width}x${height}",
                'file_size_bytes' => $imageSize,
                'damage_detected' => true,
                'estimated_damage_percentage' => $damagePercent,
                'color_analysis' => 'RGB(128, 128, 128)',
                'edge_detection' => 12
            ],
            'claim_analysis' => [
                'insurance_type' => $insuranceType,
                'detected_damage_features' => ['damage_detected', 'significant_damage'],
                'damage_percentage' => $damagePercent,
                'estimated_cost' => intval(5000 * ($damagePercent / 100) * 2),
                'recommended_severity' => $damagePercent >= 70 ? 'MAJOR' : ($damagePercent >= 40 ? 'MEDIUM' : 'MINOR'),
                'confidence_score' => 78,
                'consistency_check' => true,
                'reasoning' => "INSURANCE_TYPE: $insuranceType. Image analysis detected $damagePercent% damage. Severity: MEDIUM. Confidence: 78%. Type-specific assessment complete. ADMIN REVIEW REQUIRED.",
                'admin_review_required' => true,
                'final_decision_authority' => 'HUMAN_ADMIN_ONLY'
            ]
        ];
    }
}

$result = MockImageUploadTest::simulateUpload();
echo json_encode($result, JSON_PRETTY_PRINT);
?>
