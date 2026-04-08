<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Entity\Assurance;
use App\Entity\ContratAssurance;
use App\Entity\Sinistre;
use App\Entity\Abonnement;
use App\Entity\CompteBancaire;
use App\Entity\Depense;
use App\Entity\Credit;
use App\Entity\Remboursement;
use App\Entity\Projet;
use App\Entity\Investissement;
use App\Service\BulkEmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
class AdminController extends AbstractController
{
    #[Route('/dashboard', name: 'app_admin_dashboard')]
    public function dashboard(EntityManagerInterface $entityManager): Response
    {
        // Check authentication and admin role
        if (!$this->getUser() || $this->getUser()->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Access denied');
        }

        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $assuranceRepository = $entityManager->getRepository(Assurance::class);
        $contractRepository = $entityManager->getRepository(ContratAssurance::class);
        $sinistreRepository = $entityManager->getRepository(Sinistre::class);
        $compteBancaireRepository = $entityManager->getRepository(CompteBancaire::class);
        $depenseRepository = $entityManager->getRepository(Depense::class);

        // Get statistics
        $totalUsers = count($userRepository->findAll());
        $totalAdmins = count($userRepository->findBy(['role' => 'admin']));
        $totalClients = count($userRepository->findBy(['role' => 'client']));
        $totalOrganisateurs = count($userRepository->findBy(['role' => 'organisateur']));

        $totalAssurances = count($assuranceRepository->findAll());
        $activeAssurances = count($assuranceRepository->findBy(['statut' => 'ACTIF']));
        $inactiveAssurances = count($assuranceRepository->findBy(['statut' => 'INACTIF']));

        $totalContracts = count($contractRepository->findAll());
        $activeContracts = count($contractRepository->findBy(['statut' => 'ACTIF']));
        $expiredContracts = count($contractRepository->findBy(['statut' => 'EXPIRE']));

        $totalSinistres = count($sinistreRepository->findAll());
        $pendingSinistres = count($sinistreRepository->findBy(['statut' => 'EN_ATTENTE']));
        $acceptedSinistres = count($sinistreRepository->findBy(['statut' => 'ACCEPTE']));
        $rejectedSinistres = count($sinistreRepository->findBy(['statut' => 'REJETE']));

        // Bank Account Statistics
        $totalBankAccounts = count($compteBancaireRepository->findAll());
        $activeBankAccounts = count($compteBancaireRepository->findBy(['actif' => true]));
        $inactiveBankAccounts = count($compteBancaireRepository->findBy(['actif' => false]));
        
        // Calculate total balance across all accounts
        $allAccounts = $compteBancaireRepository->findAll();
        $totalBalance = 0;
        foreach ($allAccounts as $account) {
            $totalBalance += (float)$account->getSolde();
        }

        // Get recent users (last 5)
        $recentUsers = $userRepository->findBy([], ['id' => 'DESC'], 5);

        // Get recent assurances (last 5)
        $recentAssurances = $assuranceRepository->findBy([], ['id' => 'DESC'], 5);

        // Get recent contracts (last 5)
        $recentContracts = $contractRepository->findBy([], ['id' => 'DESC'], 5);

        // Get recent sinistres (last 5)
        $recentSinistres = $sinistreRepository->findBy([], ['created_at' => 'DESC'], 5);

        // Get recent bank accounts (last 5)
        $recentBankAccounts = $compteBancaireRepository->findBy([], ['id' => 'DESC'], 5);

        // Depense Statistics
        $totalDepenses = count($depenseRepository->findAll());
        $depenseBudget = 0;
        $allDepenses = $depenseRepository->findAll();
        foreach ($allDepenses as $depense) {
            $depenseBudget += (float)$depense->getMontant();
        }
        $recentDepenses = $depenseRepository->findBy([], ['date_depense' => 'DESC'], 5);

        // Credit Statistics
        $creditRepository = $entityManager->getRepository(Credit::class);
        $totalCredits = count($creditRepository->findAll());
        $pendingCredits = count($creditRepository->findBy(['statut_credit' => 'EN_ATTENTE']));
        $acceptedCredits = count($creditRepository->findBy(['statut_credit' => 'ACCEPTE']));
        $rejectedCredits = count($creditRepository->findBy(['statut_credit' => 'REFUSE']));
        
        // Calculate total credit amount
        $totalCreditAmount = 0;
        $allCredits = $creditRepository->findAll();
        foreach ($allCredits as $credit) {
            $totalCreditAmount += (float)$credit->getMontantDemande();
        }
        $recentCredits = $creditRepository->findBy([], ['date_demande' => 'DESC'], 5);

        // Remboursement Statistics
        $remboursementRepository = $entityManager->getRepository(Remboursement::class);
        $totalRemboursements = count($remboursementRepository->findAll());
        $pendingRemboursements = count($remboursementRepository->findBy(['statut' => 'EN_ATTENTE']));
        
        // Count completed remboursements (both COMPLETE and COMPLÈTE)
        $allRemboursementsForStatus = $remboursementRepository->findAll();
        $completedRemboursements = 0;
        foreach ($allRemboursementsForStatus as $remb) {
            $status = $remb->getStatut();
            if ($status === 'COMPLETE' || $status === 'COMPLÈTE') {
                $completedRemboursements++;
            }
        }
        
        // Calculate total remboursement amounts
        $totalRemboursementAmount = 0;
        $totalCashbackAmount = 0;
        $allRemboursements = $remboursementRepository->findAll();
        foreach ($allRemboursements as $remboursement) {
            $totalRemboursementAmount += (float)$remboursement->getMontant();
            if ($remboursement->getMontantCashback()) {
                $totalCashbackAmount += (float)$remboursement->getMontantCashback();
            }
        }
        $recentRemboursements = $remboursementRepository->findBy([], ['date_remboursement' => 'DESC'], 5);

        // Prepare chart data
        $userRoleData = [
            'admins' => $totalAdmins,
            'clients' => $totalClients,
            'organisateurs' => $totalOrganisateurs,
        ];

        $assuranceStatusData = [
            'active' => $activeAssurances,
            'inactive' => $inactiveAssurances,
        ];

        $contractStatusData = [
            'active' => $activeContracts,
            'expired' => $expiredContracts,
            'pending' => $totalContracts - $activeContracts - $expiredContracts,
        ];

        $sinistreStatusData = [
            'pending' => $pendingSinistres,
            'accepted' => $acceptedSinistres,
            'rejected' => $rejectedSinistres,
        ];

        // Project Statistics
        $projetRepository = $entityManager->getRepository(Projet::class);
        $totalProjects = count($projetRepository->findAll());
        $activeProjects = count($projetRepository->findBy(['statut_projet' => 'ACTIF']));
        $suspendedProjects = count($projetRepository->findBy(['statut_projet' => 'SUSPENDU']));

        // Investment Statistics
        $investissementRepository = $entityManager->getRepository(Investissement::class);
        $totalInvestments = count($investissementRepository->findAll());
        $pendingInvestments = count($investissementRepository->findBy(['statut_investissement' => 'EN_ATTENTE']));
        $acceptedInvestments = count($investissementRepository->findBy(['statut_investissement' => 'ACCEPTE']));
        $rejectedInvestments = count($investissementRepository->findBy(['statut_investissement' => 'REJETE']));

        return $this->render('admin/dashboard.html.twig', [
            'totalUsers' => $totalUsers,
            'totalAdmins' => $totalAdmins,
            'totalClients' => $totalClients,
            'totalOrganisateurs' => $totalOrganisateurs,
            'totalAssurances' => $totalAssurances,
            'activeAssurances' => $activeAssurances,
            'inactiveAssurances' => $inactiveAssurances,
            'totalContracts' => $totalContracts,
            'activeContracts' => $activeContracts,
            'expiredContracts' => $expiredContracts,
            'totalSinistres' => $totalSinistres,
            'pendingSinistres' => $pendingSinistres,
            'acceptedSinistres' => $acceptedSinistres,
            'rejectedSinistres' => $rejectedSinistres,
            'totalBankAccounts' => $totalBankAccounts,
            'activeBankAccounts' => $activeBankAccounts,
            'inactiveBankAccounts' => $inactiveBankAccounts,
            'totalBalance' => $totalBalance,
            'totalDepenses' => $totalDepenses,
            'depenseBudget' => $depenseBudget,
            'totalCredits' => $totalCredits,
            'pendingCredits' => $pendingCredits,
            'acceptedCredits' => $acceptedCredits,
            'rejectedCredits' => $rejectedCredits,
            'totalCreditAmount' => $totalCreditAmount,
            'totalRemboursements' => $totalRemboursements,
            'pendingRemboursements' => $pendingRemboursements,
            'completedRemboursements' => $completedRemboursements,
            'totalRemboursementAmount' => $totalRemboursementAmount,
            'totalCashbackAmount' => $totalCashbackAmount,
            'totalProjects' => $totalProjects,
            'activeProjects' => $activeProjects,
            'suspendedProjects' => $suspendedProjects,
            'totalInvestments' => $totalInvestments,
            'pendingInvestments' => $pendingInvestments,
            'acceptedInvestments' => $acceptedInvestments,
            'rejectedInvestments' => $rejectedInvestments,
            'recentUsers' => $recentUsers,
            'recentAssurances' => $recentAssurances,
            'recentContracts' => $recentContracts,
            'recentSinistres' => $recentSinistres,
            'recentBankAccounts' => $recentBankAccounts,
            'recentDepenses' => $recentDepenses,
            'recentCredits' => $recentCredits,
            'recentRemboursements' => $recentRemboursements,
            'userRoleData' => $userRoleData,
            'assuranceStatusData' => $assuranceStatusData,
            'contractStatusData' => $contractStatusData,
            'sinistreStatusData' => $sinistreStatusData,
        ]);
    }

    #[Route('/users', name: 'app_admin_users')]
    public function users(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || $this->getUser()->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Access denied');
        }

        /** @var \App\Repository\UtilisateurRepository $userRepository */
        $userRepository = $entityManager->getRepository(Utilisateur::class);
        
        // Get filter parameters
        $search = $request->query->get('search', '');
        $role = $request->query->get('role', '');
        $sort = $request->query->get('sort', 'id');
        $order = $request->query->get('order', 'DESC');

