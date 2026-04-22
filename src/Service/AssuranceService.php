<?php

namespace App\Service;

use App\Entity\Assurance;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Repository\RepositoryFactory;

class AssuranceService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Create a new Assurance
     */
    public function create(array $data): Assurance
    {
        $assurance = new Assurance();
        
        // Utilisateur is required
        if (!isset($data['utilisateur_id'])) {
            throw new \InvalidArgumentException('utilisateur_id is required to create an Assurance');
        }
        
        $utilisateur = $this->entityManager->getRepository(\App\Entity\Utilisateur::class)->find($data['utilisateur_id']);
        if (!$utilisateur) {
            throw new \InvalidArgumentException('Utilisateur not found with id: ' . $data['utilisateur_id']);
        }
        $assurance->setUtilisateur($utilisateur);
        
        if (isset($data['type_assurance'])) {
            $assurance->setTypeAssurance($data['type_assurance']);
        }
        if (isset($data['compagnie'])) {
            $assurance->setCompagnie($data['compagnie']);
        }
        if (isset($data['numero_police'])) {
            $assurance->setNumeroPolice($data['numero_police']);
        }
        if (isset($data['montant_couverture'])) {
            $assurance->setMontantCouverture($data['montant_couverture']);
        }
        if (isset($data['franchise'])) {
            $assurance->setFranchise($data['franchise']);
        }
        if (isset($data['prime_annuelle'])) {
            $assurance->setPrimeAnnuelle($data['prime_annuelle']);
        }
        if (isset($data['prime_mensuelle'])) {
            $assurance->setPrimeMensuelle($data['prime_mensuelle']);
        }
        if (isset($data['prix_assurance'])) {
            $assurance->setPrixAssurance($data['prix_assurance']);
        }
        if (isset($data['date_debut'])) {
            if (is_string($data['date_debut'])) {
                $assurance->setDateDebut(new \DateTime($data['date_debut']));
            } else {
                $assurance->setDateDebut($data['date_debut']);
            }
        }
        if (isset($data['date_echeance'])) {
            if (is_string($data['date_echeance'])) {
                $assurance->setDateEcheance(new \DateTime($data['date_echeance']));
            } else {
                $assurance->setDateEcheance($data['date_echeance']);
            }
        }
        if (isset($data['mode_paiement'])) {
            $assurance->setModePaiement($data['mode_paiement']);
        }
        if (isset($data['statut'])) {
            $assurance->setStatut($data['statut']);
        }
        if (isset($data['renouvellement_auto'])) {
            $assurance->setRenouvellementAuto($data['renouvellement_auto']);
        }
        if (isset($data['garanties_incluses']) && !empty($data['garanties_incluses'])) {
            // Handle garanties_incluses as JSON
            $garanties = $data['garanties_incluses'];
            if (is_string($garanties)) {
                // Try to parse as JSON
                @json_decode($garanties);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    // Not valid JSON, wrap it in JSON array
                    $garanties = json_encode([$garanties]);
                }
            } else {
                // Convert to JSON
                $garanties = json_encode($garanties);
            }
            $assurance->setGarantiesIncluses($garanties);
        } else {
            $assurance->setGarantiesIncluses(null);
        }

        $this->entityManager->persist($assurance);
        $this->entityManager->flush();

        return $assurance;
    }

    /**
     * Find Assurance by ID
     */
    public function findById(int $id): ?Assurance
    {
        return $this->entityManager->getRepository(Assurance::class)->find($id);
    }

    /**
     * Find all Assurances
     */
    public function findAll(): array
    {
        return $this->entityManager->getRepository(Assurance::class)->findAll();
    }

    /**
     * Find by user ID
     */
    public function findByUserId(int $userId): array
    {
        $utilisateur = $this->entityManager->getRepository(\App\Entity\Utilisateur::class)->find($userId);
        if (!$utilisateur) {
            return [];
        }
        return $this->entityManager->getRepository(Assurance::class)->findBy(['utilisateur' => $utilisateur]);
    }

    /**
     * Find by type
     */
    public function findByType(string $type): array
    {
        return $this->entityManager->getRepository(Assurance::class)->findBy(['type_assurance' => $type]);
    }

    /**
     * Find active assurances
     */
    public function findActive(): array
    {
        return $this->entityManager->getRepository(Assurance::class)->findBy(['statut' => 'ACTIF']);
    }

    /**
     * Update Assurance
     */
    public function update(Assurance $assurance, array $data): Assurance
    {
        if (isset($data['utilisateur_id'])) {
            $utilisateur = $this->entityManager->getRepository(\App\Entity\Utilisateur::class)->find($data['utilisateur_id']);
            if ($utilisateur) {
                $assurance->setUtilisateur($utilisateur);
            }
        }
        if (isset($data['type_assurance'])) {
            $assurance->setTypeAssurance($data['type_assurance']);
        }
        if (isset($data['compagnie'])) {
            $assurance->setCompagnie($data['compagnie']);
        }
        if (isset($data['numero_police'])) {
            $assurance->setNumeroPolice($data['numero_police']);
        }
        if (isset($data['montant_couverture'])) {
            $assurance->setMontantCouverture($data['montant_couverture']);
        }
        if (isset($data['franchise'])) {
            $assurance->setFranchise($data['franchise']);
        }
        if (isset($data['prime_annuelle'])) {
            $assurance->setPrimeAnnuelle($data['prime_annuelle']);
        }
        if (isset($data['prime_mensuelle'])) {
            $assurance->setPrimeMensuelle($data['prime_mensuelle']);
        }
        if (isset($data['prix_assurance'])) {
            $assurance->setPrixAssurance($data['prix_assurance']);
        }
        if (isset($data['date_debut'])) {
            if (is_string($data['date_debut'])) {
                $assurance->setDateDebut(new \DateTime($data['date_debut']));
            } else {
                $assurance->setDateDebut($data['date_debut']);
            }
        }
        if (isset($data['date_echeance'])) {
            if (is_string($data['date_echeance'])) {
                $assurance->setDateEcheance(new \DateTime($data['date_echeance']));
            } else {
                $assurance->setDateEcheance($data['date_echeance']);
            }
        }
        if (isset($data['mode_paiement'])) {
            $assurance->setModePaiement($data['mode_paiement']);
        }
        if (isset($data['statut'])) {
            $assurance->setStatut($data['statut']);
        }
        if (isset($data['renouvellement_auto'])) {
            $assurance->setRenouvellementAuto($data['renouvellement_auto']);
        }
        if (isset($data['garanties_incluses']) && !empty($data['garanties_incluses'])) {
            // Handle garanties_incluses as JSON
            $garanties = $data['garanties_incluses'];
            if (is_string($garanties)) {
                // Try to parse as JSON
                @json_decode($garanties);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    // Not valid JSON, wrap it in JSON array
                    $garanties = json_encode([$garanties]);
                }
            } else {
                // Convert to JSON
                $garanties = json_encode($garanties);
            }
            $assurance->setGarantiesIncluses($garanties);
        }

        $this->entityManager->flush();

        return $assurance;
    }

    /**
     * Delete Assurance
     */
    public function delete(Assurance $assurance): void
    {
        $this->entityManager->remove($assurance);
        $this->entityManager->flush();
    }

    /**
     * Delete by ID
     */
    public function deleteById(int $id): bool
    {
        $assurance = $this->findById($id);
        if ($assurance) {
            $this->delete($assurance);
            return true;
        }
        return false;
    }
}
