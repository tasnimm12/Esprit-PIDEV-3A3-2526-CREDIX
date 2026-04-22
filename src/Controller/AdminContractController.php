<?php

namespace App\Controller;

use App\Entity\ContratAssurance;
use App\Entity\Utilisateur;
use App\Entity\Assurance;
use App\Entity\CompteBancaire;
use App\Service\ContratAssuranceService;
use App\Service\ContractValidationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[Route('/admin/contracts')]
class AdminContractController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private ContractValidationService $validationService;

    public function __construct(EntityManagerInterface $entityManager, ContractValidationService $validationService)
    {
        $this->entityManager = $entityManager;
        $this->validationService = $validationService;
    }

    #[Route('/', name: 'app_admin_contracts')]
    public function list(): Response
    {
        // Check if user is authenticated and is admin
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        if ($this->getUser()->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Only administrators can view contracts');
        }

        $contracts = $this->entityManager->getRepository(ContratAssurance::class)->findAll();
        
        return $this->render('admin/contracts_list.html.twig', [
            'contracts' => $contracts,
        ]);
    }

    #[Route('/new', name: 'app_admin_contract_new')]
    public function new(Request $request, ContratAssuranceService $contractService): Response
    {
        // Check if user is authenticated and is admin
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        if ($this->getUser()->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Only administrators can create contracts');
        }

        $errors = [];
        $fieldErrors = [];
        $formData = [];

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            
            // Store form data for re-population on error
            $formData = $data;
            
            // Validate using the validation service (pass isAdmin flag)
            $isAdmin = true;
            $errors = $this->validationService->validateContractCreation($data, $this->getUser(), $isAdmin);
            
            // Additional field-level validation
            $additionalErrors = $this->validationService->validateAllFields($data);
            $errors = array_merge($errors, $additionalErrors);
            
            // Map errors to specific fields
            $fieldErrors = $this->mapErrorsToFields($errors);
            
            if (empty($errors)) {
                // Get user and set up contract data
                $userId = (int)($data['utilisateur_id'] ?? $this->getUser()->getId());
                $utilisateur = $this->entityManager->getRepository(Utilisateur::class)->find($userId);
                $assurance = $this->entityManager->getRepository(Assurance::class)->find((int)$data['assurance_id']);
                
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
                return $this->redirectToRoute('app_admin_contracts');
            }

            $assurances = $this->entityManager->getRepository(Assurance::class)->findAll();
            $users = $this->entityManager->getRepository(Utilisateur::class)->findAll();
            
            // Get all bank accounts from all users
            $comptesBancaires = [];
            foreach ($users as $u) {
                foreach ($u->getComptesbanCaires() as $compte) {
                    $comptesBancaires[] = $compte;
                }
            }
            
            return $this->render('admin/contract_form.html.twig', [
                'contract' => null,
                'assurances' => $assurances,
                'comptesBancaires' => $comptesBancaires,
                'users' => $users,
                'title' => 'New Contract',
                'errors' => $errors,
                'fieldErrors' => $fieldErrors,
                'formData' => $formData,
            ]);
        }

        $assurances = $this->entityManager->getRepository(Assurance::class)->findAll();
        $users = $this->entityManager->getRepository(Utilisateur::class)->findAll();
        
        // Get all bank accounts from all users
        $comptesBancaires = [];
        foreach ($users as $u) {
            foreach ($u->getComptesbanCaires() as $compte) {
                $comptesBancaires[] = $compte;
            }
        }
        
        return $this->render('admin/contract_form.html.twig', [
            'contract' => null,
            'assurances' => $assurances,
            'comptesBancaires' => $comptesBancaires,
            'users' => $users,
            'title' => 'New Contract',
            'errors' => [],
            'fieldErrors' => [],
            'formData' => [],
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_contract_edit')]
    public function edit(int $id, Request $request, ContratAssuranceService $contractService, EntityManagerInterface $entityManager): Response
    {
        // Admin-only access control
        if ($this->getUser()->getRole() !== 'admin') {
            throw new AccessDeniedException('Only administrators can edit contracts.');
        }

        // Create necessary repositories
        $contractRepo = $entityManager->getRepository(ContratAssurance::class);
        $assuranceRepo = $entityManager->getRepository(Assurance::class);
        $userRepo = $entityManager->getRepository(Utilisateur::class);
        $accountRepo = $entityManager->getRepository(CompteBancaire::class);

        // Fetch the contract
        $contract = $contractRepo->find($id);
        if (!$contract) {
            $this->addFlash('danger', 'Contract not found.');
            return $this->redirectToRoute('app_admin_contracts');
        }

        // Fetch necessary data for form dropdowns
        $assurances = $assuranceRepo->findAll();
        $users = $userRepo->findAll();
        $comptesBancaires = [];
        foreach ($users as $user) {
            $accounts = $accountRepo->findBy(['utilisateur' => $user]);
            if (count($accounts) > 0) {
                foreach ($accounts as $compte) {
                    $comptesBancaires[] = $compte;
                }
            }
        }

        if ($request->isMethod('POST')) {
            $data = [
                'statut' => $request->request->get('statut'),
                'assurance_id' => $request->request->get('assurance_id'),
                'compte_bancaire_id' => $request->request->get('compte_bancaire_id'),
                'numero_contrat' => $request->request->get('numero_contrat'),
                'date_signature' => $request->request->get('date_signature'),
                'date_fin_contrat' => $request->request->get('date_fin_contrat'),
                'duree_contrat' => $request->request->get('duree_contrat'),
                'plafond_annuel' => $request->request->get('plafond_annuel'),
                'taux_remboursement' => $request->request->get('taux_remboursement'),
                'delai_carence' => $request->request->get('delai_carence'),
            ];

            // Validate contract edit
            $errors = $this->validationService->validateContractEdit($data);

            if (empty($errors)) {
                try {
                    $assurance = $assuranceRepo->find($data['assurance_id']);
                    $compte = $accountRepo->find($data['compte_bancaire_id']);

                    $contract->setAssurance($assurance);
                    $contract->setCompteBancaire($compte);
                    $contract->setStatut($data['statut']);
                    $contract->setNumeroContrat($data['numero_contrat']);
                    $contract->setDateSignature(new \DateTime($data['date_signature']));
                    $contract->setDateFinContrat(new \DateTime($data['date_fin_contrat']));
                    $contract->setDureeContrat((int)$data['duree_contrat']);
                    $contract->setPlafondAnnuel((float)$data['plafond_annuel']);
                    $contract->setTauxRemboursement((float)$data['taux_remboursement']);
                    if (!empty($data['delai_carence'])) {
                        $contract->setDelaiCarence((int)$data['delai_carence']);
                    }

                    $entityManager->persist($contract);
                    $entityManager->flush();

                    $this->addFlash('success', 'Contract updated successfully.');
                    return $this->redirectToRoute('app_admin_contracts');
                } catch (\Exception $e) {
                    $this->addFlash('danger', 'An error occurred while updating the contract: ' . $e->getMessage());
                    // Re-render form with the submitted data
                    $fieldErrors = [];
                    return $this->render('admin/contract_form.html.twig', [
                        'contract' => $contract,
                        'assurances' => $assurances,
                        'comptesBancaires' => $comptesBancaires,
                        'users' => $users,
                        'title' => 'Edit Contract',
                        'errors' => ['An error occurred while saving the contract'],
                        'fieldErrors' => $fieldErrors,
                        'formData' => $data,
                    ]);
                }
            } else {
                // Map errors to form fields for display
                $fieldErrors = $this->mapErrorsToFields($errors);
                
                return $this->render('admin/contract_form.html.twig', [
                    'contract' => $contract,
                    'assurances' => $assurances,
                    'comptesBancaires' => $comptesBancaires,
                    'users' => $users,
                    'title' => 'Edit Contract',
                    'errors' => $errors,
                    'fieldErrors' => $fieldErrors,
                    'formData' => $data,
                ]);
            }
        }

        return $this->render('admin/contract_form.html.twig', [
            'contract' => $contract,
            'assurances' => $assurances,
            'comptesBancaires' => $comptesBancaires,
            'users' => $users,
            'title' => 'Edit Contract',
            'errors' => [],
            'fieldErrors' => [],
            'formData' => [
                'statut' => $contract->getStatut(),
                'assurance_id' => $contract->getAssurance()?->getId(),
                'compte_bancaire_id' => $contract->getCompteBancaire()?->getId(),
                'numero_contrat' => $contract->getNumeroContrat(),
                'date_signature' => $contract->getDateSignature()?->format('Y-m-d'),
                'date_fin_contrat' => $contract->getDateFinContrat()?->format('Y-m-d'),
                'duree_contrat' => $contract->getDureeContrat(),
                'plafond_annuel' => $contract->getPlafondAnnuel(),
                'taux_remboursement' => $contract->getTauxRemboursement(),
                'delai_carence' => $contract->getDelaiCarence(),
            ],
        ]);
    }

    private function mapErrorsToFields(array $errors): array
    {
        $fieldErrors = [];
        
        foreach ($errors as $error) {
            $error_lower = strtolower($error);
            
            // Status field
            if (strpos($error_lower, 'status') !== false || strpos($error_lower, 'statut') !== false) {
                $fieldErrors['statut'] = $error;
            }
            // Insurance field
            elseif (strpos($error_lower, 'insurance') !== false || strpos($error_lower, 'assurance') !== false || strpos($error_lower, 'policy') !== false) {
                $fieldErrors['assurance_id'] = $error;
            }
            // Bank account field
            elseif (strpos($error_lower, 'bank account') !== false || strpos($error_lower, 'compte') !== false) {
                $fieldErrors['compte_bancaire_id'] = $error;
            }
            // Contract number field
            elseif (strpos($error_lower, 'contract number') !== false || strpos($error_lower, 'numero') !== false) {
                $fieldErrors['numero_contrat'] = $error;
            }
            // Signing date field
            elseif (strpos($error_lower, 'signing date') !== false || strpos($error_lower, 'date_signature') !== false) {
                $fieldErrors['date_signature'] = $error;
            }
            // Expiration date field
            elseif (strpos($error_lower, 'expiration date') !== false || strpos($error_lower, 'date_fin') !== false) {
                $fieldErrors['date_fin_contrat'] = $error;
            }
            // Duration field
            elseif (strpos($error_lower, 'duration') !== false || strpos($error_lower, 'duree') !== false || strpos($error_lower, 'months') !== false) {
                $fieldErrors['duree_contrat'] = $error;
            }
            // Annual maximum/Ceiling field
            elseif (strpos($error_lower, 'annual maximum') !== false || strpos($error_lower, 'annual ceiling') !== false || strpos($error_lower, 'plafond') !== false) {
                $fieldErrors['plafond_annuel'] = $error;
            }
            // Reimbursement rate field
            elseif (strpos($error_lower, 'reimbursement rate') !== false || strpos($error_lower, 'taux') !== false) {
                $fieldErrors['taux_remboursement'] = $error;
            }
            // Waiting period field
            elseif (strpos($error_lower, 'waiting period') !== false || strpos($error_lower, 'delai') !== false || strpos($error_lower, 'carence') !== false) {
                $fieldErrors['delai_carence'] = $error;
            }
            // User/Utilisateur field
            elseif (strpos($error_lower, 'user') !== false || strpos($error_lower, 'utilisateur') !== false) {
                $fieldErrors['utilisateur_id'] = $error;
            }
        }
        
        return $fieldErrors;
    }
}