        // Use repository method instead of inline QueryBuilder
        $users = $userRepository->findFiltered($search, $role, $sort, $order);

        return $this->render('admin/users.html.twig', [
            'users' => $users,
            'search' => $search,
            'role' => $role,
            'sort' => $sort,
            'order' => $order,
        ]);
    }

    #[Route('/users/{id<\d+>}/edit', name: 'app_admin_users_edit')]
    public function editUser(Utilisateur $user, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || $this->getUser()->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Access denied');
        }

        if ($request->isMethod('POST')) {
            $nom = $request->request->get('nom', '');
            $prenom = $request->request->get('prenom', '');
            $email = $request->request->get('email', '');
            $telephone = $request->request->get('telephone', '');
            $role = $request->request->get('role', '');
            $statut_compte = $request->request->get('statut_compte', '');
            $password = $request->request->get('mot_de_passe', '');
            $errors = [];

            // Validate names
            if (empty($prenom)) {
                $errors[] = 'First name is required';
            } elseif (strlen($prenom) < 2 || strlen($prenom) > 100) {
                $errors[] = 'First name must be between 2 and 100 characters';
            } elseif (!preg_match('/^[a-zA-Z\s\-\']+$/', $prenom)) {
                $errors[] = 'First name can only contain letters, spaces, hyphens and apostrophes';
            }

            if (empty($nom)) {
                $errors[] = 'Last name is required';
            } elseif (strlen($nom) < 2 || strlen($nom) > 100) {
                $errors[] = 'Last name must be between 2 and 100 characters';
            } elseif (!preg_match('/^[a-zA-Z\s\-\']+$/', $nom)) {
                $errors[] = 'Last name can only contain letters, spaces, hyphens and apostrophes';
            }

            // Validate email
            if (empty($email)) {
                $errors[] = 'Email is required';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email format';
            } elseif (strlen($email) > 255) {
                $errors[] = 'Email must not exceed 255 characters';
            }

            // Validate phone format
            if (!empty($telephone)) {
                if (!preg_match('/^[0-9\s\-\+\(\)]{7,20}$/', $telephone)) {
                    $errors[] = 'Invalid phone format';
                }
            }

            // Validate role
            if (empty($role) || !in_array($role, ['admin', 'client', 'organisateur'])) {
                $errors[] = 'Invalid role selected';
            }

            // Validate password if provided
            if (!empty($password)) {
                if (strlen($password) < 8) {
                    $errors[] = 'Password must be at least 8 characters';
                } elseif (strlen($password) > 100) {
                    $errors[] = 'Password must not exceed 100 characters';
                }
            }

            if (!empty($errors)) {
                $this->addFlash('error', 'Validation failed: ' . implode(', ', $errors));
                return $this->redirectToRoute('app_admin_users_edit', ['id' => $user->getId()]);
            }

            // Check if email already exists for other users
            $existingUser = $entityManager->getRepository(Utilisateur::class)->findOneBy(['email' => $email]);
            if ($existingUser && $existingUser->getId() !== $user->getId()) {
                $this->addFlash('error', 'This email is already in use');
                return $this->redirectToRoute('app_admin_users_edit', ['id' => $user->getId()]);
            }

            // Update user
            $user->setNom(htmlspecialchars($nom));
            $user->setPrenom(htmlspecialchars($prenom));
            $user->setEmail(htmlspecialchars($email));
            $user->setTelephone(htmlspecialchars($telephone));
            $user->setRole($role);
            $user->setStatutCompte($statut_compte);

            // Update password if provided
            if (!empty($password)) {
                $user->setMotDePasse(password_hash($password, PASSWORD_BCRYPT));
            }

            $entityManager->flush();

            $this->addFlash('success', 'User updated successfully');
            return $this->redirectToRoute('app_admin_users');
        }

        return $this->render('admin/users/edit.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/assurances', name: 'app_admin_assurances')]
    public function assurances(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || $this->getUser()->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Access denied');
        }

        /** @var \App\Repository\AssuranceRepository $assuranceRepository */
        $assuranceRepository = $entityManager->getRepository(Assurance::class);
        
        // Get filter parameters
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');
        $sort = $request->query->get('sort', 'id');
        $order = $request->query->get('order', 'DESC');

        // Use repository method instead of inline QueryBuilder
        $assurances = $assuranceRepository->findFiltered($search, $status, $sort, $order);

        return $this->render('admin/assurances.html.twig', [
            'assurances' => $assurances,
            'search' => $search,
            'status' => $status,
            'sort' => $sort,
            'order' => $order,
        ]);
    }

    #[Route('/contracts', name: 'app_admin_contracts')]
    public function contracts(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || $this->getUser()->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Access denied');
        }

        /** @var \App\Repository\ContratAssuranceRepository $contractRepository */
        $contractRepository = $entityManager->getRepository(ContratAssurance::class);
        
        // Get filter parameters
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');
        $sort = $request->query->get('sort', 'id');
        $order = $request->query->get('order', 'DESC');

        // Use repository method instead of inline QueryBuilder
        $contracts = $contractRepository->findFiltered($search, $status, $sort, $order);

        return $this->render('admin/contracts.html.twig', [
            'contracts' => $contracts,
            'search' => $search,
            'status' => $status,
            'sort' => $sort,
            'order' => $order,
        ]);
    }

    #[Route('/bulk-email', name: 'app_admin_bulk_email')]
    public function bulkEmail(Request $request, EntityManagerInterface $entityManager, BulkEmailService $bulkEmailService): Response
    {
        if (!$this->getUser() || $this->getUser()->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Access denied');
        }

        $userRepository = $entityManager->getRepository(Utilisateur::class);

        if ($request->isMethod('POST')) {
            $subject = $request->request->get('subject', '');
            $body = $request->request->get('body', '');
            $recipientMode = $request->request->get('recipient_mode', 'search'); // 'search' or 'all'
            $selectedUsers = $request->request->get('selected_users', ''); // JSON array of user IDs

            // Validate inputs
            if (empty($subject) || empty($body)) {
                $this->addFlash('error', 'Subject and message body are required');
                return $this->redirectToRoute('app_admin_bulk_email');
            }

            $recipients = [];

            if ($recipientMode === 'all') {
                // Send to all clients
                $recipients = $userRepository->findBy(['role' => 'client']);
            } else {
                // Send to selected users
                if (empty($selectedUsers)) {
                    $this->addFlash('error', 'Please select at least one user to send the email');
                    return $this->redirectToRoute('app_admin_bulk_email');
                }

                $userIds = json_decode($selectedUsers, true);
                if (!is_array($userIds) || empty($userIds)) {
                    $this->addFlash('error', 'Invalid user selection');
                    return $this->redirectToRoute('app_admin_bulk_email');
                }

                // Get selected users
                foreach ($userIds as $userId) {
                    $user = $userRepository->find((int)$userId);
                    if ($user) {
                        $recipients[] = $user;
                    }
                }
            }

            if (empty($recipients)) {
                $this->addFlash('warning', 'No recipients found');
                return $this->redirectToRoute('app_admin_bulk_email');
            }

            // Render email template
            $emailBody = $this->renderView('email/bulk_admin_email.html.twig', [
                'subject' => $subject,
                'body' => $body,
                'adminName' => $this->getUser()->getPrenom() . ' ' . $this->getUser()->getNom(),
            ]);

            // Send bulk emails
            $result = $bulkEmailService->sendBulkEmail($recipients, $subject, $emailBody);

            // Show results
            $this->addFlash('success', sprintf(
                'Emails sent successfully! %d sent, %d failed out of %d recipients',
                $result['sent'],
                $result['failed'],
                $result['totalRecipients']
            ));

            if ($result['failed'] > 0) {
                $this->addFlash('warning', sprintf(
                    'Failed to send to: %s',
                    implode(', ', $result['failedEmails'])
                ));
            }

            return $this->redirectToRoute('app_admin_bulk_email');
        }

        // Get all users for search
        $allUsers = $userRepository->findBy([], ['email' => 'ASC']);

        return $this->render('admin/bulk_email.html.twig', [
            'allUsers' => $allUsers,
        ]);
    }

    #[Route('/api/search-users', name: 'app_admin_search_users', methods: ['GET'])]
    public function searchUsers(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || $this->getUser()->getRole() !== 'admin') {
            return $this->json(['error' => 'Access denied'], 403);
        }

        $query = $request->query->get('q', '');
        if (strlen($query) < 2) {
            return $this->json([]);
        }

        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $users = $userRepository->createQueryBuilder('u')
            ->where('u.email LIKE :query OR u.prenom LIKE :query OR u.nom LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('u.email', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($users as $user) {
            $result[] = [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'name' => $user->getPrenom() . ' ' . $user->getNom(),
                'role' => $user->getRole(),
            ];
        }

        return $this->json($result);
    }

    // ===== ABONNEMENT MANAGEMENT =====

    #[Route('/abonnements', name: 'app_admin_abonnements')]
    public function manageAbonnements(EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || $this->getUser()->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Access denied');
        }

        $abonnementRepository = $entityManager->getRepository(Abonnement::class);
        $abonnements = $abonnementRepository->findAll();

        return $this->render('admin/abonnements/list.html.twig', [
            'abonnements' => $abonnements,
        ]);
    }

    #[Route('/abonnement/new', name: 'app_admin_abonnement_new')]
    public function newAbonnement(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || $this->getUser()->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Access denied');
        }

        $errors = [];

        if ($request->isMethod('POST')) {
            $type = trim($request->request->get('type_abonnement', ''));
            $prixMensuel = (float)$request->request->get('prix_mensuel', 0);
            $prixAnnuel = (float)$request->request->get('prix_annuel', 0);
            $duree = trim($request->request->get('duree', ''));
            $description = trim($request->request->get('description', ''));
            $avantages = trim($request->request->get('avantages', ''));
            $actif = (bool)$request->request->get('actif', false);

            // Validation
            if (empty($type)) {
                $errors[] = 'Plan name is required';
            } elseif (strlen($type) < 3) {
                $errors[] = 'Plan name must be at least 3 characters';
            } elseif (strlen($type) > 100) {
                $errors[] = 'Plan name cannot exceed 100 characters';
            }

            if ($prixMensuel <= 0) {
                $errors[] = 'Monthly price is required and must be greater than 0';
            }

            if (empty($prixAnnuel) || $prixAnnuel <= 0) {
                $errors[] = 'Annual price is required and must be greater than 0';
            }

            if (empty($duree)) {
                $errors[] = 'Duration is required';
            } elseif (strlen($duree) < 2) {
                $errors[] = 'Duration must be at least 2 characters';
            }

            if (empty($description)) {
                $errors[] = 'Description is required';
            } elseif (strlen($description) < 10) {
                $errors[] = 'Description must be at least 10 characters';
            } elseif (strlen($description) > 5000) {
                $errors[] = 'Description cannot exceed 5000 characters';
            }

            if (empty($avantages)) {
                $errors[] = 'Features list is required';
            } elseif (strlen($avantages) < 10) {
                $errors[] = 'Features list must be at least 10 characters';
            } elseif (strlen($avantages) > 5000) {
                $errors[] = 'Features list cannot exceed 5000 characters';
            }

            if (empty($errors)) {
                $abonnement = new Abonnement();
                $abonnement->setTypeAbonnement($type);
                $abonnement->setPrixMensuel($prixMensuel);
                $abonnement->setPrixAnnuel($prixAnnuel);
                $abonnement->setDuree($duree);
                $abonnement->setDescription($description);
                $abonnement->setAvantages($avantages);
                $abonnement->setActif($actif);

                $entityManager->persist($abonnement);
                $entityManager->flush();

                $this->addFlash('success', 'Subscription plan created successfully');
                return $this->redirectToRoute('app_admin_abonnements');
            } else {
                // Re-render form with validation errors
                return $this->render('admin/abonnements/new.html.twig', [
                    'errors' => $errors,
                    'form_data' => [
                        'type_abonnement' => $type,
                        'prix_mensuel' => $prixMensuel,
                        'prix_annuel' => $prixAnnuel,
                        'duree' => $duree,
                        'description' => $description,
                        'avantages' => $avantages,
                        'actif' => $actif,
                    ]
                ]);
            }
        }

        return $this->render('admin/abonnements/new.html.twig', [
            'errors' => [],
            'form_data' => []
        ]);
    }

    #[Route('/abonnement/{id<\d+>}/edit', name: 'app_admin_abonnement_edit')]
    public function editAbonnement(Abonnement $abonnement, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || $this->getUser()->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Access denied');
        }

        $errors = [];

        if ($request->isMethod('POST')) {
            $type = trim($request->request->get('type_abonnement', ''));
            $prixMensuel = (float)$request->request->get('prix_mensuel', 0);
            $prixAnnuel = (float)$request->request->get('prix_annuel', 0);
            $duree = trim($request->request->get('duree', ''));
            $description = trim($request->request->get('description', ''));
            $avantages = trim($request->request->get('avantages', ''));
            $actif = (bool)$request->request->get('actif', false);

            // Validation
            if (empty($type)) {
                $errors[] = 'Plan name is required';
            } elseif (strlen($type) < 3) {
                $errors[] = 'Plan name must be at least 3 characters';
            } elseif (strlen($type) > 100) {
                $errors[] = 'Plan name cannot exceed 100 characters';
            }

            if ($prixMensuel <= 0) {
                $errors[] = 'Monthly price is required and must be greater than 0';
            }

            if (empty($prixAnnuel) || $prixAnnuel <= 0) {
                $errors[] = 'Annual price is required and must be greater than 0';
            }

            if (empty($duree)) {
                $errors[] = 'Duration is required';
            } elseif (strlen($duree) < 2) {
                $errors[] = 'Duration must be at least 2 characters';
            }

            if (empty($description)) {
                $errors[] = 'Description is required';
            } elseif (strlen($description) < 10) {
                $errors[] = 'Description must be at least 10 characters';
            } elseif (strlen($description) > 5000) {
                $errors[] = 'Description cannot exceed 5000 characters';
            }

            if (empty($avantages)) {
                $errors[] = 'Features list is required';
            } elseif (strlen($avantages) < 10) {
                $errors[] = 'Features list must be at least 10 characters';
            } elseif (strlen($avantages) > 5000) {
                $errors[] = 'Features list cannot exceed 5000 characters';
            }

            if (empty($errors)) {
                $abonnement->setTypeAbonnement($type);
                $abonnement->setPrixMensuel($prixMensuel);
                $abonnement->setPrixAnnuel($prixAnnuel);
                $abonnement->setDuree($duree);
                $abonnement->setDescription($description);
                $abonnement->setAvantages($avantages);
                $abonnement->setActif($actif);

                $entityManager->flush();

                $this->addFlash('success', 'Subscription plan updated successfully');
                return $this->redirectToRoute('app_admin_abonnements');
            } else {
                // Re-render form with validation errors
                return $this->render('admin/abonnements/edit.html.twig', [
                    'abonnement' => $abonnement,
                    'errors' => $errors,
                    'form_data' => [
                        'type_abonnement' => $type,
                        'prix_mensuel' => $prixMensuel,
                        'prix_annuel' => $prixAnnuel,
                        'duree' => $duree,
                        'description' => $description,
                        'avantages' => $avantages,
                        'actif' => $actif,
                    ]
                ]);
            }
        }

        return $this->render('admin/abonnements/edit.html.twig', [
            'abonnement' => $abonnement,
            'errors' => [],
        ]);
    }

    #[Route('/abonnement/{id<\d+>}/delete', name: 'app_admin_abonnement_delete')]
    public function deleteAbonnement(Abonnement $abonnement, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || $this->getUser()->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Access denied');
        }

        $entityManager->remove($abonnement);
        $entityManager->flush();

        $this->addFlash('success', 'Subscription plan deleted successfully');
        return $this->redirectToRoute('app_admin_abonnements');
    }

    #[Route('/clients-subscriptions', name: 'app_admin_client_subscriptions')]
    public function clientSubscriptions(EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || $this->getUser()->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Access denied');
        }

        $userRepository = $entityManager->getRepository(Utilisateur::class);
        
        // Get all clients with their abonnements
        $clients = $userRepository->createQueryBuilder('u')
            ->where('u.role = :role')
            ->setParameter('role', 'client')
            ->leftJoin('u.abonnements', 'a')
            ->addSelect('a')
            ->orderBy('u.email', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('admin/abonnements/client_subscriptions.html.twig', [
            'clients' => $clients,
        ]);
    }

    #[Route('/client/{userId}/cancel-subscription/{abonnementId<\d+>}', name: 'app_admin_cancel_client_subscription')]
    public function cancelClientSubscription(int $userId, int $abonnementId, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || $this->getUser()->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Access denied');
        }

        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $abonnementRepository = $entityManager->getRepository(Abonnement::class);
        
        $user = $userRepository->find($userId);
        $abonnement = $abonnementRepository->find($abonnementId);

        if (!$user) {
            $this->addFlash('error', 'Client not found');
            return $this->redirectToRoute('app_admin_client_subscriptions');
        }

        if (!$abonnement) {
            $this->addFlash('error', 'Subscription plan not found');
            return $this->redirectToRoute('app_admin_client_subscriptions');
        }

        if (!$user->getAbonnements()->contains($abonnement)) {
            $this->addFlash('warning', 'This client does not have this subscription');
            return $this->redirectToRoute('app_admin_client_subscriptions');
        }

        $userName = $user->getPrenom() . ' ' . $user->getNom();
        $abonnementName = $abonnement->getNomAbonnement();
        
        $user->removeAbonnement($abonnement);
        $entityManager->flush();

        $this->addFlash('success', "Cancelled $abonnementName subscription for $userName");
        return $this->redirectToRoute('app_admin_client_subscriptions');
    }

    #[Route('/client/{userId}/set-subscription', name: 'app_admin_set_client_subscription')]
    public function setClientSubscription(int $userId, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || $this->getUser()->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Access denied');
        }

        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $abonnementRepository = $entityManager->getRepository(Abonnement::class);
        
        $user = $userRepository->find($userId);

        if (!$user) {
            $this->addFlash('error', 'Client not found');
            return $this->redirectToRoute('app_admin_client_subscriptions');
        }

        $errors = [];

        if ($request->isMethod('POST')) {
            $abonnementId = $request->request->get('abonnement_id');
            $action = $request->request->get('action');
            
            // Validation
            if (!$action) {
                $errors[] = 'Action is required';
            } elseif (!in_array($action, ['add', 'remove'])) {
                $errors[] = 'Invalid action. Choose "add" or "remove"';
            }

            if (!$abonnementId) {
                $errors[] = 'Subscription plan selection is required';
            } else {
                $abonnement = $abonnementRepository->find((int)$abonnementId);

                if (!$abonnement) {
                    $errors[] = 'Selected subscription plan not found';
                }
            }

            // If validation passes, process the request
            if (empty($errors)) {
                $abonnement = $abonnementRepository->find((int)$abonnementId);

                if ($action === 'add') {
                    if ($user->getAbonnements()->contains($abonnement)) {
                        $errors[] = 'Client already has this subscription';
                    } else {
                        $user->addAbonnement($abonnement);
                        $entityManager->flush();
                        $this->addFlash('success', "Added {$abonnement->getTypeAbonnement()} to {$user->getPrenom()} {$user->getNom()}");
                        return $this->redirectToRoute('app_admin_client_subscriptions');
                    }
                } elseif ($action === 'remove') {
                    if ($user->getAbonnements()->contains($abonnement)) {
                        $user->removeAbonnement($abonnement);
                        $entityManager->flush();
                        $this->addFlash('success', "Removed {$abonnement->getTypeAbonnement()} from {$user->getPrenom()} {$user->getNom()}");
                        return $this->redirectToRoute('app_admin_client_subscriptions');
                    } else {
                        $errors[] = 'Client does not have this subscription';
                    }
                }
            }
        }

        $abonnements = $abonnementRepository->findBy(['actif' => true]);

        return $this->render('admin/abonnements/set_subscription.html.twig', [
            'user' => $user,
            'abonnements' => $abonnements,
            'errors' => $errors,
        ]);
    }

    // ===== BANK ACCOUNTS MANAGEMENT =====

    #[Route('/bank-accounts', name: 'app_admin_bank_accounts')]
    public function bankAccounts(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || $this->getUser()->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Access denied');
        }

        $compteBancaireRepository = $entityManager->getRepository(CompteBancaire::class);
        
        // Get filter parameters
        $emailFilter = $request->query->get('email', '');
        $statusFilter = $request->query->get('status', '');
        $sort = $request->query->get('sort', 'id');
        $order = $request->query->get('order', 'DESC');

        $query = $compteBancaireRepository->createQueryBuilder('cb')
            ->leftJoin('cb.utilisateur', 'u')
            ->addSelect('u');

        // Filter by user email
        if ($emailFilter) {
            $query->where('u.email LIKE :email')
                  ->setParameter('email', '%' . $emailFilter . '%');
        }

        // Filter by status (active/inactive)
        if ($statusFilter !== '') {
            $active = $statusFilter === '1' ? true : false;
            $query->andWhere('cb.actif = :actif')
                  ->setParameter('actif', $active);
        }

        $query->orderBy('cb.' . $sort, $order);
        $bankAccounts = $query->getQuery()->getResult();

        return $this->render('admin/bank_accounts/index.html.twig', [
            'bankAccounts' => $bankAccounts,
            'emailFilter' => $emailFilter,
            'statusFilter' => $statusFilter,
            'sort' => $sort,
            'order' => $order,
        ]);
    }

    #[Route('/bank-account/new', name: 'app_admin_bank_account_new')]
    public function newBankAccount(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || $this->getUser()->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Access denied');
        }

        $errors = [];
        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $users = $userRepository->findAll();

        if ($request->isMethod('POST')) {
            $userId = $request->request->get('user_id', 0);
            $numero_compte = $request->request->get('numero_compte', '');
            $titulaire = $request->request->get('titulaire', '');
            $email = $request->request->get('email', '');
            $telephone = $request->request->get('telephone', '');
            $solde = $request->request->get('solde', '0');
            $devise = $request->request->get('devise', 'USD');
            $type_compte = $request->request->get('type_compte', '');
            $actif = (bool)$request->request->get('actif', false);

            // Validation
            if (empty($userId) || (int)$userId <= 0) {
                $errors[] = 'User is required';
            }
            if (empty($numero_compte)) {
                $errors[] = 'Account number is required';
            } elseif (strlen($numero_compte) < 5 || strlen($numero_compte) > 34) {
                $errors[] = 'Account number must be between 5 and 34 characters';
            } elseif (!preg_match('/^[A-Z0-9]+$/', $numero_compte)) {
                $errors[] = 'Account number must contain only uppercase letters and numbers (IBAN format)';
            }
            
            if (empty($titulaire)) {
                $errors[] = 'Account holder is required';
            } elseif (strlen($titulaire) < 2 || strlen($titulaire) > 100) {
                $errors[] = 'Account holder must be between 2 and 100 characters';
            }
            
            if (empty($email)) {
                $errors[] = 'Email is required';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email format';
            }
            
            if (!empty($telephone)) {
                if (!preg_match('/^[0-9\s\-\+\(\)]{7,20}$/', $telephone)) {
                    $errors[] = 'Invalid phone format';
                }
            }
            
            if (empty($type_compte)) {
                $errors[] = 'Account type is required';
            } elseif (!in_array($type_compte, ['Checking', 'Savings', 'Money Market', 'Investment', 'Business', 'Other'])) {
                $errors[] = 'Invalid account type selected';
            }
            
            if (!is_numeric($solde) || (float)$solde < 0 || (float)$solde > 999999999) {
                $errors[] = 'Balance must be a valid number between 0 and 999,999,999';
            }
            
            if (empty($devise) || strlen($devise) < 2 || strlen($devise) > 10) {
                $errors[] = 'Invalid currency format';
            }

            if (empty($errors)) {
                $user = $userRepository->find((int)$userId);
                if (!$user) {
                    $errors[] = 'Selected user not found';
                } else {
                    $bankAccount = new CompteBancaire();
                    $bankAccount->setUtilisateur($user);
                    $bankAccount->setNumeroCompte(htmlspecialchars($numero_compte));
                    $bankAccount->setTitulaire(htmlspecialchars($titulaire));
                    $bankAccount->setEmail(htmlspecialchars($email));
                    $bankAccount->setTelephone(htmlspecialchars($telephone ?? ''));
                    $bankAccount->setSolde((float)$solde);
                    $bankAccount->setDevise(htmlspecialchars($devise));
                    $bankAccount->setTypeCompte($type_compte);
                    $bankAccount->setDateCreation(new \DateTime());
                    $bankAccount->setActif($actif);

                    $entityManager->persist($bankAccount);
                    $entityManager->flush();

                    $this->addFlash('success', 'Bank account created successfully');
                    return $this->redirectToRoute('app_admin_bank_accounts');
                }
            }
        }

        return $this->render('admin/bank_accounts/new.html.twig', [
            'users' => $users,
            'errors' => $errors,
        ]);
    }

    #[Route('/bank-account/{id<\d+>}/edit', name: 'app_admin_bank_account_edit')]
    public function editBankAccount(CompteBancaire $bankAccount, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || $this->getUser()->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Access denied');
        }

        $errors = [];
        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $users = $userRepository->findAll();

        if ($request->isMethod('POST')) {
            $userId = $request->request->get('user_id', 0);
            $numero_compte = $request->request->get('numero_compte', '');
            $titulaire = $request->request->get('titulaire', '');
            $email = $request->request->get('email', '');
            $telephone = $request->request->get('telephone', '');
            $solde = $request->request->get('solde', '0');
            $devise = $request->request->get('devise', 'USD');
            $type_compte = $request->request->get('type_compte', '');
            $actif = (bool)$request->request->get('actif', false);

            // Validation
            if (empty($userId) || (int)$userId <= 0) {
                $errors[] = 'User is required';
            }
            if (empty($numero_compte)) {
                $errors[] = 'Account number is required';
            } elseif (strlen($numero_compte) < 5 || strlen($numero_compte) > 34) {
                $errors[] = 'Account number must be between 5 and 34 characters';
            } elseif (!preg_match('/^[A-Z0-9]+$/', $numero_compte)) {
                $errors[] = 'Account number must contain only uppercase letters and numbers (IBAN format)';
            }
            
            if (empty($titulaire)) {
                $errors[] = 'Account holder is required';
            } elseif (strlen($titulaire) < 2 || strlen($titulaire) > 100) {
                $errors[] = 'Account holder must be between 2 and 100 characters';
            }
            
            if (empty($email)) {
                $errors[] = 'Email is required';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email format';
            }
            
            if (!empty($telephone)) {
                if (!preg_match('/^[0-9\s\-\+\(\)]{7,20}$/', $telephone)) {
                    $errors[] = 'Invalid phone format';
                }
            }
            
            if (empty($type_compte)) {
                $errors[] = 'Account type is required';
            } elseif (!in_array($type_compte, ['Checking', 'Savings', 'Money Market', 'Investment', 'Business', 'Other'])) {
                $errors[] = 'Invalid account type selected';
            }
            
            if (!is_numeric($solde) || (float)$solde < 0 || (float)$solde > 999999999) {
                $errors[] = 'Balance must be a valid number between 0 and 999,999,999';
            }
            
            if (empty($devise) || strlen($devise) < 2 || strlen($devise) > 10) {
                $errors[] = 'Invalid currency format';
            }

            if (empty($errors)) {
                $user = $userRepository->find((int)$userId);
                if (!$user) {
                    $errors[] = 'Selected user not found';
                } else {
                    $bankAccount->setUtilisateur($user);
                    $bankAccount->setNumeroCompte(htmlspecialchars($numero_compte));
                    $bankAccount->setTitulaire(htmlspecialchars($titulaire));
                    $bankAccount->setEmail(htmlspecialchars($email));
                    $bankAccount->setTelephone(htmlspecialchars($telephone ?? ''));
                    $bankAccount->setSolde((float)$solde);
                    $bankAccount->setDevise(htmlspecialchars($devise));
                    $bankAccount->setTypeCompte($type_compte);
                    $bankAccount->setActif($actif);

                    $entityManager->flush();

                    $this->addFlash('success', 'Bank account updated successfully');
                    return $this->redirectToRoute('app_admin_bank_accounts');
                }
            }
        }

        return $this->render('admin/bank_accounts/edit.html.twig', [
            'bankAccount' => $bankAccount,
            'users' => $users,
            'errors' => $errors,
        ]);
    }

    #[Route('/bank-account/{id<\d+>}/delete', name: 'app_admin_bank_account_delete')]
    public function deleteBankAccount(CompteBancaire $bankAccount, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || $this->getUser()->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Access denied');
        }

        $accountNumber = substr($bankAccount->getNumeroCompte(), -4);
        $titulaire = $bankAccount->getTitulaire();

        $entityManager->remove($bankAccount);
        $entityManager->flush();

        $this->addFlash('success', "Bank account ending in $accountNumber ($titulaire) deleted successfully");
        return $this->redirectToRoute('app_admin_bank_accounts');
    }

    // ===== DEPENSE MANAGEMENT =====

    #[Route('/depenses', name: 'app_admin_depenses')]
    public function depenses(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || $this->getUser()->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Access denied');
        }

        $depenseRepository = $entityManager->getRepository(Depense::class);
        
        // Get filter parameters
        $search = $request->query->get('search', '');
        $category = $request->query->get('category', '');
        $sort = $request->query->get('sort', 'date_depense');
        $order = $request->query->get('order', 'DESC');

        $query = $depenseRepository->createQueryBuilder('d')
            ->leftJoin('d.utilisateur', 'u')
            ->addSelect('u');

        // Search by description or user email
        if ($search) {
            $query->where('d.description LIKE :search OR u.email LIKE :search')
                  ->setParameter('search', '%' . $search . '%');
        }

        // Filter by category
        if ($category) {
            $query->andWhere('d.categorie = :category')
                  ->setParameter('category', $category);
        }

        $query->orderBy('d.' . $sort, $order);
        $depenses = $query->getQuery()->getResult();

        // Get unique categories for filter dropdown
        $categoriesQuery = $depenseRepository->createQueryBuilder('d')
            ->select('DISTINCT d.categorie')
            ->orderBy('d.categorie', 'ASC')
            ->getQuery()
            ->getResult();
        
        $categories = array_map(fn($item) => $item['categorie'], $categoriesQuery);

        return $this->render('admin/depenses/index.html.twig', [
            'depenses' => $depenses,
            'search' => $search,
            'category' => $category,
            'sort' => $sort,
            'order' => $order,
            'categories' => $categories,
        ]);
    }

    #[Route('/depense/new', name: 'app_admin_depense_new')]
    public function newDepense(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || $this->getUser()->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Access denied');
        }

        $errors = [];
        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $compteBancaireRepository = $entityManager->getRepository(CompteBancaire::class);
        $users = $userRepository->findAll();
        $accounts = $compteBancaireRepository->findBy(['actif' => true]);

        if ($request->isMethod('POST')) {
            $userId = $request->request->get('user_id', 0);
            $compteId = $request->request->get('compte_id', 0);
            $description = $request->request->get('description', '');
            $montant = $request->request->get('montant', '0');
            $dateDepense = $request->request->get('date_depense', '');
            $categorie = $request->request->get('categorie', '');
            $modePaiement = $request->request->get('mode_paiement', '');

            // Validation
            if (empty($userId) || (int)$userId <= 0) {
                $errors[] = 'User is required';
            }
            if (empty($compteId) || (int)$compteId <= 0) {
                $errors[] = 'Bank account is required';
            }
            
            if (empty($description)) {
                $errors[] = 'Description is required';
            } elseif (strlen($description) < 3 || strlen($description) > 500) {
                $errors[] = 'Description must be between 3 and 500 characters';
            }
            
            if (empty($montant) || !is_numeric($montant) || (float)$montant <= 0 || (float)$montant > 999999999) {
                $errors[] = 'Amount must be a valid positive number not exceeding 999,999,999';
            }
            
            if (empty($dateDepense)) {
                $errors[] = 'Expense date is required';
            } else {
                try {
                    $parsedDate = \DateTime::createFromFormat('Y-m-d', $dateDepense);
                    if (!$parsedDate) {
                        $errors[] = 'Invalid expense date format';
                    } else {
                        $today = new \DateTime();
                        if ($parsedDate > $today) {
                            $errors[] = 'Expense date cannot be in the future';
                        }
                    }
                } catch (\Exception $e) {
                    $errors[] = 'Invalid expense date format';
                }
            }
            
            if (empty($categorie)) {
                $errors[] = 'Category is required';
            }
            
            $validPaymentModes = ['CARTE_CREDITDEBIT', 'VIREMENT_BANCAIRE', 'CHEQUE', 'ESPECES', 'PRELEVEMENT', 'CRYPTOMONNAIE'];
            if (!empty($modePaiement) && !in_array($modePaiement, $validPaymentModes)) {
                $errors[] = 'Invalid payment method selected';
            }

            if (empty($errors)) {
                $user = $userRepository->find((int)$userId);
                $compte = $compteBancaireRepository->find((int)$compteId);
                
                if (!$user) {
                    $errors[] = 'Selected user not found';
                } elseif (!$compte) {
                    $errors[] = 'Selected bank account not found';
                } else {
                    try {
                        $depense = new Depense();
                        $depense->setUtilisateur($user);
                        $depense->setCompteBancaire($compte);
                        $depense->setDescription(htmlspecialchars($description));
                        $depense->setMontant((float)$montant);
                        $depense->setDateDepense(\DateTime::createFromFormat('Y-m-d', $dateDepense));
                        $depense->setCategorie($categorie);
                        $depense->setModePaiement($modePaiement);
                        $depense->setCreatedAt(new \DateTime());

                        $entityManager->persist($depense);
                        $entityManager->flush();

                        $this->addFlash('success', 'Expense created successfully');
                        return $this->redirectToRoute('app_admin_depenses');
                    } catch (\Exception $e) {
                        $errors[] = 'Error creating expense: ' . $e->getMessage();
                    }
                }
            }
        }

        return $this->render('admin/depenses/new.html.twig', [
            'users' => $users,
            'accounts' => $accounts,
            'errors' => $errors,
        ]);
    }

    #[Route('/depense/{id<\d+>}/edit', name: 'app_admin_depense_edit')]
    public function editDepense(Depense $depense, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || $this->getUser()->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Access denied');
        }

        $errors = [];
        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $compteBancaireRepository = $entityManager->getRepository(CompteBancaire::class);
        $users = $userRepository->findAll();
        $accounts = $compteBancaireRepository->findBy(['actif' => true]);

        if ($request->isMethod('POST')) {
            $userId = $request->request->get('user_id', 0);
            $compteId = $request->request->get('compte_id', 0);
            $description = $request->request->get('description', '');
            $montant = $request->request->get('montant', '0');
            $dateDepense = $request->request->get('date_depense', '');
            $categorie = $request->request->get('categorie', '');
            $modePaiement = $request->request->get('mode_paiement', '');

            // Validation
            if (empty($userId) || (int)$userId <= 0) {
                $errors[] = 'User is required';
            }
            if (empty($compteId) || (int)$compteId <= 0) {
                $errors[] = 'Bank account is required';
            }
            
            if (empty($description)) {
                $errors[] = 'Description is required';
            } elseif (strlen($description) < 3 || strlen($description) > 500) {
                $errors[] = 'Description must be between 3 and 500 characters';
            }
            
            if (empty($montant) || !is_numeric($montant) || (float)$montant <= 0 || (float)$montant > 999999999) {
                $errors[] = 'Amount must be a valid positive number not exceeding 999,999,999';
            }
            
            if (empty($dateDepense)) {
                $errors[] = 'Expense date is required';
            } else {
                try {
                    $parsedDate = \DateTime::createFromFormat('Y-m-d', $dateDepense);
                    if (!$parsedDate) {
                        $errors[] = 'Invalid expense date format';
                    } else {
                        $today = new \DateTime();
                        if ($parsedDate > $today) {
                            $errors[] = 'Expense date cannot be in the future';
                        }
                    }
                } catch (\Exception $e) {
                    $errors[] = 'Invalid expense date format';
                }
            }
            
            if (empty($categorie)) {
                $errors[] = 'Category is required';
            }
            
            $validPaymentModes = ['CARTE_CREDITDEBIT', 'VIREMENT_BANCAIRE', 'CHEQUE', 'ESPECES', 'PRELEVEMENT', 'CRYPTOMONNAIE'];
            if (!empty($modePaiement) && !in_array($modePaiement, $validPaymentModes)) {
                $errors[] = 'Invalid payment method selected';
            }

            if (empty($errors)) {
                $user = $userRepository->find((int)$userId);
                $compte = $compteBancaireRepository->find((int)$compteId);
                
                if (!$user) {
                    $errors[] = 'Selected user not found';
                } elseif (!$compte) {
                    $errors[] = 'Selected bank account not found';
                } else {
                    try {
                        $depense->setUtilisateur($user);
                        $depense->setCompteBancaire($compte);
                        $depense->setDescription(htmlspecialchars($description));
                        $depense->setMontant((float)$montant);
                        $depense->setDateDepense(\DateTime::createFromFormat('Y-m-d', $dateDepense));
                        $depense->setCategorie($categorie);
                        $depense->setModePaiement($modePaiement);

                        $entityManager->flush();

                        $this->addFlash('success', 'Expense updated successfully');
                        return $this->redirectToRoute('app_admin_depenses');
                    } catch (\Exception $e) {
                        $errors[] = 'Error updating expense: ' . $e->getMessage();
                    }
                }
            }
        }

        return $this->render('admin/depenses/edit.html.twig', [
            'depense' => $depense,
            'users' => $users,
            'accounts' => $accounts,
            'errors' => $errors,
        ]);
    }

    #[Route('/depense/{id<\d+>}/delete', name: 'app_admin_depense_delete')]
    public function deleteDepense(Depense $depense, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || $this->getUser()->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Access denied');
        }

        $montant = $depense->getMontant();
        $description = $depense->getDescription();

        $entityManager->remove($depense);
        $entityManager->flush();

        $this->addFlash('success', "Expense ($description - ${montant}) deleted successfully");
        return $this->redirectToRoute('app_admin_depenses');
    }

    // ===== CREDIT MANAGEMENT =====

    #[Route('/credits', name: 'app_admin_credits')]
    public function listCredits(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || $this->getUser()->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Access denied');
        }

        $creditRepository = $entityManager->getRepository(\App\Entity\Credit::class);
        
        // Get filter parameters
        $status = $request->query->get('status', '');
        $search = $request->query->get('search', '');
        $sort = $request->query->get('sort', 'date_demande');
        $order = $request->query->get('order', 'DESC');

        $query = $creditRepository->createQueryBuilder('c')
            ->leftJoin('c.utilisateur', 'u')
            ->leftJoin('c.abonnement', 'a');

        if ($search) {
            $query->where('u.email LIKE :search OR u.prenom LIKE :search OR u.nom LIKE :search')
                  ->setParameter('search', '%' . $search . '%');
        }

        if ($status) {
            $query->andWhere('c.statut_credit = :status')
                  ->setParameter('status', $status);
        }

        $query->orderBy('c.' . $sort, $order);
        $credits = $query->getQuery()->getResult();

        $creditStatuses = ['EN_ATTENTE', 'ACCEPTE', 'REFUSE', 'EN_COURS', 'TERMINE', 'EN_RETARD'];

        return $this->render('admin/credits/index.html.twig', [
            'credits' => $credits,
            'status' => $status,
            'search' => $search,
            'creditStatuses' => $creditStatuses,
            'sort' => $sort,
            'order' => $order,
        ]);
    }

    #[Route('/api/user-bank-accounts/{userId}', name: 'app_admin_api_user_bank_accounts', methods: ['GET'])]
    public function apiUserBankAccounts(int $userId, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || $this->getUser()->getRole() !== 'admin') {
            return $this->json(['error' => 'Access denied'], 403);
        }

        $compteBancaireRepository = $entityManager->getRepository(CompteBancaire::class);
        $bankAccounts = $compteBancaireRepository->findBy(['utilisateur' => $userId, 'actif' => true]);

        $result = [];
        foreach ($bankAccounts as $account) {
            $result[] = [
                'id' => $account->getId(),
                'type' => $account->getTypeCompte(),
                'numero' => $account->getNumeroCompte(),
                'solde' => $account->getSolde(),
                'devise' => $account->getDevise(),
            ];
        }

        return $this->json($result);
    }

    #[Route('/credits/new', name: 'app_admin_credit_new', methods: ['GET', 'POST'])]
    public function newCredit(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || $this->getUser()->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Access denied');
        }

        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $abonnementRepository = $entityManager->getRepository(Abonnement::class);
        $compteBancaireRepository = $entityManager->getRepository(CompteBancaire::class);
        $creditTypes = ['3M', '6M', '12M', '24M', '36M'];
        $standardRates = [3.5, 4.5, 5.5, 6.5, 7.5];
        $users = $userRepository->findAll();
        $abonnements = $abonnementRepository->findAll();

        if ($request->isMethod('POST')) {
            $userId = (int)$request->request->get('user_id');
            $montantDemande = (float)$request->request->get('montant_demande');
            $typeCredit = $request->request->get('type_credit');
            $tauxInteret = (float)$request->request->get('taux_interet');
            $compteId = (int)$request->request->get('compte_id');
            $abonnementId = $request->request->get('abonnement_id');

            // Validate inputs with detailed error messages
            $errors = [];
            
            if (empty($userId)) {
                $errors[] = 'User is required';
            }
            
            if (empty($montantDemande)) {
                $errors[] = 'Amount is required';
            } elseif ($montantDemande < 100 || $montantDemande > 100000) {
                $errors[] = 'Amount must be between $100 and $100,000';
            }
            
            if (empty($typeCredit)) {
                $errors[] = 'Credit duration is required';
            }
            
            if ($tauxInteret <= 0 || $tauxInteret > 15) {
                $errors[] = 'Interest rate must be between 0 and 15%';
            }
            
            if (empty($compteId)) {
                $errors[] = 'Bank account is required';
            }

            // Re-render form if validation fails
            if (!empty($errors)) {
                return $this->render('admin/credits/new.html.twig', [
                    'users' => $users,
                    'abonnements' => $abonnements,
                    'creditTypes' => $creditTypes,
                    'standardRates' => $standardRates,
                    'errors' => $errors,
                ]);
            }

            $utilisateur = $userRepository->find($userId);
            $compte = $compteBancaireRepository->find($compteId);
            $abonnement = $abonnementId ? $abonnementRepository->find($abonnementId) : null;

            if (!$utilisateur || !$compte) {
                $errors[] = 'Invalid user or bank account.';
                return $this->render('admin/credits/new.html.twig', [
                    'users' => $users,
                    'abonnements' => $abonnements,
                    'creditTypes' => $creditTypes,
                    'standardRates' => $standardRates,
                    'errors' => $errors,
                ]);
            }

            $credit = new \App\Entity\Credit();
            $credit->setUtilisateur($utilisateur);
            $credit->setCompte($compte);
            $credit->setMontantDemande($montantDemande);
            $credit->setTypeCredit($typeCredit);

            // Apply discount if abonnement is selected
            $reductionPourcentage = 0;
            $tauxFinal = $tauxInteret;
            if ($abonnement) {
                $credit->setAbonnement($abonnement);
                $reductionPourcentage = $abonnement->getReductionPourcentage() ?? 0;
                $tauxFinal = $tauxInteret - $reductionPourcentage;
                $credit->setReductionPourcentage($reductionPourcentage);
            }

            $credit->setTauxInteret($tauxFinal);

            // Calculate monthly payment
            $durationMonths = (int)str_replace('M', '', $typeCredit);
            $monthlyRate = $tauxFinal / 100 / 12;
            $numerator = $monthlyRate * pow(1 + $monthlyRate, $durationMonths);
            $denominator = pow(1 + $monthlyRate, $durationMonths) - 1;
            $mensualite = $denominator > 0 ? $montantDemande * ($numerator / $denominator) : 0;
            $montantTotal = $mensualite * $durationMonths;

            $credit->setMensualite($mensualite);
            $credit->setMontantTotal($montantTotal);
            $credit->setMontantRestant($montantTotal);
            $credit->setDateDemande(new \DateTime());
            $credit->setStatutCredit('EN_ATTENTE');
            $credit->setCreatedAt(new \DateTime());

            $entityManager->persist($credit);
            $entityManager->flush();

            $this->addFlash('success', 'Credit created successfully');
            return $this->redirectToRoute('app_admin_credits');
        }

        return $this->render('admin/credits/new.html.twig', [
            'users' => $users,
            'abonnements' => $abonnements,
            'creditTypes' => $creditTypes,
            'standardRates' => $standardRates,
            'errors' => [],
        ]);
    }

    #[Route('/credits/{id}', name: 'app_admin_credit_view')]
    public function viewCredit($id, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || $this->getUser()->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Access denied');
        }

        $credit = $entityManager->getRepository(\App\Entity\Credit::class)->find($id);
        if (!$credit) {
            throw $this->createNotFoundException('Credit not found');
        }

        return $this->render('admin/credits/view.html.twig', [
            'credit' => $credit,
        ]);
    }

    #[Route('/credits/{id}/accept', name: 'app_admin_credit_accept', methods: ['POST'])]
    public function acceptCredit($id, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || $this->getUser()->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Access denied');
        }

        $credit = $entityManager->getRepository(\App\Entity\Credit::class)->find($id);
        if (!$credit) {
            throw $this->createNotFoundException('Credit not found');
        }

        $credit->setStatutCredit('ACCEPTE');
        $credit->setDateDebut(new \DateTime());
        
        // Calculate end date based on credit type (months)
        $durationMonths = (int)str_replace('M', '', $credit->getTypeCredit());
        $endDate = new \DateTime();
        $endDate->modify("+{$durationMonths} months");
        $credit->setDateFin($endDate);
        
        $credit->setUpdatedAt(new \DateTime());

        // Create remboursement records for each monthly payment
        $mensualite = $credit->getMensualite();
        $startDate = $credit->getDateDebut();
        
        for ($i = 0; $i < $durationMonths; $i++) {
            $remboursement = new \App\Entity\Remboursement();
            $remboursement->setUtilisateur($credit->getUtilisateur());
            $remboursement->setCredit($credit);
            $remboursement->setCompte($credit->getCompte());
            $remboursement->setMontant($mensualite);
            $remboursement->setTypeRemboursement('CREDIT_PAYMENT');
            $remboursement->setStatut('EN_ATTENTE');
            
            // Calculate the payment date for this month
            $paymentDate = clone $startDate;
            $paymentDate->modify("+{$i} months");
            $remboursement->setDateRemboursement($paymentDate);
            $remboursement->setCreatedAt(new \DateTime());
            
            $entityManager->persist($remboursement);
        }

        $entityManager->flush();

        $this->addFlash('success', 'Credit accepted successfully and remboursement schedule created');
        return $this->redirectToRoute('app_admin_credits');
    }

    #[Route('/credits/{id}/deny', name: 'app_admin_credit_deny', methods: ['POST'])]
    public function denyCredit($id, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || $this->getUser()->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Access denied');
        }

        $credit = $entityManager->getRepository(\App\Entity\Credit::class)->find($id);
        if (!$credit) {
            throw $this->createNotFoundException('Credit not found');
        }

        $motifRefus = $request->request->get('motif_refus', 'No reason provided');
        
        $credit->setStatutCredit('REFUSE');
        $credit->setMotifRefus($motifRefus);
        $credit->setUpdatedAt(new \DateTime());

        $entityManager->flush();

        $this->addFlash('success', 'Credit denied successfully');
        return $this->redirectToRoute('app_admin_credits');
    }

    #[Route('/credits/{id}/edit', name: 'app_admin_credit_edit', methods: ['GET', 'POST'])]
    public function editCredit($id, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || $this->getUser()->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Access denied');
        }

        $credit = $entityManager->getRepository(\App\Entity\Credit::class)->find($id);
        if (!$credit) {
            throw $this->createNotFoundException('Credit not found');
        }

        $compteBancaireRepository = $entityManager->getRepository(CompteBancaire::class);
        $abonnementRepository = $entityManager->getRepository(Abonnement::class);
        $creditTypes = ['3M', '6M', '12M', '24M', '36M'];
        $creditStatuses = ['EN_ATTENTE', 'ACCEPTE', 'REFUSE', 'EN_COURS', 'TERMINE', 'EN_RETARD'];

        if ($request->isMethod('POST')) {
            $montantDemande = $request->request->get('montant_demande');
            $typeCredit = $request->request->get('type_credit');
            $tauxInteret = $request->request->get('taux_interet');
            $compteId = $request->request->get('compte_id');
            $abonnementId = $request->request->get('abonnement_id');
            $statut = $request->request->get('statut_credit');

            $errors = [];
            $fieldErrors = [];

            // Validate montantDemande
            if (!$montantDemande || trim($montantDemande) === '') {
                $errors[] = 'Credit amount is required';
                $fieldErrors['montant_demande'] = 'Credit amount is required';
            } elseif (!is_numeric($montantDemande) || (float)$montantDemande <= 0) {
                $errors[] = 'Credit amount must be a positive number';
                $fieldErrors['montant_demande'] = 'Credit amount must be a positive number';
            } elseif ((float)$montantDemande > 1000000) {
                $errors[] = 'Credit amount cannot exceed $1,000,000';
                $fieldErrors['montant_demande'] = 'Credit amount cannot exceed $1,000,000';
            }

            // Validate typeCredit
            if (!$typeCredit || trim($typeCredit) === '') {
                $errors[] = 'Credit type is required';
                $fieldErrors['type_credit'] = 'Credit type is required';
            } elseif (!in_array($typeCredit, $creditTypes)) {
                $errors[] = 'Invalid credit type selected';
                $fieldErrors['type_credit'] = 'Invalid credit type selected';
            }

            // Validate tauxInteret
            if ($tauxInteret === null || $tauxInteret === '') {
                $errors[] = 'Interest rate is required';
                $fieldErrors['taux_interet'] = 'Interest rate is required';
            } elseif (!is_numeric($tauxInteret)) {
                $errors[] = 'Interest rate must be a valid number';
                $fieldErrors['taux_interet'] = 'Interest rate must be a valid number';
            } elseif ((float)$tauxInteret < 0 || (float)$tauxInteret > 100) {
                $errors[] = 'Interest rate must be between 0 and 100';
                $fieldErrors['taux_interet'] = 'Interest rate must be between 0 and 100';
            }

            // Validate compteId
            if (!$compteId || trim($compteId) === '') {
                $errors[] = 'Bank account is required';
                $fieldErrors['compte_id'] = 'Bank account is required';
            } else {
                $compte = $compteBancaireRepository->find((int)$compteId);
                if (!$compte) {
                    $errors[] = 'Selected bank account does not exist';
                    $fieldErrors['compte_id'] = 'Selected bank account does not exist';
                }
            }

            // Validate abonnementId (optional)
            if ($abonnementId && trim($abonnementId) !== '') {
                $abonnement = $abonnementRepository->find((int)$abonnementId);
                if (!$abonnement) {
                    $errors[] = 'Selected subscription does not exist';
                    $fieldErrors['abonnement_id'] = 'Selected subscription does not exist';
                }
            }

            // Validate statut_credit
            if (!$statut || trim($statut) === '') {
                $errors[] = 'Credit status is required';
                $fieldErrors['statut_credit'] = 'Credit status is required';
            } elseif (!in_array($statut, $creditStatuses)) {
                $errors[] = 'Invalid credit status selected';
                $fieldErrors['statut_credit'] = 'Invalid credit status selected';
            }

            // If there are errors, re-render form with errors
            if (!empty($errors)) {
                $bankAccounts = $compteBancaireRepository->findAll();
                $abonnements = $abonnementRepository->findAll();

                return $this->render('admin/credits/edit.html.twig', [
                    'credit' => $credit,
                    'bankAccounts' => $bankAccounts,
                    'abonnements' => $abonnements,
                    'creditTypes' => $creditTypes,
                    'creditStatuses' => $creditStatuses,
                    'errors' => $errors,
                    'fieldErrors' => $fieldErrors,
                    'formData' => [
                        'montant_demande' => $montantDemande,
                        'type_credit' => $typeCredit,
                        'taux_interet' => $tauxInteret,
                        'compte_id' => $compteId,
                        'abonnement_id' => $abonnementId,
                        'statut_credit' => $statut,
                    ],
                ]);
            }

            // All validations passed, update credit
            $montantDemande = (float)$montantDemande;
            $tauxInteret = (float)$tauxInteret;
            $compte = $compteBancaireRepository->find((int)$compteId);

            $credit->setCompte($compte);
            $credit->setMontantDemande($montantDemande);
            $credit->setTypeCredit($typeCredit);
            $credit->setStatutCredit($statut);

            // Update abonnement and discount
            if ($abonnementId && trim($abonnementId) !== '') {
                $abonnement = $abonnementRepository->find((int)$abonnementId);
                if ($abonnement) {
                    $credit->setAbonnement($abonnement);
                    $reductionPourcentage = $abonnement->getReductionPourcentage() ?? 0;
                    $credit->setReductionPourcentage($reductionPourcentage);
                    $tauxFinal = $tauxInteret - $reductionPourcentage;
                } else {
                    $tauxFinal = $tauxInteret;
                }
            } else {
                $credit->setAbonnement(null);
                $credit->setReductionPourcentage(null);
                $tauxFinal = $tauxInteret;
            }

            $credit->setTauxInteret($tauxFinal);

            // Recalculate monthly payment
            $durationMonths = (int)str_replace('M', '', $typeCredit);
            $monthlyRate = $tauxFinal / 100 / 12;
            $numerator = $monthlyRate * pow(1 + $monthlyRate, $durationMonths);
            $denominator = pow(1 + $monthlyRate, $durationMonths) - 1;
            $mensualite = $denominator > 0 ? $montantDemande * ($numerator / $denominator) : 0;
            $montantTotal = $mensualite * $durationMonths;

            $credit->setMensualite($mensualite);
            $credit->setMontantTotal($montantTotal);
            $credit->setMontantRestant($montantTotal);
            $credit->setUpdatedAt(new \DateTime());

            $entityManager->flush();

            $this->addFlash('success', 'Credit updated successfully');
            return $this->redirectToRoute('app_admin_credits');
        }

        $bankAccounts = $compteBancaireRepository->findAll();
        $abonnements = $abonnementRepository->findAll();

        return $this->render('admin/credits/edit.html.twig', [
            'credit' => $credit,
            'bankAccounts' => $bankAccounts,
            'abonnements' => $abonnements,
            'creditTypes' => $creditTypes,
            'creditStatuses' => $creditStatuses,
            'errors' => [],
            'fieldErrors' => [],
            'formData' => [
                'montant_demande' => $credit->getMontantDemande(),
                'type_credit' => $credit->getTypeCredit(),
                'taux_interet' => $credit->getTauxInteret(),
                'compte_id' => $credit->getCompte()->getId(),
                'abonnement_id' => $credit->getAbonnement() ? $credit->getAbonnement()->getIdAbonnement() : '',
                'statut_credit' => $credit->getStatutCredit(),
            ],
        ]);
    }

    #[Route('/credits/{id}/delete', name: 'app_admin_credit_delete', methods: ['POST'])]
    public function deleteCredit($id, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || $this->getUser()->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Access denied');
        }

        $credit = $entityManager->getRepository(\App\Entity\Credit::class)->find($id);
        if (!$credit) {
            throw $this->createNotFoundException('Credit not found');
        }

        $montant = $credit->getMontantDemande();
        $statut = $credit->getStatutCredit();

        $entityManager->remove($credit);
        $entityManager->flush();

        $this->addFlash('success', "Credit ($statut - ${montant}) deleted successfully");
        return $this->redirectToRoute('app_admin_credits');
    }

    // ==================== PROJECT MANAGEMENT ====================

    #[Route('/projets', name: 'app_admin_projets')]
    public function projets(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || $this->getUser()->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Access denied');
        }

        $search = $request->query->get('search', '');
        $secteur = $request->query->get('secteur', '');
        $statut = $request->query->get('statut', '');
        $sortBy = $request->query->get('sort', 'date_debut');
        $sortOrder = $request->query->get('order', 'desc');
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

        $qb = $entityManager->createQueryBuilder();
        $qb->select('p')->from(Projet::class, 'p');

        if ($search) {
            $qb->andWhere('p.nomprojet LIKE :search OR p.description LIKE :search OR p.secteur LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($secteur) {
            $qb->andWhere('p.secteur = :secteur')->setParameter('secteur', $secteur);
        }

        if ($statut) {
            $qb->andWhere('p.statut_projet = :statut')->setParameter('statut', $statut);
        }

        switch ($sortBy) {
            case 'name': $qb->orderBy('p.nomprojet', $sortOrder); break;
            case 'target': $qb->orderBy('p.montant_objectif', $sortOrder); break;
            case 'status': $qb->orderBy('p.statut_projet', $sortOrder); break;
            default: $qb->orderBy('p.date_debut', $sortOrder);
        }

        $projets = $qb->getQuery()->getResult();

        $secteurs = $entityManager->createQueryBuilder()
            ->select('DISTINCT p.secteur')
            ->from(Projet::class, 'p')
            ->orderBy('p.secteur', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return $this->render('admin/projets.html.twig', [
            'projets' => $projets,
            'search' => $search,
            'secteur' => $secteur,
            'statut' => $statut,
            'sortBy' => $sortBy,
            'sortOrder' => strtolower($sortOrder),
            'secteurs' => array_map(fn($i) => $i['secteur'], $secteurs),
            'statutOptions' => ['ACTIF', 'SUSPENDU', 'TERMINE', 'ANNULE'],
        ]);
    }

    #[Route('/projets/create', name: 'app_admin_projet_create', methods: ['GET', 'POST'])]
    public function createProjet(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || $this->getUser()->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Access denied');
        }

        $errors = [];
        $fieldErrors = [];
        $formData = [];

        if ($request->isMethod('POST')) {
            $nomprojet = $request->request->get('nomprojet') ?: '';
            $description = $request->request->get('description') ?: '';
            $secteur = $request->request->get('secteur') ?: '';
            $montantObjectif = (float)($request->request->get('montant_objectif') ?: 0);
            $dateDebut = $request->request->get('date_debut') ?: '';
            $dateFin = $request->request->get('date_fin') ?: '';
            $statutProjet = $request->request->get('statut_projet') ?: 'ACTIF';

            // Store form data for re-population on error
            $formData = [
                'nomprojet' => $nomprojet,
                'description' => $description,
                'secteur' => $secteur,
                'montant_objectif' => $montantObjectif,
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin,
                'statut_projet' => $statutProjet,
            ];

            // Validate inputs
            if (empty($nomprojet)) {
                $fieldErrors['nomprojet'] = 'Project name is required';
                $errors[] = 'Project name is required';
            } elseif (strlen($nomprojet) > 255) {
                $fieldErrors['nomprojet'] = 'Project name is too long (max 255 characters)';
                $errors[] = 'Project name is too long (max 255 characters)';
            }
            
            if (empty($montantObjectif) || $montantObjectif == 0) {
                $fieldErrors['montant'] = 'Target amount is required';
                $errors[] = 'Target amount is required';
            } elseif ($montantObjectif <= 0) {
                $fieldErrors['montant'] = 'Target amount must be greater than 0';
                $errors[] = 'Target amount must be greater than 0';
            }
            
            if (empty($dateDebut)) {
                $fieldErrors['date_debut'] = 'Start date is required';
                $errors[] = 'Start date is required';
            }
            
            if (empty($dateFin)) {
                $fieldErrors['date_fin'] = 'End date is required';
                $errors[] = 'End date is required';
            }
            
            if (!empty($dateDebut) && !empty($dateFin)) {
                try {
                    $dStart = new \DateTime($dateDebut);
                    $dEnd = new \DateTime($dateFin);
                    if ($dEnd <= $dStart) {
                        $fieldErrors['date_fin'] = 'End date must be after start date';
                        $errors[] = 'End date must be after start date';
                    }
                } catch (\Exception $e) {
                    $fieldErrors['date_fin'] = 'Invalid date format';
                    $errors[] = 'Invalid date format';
                }
            }
            
            if (empty($errors)) {
                try {
                    $projet = new Projet();
                    $projet->setNomprojet($nomprojet);
                    $projet->setDescription($description);
                    $projet->setSecteur($secteur);
                    $projet->setMontantObjectif($montantObjectif);
                    $projet->setMontantCollecte(0);
                    $projet->setDateDebut(new \DateTime($dateDebut));
                    $projet->setDateFin(new \DateTime($dateFin));
                    $projet->setStatutProjet($statutProjet);
                    $projet->setAdminCreateur($this->getUser());
                    $projet->setCreatedAt(new \DateTime());

                    $entityManager->persist($projet);
                    $entityManager->flush();

                    $this->addFlash('success', 'Project created successfully');
                    return $this->redirectToRoute('app_admin_projets');
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Error creating project: ' . $e->getMessage());
                }
            }
        }

        return $this->render('admin/projet_form.html.twig', [
            'projet' => null,
            'statutOptions' => ['ACTIF', 'SUSPENDU', 'TERMINE', 'ANNULE'],
            'errors' => $errors,
            'fieldErrors' => $fieldErrors,
            'formData' => $formData,
        ]);
    }

    #[Route('/projets/{id}/edit', name: 'app_admin_projet_edit', methods: ['GET', 'POST'])]
    public function editProjet($id, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || $this->getUser()->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Access denied');
        }

        $projet = $entityManager->getRepository(Projet::class)->find($id);
        if (!$projet) {
            throw $this->createNotFoundException('Project not found');
        }

        $errors = [];
        $fieldErrors = [];
        $formData = [];

        if ($request->isMethod('POST')) {
            $nomprojet = $request->request->get('nomprojet') ?: '';
            $description = $request->request->get('description') ?: '';
            $secteur = $request->request->get('secteur') ?: '';
            $montantObjectif = (float)($request->request->get('montant_objectif') ?: 0);
            $dateDebut = $request->request->get('date_debut') ?: '';
            $dateFin = $request->request->get('date_fin') ?: '';
            $statutProjet = $request->request->get('statut_projet') ?: 'ACTIF';

            // Store form data for re-population on error
            $formData = [
                'nomprojet' => $nomprojet,
                'description' => $description,
                'secteur' => $secteur,
                'montant_objectif' => $montantObjectif,
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin,
                'statut_projet' => $statutProjet,
            ];

            // Validate inputs
            if (empty($nomprojet)) {
                $fieldErrors['nomprojet'] = 'Project name is required';
                $errors[] = 'Project name is required';
            } elseif (strlen($nomprojet) > 255) {
                $fieldErrors['nomprojet'] = 'Project name is too long (max 255 characters)';
                $errors[] = 'Project name is too long (max 255 characters)';
            }
            
            if (empty($montantObjectif) || $montantObjectif == 0) {
                $fieldErrors['montant'] = 'Target amount is required';
                $errors[] = 'Target amount is required';
            } elseif ($montantObjectif <= 0) {
                $fieldErrors['montant'] = 'Target amount must be greater than 0';
                $errors[] = 'Target amount must be greater than 0';
            }
            
            if (empty($dateDebut)) {
                $fieldErrors['date_debut'] = 'Start date is required';
                $errors[] = 'Start date is required';
            }
            
            if (empty($dateFin)) {
                $fieldErrors['date_fin'] = 'End date is required';
                $errors[] = 'End date is required';
            }
            
            if (!empty($dateDebut) && !empty($dateFin)) {
                try {
                    $dStart = new \DateTime($dateDebut);
                    $dEnd = new \DateTime($dateFin);
                    if ($dEnd <= $dStart) {
                        $fieldErrors['date_fin'] = 'End date must be after start date';
                        $errors[] = 'End date must be after start date';
                    }
                } catch (\Exception $e) {
                    $fieldErrors['date_fin'] = 'Invalid date format';
                    $errors[] = 'Invalid date format';
                }
            }
            
            if (empty($errors)) {
                try {
                    $projet->setNomprojet($nomprojet);
                    $projet->setDescription($description);
                    $projet->setSecteur($secteur);
                    $projet->setMontantObjectif($montantObjectif);
                    $projet->setDateDebut(new \DateTime($dateDebut));
                    $projet->setDateFin(new \DateTime($dateFin));
                    $projet->setStatutProjet($statutProjet);
                    $projet->setUpdatedAt(new \DateTime());

                    $entityManager->flush();

                    $this->addFlash('success', 'Project updated successfully');
                    return $this->redirectToRoute('app_admin_projets');
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Error updating project: ' . $e->getMessage());
                }
            }
        }

        return $this->render('admin/projet_form.html.twig', [
            'projet' => $projet,
            'statutOptions' => ['ACTIF', 'SUSPENDU', 'TERMINE', 'ANNULE'],
            'errors' => $errors,
            'fieldErrors' => $fieldErrors,
            'formData' => $formData,
        ]);
    }

    #[Route('/projets/{id}/delete', name: 'app_admin_projet_delete', methods: ['POST'])]
    public function deleteProjet($id, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || $this->getUser()->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Access denied');
        }

        $projet = $entityManager->getRepository(Projet::class)->find($id);
        if (!$projet) {
            throw $this->createNotFoundException('Project not found');
        }

        $investissementRepository = $entityManager->getRepository(Investissement::class);
        $count = count($investissementRepository->findBy(['projet' => $projet]));
        
        if ($count > 0) {
            $this->addFlash('error', 'Cannot delete project with existing investments. (' . $count . ' investment(s) exist)');
            return $this->redirectToRoute('app_admin_projets');
        }

        $nom = $projet->getNomprojet();
        $entityManager->remove($projet);
        $entityManager->flush();

        $this->addFlash('success', 'Project "' . $nom . '" deleted successfully');
        return $this->redirectToRoute('app_admin_projets');
    }

    // ==================== INVESTMENT MANAGEMENT ====================

    #[Route('/investissements', name: 'app_admin_investissements')]
    public function investissements(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || $this->getUser()->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Access denied');
        }

        $search = $request->query->get('search', '');
        $statut = $request->query->get('statut', '');
        $sortBy = $request->query->get('sort', 'dateinves');
        $sortOrder = $request->query->get('order', 'desc');
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

        $qb = $entityManager->createQueryBuilder();
        $qb->select('i')
            ->from(Investissement::class, 'i')
            ->leftJoin('i.projet', 'p')
            ->leftJoin('i.utilisateur', 'u');

        if ($search) {
            $qb->andWhere('p.nomprojet LIKE :search OR p.secteur LIKE :search OR u.prenom LIKE :search OR u.nom LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($statut) {
            $qb->andWhere('i.statut_investissement = :statut')->setParameter('statut', $statut);
        }

        switch ($sortBy) {
            case 'montant': $qb->orderBy('i.montantinvesti', $sortOrder); break;
            case 'projet': $qb->orderBy('p.nomprojet', $sortOrder); break;
            case 'utilisateur': $qb->orderBy('u.prenom', $sortOrder); break;
            default: $qb->orderBy('i.dateinves', $sortOrder);
        }

        $investissements = $qb->getQuery()->getResult();

        $totalInvesti = 0;
        $pendingCount = 0;
        $acceptedCount = 0;
        $rejectedCount = 0;

        foreach ($investissements as $inv) {
            $totalInvesti += (float)$inv->getMontantinvesti();
            if ($inv->getStatutInvestissement() === 'EN_ATTENTE') $pendingCount++;
            elseif ($inv->getStatutInvestissement() === 'ACCEPTE') $acceptedCount++;
            elseif ($inv->getStatutInvestissement() === 'REJETE') $rejectedCount++;
        }

        return $this->render('admin/investissements.html.twig', [
            'investissements' => $investissements,
            'totalInvesti' => $totalInvesti,
            'pendingCount' => $pendingCount,
            'acceptedCount' => $acceptedCount,
            'rejectedCount' => $rejectedCount,
            'search' => $search,
            'statut' => $statut,
            'sortBy' => $sortBy,
            'sortOrder' => strtolower($sortOrder),
            'statutOptions' => ['EN_ATTENTE', 'ACCEPTE', 'REJETE'],
        ]);
    }

    #[Route('/investissements/{id}', name: 'app_admin_investissement_view')]
    public function viewInvestissement($id, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || $this->getUser()->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Access denied');
        }

        $investissement = $entityManager->getRepository(Investissement::class)->find($id);
        if (!$investissement) {
            throw $this->createNotFoundException('Investment not found');
        }

        return $this->render('admin/investissement_view.html.twig', [
            'investissement' => $investissement,
        ]);
    }

    #[Route('/investissements/{id}/accept', name: 'app_admin_investissement_accept', methods: ['POST'])]
    public function acceptInvestissement($id, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || $this->getUser()->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Access denied');
        }

        $investissement = $entityManager->getRepository(Investissement::class)->find($id);
        if (!$investissement) {
            throw $this->createNotFoundException('Investment not found');
        }

        if ($investissement->getStatutInvestissement() !== 'EN_ATTENTE') {
            $this->addFlash('error', 'Can only accept pending investments.');
            return $this->redirectToRoute('app_admin_investissements');
        }

        $investissement->setStatutInvestissement('ACCEPTE');
        $investissement->setUpdatedAt(new \DateTime());

        $entityManager->flush();

        $this->addFlash('success', 'Investment accepted successfully');
        return $this->redirectToRoute('app_admin_investissements');
    }

    #[Route('/investissements/{id}/reject', name: 'app_admin_investissement_reject', methods: ['POST'])]
    public function rejectInvestissement($id, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || $this->getUser()->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Access denied');
        }

        $investissement = $entityManager->getRepository(Investissement::class)->find($id);
        if (!$investissement) {
            throw $this->createNotFoundException('Investment not found');
        }

        // Refund if pending
        if ($investissement->getStatutInvestissement() === 'EN_ATTENTE') {
            $montant = (float)$investissement->getMontantinvesti();
            $compte = $investissement->getCompte();
            $projet = $investissement->getProjet();

            if ($compte) {
                $nowSolde = (float)$compte->getSolde();
                $compte->setSolde($nowSolde + $montant);
            }

            if ($projet) {
                $montantCollecte = (float)$projet->getMontantCollecte();
                $projet->setMontantCollecte(max(0, $montantCollecte - $montant));
                $projet->setUpdatedAt(new \DateTime());
            }
        }

        $investissement->setStatutInvestissement('REJETE');
        $investissement->setUpdatedAt(new \DateTime());

        $entityManager->flush();

        $this->addFlash('success', 'Investment rejected and amount refunded');
        return $this->redirectToRoute('app_admin_investissements');
    }
}

