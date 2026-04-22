<?php
/**
 * Insurance Claim Analysis - Database Persistence Layer
 * 
 * Stores claim analyses in database for audit trail and admin review
 */

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;

class ClaimAnalysisPersistenceService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Store claim analysis in database
     * Creates audit trail for admin review workflow
     */
    public function saveAnalysis(array $analysisData): array
    {
        try {
            // Log analysis to database
            $sql = "
                INSERT INTO claim_analyses (
                    insurance_type, 
                    damage_percentage, 
                    estimated_cost, 
                    recommended_severity, 
                    confidence_score, 
                    consistency_check, 
                    reasoning, 
                    admin_review_required,
                    analysis_timestamp,
                    created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
                )
            ";

            $stmt = $this->entityManager->getConnection()->prepare($sql);
            
            $stmt->executeStatement([
                $analysisData['insurance_type'] ?? 'UNKNOWN',
                $analysisData['damage_percentage'] ?? 0,
                $analysisData['estimated_cost'] ?? 0,
                $analysisData['recommended_severity'] ?? 'UNKNOWN',
                $analysisData['confidence_score'] ?? 0,
                $analysisData['consistency_check'] ? 1 : 0,
                $analysisData['reasoning'] ?? '',
                1, // admin_review_required always true
                date('Y-m-d H:i:s')
            ]);

            return [
                'stored' => true,
                'message' => 'Analysis saved for admin review'
            ];
        } catch (\Exception $e) {
            return [
                'stored' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Retrieve pending analyses for admin review
     */
    public function getPendingReviews(int $limit = 10): array
    {
        try {
            $sql = "
                SELECT * FROM claim_analyses 
                WHERE admin_review_completed = 0 
                ORDER BY created_at DESC 
                LIMIT ?
            ";

            $stmt = $this->entityManager->getConnection()->prepare($sql);
            $result = $stmt->executeQuery([$limit]);
            
            return $result->fetchAllAssociative();
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Mark analysis as reviewed by admin
     */
    public function markAsReviewed(int $analysisId, string $adminDecision): bool
    {
        try {
            $sql = "
                UPDATE claim_analyses 
                SET admin_review_completed = 1, 
                    admin_decision = ?,
                    admin_review_timestamp = NOW()
                WHERE id = ?
            ";

            $stmt = $this->entityManager->getConnection()->prepare($sql);
            $stmt->executeStatement([$adminDecision, $analysisId]);
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
