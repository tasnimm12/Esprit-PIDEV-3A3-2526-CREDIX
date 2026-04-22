<?php

namespace App\Controller;

use App\Entity\Credit;
use App\Entity\Utilisateur;
use App\Entity\Abonnement;
use App\Entity\CompteBancaire;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use DateTime;

#[Route('/credit')]
class CreditController extends AbstractController
{
    /**
     * List user's credits
     */
    #[Route('/', name: 'app_credit_list')]
    public function list(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Get search, filter, and sort parameters
        $search = $request->query->get('search', '');
        $statut = $request->query->get('statut', '');
        $sortBy = $request->query->get('sort', 'date_demande');
        $sortOrder = $request->query->get('order', 'DESC');

        // Validate sort order
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

        // Use repository method with user filter
        /** @var \App\Repository\CreditRepository $creditRepository */
        $creditRepository = $entityManager->getRepository(Credit::class);
        $credits = $creditRepository->findByUser($user, $statut);

        // Apply custom sorting and search
        if ($search) {
            $credits = array_filter($credits, function($credit) use ($search) {
                return stripos($credit->getTypeCredit() ?? '', $search) !== false ||
                       stripos($credit->getMotif() ?? '', $search) !== false;
            });
        }

        usort($credits, function($a, $b) use ($sortBy, $sortOrder) {
            $aVal = null;
            $bVal = null;
            
            switch ($sortBy) {
                case 'montant':
                    $aVal = $a->getMontant();
                    $bVal = $b->getMontant();
                    break;
                case 'type':
                    $aVal = $a->getTypeCredit();
                    $bVal = $b->getTypeCredit();
                    break;
                case 'statut':
                    $aVal = $a->getStatutCredit();
                    $bVal = $b->getStatutCredit();
                    break;
                case 'date_demande':
                default:
                    $aVal = $a->getDateDemande();
                    $bVal = $b->getDateDemande();
            }
            
            $result = $aVal <=> $bVal;
            return $sortOrder === 'ASC' ? $result : -$result;
        });

        // Calculate statistics
        $approvedCount = 0;
        $pendingCount = 0;
        $rejectedCount = 0;
        $totalAmount = 0;

        foreach ($credits as $credit) {
            if ($credit->getStatutCredit() === 'ACCEPTE') {
                $approvedCount++;
                $totalAmount += (float)$credit->getMontantDemande();
            } elseif ($credit->getStatutCredit() === 'EN_ATTENTE') {
                $pendingCount++;
            } elseif ($credit->getStatutCredit() === 'REJETE') {
                $rejectedCount++;
            }
        }

        $statutOptions = ['EN_ATTENTE', 'ACCEPTE', 'REJETE', 'REMBOURSEMENT_EN_COURS', 'REMBOURSÉ'];

        return $this->render('credit/list.html.twig', [
            'credits' => $credits,
            'search' => $search,
            'statut' => $statut,
            'sortBy' => $sortBy,
            'sortOrder' => strtolower($sortOrder),
            'statutOptions' => $statutOptions,
            'approvedCount' => $approvedCount,
            'pendingCount' => $pendingCount,
            'rejectedCount' => $rejectedCount,
            'totalAmount' => $totalAmount,
        ]);
    }

