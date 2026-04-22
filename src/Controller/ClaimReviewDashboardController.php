<?php
/**
 * Insurance Claim Admin Review Dashboard
 * 
 * Human admin interface for reviewing claim analyses
 * Required: Admin has final decision authority
 */

namespace App\Controller;

use App\Entity\DamageAnalysis;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/admin/claims')]
class ClaimReviewDashboardController extends AbstractController
{
    #[Route('/pending', name: 'admin_claims_pending', methods: ['GET'])]
    public function viewPendingClaims(EntityManagerInterface $em): Response
    {
        $analyses = $em->getRepository(DamageAnalysis::class)->findBy([], ['analyzed_at' => 'DESC'], 50);

        return $this->render('admin/claims/pending.html.twig', [
            'analyses' => $analyses,
            'count' => count($analyses)
        ]);
    }

    #[Route('/{id}/detail', name: 'admin_claims_detail', methods: ['GET'])]
    public function viewClaimDetail(int $id, EntityManagerInterface $em): Response
    {
        $analysis = $em->getRepository(DamageAnalysis::class)->find($id);

        if (!$analysis) {
            throw $this->createNotFoundException('Claim not found');
        }

        return $this->render('admin/claims/detail.html.twig', [
            'analysis' => $analysis,
            'insurance_type' => $analysis->getSinistre()?->getContrat()?->getAssurance()?->getTypeAssurance()
        ]);
    }

    #[Route('/{id}/approve', name: 'admin_claims_approve', methods: ['POST'])]
    public function approveClaim(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $this->recordDecision($em, $id, 'ACCEPTE');

        return $this->redirectToRoute('admin_claims_pending');
    }

    #[Route('/{id}/deny', name: 'admin_claims_deny', methods: ['POST'])]
    public function denyClaim(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $this->recordDecision($em, $id, 'REJETE');

        return $this->redirectToRoute('admin_claims_pending');
    }

    #[Route('/{id}/request-review', name: 'admin_claims_request_review', methods: ['POST'])]
    public function requestAdditionalReview(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $this->addFlash('info', 'Claim kept pending for additional review.');

        return $this->redirectToRoute('admin_claims_pending');
    }

    private function recordDecision(EntityManagerInterface $em, int $id, string $decision): void
    {
        $analysis = $em->getRepository(DamageAnalysis::class)->find($id);
        if (!$analysis || !$analysis->getSinistre()) {
            throw $this->createNotFoundException('Claim analysis not found');
        }

        $analysis->getSinistre()->setStatut($decision);
        $analysis->getSinistre()->setDateReponse(new \DateTime());
        if ($decision === 'ACCEPTE') {
            $analysis->getSinistre()->setAdminReponse('Approved from claim review dashboard.');
        } elseif ($decision === 'REJETE') {
            $analysis->getSinistre()->setAdminReponse('Rejected from claim review dashboard.');
        }

        $em->flush();
    }
}
