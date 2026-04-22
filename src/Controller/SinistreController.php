<?php

namespace App\Controller;

use App\Entity\Sinistre;
use App\Entity\ContratAssurance;
use App\Entity\SinistrePreuve;
use App\Service\ClaimContractCompatibilityService;
use App\Service\LocalImageAnalysisService;
use App\Service\DamageDetection\DamageDetectionOrchestrator;
use App\Service\ClaimNotificationService;
use App\Service\ClaimCoverageAnalysisService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/sinistre')]
class SinistreController extends AbstractController
{
    private const CONSTAT_DRAFT_SESSION_KEY = 'sinistre.constat_draft';

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
    public function new(Request $request, EntityManagerInterface $em, ValidatorInterface $validator, DamageDetectionOrchestrator $orchestrator, LocalImageAnalysisService $analysisService, ClaimContractCompatibilityService $claimCompatibilityService, ClaimNotificationService $notificationService): Response
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

            if (empty($errors) && isset($contrat)) {
                $compatibility = $claimCompatibilityService->evaluate(
                    $data['description'] ?? '',
                    $contrat->getAssurance()?->getTypeAssurance()
                );

                if (!$compatibility['is_compatible']) {
                    $message = $compatibility['message'] ?? 'This claim does not match the selected insurance contract.';
                    $errors[] = $message;
                    $this->addFlash('warning', $message);
                }
            }

