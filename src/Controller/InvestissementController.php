<?php

namespace App\Controller;

use App\Entity\Investissement;
use App\Entity\Projet;
use App\Entity\CompteBancaire;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use DateTime;

#[Route('/investissement')]
class InvestissementController extends AbstractController
{
    /**
     * List user's investments
     */
    #[Route('/', name: 'app_investissement_list')]
    public function list(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Get search, filter, and sort parameters
        $search = $request->query->get('search', '');
        $statut = $request->query->get('statut', '');
        $sortBy = $request->query->get('sort', 'dateinves');
        $sortOrder = $request->query->get('order', 'DESC');

        // Validate sort order
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

        // Use repository method - pass null for utilisateur if admin
        /** @var \App\Repository\InvestissementRepository $investissementRepository */
        $investissementRepository = $entityManager->getRepository(Investissement::class);
        
        $isAdmin = $user instanceof Utilisateur && $user->getRole() === 'admin';
        
        // Map sort parameter to field name
        $sortField = match($sortBy) {
            'montant' => 'montantinvesti',
            'projet' => 'nomprojet', // This won't work directly - needs join
            'utilisateur' => 'prenom', // This won't work directly - needs join
            'dateinves' => 'dateinves',
            default => 'dateinves',
        };
        
        $investissements = $investissementRepository->findFiltered(
            $search,
            $statut,
            $sortField,
            $sortOrder,
            null,
            0,
            $isAdmin ? null : $user
        );

        // Calculate statistics
        $totalInvesti = 0;
        $pendingCount = 0;
        $completedCount = 0;
        $rejectedCount = 0;

        foreach ($investissements as $inv) {
            $totalInvesti += (float)$inv->getMontantinvesti();
            if ($inv->getStatutInvestissement() === 'EN_ATTENTE') {
                $pendingCount++;
            } elseif ($inv->getStatutInvestissement() === 'ACCEPTE') {
                $completedCount++;
            } elseif ($inv->getStatutInvestissement() === 'REJETE') {
                $rejectedCount++;
            }
        }

        $statutOptions = ['EN_ATTENTE', 'ACCEPTE', 'REJETE', 'REMBOURSÉ'];

        return $this->render('investissement/list.html.twig', [
            'investissements' => $investissements,
            'totalInvesti' => $totalInvesti,
            'pendingCount' => $pendingCount,
            'completedCount' => $completedCount,
            'rejectedCount' => $rejectedCount,
            'isAdmin' => $isAdmin,
            'search' => $search,
            'statut' => $statut,
            'sortBy' => $sortBy,
            'sortOrder' => strtolower($sortOrder),
            'statutOptions' => $statutOptions,
        ]);
    }

    /**
     * Invest in a project
     */
    #[Route('/invest/{projetId}', name: 'app_investissement_create', methods: ['GET', 'POST'])]
    public function invest($projetId, Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $projet = $entityManager->getRepository(Projet::class)->find($projetId);
        if (!$projet) {
            throw $this->createNotFoundException('Project not found');
        }

        // Check if project is active
        if ($projet->getStatutProjet() !== 'ACTIF') {
            $this->addFlash('error', 'This project is not currently accepting investments.');
            return $this->redirectToRoute('app_projet_view', ['id' => $projetId]);
        }

        // Get user's active bank accounts
        $compteBancaireRepository = $entityManager->getRepository(CompteBancaire::class);
        $bankAccounts = $compteBancaireRepository->findBy(['utilisateur' => $user, 'actif' => true]);

        if (empty($bankAccounts)) {
            $this->addFlash('error', 'You must have at least one active bank account to invest.');
            return $this->redirectToRoute('app_projet_view', ['id' => $projetId]);
        }

        if ($request->isMethod('POST')) {
            $montantInvesti = (float)($request->request->get('montant_investi') ?: 0);
            $compteId = (int)($request->request->get('compte_id') ?: 0);
            $errors = [];

            // Validate investment amount
            if (empty($montantInvesti) || $montantInvesti <= 0) {
                $errors[] = 'Investment amount must be greater than 0';
            } elseif ($montantInvesti > 999999999) {
                $errors[] = 'Investment amount cannot exceed 999,999,999';
            }
            
            // Validate account selection
            if ($compteId <= 0) {
                $errors[] = 'You must select a valid bank account';
            }
            
            if (!empty($errors)) {
                // Re-render form with errors instead of redirecting
                return $this->render('investissement/create.html.twig', [
                    'projet' => $projet,
                    'bankAccounts' => $bankAccounts,
                    'errors' => $errors,
                ]);
            }

            // Verify bank account belongs to user
            $compte = $entityManager->getRepository(CompteBancaire::class)->find($compteId);
            if (!$compte || $compte->getUtilisateur() !== $user) {
                $errors[] = 'Invalid bank account.';
                return $this->render('investissement/create.html.twig', [
                    'projet' => $projet,
                    'bankAccounts' => $bankAccounts,
                    'errors' => $errors,
                ]);
            }

            // Check if account is active
            if (!$compte->getActif()) {
                $errors[] = 'Selected bank account is not active.';
                return $this->render('investissement/create.html.twig', [
                    'projet' => $projet,
                    'bankAccounts' => $bankAccounts,
                    'errors' => $errors,
                ]);
            }

            // Check if account has sufficient balance
            $solde = (float)$compte->getSolde();
            if ($solde < $montantInvesti) {
                $errors[] = 'Insufficient balance. Current balance: ' . number_format($solde, 2) . ' D.T';
                return $this->render('investissement/create.html.twig', [
                    'projet' => $projet,
                    'bankAccounts' => $bankAccounts,
                    'errors' => $errors,
                ]);
            }

            try {
                // Create investment
                $investissement = new Investissement();
                $investissement->setUtilisateur($user);
                $investissement->setProjet($projet);
                $investissement->setCompte($compte);
                $investissement->setMontantinvesti($montantInvesti);
                $investissement->setDateinves(new DateTime());
                $investissement->setModepaiement('COMPTE_BANCAIRE');
                $investissement->setStatutInvestissement('EN_ATTENTE');
                $investissement->setCreatedAt(new DateTime());

                // Deduct from bank account balance
                $newSolde = $solde - $montantInvesti;
                $compte->setSolde($newSolde);

                // Update project's collected amount
                $montantCollecte = (float)$projet->getMontantCollecte();
                $projet->setMontantCollecte($montantCollecte + $montantInvesti);
                $projet->setUpdatedAt(new DateTime());

                $entityManager->persist($investissement);
                $entityManager->flush();

                $this->addFlash('success', 'Investment submitted successfully. Pending admin approval.');
                return $this->redirectToRoute('app_investissement_list');
            } catch (\Exception $e) {
                $errors[] = 'Error creating investment: ' . $e->getMessage();
                return $this->render('investissement/create.html.twig', [
                    'projet' => $projet,
                    'bankAccounts' => $bankAccounts,
                    'errors' => $errors,
                ]);
            }
        }

        return $this->render('investissement/create.html.twig', [
            'projet' => $projet,
            'bankAccounts' => $bankAccounts,
            'errors' => [],
        ]);
    }

