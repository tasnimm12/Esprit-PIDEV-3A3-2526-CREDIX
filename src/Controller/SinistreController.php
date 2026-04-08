<?php

namespace App\Controller;

use App\Entity\Sinistre;
use App\Entity\ContratAssurance;
use App\Entity\SinistrePreuve;
use App\Service\LocalImageAnalysisService;
use App\Service\ClaimNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/sinistre')]
class SinistreController extends AbstractController
{

    #[Route('/', name: 'app_sinistre_list')]
    public function list(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        // Check if user is admin - case insensitive
        $isAdmin = strtolower($this->getUser()->getRole() ?? '') === 'admin';
        
        // Get query parameters for filtering
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');
        $dateFrom = $request->query->get('date_from', '');
        $dateTo = $request->query->get('date_to', '');
        $sort = $request->query->get('sort', 'created_at');
        $order = strtoupper($request->query->get('order', 'DESC'));
        $page = max(1, (int)$request->query->get('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        // Build filter array
        $filters = [];
        if (!empty($search)) {
            $filters['search'] = $search;
        }
        if (!empty($status)) {
            $filters['status'] = $status;
        }
        if (!empty($dateFrom)) {
            $filters['date_from'] = $dateFrom;
        }
        if (!empty($dateTo)) {
            $filters['date_to'] = $dateTo;
        }
        
        // Use repository method instead of inline QueryBuilder
        /** @var \App\Repository\SinistreRepository $sinistreRepository */
        $sinistreRepository = $em->getRepository(Sinistre::class);
        
        if ($isAdmin) {
            // Admin sees all claims with filters
            $sinistres = $sinistreRepository->findWithFilters($filters, null, $sort, $order, $limit, $offset);
            $total = $sinistreRepository->countWithFilters($filters, null);
            $template = 'sinistre/admin_list.html.twig';
            $title = 'All Claims Management';
        } else {
            // Regular users see ONLY their own claims with filters
            $sinistres = $sinistreRepository->findWithFilters($filters, $this->getUser(), $sort, $order, $limit, $offset);
            $total = $sinistreRepository->countWithFilters($filters, $this->getUser());
            $template = 'sinistre/list.html.twig';
            $title = 'My Claims';
        }
        
        // Calculate pagination
        $totalPages = ceil($total / $limit);

        return $this->render($template, [
            'sinistres' => $sinistres,
            'title' => $title,
            'isAdmin' => $isAdmin,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'sort' => $sort,
                'order' => $order,
            ],
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'totalPages' => $totalPages,
            ],
        ]);
    }

    #[Route('/new', name: 'app_sinistre_new')]
    public function new(Request $request, EntityManagerInterface $em, ValidatorInterface $validator, LocalImageAnalysisService $analysisService): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        // Check if user has active contracts
        $activeContracts = $em->getRepository(ContratAssurance::class)->findBy([
            'utilisateur' => $this->getUser(),
            'statut' => 'ACTIF'
        ]);

        if (count($activeContracts) === 0) {
            $this->addFlash('error', 'You must have at least one active contract to file a claim.');
            return $this->redirectToRoute('app_contract_list');
        }

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $errors = [];

            // Validate contract selection
            if (empty($data['contrat_id'])) {
                $errors[] = 'Please select a contract';
            } else {
                $contractId = (int)$data['contrat_id'];
                
                // Validate contract ID is a positive integer
                if ($contractId <= 0) {
                    $errors[] = 'Invalid contract ID';
                } else {
                    $contrat = $em->getRepository(ContratAssurance::class)->find($contractId);

                    if (!$contrat) {
                        $errors[] = 'Selected contract not found';
                    } elseif ($contrat->getUtilisateur()->getId() !== $this->getUser()->getId()) {
                        $errors[] = 'This contract does not belong to you';
                    } elseif ($contrat->getStatut() !== 'ACTIF') {
                        $errors[] = 'Selected contract is not active';
                    }
                }
            }