            if (empty($errors) && isset($contrat)) {
                $detectedType = $claimCompatibilityService->detectClaimTypeFromDescription($data['description'] ?? '');
                $contractType = strtoupper((string) $contrat->getAssurance()?->getTypeAssurance());

                if ($contractType === 'AUTO' && $detectedType === 'AUTO') {
                    $this->storeConstatDraft($request, $data, $uploadedFiles);
                    $this->addFlash('info', 'Auto accident detected. Please complete the constat form to continue your claim.');

                    return $this->redirectToRoute('app_sinistre_constat_new');
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

            // Validate location data
            $latitude = isset($data['latitude']) ? trim($data['latitude']) : '';
            $longitude = isset($data['longitude']) ? trim($data['longitude']) : '';
            $locationAddress = isset($data['location_address']) ? trim($data['location_address']) : '';
            
            if (empty($latitude) || empty($longitude)) {
                $errors[] = 'Incident location is required. Please select a location on the map';
            } else {
                // Validate latitude and longitude are valid numbers
                if (!is_numeric($latitude) || !is_numeric($longitude)) {
                    $errors[] = 'Invalid location coordinates';
                } else {
                    $lat = (float)$latitude;
                    $lng = (float)$longitude;
                    
                    // Validate latitude range (-90 to 90)
                    if ($lat < -90 || $lat > 90) {
                        $errors[] = 'Invalid latitude value';
                    }
                    
                    // Validate longitude range (-180 to 180)
                    if ($lng < -180 || $lng > 180) {
                        $errors[] = 'Invalid longitude value';
                    }
                }
            }

            // Validate signature
            $signature = isset($data['signature']) ? trim($data['signature']) : '';
            if (empty($signature)) {
                $errors[] = 'Signature is required. Please sign the form';
            } elseif (!preg_match('/^data:image\/png;base64,/', $signature)) {
                $errors[] = 'Invalid signature format';
            }

            if (empty($errors)) {
                $sinistre = new Sinistre();
                $sinistre->setUtilisateur($this->getUser());
                $sinistre->setContrat($contrat);
                $sinistre->setDescription(trim($data['description']));
                $sinistre->setDateSinistre(new \DateTime($dateStr));
                $sinistre->setStatut('EN_ATTENTE');
                $sinistre->setLatitude((float)$latitude);
                $sinistre->setLongitude((float)$longitude);
                if (!empty($locationAddress)) {
                    $sinistre->setLocationAddress($locationAddress);
                }
                if (!empty($signature)) {
                    $sinistre->setSignature($signature);
                }

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

                $imageUploadCount = 0;
                // Handle file uploads
                if ($uploadedFiles) {
                    foreach ($uploadedFiles as $file) {
                        if ($file->getSize() > 0) {
                            $extension = strtolower((string) $file->getClientOriginalExtension());
                            if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                                $imageUploadCount++;
                            }
                            $this->handleFileUpload($file, $sinistre, $em, $analysisService, $orchestrator);
                        }
                    }
                }

                $this->addFlash('success', 'Claim filed successfully. Claim #' . $sinistre->getId());
                $this->addAnalysisFlash($sinistre, $em, $imageUploadCount);
                $notificationService->sendNewClaimNotificationToAdmins($sinistre);

                return $this->redirect($this->generateUrl('app_sinistre_show', ['id' => $sinistre->getId()]) . '#damage-analysis');
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

    #[Route('/constat/new', name: 'app_sinistre_constat_new')]
    public function constatNew(Request $request, EntityManagerInterface $em, ValidatorInterface $validator, DamageDetectionOrchestrator $orchestrator, LocalImageAnalysisService $analysisService, ClaimNotificationService $notificationService): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        $draft = $request->getSession()->get(self::CONSTAT_DRAFT_SESSION_KEY);
        if (!is_array($draft) || (($draft['user_id'] ?? null) !== $this->getUser()->getId())) {
            $this->addFlash('warning', 'Start from the claim form before filling a constat.');
            return $this->redirectToRoute('app_sinistre_new');
        }

        $contrat = $em->getRepository(ContratAssurance::class)->find((int) ($draft['contrat_id'] ?? 0));
        if (!$contrat || $contrat->getUtilisateur()?->getId() !== $this->getUser()->getId() || $contrat->getStatut() !== 'ACTIF') {
            $request->getSession()->remove(self::CONSTAT_DRAFT_SESSION_KEY);
            $this->addFlash('error', 'The selected auto insurance contract is no longer available.');
            return $this->redirectToRoute('app_sinistre_new');
        }

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $errors = [];
            $uploadedFiles = $request->files->get('preuves');
            $uploadedFiles = $request->files->get('preuves');
            $uploadedFiles = $request->files->get('preuves');

            if (trim((string) ($data['other_driver_name'] ?? '')) === '') {
                $errors[] = 'Other driver name is required for the constat.';
            }

            if (trim((string) ($data['other_vehicle_plate'] ?? '')) === '') {
                $errors[] = 'Other vehicle plate is required for the constat.';
            }

            if (trim((string) ($data['accident_circumstances'] ?? '')) === '') {
                $errors[] = 'Please describe the accident circumstances in the constat.';
            }

            if ($uploadedFiles) {
                $maxFiles = 5;
                $maxSize = 25 * 1024 * 1024;
                $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];

                if (count($uploadedFiles) > $maxFiles) {
                    $errors[] = "Maximum $maxFiles files allowed";
                }

                foreach ($uploadedFiles as $file) {
                    if ($file->getSize() === 0) {
                        continue;
                    }

                    if ($file->getSize() > $maxSize) {
                        $errors[] = 'File "' . htmlspecialchars($file->getClientOriginalName(), ENT_QUOTES) . '" exceeds 25MB limit';
                    }

                    if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
                        $errors[] = 'File type not allowed: ' . htmlspecialchars($file->getClientOriginalName(), ENT_QUOTES) . '. Only images, PDF, and Word docs are allowed.';
                    }
                }
            }

            if (empty($errors)) {
                $sinistre = new Sinistre();
                $sinistre->setUtilisateur($this->getUser());
                $sinistre->setContrat($contrat);
                $sinistre->setDescription($this->buildConstatDescription($draft, $data));
                $sinistre->setDateSinistre(new \DateTime((string) $draft['date_sinistre']));
                $sinistre->setStatut('EN_ATTENTE');
                $sinistre->setLatitude((float) $draft['latitude']);
                $sinistre->setLongitude((float) $draft['longitude']);

                if (!empty($draft['location_address'])) {
                    $sinistre->setLocationAddress($draft['location_address']);
                }

                if (!empty($draft['signature'])) {
                    $sinistre->setSignature($draft['signature']);
                }

                $validationErrors = $validator->validate($sinistre);
                if (count($validationErrors) > 0) {
                    foreach ($validationErrors as $validationError) {
                        $errors[] = $validationError->getPropertyPath() . ': ' . $validationError->getMessage();
                    }
                }
            }