    /**
     * View investment details
     */
    #[Route('/{id}', name: 'app_investissement_view')]
    public function view($id, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $investissement = $entityManager->getRepository(Investissement::class)->find($id);
        if (!$investissement) {
            throw $this->createNotFoundException('Investment not found');
        }

        // Check access
        $isAdmin = $user instanceof Utilisateur && $user->getRole() === 'admin';
        if (!$isAdmin && $investissement->getUtilisateur() !== $user) {
            throw $this->createAccessDeniedException('Access denied');
        }

        return $this->render('investissement/view.html.twig', [
            'investissement' => $investissement,
            'isAdmin' => $isAdmin,
        ]);
    }

    /**
     * Admin - Accept investment
     */
    #[Route('/{id}/accept', name: 'app_investissement_accept', methods: ['POST'])]
    public function acceptInvestment($id, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user || $user->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Access denied');
        }

        $investissement = $entityManager->getRepository(Investissement::class)->find($id);
        if (!$investissement) {
            throw $this->createNotFoundException('Investment not found');
        }

        $investissement->setStatutInvestissement('ACCEPTE');
        $investissement->setUpdatedAt(new DateTime());

        $entityManager->flush();

        $this->addFlash('success', 'Investment accepted successfully');
        return $this->redirectToRoute('app_investissement_list');
    }

    /**
     * Admin - Reject investment
     */
    #[Route('/{id}/reject', name: 'app_investissement_reject', methods: ['POST'])]
    public function rejectInvestment($id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user || $user->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Access denied');
        }

        $investissement = $entityManager->getRepository(Investissement::class)->find($id);
        if (!$investissement) {
            throw $this->createNotFoundException('Investment not found');
        }

        // Refund the amount to user's account
        if ($investissement->getStatutInvestissement() === 'EN_ATTENTE') {
            $montant = (float)$investissement->getMontantinvesti();
            $compte = $investissement->getCompte();
            $projet = $investissement->getProjet();

            // Refund to account
            $nowSolde = (float)$compte->getSolde();
            $compte->setSolde($nowSolde + $montant);

            // Reduce project's collected amount
            $montantCollecte = (float)$projet->getMontantCollecte();
            $projet->setMontantCollecte(max(0, $montantCollecte - $montant));
            $projet->setUpdatedAt(new DateTime());
        }

        $investissement->setStatutInvestissement('REJETE');
        $investissement->setUpdatedAt(new DateTime());

        $entityManager->flush();

        $this->addFlash('success', 'Investment rejected and amount refunded');
        return $this->redirectToRoute('app_investissement_list');
    }

    /**
     * Admin - Delete investment (only pending ones)
     */
    #[Route('/{id}/delete', name: 'app_investissement_delete', methods: ['POST'])]
    public function deleteInvestment($id, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user || $user->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Access denied');
        }

        $investissement = $entityManager->getRepository(Investissement::class)->find($id);
        if (!$investissement) {
            throw $this->createNotFoundException('Investment not found');
        }

        if ($investissement->getStatutInvestissement() !== 'EN_ATTENTE') {
            $this->addFlash('error', 'Can only delete pending investments.');
            return $this->redirectToRoute('app_investissement_list');
        }

        // Refund the amount
        $montant = (float)$investissement->getMontantinvesti();
        $compte = $investissement->getCompte();
        $projet = $investissement->getProjet();

        $nowSolde = (float)$compte->getSolde();
        $compte->setSolde($nowSolde + $montant);

        $montantCollecte = (float)$projet->getMontantCollecte();
        $projet->setMontantCollecte(max(0, $montantCollecte - $montant));
        $projet->setUpdatedAt(new DateTime());

        $entityManager->remove($investissement);
        $entityManager->flush();

        $this->addFlash('success', 'Investment deleted and amount refunded');
        return $this->redirectToRoute('app_investissement_list');
    }
}
