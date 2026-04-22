<?php

namespace App\Controller;

use App\Entity\Assurance;
use App\Entity\ContratAssurance;
use App\Service\AssuranceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/assurance')]
class AssuranceController extends AbstractController
{
    #[Route('/', name: 'app_assurance_list')]
    public function list(Request $request, AssuranceService $assuranceService, EntityManagerInterface $em): Response
    {
        // Check if user is authenticated
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        // Get all assurances (admins see all, clients see all available to subscribe)
        $assurances = $assuranceService->findAll();
        
        // Get user's active contracts for claim filing
        $userActiveContracts = $em->getRepository(ContratAssurance::class)->findBy([
            'utilisateur' => $this->getUser(),
            'statut' => 'ACTIF'
        ]);
        $hasActiveContracts = count($userActiveContracts) > 0;
        
        // Get filter parameters
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');
        $sort = $request->query->get('sort', '');
        
        // Use repository method for filtering instead of in-memory filtering
        /** @var \App\Repository\AssuranceRepository $assuranceRepository */
        $assuranceRepository = $em->getRepository(Assurance::class);
        $assurances = $assuranceRepository->findFiltered($search, $status, 'date_debut', 'DESC');
        
        // Apply custom sorting if specified
        if ($sort) {
            usort($assurances, function($a, $b) use ($sort) {
                switch ($sort) {
                    case 'recent':
                        return ($b->getDateDebut() ?? new \DateTime(0)) <=> ($a->getDateDebut() ?? new \DateTime(0));
                    case 'oldest':
                        return ($a->getDateDebut() ?? new \DateTime(0)) <=> ($b->getDateDebut() ?? new \DateTime(0));
                    default:
                        return 0;
                }
            });
        }

        return $this->render('assurance/list.html.twig', [
            'assurances' => $assurances,
            'hasActiveContracts' => $hasActiveContracts,
        ]);
    }

