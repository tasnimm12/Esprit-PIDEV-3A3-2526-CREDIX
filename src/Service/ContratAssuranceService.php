<?php

namespace App\Service;

use App\Entity\ContratAssurance;
use Doctrine\ORM\EntityManagerInterface;

class ContratAssuranceService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Create a new Contrat Assurance
     */
    public function create(array $data): ContratAssurance
    {
        $contrat = new ContratAssurance();

        // Both assurance and utilisateur are required
        if (!isset($data['assurance_id'])) {
            throw new \InvalidArgumentException('assurance_id is required to create a ContratAssurance');
        }
        if (!isset($data['utilisateur_id'])) {
            throw new \InvalidArgumentException('utilisateur_id is required to create a ContratAssurance');
        }

        $assurance = $this->entityManager->getRepository(\App\Entity\Assurance::class)->find($data['assurance_id']);
        if (!$assurance) {
            throw new \InvalidArgumentException('Assurance not found with id: ' . $data['assurance_id']);
        }
        $contrat->setAssurance($assurance);
        
        $utilisateur = $this->entityManager->getRepository(\App\Entity\Utilisateur::class)->find($data['utilisateur_id']);
        if (!$utilisateur) {
            throw new \InvalidArgumentException('Utilisateur not found with id: ' . $data['utilisateur_id']);
        }
        $contrat->setUtilisateur($utilisateur);
        
        // Set bank account if provided
        if (isset($data['compte_bancaire_id']) && !empty($data['compte_bancaire_id'])) {
            $compteBancaire = $this->entityManager->getRepository(\App\Entity\CompteBancaire::class)->find((int)$data['compte_bancaire_id']);
            if ($compteBancaire) {
                $contrat->setCompteBancaire($compteBancaire);
            }
        }
        
        // Generate or set numero_contrat - must be unique
        if (isset($data['numero_contrat']) && !empty($data['numero_contrat'])) {
            $contrat->setNumeroContrat($data['numero_contrat']);
        } else {
            // Generate auto contract number: CTR-YYYYMMDD-HHMMSS-RANDOM
            $numeroContrat = 'CTR-' . date('YmdHis') . '-' . rand(1000, 9999);
            $contrat->setNumeroContrat($numeroContrat);
        }
        if (isset($data['date_signature'])) {
            if (is_string($data['date_signature'])) {
                $contrat->setDateSignature(new \DateTime($data['date_signature']));
            } else {
                $contrat->setDateSignature($data['date_signature']);
            }
        }
        if (isset($data['date_fin_contrat'])) {
            if (is_string($data['date_fin_contrat'])) {
                $contrat->setDateFinContrat(new \DateTime($data['date_fin_contrat']));
            } else {
                $contrat->setDateFinContrat($data['date_fin_contrat']);
            }
        }
        if (isset($data['duree_contrat'])) {
            $contrat->setDureeContrat($data['duree_contrat']);
        }
        if (isset($data['conditions_particulieres'])) {
            $contrat->setConditionsParticulieres($data['conditions_particulieres']);
        }
        if (isset($data['exclusions'])) {
            $contrat->setExclusions($data['exclusions']);
        }
        if (isset($data['plafond_annuel'])) {
            $contrat->setPlafondAnnuel($data['plafond_annuel']);
        }
        if (isset($data['taux_remboursement'])) {
            $contrat->setTauxRemboursement($data['taux_remboursement']);
        }
        if (isset($data['delai_carence'])) {
            $contrat->setDelaiCarence($data['delai_carence']);
        }
        if (isset($data['clause_beneficiaire'])) {
            $contrat->setClauseBeneficiaire($data['clause_beneficiaire']);
        }
        if (isset($data['document_contrat'])) {
            $contrat->setDocumentContrat($data['document_contrat']);
        }
        if (isset($data['amendements'])) {
            $contrat->setAmendements($data['amendements']);
        }
        if (isset($data['conseiller_attribue'])) {
            $contrat->setConseillierAttribue($data['conseiller_attribue']);
        }
        if (isset($data['contacts'])) {
            $contrat->setContacts($data['contacts']);
        }
        
        // Set status - default to EN_ATTENTE if not provided
        if (isset($data['statut'])) {
            $contrat->setStatut($data['statut']);
        } else {
            $contrat->setStatut('EN_ATTENTE');
        }

        $this->entityManager->persist($contrat);
        
        // Debit bank account if prix_assurance is set and compte_bancaire_id is provided
        $priceAssurance = (float)($assurance->getPrixAssurance() ?? 0);
        if ($priceAssurance > 0 && isset($data['compte_bancaire_id']) && !empty($data['compte_bancaire_id'])) {
            $compteBancaire = $this->entityManager->getRepository(\App\Entity\CompteBancaire::class)->find($data['compte_bancaire_id']);
            if ($compteBancaire) {
                // Debit the account
                $newSolde = (float)$compteBancaire->getSolde() - $priceAssurance;
                $compteBancaire->setSolde($newSolde);
                $this->entityManager->persist($compteBancaire);
                
                // Create a depense record for this insurance charge
                $depense = new \App\Entity\Depense();
                $depense->setUtilisateur($utilisateur);
                $depense->setCompteBancaire($compteBancaire);
                $depense->setAssurance($assurance);
                $depense->setMontant($priceAssurance);
                $depense->setDateDepense(new \DateTime());
                $depense->setCategorie('ASSURANCE');
                $depense->setModePaiement('DEBIT');
                $depense->setDescription('Insurance subscription: ' . $assurance->getTypeAssurance() . ' - Policy: ' . $assurance->getNumeroPolice());
                $depense->setCreatedAt(new \DateTime());
                $this->entityManager->persist($depense);
            }
        }
        
        $this->entityManager->flush();

        return $contrat;
    }

    /**
     * Find Contrat by ID
     */
    public function findById(int $id): ?ContratAssurance
    {
        return $this->entityManager->getRepository(ContratAssurance::class)->find($id);
    }

    /**
     * Find all Contrats
     */
    public function findAll(): array
    {
        return $this->entityManager->getRepository(ContratAssurance::class)->findAll();
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
        return $this->entityManager->getRepository(ContratAssurance::class)->findBy(['utilisateur' => $utilisateur]);
    }

    /**
     * Find by assurance ID
     */
    public function findByAssuranceId(int $assuranceId): array
    {
        $assurance = $this->entityManager->getRepository(\App\Entity\Assurance::class)->find($assuranceId);
        if (!$assurance) {
            return [];
        }
        return $this->entityManager->getRepository(ContratAssurance::class)->findBy(['assurance' => $assurance]);
    }

    /**
     * Find by status
     */
    public function findByStatus(string $status): array
    {
        return $this->entityManager->getRepository(ContratAssurance::class)->findBy(['statut' => $status]);
    }

    /**
     * Find active contracts
     */
    public function findActiveContracts(): array
    {
        return $this->entityManager->getRepository(ContratAssurance::class)->findBy(['statut' => 'ACTIF']);
    }

    /**
     * Find expired contracts
     */
    public function findExpiredContracts(): array
    {
        $now = new \DateTime();
        $contracts = $this->entityManager->getRepository(ContratAssurance::class)->findAll();
        
        return array_filter($contracts, function(ContratAssurance $contract) use ($now) {
            $dateEnd = $contract->getDateFinContrat();
            return $dateEnd && $dateEnd <= $now;
        });
    }

    /**
     * Update Contrat
     */
    public function update(ContratAssurance $contrat, array $data): ContratAssurance
    {
        if (isset($data['assurance_id'])) {
            $assurance = $this->entityManager->getRepository(\App\Entity\Assurance::class)->find($data['assurance_id']);
            if ($assurance) {
                $contrat->setAssurance($assurance);
            }
        }
        if (isset($data['utilisateur_id'])) {
            $utilisateur = $this->entityManager->getRepository(\App\Entity\Utilisateur::class)->find($data['utilisateur_id']);
            if ($utilisateur) {
                $contrat->setUtilisateur($utilisateur);
            }
        }
        if (isset($data['numero_contrat'])) {
            $contrat->setNumeroContrat($data['numero_contrat']);
        }
        if (isset($data['date_signature'])) {
            if (is_string($data['date_signature'])) {
                $contrat->setDateSignature(new \DateTime($data['date_signature']));
            } else {
                $contrat->setDateSignature($data['date_signature']);
            }
        }
        if (isset($data['date_fin_contrat'])) {
            if (is_string($data['date_fin_contrat'])) {
                $contrat->setDateFinContrat(new \DateTime($data['date_fin_contrat']));
            } else {
                $contrat->setDateFinContrat($data['date_fin_contrat']);
            }
        }
        if (isset($data['duree_contrat'])) {
            $contrat->setDureeContrat($data['duree_contrat']);
        }
        if (isset($data['conditions_particulieres'])) {
            $contrat->setConditionsParticulieres($data['conditions_particulieres']);
        }
        if (isset($data['exclusions'])) {
            $contrat->setExclusions($data['exclusions']);
        }
        if (isset($data['plafond_annuel'])) {
            $contrat->setPlafondAnnuel($data['plafond_annuel']);
        }
        if (isset($data['taux_remboursement'])) {
            $contrat->setTauxRemboursement($data['taux_remboursement']);
        }
        if (isset($data['delai_carence'])) {
            $contrat->setDelaiCarence($data['delai_carence']);
        }
        if (isset($data['clause_beneficiaire'])) {
            $contrat->setClauseBeneficiaire($data['clause_beneficiaire']);
        }
        if (isset($data['document_contrat'])) {
            $contrat->setDocumentContrat($data['document_contrat']);
        }
        if (isset($data['amendements'])) {
            $contrat->setAmendements($data['amendements']);
        }
        if (isset($data['conseiller_attribue'])) {
            $contrat->setConseillierAttribue($data['conseiller_attribue']);
        }
        if (isset($data['contacts'])) {
            $contrat->setContacts($data['contacts']);
        }
        if (isset($data['statut'])) {
            $contrat->setStatut($data['statut']);
        }

        $this->entityManager->flush();

        return $contrat;
    }

    /**
     * Delete Contrat
     */
    public function delete(ContratAssurance $contrat): void
    {
        $this->entityManager->remove($contrat);
        $this->entityManager->flush();
    }

    /**
     * Delete by ID
     */
    public function deleteById(int $id): bool
    {
        $contrat = $this->findById($id);
        if ($contrat) {
            $this->delete($contrat);
            return true;
        }
        return false;
    }

    /**
     * Get contract summary
     */
    public function getContractSummary(int $contractId): ?array
    {
        $contract = $this->findById($contractId);
        if (!$contract) {
            return null;
        }

        return [
            'id' => $contract->getId(),
            'numero' => $contract->getNumeroContrat(),
            'assurance_id' => $contract->getAssuranceId(),
            'user_id' => $contract->getUtilisateurId(),
            'date_signature' => $contract->getDateSignature(),
            'date_expiration' => $contract->getDateFinContrat(),
            'status' => $contract->getStatut(),
            'plafond' => $contract->getPlafondAnnuel(),
            'taux_remboursement' => $contract->getTauxRemboursement(),
        ];
    }
}
