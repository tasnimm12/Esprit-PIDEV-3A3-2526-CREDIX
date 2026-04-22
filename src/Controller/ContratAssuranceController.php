<?php

namespace App\Controller;

use App\Entity\ContratAssurance;
use App\Service\ContratAssuranceService;
use App\Service\ContractPdfService;
use App\Service\ContractValidationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/contract')]
class ContratAssuranceController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private ContractValidationService $validationService;

    public function __construct(EntityManagerInterface $entityManager, ContractValidationService $validationService)
    {
        $this->entityManager = $entityManager;
        $this->validationService = $validationService;
    }

    #[Route('/', name: 'app_contract_list')]
    public function list(Request $request, ContratAssuranceService $contractService): Response
    {
        // Check if user is authenticated
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        // Get filter parameters
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');
        $sort = $request->query->get('sort', '');
        
        // Use repository method for filtering
        /** @var \App\Repository\ContratAssuranceRepository $contractRepository */
        $contractRepository = $this->entityManager->getRepository(ContratAssurance::class);
        
        $contracts = $contractRepository->findFiltered(
            $search,
            $status,
            'id',
            'DESC',
            null,
            0,
            $this->getUser()
        );
        
        // Apply custom sorting if specified
        if ($sort) {
            usort($contracts, function($a, $b) use ($sort) {
                switch ($sort) {
                    case 'date_asc':
                        return ($a->getDateSignature() ?? new \DateTime(0)) <=> ($b->getDateSignature() ?? new \DateTime(0));
                    case 'date_desc':
                        return ($b->getDateSignature() ?? new \DateTime(0)) <=> ($a->getDateSignature() ?? new \DateTime(0));
                    case 'expiration_asc':
                        return ($a->getDateFinContrat() ?? new \DateTime('9999-12-31')) <=> ($b->getDateFinContrat() ?? new \DateTime('9999-12-31'));
                    case 'expiration_desc':
                        return ($b->getDateFinContrat() ?? new \DateTime('9999-12-31')) <=> ($a->getDateFinContrat() ?? new \DateTime('9999-12-31'));
                    default:
                        return 0;
                }
            });
        }

        return $this->render('contract/list.html.twig', [
            'contracts' => $contracts,
        ]);
    }

    #[Route('/new', name: 'app_contract_new')]
    public function new(Request $request, ContratAssuranceService $contractService): Response
    {
        // Check if user is authenticated
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        $errors = [];
        $fieldErrors = [];
        $formData = [];

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            
            // Store form data for re-population on error
            $formData = $data;
            
            // For clients, automatically set their user ID; admins can specify
            if ($this->getUser()->getRole() !== 'admin') {
                $data['utilisateur_id'] = $this->getUser()->getId();
            }
            
            // Validate using the validation service (pass isAdmin flag)
            $isAdmin = $this->getUser()->getRole() === 'admin';
            $errors = $this->validationService->validateContractCreation($data, $this->getUser(), $isAdmin);
            
            // Additional field-level validation
            $additionalErrors = $this->validationService->validateAllFields($data);
            $errors = array_merge($errors, $additionalErrors);
            
            // Map errors to specific fields
            $fieldErrors = $this->mapErrorsToFields($errors);
            
            if (empty($errors)) {
                // Get user and set up contract data
                $userId = (int)($data['utilisateur_id'] ?? $this->getUser()->getId());
                $utilisateur = $this->entityManager->getRepository(\App\Entity\Utilisateur::class)->find($userId);
                $assurance = $this->entityManager->getRepository(\App\Entity\Assurance::class)->find((int)$data['assurance_id']);
                
                // Find compatible bank account if needed
                if ($assurance && (float)($assurance->getPrixAssurance() ?? 0) > 0) {
                    $priceAssurance = (float)$assurance->getPrixAssurance();
                    $bankAccounts = $utilisateur->getComptesbanCaires();
                    
                    foreach ($bankAccounts as $compte) {
                        if ($compte->getActif() && (float)$compte->getSolde() >= $priceAssurance) {
                            $data['compte_bancaire_id'] = $compte->getId();
                            break;
                        }
                    }
                }
                
                // Save the contract
                $contract = $contractService->create($data);
                $this->addFlash('success', 'Contract created successfully');
                return $this->redirectToRoute('app_contract_list');
            }

            $assurances = $this->entityManager->getRepository(\App\Entity\Assurance::class)->findAll();
            $user = $this->getUser();
            $comptesBancaires = [];
            $users = [];
            
            // For admin, get all users and all bank accounts for selection
            if ($user->getRole() === 'admin') {
                $users = $this->entityManager->getRepository(\App\Entity\Utilisateur::class)->findAll();
                // Get all bank accounts from all users
                $usersWithAccounts = $this->entityManager->getRepository(\App\Entity\Utilisateur::class)->findAll();
                foreach ($usersWithAccounts as $u) {
                    foreach ($u->getComptesbanCaires() as $compte) {
                        $comptesBancaires[] = $compte;
                    }
                }
            } else {
                $comptesBancaires = $user ? $user->getComptesbanCaires() : [];
            }
            
            $template = $this->getUser()->getRole() === 'admin' ? 'admin/contract_form.html.twig' : 'contract/form_user.html.twig';
            return $this->render($template, [
                'contract' => null,
                'assurances' => $assurances,
                'comptesBancaires' => $comptesBancaires,
                'users' => $users,
                'title' => $this->getUser()->getRole() === 'admin' ? 'New Contract' : 'Subscribe to Insurance',
                'errors' => $errors,
                'fieldErrors' => $fieldErrors,
                'formData' => $formData,
            ]);
        }

        $assurances = $this->entityManager->getRepository(\App\Entity\Assurance::class)->findAll();
        $user = $this->getUser();
        $comptesBancaires = [];
        $users = [];
        
        // For admin, get all users and all bank accounts for selection
        if ($user->getRole() === 'admin') {
            $users = $this->entityManager->getRepository(\App\Entity\Utilisateur::class)->findAll();
            // Get all bank accounts from all users
            $usersWithAccounts = $this->entityManager->getRepository(\App\Entity\Utilisateur::class)->findAll();
            foreach ($usersWithAccounts as $u) {
                foreach ($u->getComptesbanCaires() as $compte) {
                    $comptesBancaires[] = $compte;
                }
            }
        } else {
            $comptesBancaires = $user ? $user->getComptesbanCaires() : [];
        }
        
        $template = $this->getUser()->getRole() === 'admin' ? 'admin/contract_form.html.twig' : 'contract/form_user.html.twig';
        return $this->render($template, [
            'contract' => null,
            'assurances' => $assurances,
            'comptesBancaires' => $comptesBancaires,
            'users' => $users,
            'title' => $this->getUser()->getRole() === 'admin' ? 'New Contract' : 'Subscribe to Insurance',
            'errors' => [],
            'fieldErrors' => [],
            'formData' => [],
        ]);
    }

    #[Route('/{id}/delete', name: 'app_contract_delete')]
    public function delete(int $id, ContratAssuranceService $contractService): Response
    {
        // Check if user is authenticated
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        // Check if user has admin role
        if ($this->getUser()->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Only administrators can delete contracts');
        }

        $contractService->deleteById($id);
        $this->addFlash('success', 'Contract deleted successfully');

        return $this->redirectToRoute('app_contract_list');
    }

    #[Route('/{id}', name: 'app_contract_show')]
    public function show(int $id, ContratAssuranceService $contractService): Response
    {
        $contract = $contractService->findById($id);
        if (!$contract) {
            throw $this->createNotFoundException('Contract not found');
        }

        return $this->render('contract/show.html.twig', [
            'contract' => $contract,
        ]);
    }

    #[Route('/{id}/accept', name: 'app_contract_accept')]
    public function accept(int $id): Response
    {
        try {
            // Check if user is authenticated
            if (!$this->getUser()) {
                return $this->redirectToRoute('app_login');
            }

            // Check if user has admin role
            if ($this->getUser()->getRole() !== 'admin') {
                throw $this->createAccessDeniedException('Only administrators can accept contracts');
            }

            $contractRepository = $this->entityManager->getRepository(ContratAssurance::class);
            $contract = $contractRepository->find($id);
            
            if (!$contract) {
                throw $this->createNotFoundException('Contract not found');
            }

            $contract->setStatut('ACCEPTED');
            $this->entityManager->persist($contract);
            $this->entityManager->flush();

            $this->addFlash('success', 'Contract accepted successfully');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error accepting contract: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_contracts');
    }

    #[Route('/{id}/deny', name: 'app_contract_deny')]
    public function deny(int $id): Response
    {
        try {
            // Check if user is authenticated
            if (!$this->getUser()) {
                return $this->redirectToRoute('app_login');
            }

            // Check if user has admin role
            if ($this->getUser()->getRole() !== 'admin') {
                throw $this->createAccessDeniedException('Only administrators can deny contracts');
            }

            $contractRepository = $this->entityManager->getRepository(ContratAssurance::class);
            $contract = $contractRepository->find($id);
            
            if (!$contract) {
                throw $this->createNotFoundException('Contract not found');
            }

            $contract->setStatut('RESILIE');
            $this->entityManager->persist($contract);
            $this->entityManager->flush();

            $this->addFlash('warning', 'Contract denied/terminated successfully');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error denying contract: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_contracts');
    }

    #[Route('/{id}/download-pdf', name: 'app_contract_download_pdf')]
    public function downloadPdf(int $id, ContratAssuranceService $contractService, ContractPdfService $pdfService): Response
    {
        // Check if user is authenticated
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        $contract = $contractService->findById($id);
        if (!$contract) {
            throw $this->createNotFoundException('Contract not found');
        }

        // Check if user is owner of the contract or is admin
        $isOwner = $this->getUser()->getId() === $contract->getUtilisateur()?->getId();
        $isAdmin = $this->getUser()->getRole() === 'admin';

        if (!$isOwner && !$isAdmin) {
            throw $this->createAccessDeniedException('You cannot download this contract');
        }

        // Check if contract is confirmed (ACTIF)
        if ($contract->getStatut() !== 'ACTIF') {
            $this->addFlash('warning', 'Only confirmed contracts can be downloaded. Current status: ' . $contract->getStatut());
            return $this->redirectToRoute('app_contract_show', ['id' => $contract->getId()]);
        }

        try {
            // Generate PDF
            $pdfContent = $pdfService->generatePdf($contract);
            $filename = $pdfService->getFilename($contract);

            // Return PDF response
            return new Response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);

        } catch (\Exception $e) {
            $this->addFlash('error', 'Error generating PDF: ' . $e->getMessage());
            return $this->redirectToRoute('app_contract_show', ['id' => $contract->getId()]);
        }
    }

    /**
     * Map error messages to specific fields for field-level error display
     */
    private function mapErrorsToFields(array $errors): array
    {
        $fieldErrors = [];
        
        foreach ($errors as $error) {
            $error_lower = strtolower($error);
            
            if (strpos($error_lower, 'insurance') !== false || strpos($error_lower, 'assurance') !== false || strpos($error_lower, 'policy') !== false) {
                $fieldErrors['assurance_id'] = $error;
            } elseif (strpos($error_lower, 'bank account') !== false || strpos($error_lower, 'compte') !== false) {
                $fieldErrors['compte_bancaire_id'] = $error;
            } elseif (strpos($error_lower, 'contract number') !== false || strpos($error_lower, 'numero') !== false) {
                $fieldErrors['numero_contrat'] = $error;
            } elseif (strpos($error_lower, 'signing date') !== false || strpos($error_lower, 'date_signature') !== false) {
                $fieldErrors['date_signature'] = $error;
            } elseif (strpos($error_lower, 'expiration date') !== false || strpos($error_lower, 'date_fin') !== false) {
                $fieldErrors['date_fin_contrat'] = $error;
            } elseif (strpos($error_lower, 'duration') !== false || strpos($error_lower, 'duree') !== false || strpos($error_lower, 'months') !== false) {
                $fieldErrors['duree_contrat'] = $error;
            } elseif (strpos($error_lower, 'annual maximum') !== false || strpos($error_lower, 'annual ceiling') !== false || strpos($error_lower, 'plafond') !== false) {
                $fieldErrors['plafond_annuel'] = $error;
            } elseif (strpos($error_lower, 'reimbursement rate') !== false || strpos($error_lower, 'taux') !== false) {
                $fieldErrors['taux_remboursement'] = $error;
            } elseif (strpos($error_lower, 'waiting period') !== false || strpos($error_lower, 'delai') !== false || strpos($error_lower, 'carence') !== false) {
                $fieldErrors['delai_carence'] = $error;
            } elseif (strpos($error_lower, 'status') !== false || strpos($error_lower, 'statut') !== false) {
                $fieldErrors['statut'] = $error;
            } elseif (strpos($error_lower, 'user') !== false || strpos($error_lower, 'utilisateur') !== false) {
                $fieldErrors['utilisateur_id'] = $error;
            }
        }
        
        return $fieldErrors;
    }
}