    #[Route('/new', name: 'app_assurance_new')]
    public function new(Request $request, AssuranceService $assuranceService, ValidatorInterface $validator): Response
    {
        // Check if user is authenticated
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        // Check if user has admin role
        if ($this->getUser()->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Only administrators can create insurance policies');
        }

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $errors = [];
            
            // Helper function for text sanitization
            $sanitizeText = function($text) {
                return htmlspecialchars(trim($text), ENT_QUOTES, 'UTF-8');
            };
            
            // Validate type_assurance (Insurance Type)
            if (empty($data['type_assurance'])) {
                $errors[] = 'Insurance type is required';
            } else {
                $type = trim($data['type_assurance']);
                if (strlen($type) < 2 || strlen($type) > 100) {
                    $errors[] = 'Insurance type must be between 2 and 100 characters';
                } elseif (preg_match('/<[^>]*>/', $type)) {
                    $errors[] = 'Insurance type contains invalid HTML characters';
                }
                $data['type_assurance'] = $sanitizeText($type);
            }
            
            // Validate compagnie (Insurance Company)
            if (empty($data['compagnie'])) {
                $errors[] = 'Insurance company is required';
            } else {
                $compagnie = trim($data['compagnie']);
                if (strlen($compagnie) < 2 || strlen($compagnie) > 100) {
                    $errors[] = 'Insurance company name must be between 2 and 100 characters';
                } elseif (preg_match('/<[^>]*>/', $compagnie)) {
                    $errors[] = 'Insurance company contains invalid HTML characters';
                }
                $data['compagnie'] = $sanitizeText($compagnie);
            }
            
            // Validate numero_police (Policy Number)
            if (empty($data['numero_police'])) {
                $errors[] = 'Policy number is required';
            } else {
                $numeroPolice = trim($data['numero_police']);
                if (strlen($numeroPolice) < 3 || strlen($numeroPolice) > 50) {
                    $errors[] = 'Policy number must be between 3 and 50 characters';
                } elseif (!preg_match('/^[A-Za-z0-9\-\/_\.]+$/', $numeroPolice)) {
                    $errors[] = 'Policy number contains invalid characters. Only letters, numbers, and -_/. allowed';
                }
                $data['numero_police'] = $sanitizeText($numeroPolice);
            }
            
            // Validate montant_couverture (Coverage Amount)
            if (empty($data['montant_couverture'])) {
                $errors[] = 'Coverage amount is required';
            } else {
                $montant = (float)$data['montant_couverture'];
                if ($montant <= 0) {
                    $errors[] = 'Coverage amount must be greater than 0';
                } elseif ($montant > 999999999) {
                    $errors[] = 'Coverage amount cannot exceed 999,999,999';
                }
                $data['montant_couverture'] = $montant;
            }
            
            // Validate franchise (Deductible)
            if (!empty($data['franchise'])) {
                $franchise = (float)$data['franchise'];
                if ($franchise < 0) {
                    $errors[] = 'Deductible cannot be negative';
                } elseif ($franchise > 999999999) {
                    $errors[] = 'Deductible cannot exceed 999,999,999';
                }
                $data['franchise'] = $franchise;
            } else {
                $data['franchise'] = 0;
            }
            
            // Validate prime_annuelle (Annual Premium)
            if (!empty($data['prime_annuelle'])) {
                $primeAnnuelle = (float)$data['prime_annuelle'];
                if ($primeAnnuelle <= 0) {
                    $errors[] = 'Annual premium must be greater than 0';
                } elseif ($primeAnnuelle > 999999999) {
                    $errors[] = 'Annual premium cannot exceed 999,999,999';
                }
                $data['prime_annuelle'] = $primeAnnuelle;
            } else {
                $data['prime_annuelle'] = null;
            }
            
            // Validate prime_mensuelle (Monthly Premium)
            if (!empty($data['prime_mensuelle'])) {
                $primeMensuelle = (float)$data['prime_mensuelle'];
                if ($primeMensuelle <= 0) {
                    $errors[] = 'Monthly premium must be greater than 0';
                } elseif ($primeMensuelle > 999999999) {
                    $errors[] = 'Monthly premium cannot exceed 999,999,999';
                }
                $data['prime_mensuelle'] = $primeMensuelle;
            } else {
                $data['prime_mensuelle'] = null;
            }
            
            // Validate prix_assurance (Insurance Price)
            if (empty($data['prix_assurance'])) {
                $errors[] = 'Insurance price is required';
            } else {
                $prix = (float)$data['prix_assurance'];
                if ($prix <= 0) {
                    $errors[] = 'Insurance price must be greater than 0';
                } elseif ($prix > 999999999) {
                    $errors[] = 'Insurance price cannot exceed 999,999,999';
                }
                $data['prix_assurance'] = $prix;
            }
            
            // Validate description
            if (!empty($data['description'])) {
                $description = trim($data['description']);
                if (strlen($description) > 5000) {
                    $errors[] = 'Description cannot exceed 5000 characters';
                } elseif (strlen($description) < 10) {
                    $errors[] = 'Description must be at least 10 characters';
                }
                $data['description'] = $sanitizeText($description);
            }
            
            // Validate conditions
            if (!empty($data['conditions'])) {
                $conditions = trim($data['conditions']);
                if (strlen($conditions) > 5000) {
                    $errors[] = 'Conditions cannot exceed 5000 characters';
                }
                $data['conditions'] = $sanitizeText($conditions);
            }
            
            // Validate exclusions
            if (!empty($data['exclusions'])) {
                $exclusions = trim($data['exclusions']);
                if (strlen($exclusions) > 5000) {
                    $errors[] = 'Exclusions cannot exceed 5000 characters';
                }
                $data['exclusions'] = $sanitizeText($exclusions);
            }
            
            // Validate date_debut (Start Date)
            if (empty($data['date_debut'])) {
                $errors[] = 'Start date is required';
                $startDate = null;
            } else {
                try {
                    $startDate = new \DateTime($data['date_debut']);
                    // Check if start date is not in the past
                    if ($startDate < new \DateTime('today')) {
                        $errors[] = 'Start date cannot be in the past';
                    }
                } catch (\Exception $e) {
                    $errors[] = 'Invalid start date format (use YYYY-MM-DD)';
                    $startDate = null;
                }
            }
            
            // Validate date_echeance (Expiration Date)
            if (empty($data['date_echeance'])) {
                $errors[] = 'Expiration date is required';
                $expirationDate = null;
            } else {
                try {
                    $expirationDate = new \DateTime($data['date_echeance']);
                } catch (\Exception $e) {
                    $errors[] = 'Invalid expiration date format (use YYYY-MM-DD)';
                    $expirationDate = null;
                }
            }
            
            // Validate date range
            if (isset($startDate) && isset($expirationDate)) {
                if ($expirationDate <= $startDate) {
                    $errors[] = 'Expiration date must be after start date';
                }
                // Check if duration is reasonable (not more than 50 years)
                $diff = $startDate->diff($expirationDate);
                if ($diff->y > 50) {
                    $errors[] = 'Insurance period cannot exceed 50 years';
                }
            }
            
            // Validate mode_paiement (Payment Frequency)
            $validPaymentModes = ['MENSUEL', 'TRIMESTRIEL', 'SEMESTRIEL', 'ANNUEL'];
            if (empty($data['mode_paiement']) || !in_array($data['mode_paiement'], $validPaymentModes)) {
                $errors[] = 'Invalid payment frequency selected';
            }
            
            // Validate statut (Status)
            $validStatuses = ['ACTIF', 'INACTIF', 'SUSPENDU', 'RESILIE'];
            if (empty($data['statut']) || !in_array($data['statut'], $validStatuses)) {
                $errors[] = 'Invalid insurance status selected';
            }
            
            if (!empty($errors)) {
                return $this->render('admin/assurance_form.html.twig', [
                    'assurance' => null,
                    'title' => 'New Insurance Policy',
                    'errors' => $errors,
                    'form_data' => $data,
                ]);
            }
            
            // Add current user ID to data
            $data['utilisateur_id'] = $this->getUser()->getId();
            
            // Save
            $assurance = $assuranceService->create($data);
            $this->addFlash('success', 'Insurance policy created successfully');

            return $this->redirectToRoute('app_assurance_list');
        }

