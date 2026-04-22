<?php

namespace App\Controller;

use App\Entity\Remboursement;
use App\Entity\Credit;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/remboursement')]
class RemboursementController extends AbstractController
{
    /**
     * List user's remboursements
     */
    #[Route('/', name: 'app_remboursement_list')]
    public function list(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $remboursementRepository = $entityManager->getRepository(Remboursement::class);
        $remboursements = $remboursementRepository->findBy(['utilisateur' => $user], ['date_remboursement' => 'DESC']);

        // Calculate statistics
        $totalMontant = 0;
        $totalCashback = 0;
        $pendingCount = 0;
        $completedCount = 0;

        foreach ($remboursements as $remboursement) {
            $totalMontant += (float)$remboursement->getMontant();
            if ($remboursement->getMontantCashback()) {
                $totalCashback += (float)$remboursement->getMontantCashback();
            }
            $statut = $remboursement->getStatut();
            if ($statut === 'EN_ATTENTE') {
                $pendingCount++;
            } elseif ($statut === 'COMPLETE' || $statut === 'COMPLÈTE') {
                $completedCount++;
            }
        }

        // Get user's subscription for cashback info
        $userAbonnement = null;
        if ($user instanceof Utilisateur) {
            $abonnements = $user->getAbonnements();
            if (!empty($abonnements)) {
                $userAbonnement = $abonnements[0];
            }
        }

        return $this->render('remboursement/list.html.twig', [
            'remboursements' => $remboursements,
            'totalMontant' => $totalMontant,
            'totalCashback' => $totalCashback,
            'pendingCount' => $pendingCount,
            'completedCount' => $completedCount,
            'userAbonnement' => $userAbonnement,
        ]);
    }

    /**
     * View remboursement details
     */
    #[Route('/{id}', name: 'app_remboursement_view')]
    public function view($id, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $remboursement = $entityManager->getRepository(Remboursement::class)->find($id);

        if (!$remboursement || $remboursement->getUtilisateur() !== $user) {
            throw $this->createAccessDeniedException('Access denied');
        }

        // Get related credit info
        $credit = $remboursement->getCredit();

        return $this->render('remboursement/view.html.twig', [
            'remboursement' => $remboursement,
            'credit' => $credit,
        ]);
    }

    /**
     * Admin: List all remboursements (system-wide)
     */
    #[Route('/admin/manage', name: 'app_remboursement_admin_list')]
    public function adminList(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user || $user->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Admin access required');
        }

        $remboursementRepository = $entityManager->getRepository(Remboursement::class);
        $remboursements = $remboursementRepository->findBy([], ['date_remboursement' => 'DESC']);

        // Calculate system-wide statistics
        $totalMontant = 0;
        $totalCashback = 0;
        $pendingCount = 0;
        $completedCount = 0;
        $userCount = [];

        foreach ($remboursements as $remboursement) {
            $totalMontant += (float)$remboursement->getMontant();
            if ($remboursement->getMontantCashback()) {
                $totalCashback += (float)$remboursement->getMontantCashback();
            }
            
            $statut = $remboursement->getStatut();
            if ($statut === 'EN_ATTENTE') {
                $pendingCount++;
            } elseif ($statut === 'COMPLETE' || $statut === 'COMPLÈTE') {
                $completedCount++;
            }
            
            // Count remboursements per user
            $userId = $remboursement->getUtilisateur() ? $remboursement->getUtilisateur()->getId() : 'Unknown';
            $userCount[$userId] = ($userCount[$userId] ?? 0) + 1;
        }

        return $this->render('remboursement/admin_list.html.twig', [
            'remboursements' => $remboursements,
            'totalMontant' => $totalMontant,
            'totalCashback' => $totalCashback,
            'pendingCount' => $pendingCount,
            'completedCount' => $completedCount,
            'userCount' => $userCount,
        ]);
    }

    /**
     * Admin: View remboursement details
     */
    #[Route('/admin/view/{id}', name: 'app_remboursement_admin_view')]
    public function adminView($id, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user || $user->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Admin access required');
        }

        $remboursement = $entityManager->getRepository(Remboursement::class)->find($id);
        if (!$remboursement) {
            throw $this->createNotFoundException('Remboursement not found');
        }

        $credit = $remboursement->getCredit();
        $utilisateur = $remboursement->getUtilisateur();

        return $this->render('remboursement/admin_view.html.twig', [
            'remboursement' => $remboursement,
            'credit' => $credit,
            'utilisateur' => $utilisateur,
        ]);
    }
}