            // Validate incident date
            if (empty($data['date_sinistre'])) {
                $errors[] = 'Incident date is required';
            } else {
                $dateStr = trim($data['date_sinistre']);
                
                // Validate date format
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
                    $errors[] = 'Invalid date format. Please use YYYY-MM-DD';
                } else {
                    try {
                        $incidentDate = new \DateTime($dateStr);
                        $today = new \DateTime();
                        $today->setTime(23, 59, 59);
                        
                        if ($incidentDate > $today) {
                            $errors[] = 'Incident date cannot be in the future';
                        }
                        
                        // Prevent claims for incidents too far in the past (e.g., more than 5 years)
                        $fiveYearsAgo = new \DateTime();
                        $fiveYearsAgo->modify('-5 years');
                        
                        if ($incidentDate < $fiveYearsAgo) {
                            $errors[] = 'Incident date cannot be more than 5 years in the past';
                        }
                    } catch (\Exception $e) {
                        $errors[] = 'Invalid incident date';
                    }
                }
            }

            // Validate description
            if (empty($data['description'])) {
                $errors[] = 'Claim description is required';
            } else {
                $description = trim($data['description']);
                
                // Check if description is not just whitespace
                if (empty($description)) {
                    $errors[] = 'Claim description cannot be empty or contain only whitespace';
                } elseif (strlen($description) < 10) {
                    $errors[] = 'Description must be at least 10 characters';
                } elseif (strlen($description) > 5000) {
                    $errors[] = 'Description cannot exceed 5000 characters';
                } else {
                    // Basic XSS prevention - check for suspicious patterns
                    if (preg_match('/<script|javascript:|onerror|onclick/i', $description)) {
                        $errors[] = 'Description contains invalid content';
                    }
                }
            }

            // Validate file uploads if any
            $uploadedFiles = $request->files->get('preuves');
            if ($uploadedFiles) {
                $maxFiles = 5;
                $maxSize = 25 * 1024 * 1024; // 25MB
                $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];

                if (count($uploadedFiles) > $maxFiles) {
                    $errors[] = "Maximum $maxFiles files allowed";
                }

                foreach ($uploadedFiles as $file) {
                    // Skip empty files
                    if ($file->getSize() === 0) {
                        continue;
                    }
                    
                    // Validate file size
                    if ($file->getSize() > $maxSize) {
                        $errors[] = 'File "' . htmlspecialchars($file->getClientOriginalName(), ENT_QUOTES) . '" exceeds 25MB limit';
                    }

                    // Validate MIME type
                    if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
                        $errors[] = 'File type not allowed: ' . htmlspecialchars($file->getClientOriginalName(), ENT_QUOTES) . '. Only images, PDF, and Word docs are allowed.';
                    }
                    
                    // Validate file name length
                    $filename = $file->getClientOriginalName();
                    if (strlen($filename) > 255) {
                        $errors[] = 'File name is too long: ' . htmlspecialchars($filename, ENT_QUOTES);
                    }
                }
            }

            if (empty($errors)) {
                $sinistre = new Sinistre();
                $sinistre->setUtilisateur($this->getUser());
                $sinistre->setContrat($contrat);
                $sinistre->setDescription(trim($data['description']));
                $sinistre->setDateSinistre(new \DateTime($dateStr));
                $sinistre->setStatut('EN_ATTENTE');

                // Validate entity using Symfony validator
                $validationErrors = $validator->validate($sinistre);

                if (count($validationErrors) > 0) {
                    foreach ($validationErrors as $validationError) {
                        $errors[] = $validationError->getPropertyPath() . ': ' . $validationError->getMessage();
                    }
                }
            }

            if (empty($errors)) {
                // Save
                $em->persist($sinistre);
                $em->flush();

                // Handle file uploads
                if ($uploadedFiles) {
                    foreach ($uploadedFiles as $file) {
                        if ($file->getSize() > 0) {
                            $this->handleFileUpload($file, $sinistre, $em, $analysisService);
                        }
                    }
                }

                $this->addFlash('success', 'Claim filed successfully. Claim #' . $sinistre->getId());

                return $this->redirectToRoute('app_sinistre_show', ['id' => $sinistre->getId()]);
            } else {
                // Re-render form with validation errors
                return $this->render('sinistre/form.html.twig', [
                    'sinistre' => null,
                    'contracts' => $activeContracts,
                    'title' => 'File a Claim',
                    'errors' => $errors,
                ]);
            }
        }

        return $this->render('sinistre/form.html.twig', [
            'sinistre' => null,
            'contracts' => $activeContracts,
            'title' => 'File a Claim',
            'errors' => [],
        ]);
    }

    #[Route('/admin/new', name: 'app_sinistre_admin_new')]
    public function adminNew(Request $request, EntityManagerInterface $em, ValidatorInterface $validator, LocalImageAnalysisService $analysisService): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        // Check if user has admin role
        if (strtolower($this->getUser()->getRole() ?? '') !== 'admin') {
            throw $this->createAccessDeniedException('Only administrators can file claims for users');
        }

        $users = [];
        $selectedUserId = null;
        $activeContracts = [];

        // Get all users
        if ($request->isMethod('GET') && $request->query->get('user_id')) {
            $selectedUserId = (int)$request->query->get('user_id');
            $selectedUser = $em->getRepository(\App\Entity\Utilisateur::class)->find($selectedUserId);
            if ($selectedUser) {
                $activeContracts = $em->getRepository(ContratAssurance::class)->findBy([
                    'utilisateur' => $selectedUser,
                    'statut' => 'ACTIF'
                ]);
            }
        }

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $errors = [];

            // Validate user selection
            if (empty($data['utilisateur_id'])) {
                $errors[] = 'Please select a user';
            } else {
                $userId = (int)$data['utilisateur_id'];
                
                // Validate user ID is a positive integer
                if ($userId <= 0) {
                    $errors[] = 'Invalid user ID';
                } else {
                    $utilisateur = $em->getRepository(\App\Entity\Utilisateur::class)->find($userId);
                    if (!$utilisateur) {
                        $errors[] = 'User not found';
                    }
                }
            }

            // Validate contract selection
            if (empty($data['contrat_id'])) {
                $errors[] = 'Please select a contract';
            } else {
                $contractId = (int)$data['contrat_id'];
                
                // Validate contract ID is a positive integer
                if ($contractId <= 0) {
                    $errors[] = 'Invalid contract ID';
                } else {
                    $contrat = $em->getRepository(ContratAssurance::class)->find($contractId);

                    if (!$contrat) {
                        $errors[] = 'Selected contract not found';
                    } elseif ($contrat->getStatut() !== 'ACTIF') {
                        $errors[] = 'Selected contract is not active';
                    } elseif (isset($utilisateur) && $contrat->getUtilisateur()->getId() !== $utilisateur->getId()) {
                        // Verify contract belongs to the selected user
                        $errors[] = 'Selected contract does not belong to the chosen user';
                    }
                }
            }

            // Validate incident date
            if (empty($data['date_sinistre'])) {
                $errors[] = 'Incident date is required';
            } else {
                $dateStr = trim($data['date_sinistre']);
                
                // Validate date format
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
                    $errors[] = 'Invalid date format. Please use YYYY-MM-DD';
                } else {
                    try {
                        $incidentDate = new \DateTime($dateStr);
                        $today = new \DateTime();
                        $today->setTime(23, 59, 59);
                        
                        if ($incidentDate > $today) {
                            $errors[] = 'Incident date cannot be in the future';
                        }
                        
                        // Prevent claims for incidents too far in the past (e.g., more than 5 years)
                        $fiveYearsAgo = new \DateTime();
                        $fiveYearsAgo->modify('-5 years');
                        
                        if ($incidentDate < $fiveYearsAgo) {
                            $errors[] = 'Incident date cannot be more than 5 years in the past';
                        }
                    } catch (\Exception $e) {
                        $errors[] = 'Invalid incident date';
                    }
                }
            }

            // Validate description
            if (empty($data['description'])) {
                $errors[] = 'Claim description is required';
            } else {
                $description = trim($data['description']);
                
                // Check if description is not just whitespace
                if (empty($description)) {
                    $errors[] = 'Claim description cannot be empty or contain only whitespace';
                } elseif (strlen($description) < 10) {
                    $errors[] = 'Description must be at least 10 characters';
                } elseif (strlen($description) > 5000) {
                    $errors[] = 'Description cannot exceed 5000 characters';
                } else {
                    // Basic XSS prevention - check for suspicious patterns
                    if (preg_match('/<script|javascript:|onerror|onclick/i', $description)) {
                        $errors[] = 'Description contains invalid content';
                    }
                }
            }

            // Validate file uploads if any
            $uploadedFiles = $request->files->get('preuves');
            if ($uploadedFiles) {
                $maxFiles = 5;
                $maxSize = 25 * 1024 * 1024; // 25MB
                $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];

                if (count($uploadedFiles) > $maxFiles) {
                    $errors[] = "Maximum $maxFiles files allowed";
                }

                foreach ($uploadedFiles as $file) {
                    // Skip empty files
                    if ($file->getSize() === 0) {
                        continue;
                    }
                    
                    // Validate file size
                    if ($file->getSize() > $maxSize) {
                        $errors[] = 'File "' . htmlspecialchars($file->getClientOriginalName(), ENT_QUOTES) . '" exceeds 25MB limit';
                    }

                    // Validate MIME type
                    if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
                        $errors[] = 'File type not allowed: ' . htmlspecialchars($file->getClientOriginalName(), ENT_QUOTES) . '. Only images, PDF, and Word docs are allowed.';
                    }
                    
                    // Validate file name
                    $filename = $file->getClientOriginalName();
                    if (strlen($filename) > 255) {
                        $errors[] = 'File name is too long: ' . htmlspecialchars($filename, ENT_QUOTES);
                    }
                }
            }

            if (empty($errors)) {
                $sinistre = new Sinistre();
                $sinistre->setUtilisateur($utilisateur);
                $sinistre->setContrat($contrat);
                $sinistre->setDescription(trim($data['description']));
                $sinistre->setDateSinistre(new \DateTime($dateStr));
                $sinistre->setStatut('EN_ATTENTE');

                // Validate entity using Symfony validator
                $validationErrors = $validator->validate($sinistre);

                if (count($validationErrors) > 0) {
                    foreach ($validationErrors as $validationError) {
                        $errors[] = $validationError->getPropertyPath() . ': ' . $validationError->getMessage();
                    }
                }
            }

            if (empty($errors)) {
                // Save
                $em->persist($sinistre);
                $em->flush();

                // Handle file uploads
                if ($uploadedFiles) {
                    foreach ($uploadedFiles as $file) {
                        if ($file->getSize() > 0) {
                            $this->handleFileUpload($file, $sinistre, $em, $analysisService);
                        }
                    }
                }

                $this->addFlash('success', 'Claim filed successfully for user. Claim #' . $sinistre->getId());

                return $this->redirectToRoute('app_sinistre_show', ['id' => $sinistre->getId()]);
            } else {
                // Get all users for dropdown
                $users = $em->getRepository(\App\Entity\Utilisateur::class)->findAll();
                
                // Re-render form with validation errors
                return $this->render('sinistre/admin_form.html.twig', [
                    'sinistre' => null,
                    'contracts' => $activeContracts,
                    'users' => $users,
                    'selectedUserId' => $selectedUserId,
                    'title' => 'File a Claim - Admin',
                    'errors' => $errors,
                ]);
            }
        }

        // Get all users for dropdown
        $users = $em->getRepository(\App\Entity\Utilisateur::class)->findAll();

        return $this->render('sinistre/admin_form.html.twig', [
            'sinistre' => null,
            'contracts' => $activeContracts,
            'users' => $users,
            'selectedUserId' => $selectedUserId,
            'title' => 'File a Claim - Admin',
            'errors' => [],
        ]);
    }

    #[Route('/{id}', name: 'app_sinistre_show')]
    public function show(int $id, EntityManagerInterface $em): Response
    {
        if (!$this->getUser()) {
            $this->addFlash('info', 'Please log in to view your claim.');
            return $this->redirectToRoute('app_login');
        }

        $sinistre = $em->getRepository(Sinistre::class)->find($id);

        if (!$sinistre) {
            throw $this->createNotFoundException('Claim not found');
        }

        // Check if user is owner or admin - must verify user can view this claim
        $currentUserId = (int)$this->getUser()->getId();
        $claimOwnerId = $sinistre->getUtilisateur() ? (int)$sinistre->getUtilisateur()->getId() : null;
        $isOwner = $currentUserId === $claimOwnerId;
        $isAdmin = strtolower($this->getUser()->getRole() ?? '') === 'admin';

        if (!$isOwner && !$isAdmin) {
            $this->addFlash('error', 'You cannot view this claim. Please ensure you are logged in with the correct account that filed this claim.');
            return $this->redirectToRoute('app_sinistre_list');
        }

        $template = $isAdmin ? 'sinistre/admin_show.html.twig' : 'sinistre/show.html.twig';

        return $this->render($template, [
            'sinistre' => $sinistre,
            'title' => 'Claim Details',
            'isAdmin' => $isAdmin,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_sinistre_edit')]
    public function edit(int $id, Request $request, EntityManagerInterface $em, ValidatorInterface $validator, ClaimNotificationService $notificationService): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        $sinistre = $em->getRepository(Sinistre::class)->find($id);

        if (!$sinistre) {
            throw $this->createNotFoundException('Claim not found');
        }

        // Only allow owner or admin to edit pending claims
        $isOwner = $this->getUser()->getId() === $sinistre->getUtilisateur()->getId();
        $isAdmin = strtolower($this->getUser()->getRole() ?? '') === 'admin';

        if (!$isOwner && !$isAdmin) {
            throw $this->createAccessDeniedException('You cannot edit this claim');
        }

        if ($sinistre->getStatut() !== 'EN_ATTENTE') {
            $this->addFlash('error', 'You can only edit pending claims');
            return $this->redirectToRoute('app_sinistre_show', ['id' => $sinistre->getId()]);
        }

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $errors = [];

            // Validate description if provided
            if (isset($data['description']) && !empty($data['description'])) {
                $description = trim($data['description']);
                
                // Check if description is not just whitespace
                if (empty($description)) {
                    $errors[] = 'Claim description cannot be empty or contain only whitespace';
                } elseif (strlen($description) < 10) {
                    $errors[] = 'Description must be at least 10 characters';
                } elseif (strlen($description) > 5000) {
                    $errors[] = 'Description cannot exceed 5000 characters';
                } else {
                    // Basic XSS prevention
                    if (preg_match('/<script|javascript:|onerror|onclick/i', $description)) {
                        $errors[] = 'Description contains invalid content';
                    } else {
                        $sinistre->setDescription($description);
                    }
                }
            }

            // Validate incident date if provided
            if (isset($data['date_sinistre']) && !empty($data['date_sinistre'])) {
                $dateStr = trim($data['date_sinistre']);
                
                // Validate date format
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
                    $errors[] = 'Invalid date format. Please use YYYY-MM-DD';
                } else {
                    try {
                        $incidentDate = new \DateTime($dateStr);
                        $today = new \DateTime();
                        $today->setTime(23, 59, 59);
                        
                        if ($incidentDate > $today) {
                            $errors[] = 'Incident date cannot be in the future';
                        }
                        
                        // Prevent claims for incidents too far in the past
                        $fiveYearsAgo = new \DateTime();
                        $fiveYearsAgo->modify('-5 years');
                        
                        if ($incidentDate < $fiveYearsAgo) {
                            $errors[] = 'Incident date cannot be more than 5 years in the past';
                        } else {
                            $sinistre->setDateSinistre($incidentDate);
                        }
                    } catch (\Exception $e) {
                        $errors[] = 'Invalid incident date';
                    }
                }
            }

            // Track status change for email notification
            $statusChanged = false;
            $oldStatus = $sinistre->getStatut();
            $newStatus = null;

            // Admin can change status and add response
            if ($isAdmin && isset($data['statut'])) {
                $status = trim($data['statut']);
                
                // Validate status value
                if (!in_array($status, ['ACCEPTE', 'REJETE'])) {
                    $errors[] = 'Invalid claim status';
                } else {
                    $newStatus = $status;
                    $sinistre->setStatut($newStatus);

                    // Validate admin response if provided
                    if (isset($data['admin_reponse']) && !empty($data['admin_reponse'])) {
                        $adminResponse = trim($data['admin_reponse']);
                        
                        if (empty($adminResponse)) {
                            $errors[] = 'Admin response cannot be empty or contain only whitespace';
                        } elseif (strlen($adminResponse) < 5) {
                            $errors[] = 'Admin response must be at least 5 characters';
                        } elseif (strlen($adminResponse) > 5000) {
                            $errors[] = 'Admin response cannot exceed 5000 characters';
                        } else {
                            // Basic XSS prevention
                            if (preg_match('/<script|javascript:|onerror|onclick/i', $adminResponse)) {
                                $errors[] = 'Admin response contains invalid content';
                            } else {
                                $sinistre->setAdminReponse($adminResponse);
                                $sinistre->setDateReponse(new \DateTime());
                                $statusChanged = $oldStatus !== $newStatus;
                            }
                        }
                    } else {
                        // If changing to accepted/rejected, response should be provided
                        $errors[] = 'Admin response is required when changing the claim status';
                    }
                }
            }

            // Only run entity validation if no input validation errors
            if (empty($errors)) {
                $validationErrors = $validator->validate($sinistre);

                if (count($validationErrors) > 0) {
                    foreach ($validationErrors as $validationError) {
                        $errors[] = $validationError->getPropertyPath() . ': ' . $validationError->getMessage();
                    }
                }
            }

            if (count($errors) > 0) {
                $template = $isAdmin ? 'sinistre/admin_edit.html.twig' : 'sinistre/form.html.twig';

                return $this->render($template, [
                    'sinistre' => $sinistre,
                    'contracts' => [$sinistre->getContrat()],
                    'title' => 'Edit Claim',
                    'errors' => $errors,
                    'isEdit' => true,
                    'isAdmin' => $isAdmin,
                ]);
            }

            // Save
            $em->flush();

            // Send email notification if status was changed by admin
            if ($statusChanged && $newStatus && in_array($newStatus, ['ACCEPTE', 'REJETE'])) {
                if ($newStatus === 'ACCEPTE') {
                    $notificationService->sendApprovalNotification($sinistre);
                } else {
                    $notificationService->sendRejectionNotification($sinistre);
                }
            }

            $this->addFlash('success', 'Claim updated successfully');

            return $this->redirectToRoute('app_sinistre_show', ['id' => $sinistre->getId()]);
        }

        $template = $isAdmin ? 'sinistre/admin_edit.html.twig' : 'sinistre/form.html.twig';

        return $this->render($template, [
            'sinistre' => $sinistre,
            'contracts' => [$sinistre->getContrat()],
            'title' => 'Edit Claim',
            'errors' => [],
            'isEdit' => true,
            'isAdmin' => $isAdmin,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_sinistre_delete', methods: ['POST'])]
    public function delete(int $id, EntityManagerInterface $em): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        $sinistre = $em->getRepository(Sinistre::class)->find($id);

        if (!$sinistre) {
            throw $this->createNotFoundException('Claim not found');
        }

        // Only allow owner or admin to delete pending claims
        $isOwner = $this->getUser()->getId() === $sinistre->getUtilisateur()->getId();
        $isAdmin = strtolower($this->getUser()->getRole() ?? '') === 'admin';

        if (!$isOwner && !$isAdmin) {
            throw $this->createAccessDeniedException('You cannot delete this claim');
        }

        if ($sinistre->getStatut() !== 'EN_ATTENTE') {
            $this->addFlash('error', 'You can only delete pending claims');
            return $this->redirectToRoute('app_sinistre_show', ['id' => $sinistre->getId()]);
        }

        $em->remove($sinistre);
        $em->flush();

        $this->addFlash('success', 'Claim deleted successfully');

        return $this->redirectToRoute('app_sinistre_list');
    }

    private function handleFileUpload($file, Sinistre $sinistre, EntityManagerInterface $em, LocalImageAnalysisService $analysisService): void
    {
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx'];
        $fileExtension = strtolower($file->getClientOriginalExtension());
        
        if (!in_array($fileExtension, $allowedExtensions)) {
            return;
        }

        // MIME type mapping - never call getMimeType() to avoid Symfony Mime requirement
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
        $mimeType = $mimeTypes[$fileExtension] ?? 'application/octet-stream';

        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/sinistre_preuves/';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileName = uniqid('preuve_') . '_' . $file->getClientOriginalName();
        $file->move($uploadDir, $fileName);

        $preuve = new SinistrePreuve();
        $preuve->setSinistre($sinistre);
        $preuve->setFichier('/uploads/sinistre_preuves/' . $fileName);
        $preuve->setTypeFichier($mimeType);
        $preuve->setNomFichier($file->getClientOriginalName());

        $em->persist($preuve);
        
        // IMPORTANT: Flush preuve to database so it gets an ID before creating DamageAnalysis
        $em->flush();

        // Automatically analyze damage for images if analysis service is configured
        if ($analysisService->isConfigured()) {
            $isImageFile = in_array(strtolower($fileExtension), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
            
            if ($isImageFile) {
                try {
                    $analysisService->analyzeDamage($preuve, $sinistre);
                } catch (\Exception $e) {
                    // Log error but don't prevent file upload
                    // The service logs the full error details
                }
            }
        }
    }
}