    /**
     * Apply for a new credit
     */
    #[Route('/apply', name: 'app_credit_apply', methods: ['GET', 'POST'])]
    public function apply(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Define credit types and rates first (needed for both GET and POST)
        $creditTypes = ['3M', '6M', '12M', '24M', '36M'];
        $standardRates = [3.5, 4.5, 5.5, 6.5, 7.5];

        // Check if user has active bank account
        $compteBancaireRepository = $entityManager->getRepository(CompteBancaire::class);
        $bankAccounts = $compteBancaireRepository->findBy(['utilisateur' => $user, 'actif' => true]);

        if (empty($bankAccounts)) {
            return $this->render('credit/apply.html.twig', [
                'bankAccounts' => [],
                'creditTypes' => $creditTypes,
                'standardRates' => $standardRates,
                'abonnements' => [],
                'abonnement' => null,
                'reduction' => 0,
                'noBankAccountError' => 'You must have at least one active bank account to apply for credit. Please create one first before proceeding with your credit application.',
            ]);
        }

        // Get user's abonnements
        $abonnements = [];
        $abonnement = null;
        $reduction = 0;
        if ($user instanceof Utilisateur) {
            $abonnements = $user->getAbonnements()->toArray();
            if (!empty($abonnements)) {
                $abonnement = $abonnements[0];
                $reduction = $abonnement->getReductionPourcentage() ?? 0;
            }
        }

        if ($request->isMethod('POST')) {
            $montantDemande = (float)$request->request->get('montant_demande') ?: 0;
            $typeCredit = $request->request->get('type_credit') ?: '';
            $tauxInteret = (float)$request->request->get('taux_interet') ?: 5.5;
            $compteId = (int)$request->request->get('compte_id') ?: 0;
            $selectedAbonnementId = $request->request->get('selected_abonnement', null);

            // Validate inputs
            $errors = [];
            
            if (empty($montantDemande)) {
                $errors[] = 'Amount is required';
            } elseif ($montantDemande < 100 || $montantDemande > 100000) {
                $errors[] = 'Amount must be between $100 and $100,000';
            }
            
            if (empty($typeCredit)) {
                $errors[] = 'Credit duration is required';
            }
            
            if ($tauxInteret < 0.5 || $tauxInteret > 15) {
                $errors[] = 'Interest rate must be between 0.5% and 15%';
            }
            
            if (empty($compteId)) {
                $errors[] = 'Bank account is required';
            }
            
            if (!empty($errors)) {
                // Re-render the form with errors instead of redirecting
                return $this->render('credit/apply.html.twig', [
                    'bankAccounts' => $bankAccounts,
                    'abonnement' => $abonnement,
                    'abonnements' => $abonnements,
                    'reduction' => $reduction,
                    'creditTypes' => $creditTypes,
                    'standardRates' => $standardRates,
                    'errors' => $errors,
                ]);
            }

            // Ensure positive interest rate (minimum 0.5% if discount makes it negative)
            if ($tauxInteret < 0.5) {
                $tauxInteret = 0.5;
            }

            // Verify bank account belongs to user
            $compte = $entityManager->getRepository(CompteBancaire::class)->find($compteId);
            if (!$compte || $compte->getUtilisateur() !== $user) {
                $this->addFlash('error', 'Invalid bank account.');
                return $this->redirectToRoute('app_credit_apply');
            }

            // Calculate credit with discount
            $credit = new Credit();
            $credit->setUtilisateur($user);
            $credit->setCompte($compte);
            $credit->setMontantDemande($montantDemande);
            $credit->setTypeCredit($typeCredit);
            
            // Apply subscription discount if user selected one
            $tauxFinal = $tauxInteret;
            $selectedAbonnement = null;
            
            if ($selectedAbonnementId) {
                // Find the selected abonnement
                foreach ($abonnements as $abb) {
                    if ($abb->getIdAbonnement() == $selectedAbonnementId) {
                        $selectedAbonnement = $abb;
                        break;
                    }
                }
            }
            
            if ($selectedAbonnement) {
                $credit->setAbonnement($selectedAbonnement);
                $reductionValue = (float)($selectedAbonnement->getReductionPourcentage() ?? 0);
                $tauxFinal = $tauxInteret - $reductionValue;
                // Ensure interest rate doesn't go below 0.5% even with discount
                if ($tauxFinal < 0.5) {
                    $tauxFinal = 0.5;
                }
                $credit->setReductionPourcentage($reductionValue);
            }
            
            $credit->setTauxInteret($tauxFinal);

            // Calculate monthly payment and total
            $durationMonths = (int)str_replace('M', '', $typeCredit);
            if ($durationMonths <= 0) {
                $this->addFlash('error', 'Invalid credit duration.');
                return $this->redirectToRoute('app_credit_apply');
            }

            $monthlyRate = $tauxFinal / 100 / 12;
            
            // Use proper loan payment formula
            if ($monthlyRate > 0) {
                $numerator = $monthlyRate * pow(1 + $monthlyRate, $durationMonths);
                $denominator = pow(1 + $monthlyRate, $durationMonths) - 1;
                $mensualite = ($denominator > 0) ? ($montantDemande * ($numerator / $denominator)) : 0;
            } else {
                // If rate is 0, simple division
                $mensualite = $montantDemande / $durationMonths;
            }
            
            $montantTotal = $mensualite * $durationMonths;

            $credit->setMensualite((float)$mensualite);
            $credit->setMontantTotal((float)$montantTotal);
            $credit->setMontantRestant((float)$montantTotal);
            $credit->setDateDemande(new DateTime());
            $credit->setStatutCredit('EN_ATTENTE');

            $entityManager->persist($credit);
            $entityManager->flush();

            $this->addFlash('success', 'Credit application submitted successfully. Please wait for approval.');
            return $this->redirectToRoute('app_credit_list');
        }

        return $this->render('credit/apply.html.twig', [
            'bankAccounts' => $bankAccounts,
            'abonnement' => $abonnement,
            'abonnements' => $abonnements,
            'reduction' => $reduction,
            'creditTypes' => $creditTypes,
            'standardRates' => $standardRates,
            'errors' => [],
        ]);
    }

    /**
     * View credit details
     */
    #[Route('/{id}', name: 'app_credit_view')]
    public function view($id, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $credit = $entityManager->getRepository(Credit::class)->find($id);

        if (!$credit || $credit->getUtilisateur() !== $user) {
            throw $this->createAccessDeniedException('Access denied');
        }

        return $this->render('credit/view.html.twig', [
            'credit' => $credit,
        ]);
    }
}