        return $this->render('admin/assurance_form.html.twig', [
            'assurance' => null,
            'title' => 'New Insurance Policy',
            'errors' => [],
            'form_data' => [],
        ]);
    }

    #[Route('/{id}/edit', name: 'app_assurance_edit')]
    public function edit(int $id, Request $request, AssuranceService $assuranceService, ValidatorInterface $validator): Response
    {
        // Check if user is authenticated
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        // Check if user has admin role
        if ($this->getUser()->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Only administrators can edit insurance policies');
        }

        $assurance = $assuranceService->findById($id);
        if (!$assurance) {
            throw $this->createNotFoundException('Insurance policy not found');
        }

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $errors = [];
            
            // Helper function for text sanitization
            $sanitizeText = function($text) {
                return htmlspecialchars(trim($text), ENT_QUOTES, 'UTF-8');
            };
            
            // Validate type_assurance (Insurance Type)
            if (empty($data['type_assurance'])) {
                $errors[] = 'Insurance type is required';
            } else {
                $type = trim($data['type_assurance']);
                if (strlen($type) < 2 || strlen($type) > 100) {
                    $errors[] = 'Insurance type must be between 2 and 100 characters';
                } elseif (preg_match('/<[^>]*>/', $type)) {
                    $errors[] = 'Insurance type contains invalid HTML characters';
                }
                $data['type_assurance'] = $sanitizeText($type);
            }
            
            // Validate compagnie (Insurance Company)
            if (empty($data['compagnie'])) {
                $errors[] = 'Insurance company is required';
            } else {
                $compagnie = trim($data['compagnie']);
                if (strlen($compagnie) < 2 || strlen($compagnie) > 100) {
                    $errors[] = 'Insurance company name must be between 2 and 100 characters';
                } elseif (preg_match('/<[^>]*>/', $compagnie)) {
                    $errors[] = 'Insurance company contains invalid HTML characters';
                }
                $data['compagnie'] = $sanitizeText($compagnie);
            }
            
            // Validate numero_police (Policy Number)
            if (empty($data['numero_police'])) {
                $errors[] = 'Policy number is required';
            } else {
                $numeroPolice = trim($data['numero_police']);
                if (strlen($numeroPolice) < 3 || strlen($numeroPolice) > 50) {
                    $errors[] = 'Policy number must be between 3 and 50 characters';
                } elseif (!preg_match('/^[A-Za-z0-9\-\/_\.]+$/', $numeroPolice)) {
                    $errors[] = 'Policy number contains invalid characters. Only letters, numbers, and -_/. allowed';
                }
                $data['numero_police'] = $sanitizeText($numeroPolice);
            }
            
            // Validate montant_couverture (Coverage Amount)
            if (empty($data['montant_couverture'])) {
                $errors[] = 'Coverage amount is required';
            } else {
                $montant = (float)$data['montant_couverture'];
                if ($montant <= 0) {
                    $errors[] = 'Coverage amount must be greater than 0';
                } elseif ($montant > 999999999) {
                    $errors[] = 'Coverage amount cannot exceed 999,999,999';
                }
                $data['montant_couverture'] = $montant;
            }
            
            // Validate franchise (Deductible)
            if (!empty($data['franchise'])) {
                $franchise = (float)$data['franchise'];
                if ($franchise < 0) {
                    $errors[] = 'Deductible cannot be negative';
                } elseif ($franchise > 999999999) {
                    $errors[] = 'Deductible cannot exceed 999,999,999';
                }
                $data['franchise'] = $franchise;
            } else {
                $data['franchise'] = 0;
            }
            
            // Validate prime_annuelle (Annual Premium)
            if (!empty($data['prime_annuelle'])) {
                $primeAnnuelle = (float)$data['prime_annuelle'];
                if ($primeAnnuelle <= 0) {
                    $errors[] = 'Annual premium must be greater than 0';
                } elseif ($primeAnnuelle > 999999999) {
                    $errors[] = 'Annual premium cannot exceed 999,999,999';
                }
                $data['prime_annuelle'] = $primeAnnuelle;
            } else {
                $data['prime_annuelle'] = null;
            }
            
            // Validate prime_mensuelle (Monthly Premium)
            if (!empty($data['prime_mensuelle'])) {
                $primeMensuelle = (float)$data['prime_mensuelle'];
                if ($primeMensuelle <= 0) {
                    $errors[] = 'Monthly premium must be greater than 0';
                } elseif ($primeMensuelle > 999999999) {
                    $errors[] = 'Monthly premium cannot exceed 999,999,999';
                }
                $data['prime_mensuelle'] = $primeMensuelle;
            } else {
                $data['prime_mensuelle'] = null;
            }
            
            // Validate prix_assurance (Insurance Price)
            if (empty($data['prix_assurance'])) {
                $errors[] = 'Insurance price is required';
            } else {
                $prix = (float)$data['prix_assurance'];
                if ($prix <= 0) {
                    $errors[] = 'Insurance price must be greater than 0';
                } elseif ($prix > 999999999) {
                    $errors[] = 'Insurance price cannot exceed 999,999,999';
                }
                $data['prix_assurance'] = $prix;
            }
            
            // Validate description
            if (!empty($data['description'])) {
                $description = trim($data['description']);
                if (strlen($description) > 5000) {
                    $errors[] = 'Description cannot exceed 5000 characters';
                } elseif (strlen($description) < 10) {
                    $errors[] = 'Description must be at least 10 characters';
                }
                $data['description'] = $sanitizeText($description);
            }
            
            // Validate conditions
            if (!empty($data['conditions'])) {
                $conditions = trim($data['conditions']);
                if (strlen($conditions) > 5000) {
                    $errors[] = 'Conditions cannot exceed 5000 characters';
                }
                $data['conditions'] = $sanitizeText($conditions);
            }
            
            // Validate exclusions
            if (!empty($data['exclusions'])) {
                $exclusions = trim($data['exclusions']);
                if (strlen($exclusions) > 5000) {
                    $errors[] = 'Exclusions cannot exceed 5000 characters';
                }
                $data['exclusions'] = $sanitizeText($exclusions);
            }
            
            // Validate date_debut (Start Date)
            if (empty($data['date_debut'])) {
                $errors[] = 'Start date is required';
                $startDate = null;
            } else {
                try {
                    $startDate = new \DateTime($data['date_debut']);
                } catch (\Exception $e) {
                    $errors[] = 'Invalid start date format (use YYYY-MM-DD)';
                    $startDate = null;
                }
            }
            
            // Validate date_echeance (Expiration Date)
            if (empty($data['date_echeance'])) {
                $errors[] = 'Expiration date is required';
                $expirationDate = null;
            } else {
                try {
                    $expirationDate = new \DateTime($data['date_echeance']);
                } catch (\Exception $e) {
                    $errors[] = 'Invalid expiration date format (use YYYY-MM-DD)';
                    $expirationDate = null;
                }
            }
            
            // Validate date range
            if (isset($startDate) && isset($expirationDate)) {
                if ($expirationDate <= $startDate) {
                    $errors[] = 'Expiration date must be after start date';
                }
                // Check if duration is reasonable (not more than 50 years)
                $diff = $startDate->diff($expirationDate);
                if ($diff->y > 50) {
                    $errors[] = 'Insurance period cannot exceed 50 years';
                }
            }
            
            // Validate mode_paiement (Payment Frequency)
            $validPaymentModes = ['MENSUEL', 'TRIMESTRIEL', 'SEMESTRIEL', 'ANNUEL'];
            if (empty($data['mode_paiement']) || !in_array($data['mode_paiement'], $validPaymentModes)) {
                $errors[] = 'Invalid payment frequency selected';
            }
            
            // Validate statut (Status)
            $validStatuses = ['ACTIF', 'INACTIF', 'SUSPENDU', 'RESILIE'];
            if (empty($data['statut']) || !in_array($data['statut'], $validStatuses)) {
                $errors[] = 'Invalid insurance status selected';
            }
            
            if (!empty($errors)) {
                return $this->render('admin/assurance_form.html.twig', [
                    'assurance' => $assurance,
                    'title' => 'Edit Insurance Policy',
                    'errors' => $errors,
                    'form_data' => $data,
                ]);
            }
            
            // Update with validated data
            $assurance->setTypeAssurance($data['type_assurance']);
            $assurance->setCompagnie($data['compagnie']);
            $assurance->setNumeroPolice($data['numero_police']);
            $assurance->setMontantCouverture($data['montant_couverture']);
            $assurance->setFranchise($data['franchise']);
            $assurance->setPrimeAnnuelle($data['prime_annuelle']);
            $assurance->setPrimeMensuelle($data['prime_mensuelle']);
            $assurance->setPrixAssurance($data['prix_assurance']);
            $assurance->setDateDebut($startDate);
            $assurance->setDateEcheance($expirationDate);
            $assurance->setModePaiement($data['mode_paiement']);
            $assurance->setStatut($data['statut']);
            $assurance->setRenouvellementAuto(!empty($data['renouvellement_auto']));
            
            if (!empty($data['description'])) {
                $assurance->setDescription($data['description']);
            }
            if (!empty($data['conditions'])) {
                $assurance->setConditions($data['conditions']);
            }
            if (!empty($data['exclusions'])) {
                $assurance->setExclusions($data['exclusions']);
            }
            
            $assuranceService->update($assurance, $data);
            $this->addFlash('success', 'Insurance policy updated successfully');

            return $this->redirectToRoute('app_assurance_list');
        }

        return $this->render('admin/assurance_form.html.twig', [
            'assurance' => $assurance,
            'title' => 'Edit Insurance Policy',
            'errors' => [],
            'form_data' => [],
        ]);
    }

    #[Route('/{id}/delete', name: 'app_assurance_delete')]
    public function delete(int $id, AssuranceService $assuranceService): Response
    {
        // Check if user is authenticated
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        // Check if user has admin role
        if ($this->getUser()->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Only administrators can delete insurance policies');
        }

        $assuranceService->deleteById($id);
        $this->addFlash('success', 'Insurance policy deleted successfully');

        return $this->redirectToRoute('app_assurance_list');
    }

    #[Route('/{id}', name: 'app_assurance_show')]
    public function show(int $id, AssuranceService $assuranceService): Response
    {
        $assurance = $assuranceService->findById($id);
        if (!$assurance) {
            throw $this->createNotFoundException('Insurance policy not found');
        }

        return $this->render('assurance/show.html.twig', [
            'assurance' => $assurance,
        ]);
    }
}