            if (empty($errors)) {
                $em->persist($sinistre);
                $em->flush();

                $imageUploadCount = 0;
                if ($uploadedFiles) {
                    foreach ($uploadedFiles as $file) {
                        if ($file->getSize() > 0) {
                            $extension = strtolower((string) $file->getClientOriginalExtension());
                            if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                                $imageUploadCount++;
                            }
                            $this->handleFileUpload($file, $sinistre, $em, $analysisService, $orchestrator);
                        }
                    }
                }

                $request->getSession()->remove(self::CONSTAT_DRAFT_SESSION_KEY);
                $this->addFlash('success', 'Constat completed and auto claim filed successfully. Claim #' . $sinistre->getId());
                $this->addAnalysisFlash($sinistre, $em, $imageUploadCount);
                $notificationService->sendNewClaimNotificationToAdmins($sinistre);

                return $this->redirect($this->generateUrl('app_sinistre_show', ['id' => $sinistre->getId()]) . '#damage-analysis');
            }

            return $this->render('sinistre/constat_form.html.twig', [
                'title' => 'Auto Accident Constat',
                'draft' => $draft,
                'errors' => $errors,
                'form_data' => $data,
                'sinistre' => null,
                'isEditConstat' => false,
            ]);
        }

        return $this->render('sinistre/constat_form.html.twig', [
            'title' => 'Auto Accident Constat',
            'draft' => $draft,
            'errors' => [],
            'form_data' => [],
            'sinistre' => null,
            'isEditConstat' => false,
        ]);
    }

    #[Route('/{id}/constat', name: 'app_sinistre_constat_edit')]
    public function constatEdit(int $id, Request $request, EntityManagerInterface $em, ValidatorInterface $validator, DamageDetectionOrchestrator $orchestrator, LocalImageAnalysisService $analysisService): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        $sinistre = $em->getRepository(Sinistre::class)->find($id);
        if (!$sinistre) {
            throw $this->createNotFoundException('Claim not found');
        }

        $isOwner = (int) $this->getUser()->getId() === (int) $sinistre->getUtilisateur()?->getId();
        $isAdmin = strtolower($this->getUser()->getRole() ?? '') === 'admin';

        if (!$isOwner && !$isAdmin) {
            throw $this->createAccessDeniedException('You cannot access this constat.');
        }

        if (strtoupper((string) $sinistre->getContrat()?->getAssurance()?->getTypeAssurance()) !== 'AUTO') {
            $this->addFlash('warning', 'Constat is only available for auto claims.');
            return $this->redirectToRoute('app_sinistre_show', ['id' => $sinistre->getId()]);
        }

        $existingConstat = $this->extractConstatData($sinistre->getDescription());

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $errors = [];
            $uploadedFiles = $request->files->get('preuves');

            if (trim((string) ($data['other_driver_name'] ?? '')) === '') {
                $errors[] = 'Other driver name is required for the constat.';
            }

            if (trim((string) ($data['other_vehicle_plate'] ?? '')) === '') {
                $errors[] = 'Other vehicle plate is required for the constat.';
            }

            if (trim((string) ($data['accident_circumstances'] ?? '')) === '') {
                $errors[] = 'Please describe the accident circumstances in the constat.';
            }

            if ($uploadedFiles) {
                $maxFiles = 5;
                $maxSize = 25 * 1024 * 1024;
                $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];

                if (count($uploadedFiles) > $maxFiles) {
                    $errors[] = "Maximum $maxFiles files allowed";
                }

                foreach ($uploadedFiles as $file) {
                    if ($file->getSize() === 0) {
                        continue;
                    }

                    if ($file->getSize() > $maxSize) {
                        $errors[] = 'File "' . htmlspecialchars($file->getClientOriginalName(), ENT_QUOTES) . '" exceeds 25MB limit';
                    }

                    if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
                        $errors[] = 'File type not allowed: ' . htmlspecialchars($file->getClientOriginalName(), ENT_QUOTES) . '. Only images, PDF, and Word docs are allowed.';
                    }
                }
            }

            if (empty($errors)) {
                $sinistre->setDescription($this->mergeConstatIntoDescription($sinistre->getDescription(), $data));

                $validationErrors = $validator->validate($sinistre);
                if (count($validationErrors) > 0) {
                    foreach ($validationErrors as $validationError) {
                        $errors[] = $validationError->getPropertyPath() . ': ' . $validationError->getMessage();
                    }
                }
            }

            if (empty($errors)) {
                $imageUploadCount = 0;
                if ($uploadedFiles) {
                    foreach ($uploadedFiles as $file) {
                        if ($file->getSize() > 0) {
                            $extension = strtolower((string) $file->getClientOriginalExtension());
                            if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                                $imageUploadCount++;
                            }
                            $this->handleFileUpload($file, $sinistre, $em, $analysisService, $orchestrator);
                        }
                    }
                }

                $em->flush();
                $this->addFlash('success', 'Constat saved successfully for this auto claim.');
                if ($imageUploadCount > 0) {
                    $this->addAnalysisFlash($sinistre, $em, $imageUploadCount);
                }

                return $this->redirectToRoute('app_sinistre_show', ['id' => $sinistre->getId()]);
            }

            return $this->render('sinistre/constat_form.html.twig', [
                'title' => 'Auto Accident Constat',
                'draft' => null,
                'errors' => $errors,
                'form_data' => $data,
                'sinistre' => $sinistre,
                'isEditConstat' => true,
            ]);
        }

        return $this->render('sinistre/constat_form.html.twig', [
            'title' => 'Auto Accident Constat',
            'draft' => null,
            'errors' => [],
            'form_data' => $existingConstat,
            'sinistre' => $sinistre,
            'isEditConstat' => true,
        ]);
    }

    #[Route('/admin/new', name: 'app_sinistre_admin_new')]
    public function adminNew(Request $request, EntityManagerInterface $em, ValidatorInterface $validator, DamageDetectionOrchestrator $orchestrator, LocalImageAnalysisService $analysisService, ClaimContractCompatibilityService $claimCompatibilityService, ClaimNotificationService $notificationService): Response
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

            if (empty($errors) && isset($contrat)) {
                $compatibility = $claimCompatibilityService->evaluate(
                    $data['description'] ?? '',
                    $contrat->getAssurance()?->getTypeAssurance()
                );

                if (!$compatibility['is_compatible']) {
                    $message = $compatibility['message'] ?? 'This claim does not match the selected insurance contract.';
                    $errors[] = $message;
                    $this->addFlash('warning', $message);
                }
            }

            // Validate file uploads if any
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

            // Validate signature (optional for admin)
            $signature = isset($data['signature']) ? trim($data['signature']) : '';
            if (!empty($signature) && !preg_match('/^data:image\/png;base64,/', $signature)) {
                $errors[] = 'Invalid signature format';
            }

            if (empty($errors)) {
                $sinistre = new Sinistre();
                $sinistre->setUtilisateur($utilisateur);
                $sinistre->setContrat($contrat);
                $sinistre->setDescription(trim($data['description']));
                $sinistre->setDateSinistre(new \DateTime($dateStr));
                $sinistre->setStatut('EN_ATTENTE');
                if (!empty($signature)) {
                    $sinistre->setSignature($signature);
                }

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

                $imageUploadCount = 0;
                // Handle file uploads
                if ($uploadedFiles) {
                    foreach ($uploadedFiles as $file) {
                        if ($file->getSize() > 0) {
                            $extension = strtolower((string) $file->getClientOriginalExtension());
                            if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                                $imageUploadCount++;
                            }
                            $this->handleFileUpload($file, $sinistre, $em, $analysisService, $orchestrator);
                        }
                    }
                }

                $this->addFlash('success', 'Claim filed successfully for user. Claim #' . $sinistre->getId());
                $this->addAnalysisFlash($sinistre, $em, $imageUploadCount);
                $notificationService->sendNewClaimNotificationToAdmins($sinistre);

                return $this->redirect($this->generateUrl('app_sinistre_show', ['id' => $sinistre->getId()]) . '#damage-analysis');
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
    public function show(int $id, EntityManagerInterface $em, ClaimCoverageAnalysisService $coverageService): Response
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
        
        // Analyze coverage based on damage analysis
        $coverageAnalysis = $coverageService->analyzeCoverage($sinistre);

        return $this->render($template, [
            'sinistre' => $sinistre,
            'coverageAnalysis' => $coverageAnalysis,
            'title' => 'Claim Details',
            'isAdmin' => $isAdmin,
            'showConstatButton' => $this->shouldShowConstatButton($sinistre),
            'hasConstat' => $this->hasConstat($sinistre->getDescription()),
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

            // Validate and update location if provided
            if (isset($data['latitude']) && isset($data['longitude'])) {
                $latitude = trim($data['latitude']);
                $longitude = trim($data['longitude']);
                
                if (!empty($latitude) && !empty($longitude)) {
                    if (is_numeric($latitude) && is_numeric($longitude)) {
                        $lat = (float)$latitude;
                        $lng = (float)$longitude;
                        
                        // Validate latitude range
                        if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
                            $sinistre->setLatitude($lat);
                            $sinistre->setLongitude($lng);
                            
                            if (isset($data['location_address']) && !empty(trim($data['location_address']))) {
                                $sinistre->setLocationAddress(trim($data['location_address']));
                            }
                        } else {
                            $errors[] = 'Invalid location coordinates';
                        }
                    } else {
                        $errors[] = 'Invalid location coordinates format';
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

    private function handleFileUpload($file, Sinistre $sinistre, EntityManagerInterface $em, LocalImageAnalysisService $analysisService, DamageDetectionOrchestrator $orchestrator = null): void
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

        // Use new Damage Detection Orchestrator for image analysis
        $isImageFile = in_array(strtolower($fileExtension), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
        
        if ($isImageFile && $orchestrator) {
            try {
                // Use the new orchestrator for production-ready analysis
                $orchestrator->analyzeImage($preuve, $sinistre);
            } catch (\Exception $e) {
                // Log error but don't prevent file upload
                // Try fallback to local analysis
                try {
                    if ($analysisService->isConfigured()) {
                        $analysisService->analyzeDamage($preuve, $sinistre);
                    }
                } catch (\Exception $fallbackError) {
                    // Both methods failed, but file upload still succeeds
                }
            }
        }
    }

    private function addAnalysisFlash(Sinistre $sinistre, EntityManagerInterface $em, int $imageUploadCount): void
    {
        if ($imageUploadCount === 0) {
            $this->addFlash('info', 'No image evidence was uploaded, so no AI damage analysis was generated.');
            return;
        }

        $analysisCount = (int) $em->getRepository(\App\Entity\DamageAnalysis::class)->count([
            'sinistre' => $sinistre,
        ]);

        if ($analysisCount > 0) {
            $this->addFlash('success', sprintf(
                'AI analysis is ready for %d image%s. Scroll down to the Damage Analysis section.',
                $analysisCount,
                $analysisCount === 1 ? '' : 's'
            ));
            return;
        }

        $this->addFlash('warning', 'Image upload succeeded, but no AI analysis was generated for this claim yet.');
    }

    private function storeConstatDraft(Request $request, array $data, ?array $uploadedFiles): void
    {
        $request->getSession()->set(self::CONSTAT_DRAFT_SESSION_KEY, [
            'user_id' => $this->getUser()?->getId(),
            'contrat_id' => (int) ($data['contrat_id'] ?? 0),
            'date_sinistre' => (string) ($data['date_sinistre'] ?? ''),
            'description' => trim((string) ($data['description'] ?? '')),
            'location_address' => trim((string) ($data['location_address'] ?? '')),
            'latitude' => (float) ($data['latitude'] ?? 0),
            'longitude' => (float) ($data['longitude'] ?? 0),
            'signature' => (string) ($data['signature'] ?? ''),
            'had_uploaded_files' => is_array($uploadedFiles) && count($uploadedFiles) > 0,
        ]);

        if (is_array($uploadedFiles) && count($uploadedFiles) > 0) {
            $this->addFlash('warning', 'Please re-upload your evidence files on the constat form. Uploaded files cannot be carried across the redirect.');
        }
    }

    private function buildConstatDescription(array $draft, array $constatData): string
    {
        $lines = [
            trim((string) ($draft['description'] ?? '')),
            '',
            '--- AUTO ACCIDENT CONSTAT ---',
            'Other Driver: ' . trim((string) ($constatData['other_driver_name'] ?? '')),
            'Other Vehicle Plate: ' . trim((string) ($constatData['other_vehicle_plate'] ?? '')),
            'Circumstances: ' . trim((string) ($constatData['accident_circumstances'] ?? '')),
            'Police Report: ' . (!empty($constatData['police_report']) ? 'Yes' : 'No'),
            'Injuries Reported: ' . (!empty($constatData['injuries_reported']) ? 'Yes' : 'No'),
        ];

        $notes = trim((string) ($constatData['constat_notes'] ?? ''));
        if ($notes !== '') {
            $lines[] = 'Additional Notes: ' . $notes;
        }

        return implode("\n", $lines);
    }

    private function shouldShowConstatButton(Sinistre $sinistre): bool
    {
        return strtoupper((string) $sinistre->getContrat()?->getAssurance()?->getTypeAssurance()) === 'AUTO';
    }

    private function hasConstat(?string $description): bool
    {
        return str_contains((string) $description, '--- AUTO ACCIDENT CONSTAT ---');
    }

    private function extractConstatData(?string $description): array
    {
        $description = (string) $description;
        $marker = '--- AUTO ACCIDENT CONSTAT ---';
        if (!str_contains($description, $marker)) {
            return [];
        }

        $section = explode($marker, $description, 2)[1] ?? '';
        $lines = preg_split('/\r\n|\r|\n/', trim($section)) ?: [];
        $data = [];

        foreach ($lines as $line) {
            if (str_starts_with($line, 'Other Driver: ')) {
                $data['other_driver_name'] = substr($line, strlen('Other Driver: '));
            } elseif (str_starts_with($line, 'Other Vehicle Plate: ')) {
                $data['other_vehicle_plate'] = substr($line, strlen('Other Vehicle Plate: '));
            } elseif (str_starts_with($line, 'Circumstances: ')) {
                $data['accident_circumstances'] = substr($line, strlen('Circumstances: '));
            } elseif (str_starts_with($line, 'Police Report: ')) {
                $data['police_report'] = substr($line, strlen('Police Report: ')) === 'Yes';
            } elseif (str_starts_with($line, 'Injuries Reported: ')) {
                $data['injuries_reported'] = substr($line, strlen('Injuries Reported: ')) === 'Yes';
            } elseif (str_starts_with($line, 'Additional Notes: ')) {
                $data['constat_notes'] = substr($line, strlen('Additional Notes: '));
            }
        }

        return $data;
    }

    private function mergeConstatIntoDescription(?string $description, array $constatData): string
    {
        $description = (string) $description;
        $marker = "\n--- AUTO ACCIDENT CONSTAT ---";
        $baseDescription = str_contains($description, $marker)
            ? explode($marker, $description, 2)[0]
            : $description;

        return $this->buildConstatDescription([
            'description' => trim($baseDescription),
        ], $constatData);
    }
}
