<?php

namespace App\Controller;

use App\Entity\Abonnement;
use App\Entity\CompteBancaire;
use App\Entity\Depense;
use App\Entity\Utilisateur;
use App\Service\SubscriptionRecommendationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AbonnementController extends AbstractController
{
    private SubscriptionRecommendationService $recommendationService;
    private LoggerInterface $logger;

    public function __construct(SubscriptionRecommendationService $recommendationService, LoggerInterface $logger)
    {
        $this->recommendationService = $recommendationService;
        $this->logger = $logger;
    }

    #[Route('/abonnements', name: 'app_abonnements')]
    #[Route('/abonnement', name: 'app_abonnement_list')]
    public function list(Request $request, EntityManagerInterface $em): Response
    {
        // Get the current user first
        $user = $this->getUser();

        // Get search/filter parameters
        $keyword = $request->query->get('keyword', '');
        $minPrice = $request->query->get('minPrice', '');
        $maxPrice = $request->query->get('maxPrice', '');
        $sort = $request->query->get('sort', 'prix_mensuel');
        $order = $request->query->get('order', 'ASC');

        // Use repository method for filtering
        /** @var \App\Repository\AbonnementRepository $abonnementRepository */
        $abonnementRepository = $em->getRepository(Abonnement::class);
        
        $minPrice = !empty($minPrice) && is_numeric($minPrice) ? (float)$minPrice : null;
        $maxPrice = !empty($maxPrice) && is_numeric($maxPrice) ? (float)$maxPrice : null;
        
        $abonnements = $abonnementRepository->findFiltered(
            $keyword,
            $minPrice,
            $maxPrice,
            $sort,
            $order,
            null,
            0,
            $user
        );

        $balanceInfoByPlan = [];
        $recommendation = null;
        $userCustomPlans = [];

        // If user is logged in, check their balance for each subscription and get recommendations
        if ($user) {
            foreach ($abonnements as $abonnement) {
                $balanceInfoByPlan[$abonnement->getIdAbonnement()] = $this->checkUserBalance($user, $abonnement, $em);
            }
            
            // Get AI-powered subscription recommendation based on user expenses
            $recommendation = $this->recommendationService->recommendPlan($user, $abonnements);
            
            // Get user's custom plans (plans they are subscribed to that are custom)
            foreach ($user->getAbonnements() as $userPlan) {
                if ($userPlan->isCustom()) {
                    $userCustomPlans[] = $userPlan;
                }
            }
        }

        return $this->render('abonnement/list.html.twig', [
            'abonnements' => $abonnements,
            'user' => $user,
            'userCustomPlans' => $userCustomPlans,
            'balanceInfoByPlan' => $balanceInfoByPlan,
            'recommendation' => $recommendation,
            'keyword' => $keyword,
            'minPrice' => $minPrice,
            'maxPrice' => $maxPrice,
            'sort' => $sort,
            'order' => $order,
        ]);
    }

    #[Route('/abonnement/{id<\d+>}', name: 'app_abonnement_detail')]
    public function detail(Abonnement $abonnement, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $balanceInfo = null;
        $recommendation = null;
        $expenseStats = null;

        // If user is logged in, check their balance for this subscription and get recommendations
        if ($user) {
            $balanceInfo = $this->checkUserBalance($user, $abonnement, $em);
            
            // Get all plans for recommendation context
            $allPlans = $em->getRepository(Abonnement::class)->findAll();
            $recommendation = $this->recommendationService->recommendPlan($user, $allPlans);
            
            // Get expense statistics for context
            $sixMonthsAgo = new \DateTime();
            $sixMonthsAgo->modify('-6 months');
            $depenseRepository = $em->getRepository(Depense::class);
            $expenses = $depenseRepository->createQueryBuilder('d')
                ->where('d.utilisateur = :user')
                ->andWhere('d.date_depense >= :dateStart')
                ->setParameter('user', $user)
                ->setParameter('dateStart', $sixMonthsAgo)
                ->getQuery()
                ->getResult();
            
            if (count($expenses) > 0) {
                $totalExpenses = array_sum(array_map(function($e) { return (float)$e->getMontant(); }, $expenses));
                $expenseStats = [
                    'total' => $totalExpenses,
                    'average_monthly' => $totalExpenses / max(count(array_unique(array_map(function($e) { return $e->getDateDepense()->format('Y-m'); }, $expenses))), 1),
                    'count' => count($expenses)
                ];
            }
        }

        return $this->render('abonnement/detail.html.twig', [
            'abonnement' => $abonnement,
            'user' => $user,
            'balanceInfo' => $balanceInfo,
            'recommendation' => $recommendation,
            'expenseStats' => $expenseStats,
        ]);
    }

    #[Route('/abonnement/subscribe/{id<\d+>}', name: 'app_abonnement_subscribe')]
    #[IsGranted('ROLE_USER')]
    public function subscribe(Abonnement $abonnement, EntityManagerInterface $em): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        if (!$user) {
            $this->addFlash('error', 'You must be logged in to subscribe');
            return $this->redirectToRoute('app_login');
        }

        // Check if user already has this subscription
        if ($user->getAbonnements()->contains($abonnement)) {
            $this->addFlash('info', 'You already have this subscription');
            return $this->redirectToRoute('app_abonnement_detail', ['id' => $abonnement->getIdAbonnement()]);
        }

        // ===== CHECK BANK ACCOUNT BALANCE =====
        $userBankAccounts = $em->getRepository(CompteBancaire::class)->findBy(['utilisateur' => $user]);

        if (empty($userBankAccounts)) {
            $this->addFlash('error', 'You must have a bank account to subscribe. Please add one first.');
            return $this->redirectToRoute('app_compte_bancaire_new');
        }

        // Get pricing
        $prixMensuel = (float)$abonnement->getPrixMensuel();
        $prixAnnuel = (float)$abonnement->getPrixAnnuel();

        // Check if any account has sufficient balance
        $hasEnoughBalance = false;
        $totalBalance = 0;
        $accountWithBalance = null;

        foreach ($userBankAccounts as $account) {
            if (!$account->getActif()) {
                continue; // Skip inactive accounts
            }
            $totalBalance += (float)$account->getSolde();
            
            // Check if this account can cover monthly OR annual payment
            if ((float)$account->getSolde() >= $prixMensuel || (float)$account->getSolde() >= $prixAnnuel) {
                $hasEnoughBalance = true;
                $accountWithBalance = $account;
                break;
            }
        }

        if (!$hasEnoughBalance) {
            $this->addFlash('error', sprintf(
                'Insufficient balance. Your total balance is %s. Monthly price: %s, Annual price: %s',
                number_format($totalBalance, 2),
                number_format($prixMensuel, 2),
                number_format($prixAnnuel, 2)
            ));
            return $this->redirectToRoute('app_abonnement_detail', ['id' => $abonnement->getIdAbonnement()]);
        }

        // ===== PROCESS PAYMENT - DEDUCT PRICE FROM BANK ACCOUNT =====
        // Prefer annual if user can afford it, otherwise use monthly
        $priceToDeduct = 0;
        $paymentType = 'monthly';

        if ((float)$accountWithBalance->getSolde() >= $prixAnnuel) {
            $priceToDeduct = $prixAnnuel;
            $paymentType = 'annual';
        } else {
            $priceToDeduct = $prixMensuel;
            $paymentType = 'monthly';
        }

        // Deduct the price from bank account
        $newBalance = (float)$accountWithBalance->getSolde() - $priceToDeduct;
        $accountWithBalance->setSolde($newBalance);
        $em->persist($accountWithBalance);

        // Create depense record for this purchase
        $depense = new Depense();
        $depense->setUtilisateur($user);
        $depense->setDescription(sprintf(
            'Subscription: %s (%s)',
            $abonnement->getTypeAbonnement(),
            ucfirst($paymentType)
        ));
        $depense->setMontant($priceToDeduct);
        $depense->setDateDepense(new \DateTime());
        $depense->setCategorie('Subscription');
        $depense->setModePaiement('Bank Account');
        $depense->setCompteBancaire($accountWithBalance);
        $depense->setCreatedAt(new \DateTime());
        $em->persist($depense);

        // Add subscription to user
        $user->addAbonnement($abonnement);
        $em->persist($user);
        $em->flush();

        $this->addFlash('success', sprintf(
            'You have successfully subscribed to %s! Amount charged: %s (%s)',
            $abonnement->getNomAbonnement(),
            number_format($priceToDeduct, 2),
            ucfirst($paymentType)
        ));
        return $this->redirectToRoute('app_abonnement_detail', ['id' => $abonnement->getIdAbonnement()]);
    }

    #[Route('/my-subscription', name: 'app_my_subscription')]
    #[IsGranted('ROLE_USER')]
    public function mySubscription(): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('abonnement/my_subscription.html.twig', [
            'user' => $user,
            'abonnement' => $user->getAbonnement(),
        ]);
    }

    #[Route('/abonnement/upgrade', name: 'app_abonnement_upgrade')]
    #[IsGranted('ROLE_USER')]
    public function upgrade(EntityManagerInterface $em): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Get user's subscription IDs
        $userSubscriptionIds = array_map(fn($sub) => $sub->getIdAbonnement(), $user->getAbonnements()->toArray());
        
        // Get all available plans except those user already has
        $abonnements = $em->getRepository(Abonnement::class)->createQueryBuilder('a')
            ->where('a.actif = true');
        
        if (!empty($userSubscriptionIds)) {
            $abonnements = $abonnements->andWhere('a.id_abonnement NOT IN (:userIds)')
                ->setParameter('userIds', $userSubscriptionIds);
        }
        
        $abonnements = $abonnements->orderBy('a.prix_mensuel', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('abonnement/upgrade.html.twig', [
            'abonnements' => $abonnements,
            'currentAbonnements' => $user->getAbonnements(),
        ]);
    }

    #[Route('/abonnement/unsubscribe/{id<\d+>}', name: 'app_abonnement_unsubscribe')]
    #[IsGranted('ROLE_USER')]
    public function unsubscribe(Abonnement $abonnement, EntityManagerInterface $em): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        if (!$user) {
            $this->addFlash('error', 'You must be logged in');
            return $this->redirectToRoute('app_login');
        }

        if ($user->getAbonnements()->contains($abonnement)) {
            $user->removeAbonnement($abonnement);
            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'You have successfully unsubscribed from ' . $abonnement->getNomAbonnement() . '!');
        } else {
            $this->addFlash('info', 'You are not subscribed to this plan');
        }

        return $this->redirectToRoute('app_my_subscription');
    }

    /**
     * Check if user has sufficient balance to afford a subscription
     */
    private function checkUserBalance(Utilisateur $user, Abonnement $abonnement, EntityManagerInterface $em): array
    {
        $userBankAccounts = $em->getRepository(CompteBancaire::class)->findBy(['utilisateur' => $user]);

        $prixMensuel = (float)$abonnement->getPrixMensuel();
        $prixAnnuel = (float)$abonnement->getPrixAnnuel();

        $totalBalance = 0.0;
        $canAffordMonthly = false;
        $canAffordAnnual = false;

        foreach ($userBankAccounts as $account) {
            if (!$account->getActif()) {
                continue;
            }
            $balance = (float)$account->getSolde();
            $totalBalance += $balance;

            if ($balance >= $prixMensuel) {
                $canAffordMonthly = true;
            }
            if ($balance >= $prixAnnuel) {
                $canAffordAnnual = true;
            }
        }

        return [
            'hasBankAccount' => !empty($userBankAccounts),
            'totalBalance' => $totalBalance,
            'canAffordMonthly' => $canAffordMonthly,
            'canAffordAnnual' => $canAffordAnnual,
            'prixMensuel' => $prixMensuel,
            'prixAnnuel' => $prixAnnuel,
        ];
    }

    #[Route('/abonnement/custom', name: 'app_abonnement_custom')]
    #[IsGranted('ROLE_USER')]
    public function customForm(): Response
    {
        return $this->render('abonnement/custom.html.twig', [
            'title' => 'Create Custom Subscription Plan',
        ]);
    }

    #[Route('/abonnement/custom/create', name: 'app_abonnement_custom_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createCustom(Request $request, EntityManagerInterface $em): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        $data = $request->request->all();
        $errors = [];

        $this->logger->info('DEBUG: Custom plan form submitted by user: ' . ($user ? $user->getEmail() : 'NULL'));
        $this->logger->info('DEBUG: Form data: ' . json_encode($data));

        // Validate CSRF token
        if (!$this->isCsrfTokenValid('custom_plan_create', $data['_token'] ?? '')) {
            $this->logger->info('DEBUG: CSRF token validation failed');
            $this->addFlash('error', 'Security token is invalid. Please try again.');
            return $this->redirectToRoute('app_abonnement_custom');
        }
        
        $this->logger->info('DEBUG: CSRF token validated successfully');

        // Validate plan name
        if (empty($data['plan_name'])) {
            $errors[] = 'Plan name is required';
        } else {
            $planName = trim($data['plan_name']);
            if (strlen($planName) < 3 || strlen($planName) > 50) {
                $errors[] = 'Plan name must be between 3 and 50 characters';
            }
        }

        // Validate duration
        if (empty($data['duration'])) {
            $errors[] = 'Duration is required';
        } else {
            $validDurations = ['1 Month', '3 Months', '6 Months', '1 Year', '2 Years'];
            if (!in_array($data['duration'], $validDurations)) {
                $errors[] = 'Invalid duration selected';
            }
        }

        // Validate monthly price
        if (empty($data['monthly_price'])) {
            $errors[] = 'Monthly price is required';
        } elseif (!is_numeric($data['monthly_price']) || (float)$data['monthly_price'] < 0) {
            $errors[] = 'Monthly price must be a valid positive number';
        }

        // Validate annual price
        if (empty($data['annual_price'])) {
            $errors[] = 'Annual price is required';
        } elseif (!is_numeric($data['annual_price']) || (float)$data['annual_price'] < 0) {
            $errors[] = 'Annual price must be a valid positive number';
        }

        // Validate terms agreement
        if (empty($data['agree_terms'])) {
            $errors[] = 'You must agree to the terms and conditions';
        }

        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
            $this->logger->info('DEBUG: Validation errors: ' . json_encode($errors));
            return $this->redirectToRoute('app_abonnement_custom');
        }

        // Create custom abonnement
        $this->logger->info('DEBUG: Creating custom plan...');
        $customAbonnement = new Abonnement();
        $customAbonnement->setTypeAbonnement(trim($data['plan_name']));
        $customAbonnement->setDuree($data['duration']);
        $customAbonnement->setPrixMensuel((float)$data['monthly_price']);
        $customAbonnement->setPrixAnnuel((float)$data['annual_price']);
        $customAbonnement->setIsCustom(true);
        $customAbonnement->setActif(true);
        $customAbonnement->setCreatedBy($user);
        
        if (!empty($data['features'])) {
            $customAbonnement->setAvantages(trim($data['features']));
        }
        
        if (!empty($data['description'])) {
            $customAbonnement->setDescription(trim($data['description']));
        }

        $em->persist($customAbonnement);
        $em->flush();
        
        $this->logger->info('DEBUG: Custom plan created with ID: ' . $customAbonnement->getIdAbonnement());

        // Now subscribe user to this custom plan
        if (!$user->getAbonnements()->contains($customAbonnement)) {
            $user->addAbonnement($customAbonnement);
        }

        // Check user balance and deduct payment
        $userBankAccounts = $em->getRepository(CompteBancaire::class)->findBy([
            'utilisateur' => $user,
            'actif' => true
        ]);

        $this->logger->info('DEBUG: Found ' . count($userBankAccounts) . ' active bank accounts');

        if (count($userBankAccounts) === 0) {
            $this->logger->info('DEBUG: No active bank accounts');
            $this->addFlash('error', 'You must have an active bank account to subscribe to a plan');
            return $this->redirectToRoute('app_abonnement_list');
        }

        $prixMensuel = (float)$customAbonnement->getPrixMensuel();
        $prixAnnuel = (float)$customAbonnement->getPrixAnnuel();
        
        $this->logger->info('DEBUG: Monthly price: ' . $prixMensuel . ', Annual price: ' . $prixAnnuel);

        // Check if any account has sufficient balance
        $hasEnoughBalance = false;
        $accountWithBalance = null;
        $totalBalance = 0;

        foreach ($userBankAccounts as $account) {
            $balance = (float)$account->getSolde();
            $totalBalance += $balance;
            $this->logger->info('DEBUG: Account balance: ' . $balance);
            
            if ($balance >= $prixMensuel || $balance >= $prixAnnuel) {
                $hasEnoughBalance = true;
                $accountWithBalance = $account;
                break;
            }
        }

        if (!$hasEnoughBalance) {
            $this->logger->info('DEBUG: Insufficient balance, removing plan and subscription');
            // Remove the subscription if payment can't be made
            $user->removeAbonnement($customAbonnement);
            $em->remove($customAbonnement);
            $em->flush();

            $this->addFlash('error', sprintf(
                'Insufficient balance. Your account has €%.2f but the plan requires at least €%.2f for monthly or €%.2f for annual payment.',
                $totalBalance,
                $prixMensuel,
                $prixAnnuel
            ));
            return $this->redirectToRoute('app_abonnement_list');
        }

        // Deduct payment
        $priceToDeduct = 0;
        $paymentType = 'monthly';

        if ((float)$accountWithBalance->getSolde() >= $prixAnnuel) {
            $priceToDeduct = $prixAnnuel;
            $paymentType = 'annual';
        } else {
            $priceToDeduct = $prixMensuel;
            $paymentType = 'monthly';
        }

        $newBalance = (float)$accountWithBalance->getSolde() - $priceToDeduct;
        $accountWithBalance->setSolde($newBalance);

        $em->persist($user);
        $em->persist($accountWithBalance);
        $em->flush();
        
        $this->logger->info('DEBUG: Payment processed. New balance: ' . $newBalance);
        $this->logger->info('DEBUG: Redirecting to detail page with ID: ' . $customAbonnement->getIdAbonnement());

        $this->addFlash('success', sprintf(
            'Custom plan "%s" created and subscription activated! Payment of €%.2f (%s) has been deducted.',
            $customAbonnement->getTypeAbonnement(),
            $priceToDeduct,
            $paymentType
        ));

        return $this->redirectToRoute('app_abonnement_detail', ['id' => $customAbonnement->getIdAbonnement()]);
    }

    #[Route('/api/abonnement/search', name: 'api_abonnement_search', methods: ['GET'])]
    public function searchApi(Request $request, EntityManagerInterface $em): Response
    {
        $q = $request->query->get('q', '');
        $user = $this->getUser();

        if (empty($q) || strlen($q) < 1) {
            return $this->json([]);
        }

        // Direct database query for better performance
        $repo = $em->getRepository(Abonnement::class);
        $qb = $repo->createQueryBuilder('ab')
            ->where('ab.type_abonnement LIKE :search OR ab.description LIKE :search')
            ->setParameter('search', $q . '%')
            ->orderBy('ab.type_abonnement', 'ASC')
            ->setMaxResults(10);

        // Privacy filter for custom plans
        if ($user) {
            $qb->andWhere('(ab.is_custom = false) OR (ab.is_custom = true AND ab.createdBy = :user)')
                ->setParameter('user', $user);
        } else {
            $qb->andWhere('ab.is_custom = false');
        }

        $plans = $qb->getQuery()->getResult();

        // Format response
        $results = [];
        foreach ($plans as $plan) {
            $results[] = [
                'id' => $plan->getIdAbonnement(),
                'name' => $plan->getTypeAbonnement(),
                'type' => $plan->isCustom() ? 'custom' : 'standard',
                'price' => (float)$plan->getPrixMensuel(),
                'priceAnnual' => (float)$plan->getPrixAnnuel(),
                'description' => substr($plan->getDescription() ?? '', 0, 100),
            ];
        }

        return $this->json($results);
    }
}
