<?php

namespace App\Controller;

use App\Entity\Abonnement;
use App\Entity\CompteBancaire;
use App\Entity\Depense;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AbonnementController extends AbstractController
{
    #[Route('/abonnements', name: 'app_abonnements')]
    #[Route('/abonnement', name: 'app_abonnement_list')]
    public function list(Request $request, EntityManagerInterface $em): Response
    {
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
            $order
        );

        $user = $this->getUser();
        $balanceInfoByPlan = [];

        // If user is logged in, check their balance for each subscription
        if ($user) {
            foreach ($abonnements as $abonnement) {
                $balanceInfoByPlan[$abonnement->getIdAbonnement()] = $this->checkUserBalance($user, $abonnement, $em);
            }
        }

        return $this->render('abonnement/list.html.twig', [
            'abonnements' => $abonnements,
            'user' => $user,
            'balanceInfoByPlan' => $balanceInfoByPlan,
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

        // If user is logged in, check their balance for this subscription
        if ($user) {
            $balanceInfo = $this->checkUserBalance($user, $abonnement, $em);
        }

        return $this->render('abonnement/detail.html.twig', [
            'abonnement' => $abonnement,
            'user' => $user,
            'balanceInfo' => $balanceInfo,
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
}
