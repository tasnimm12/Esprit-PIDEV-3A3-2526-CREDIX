<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\ContratAssuranceRepository;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ContratAssuranceRepository::class)]
#[ORM\Table(name: 'contrat_assurance')]
class ContratAssurance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: Assurance::class, inversedBy: 'contrats')]
    #[ORM\JoinColumn(name: 'assurance_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private $assurance;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'contrats')]
    #[ORM\JoinColumn(name: 'utilisateur_id', referencedColumnName: 'id_user', nullable: false, onDelete: 'CASCADE')]
    private $utilisateur;

    #[ORM\ManyToOne(targetEntity: CompteBancaire::class)]
    #[ORM\JoinColumn(name: 'compte_bancaire_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private $compteBancaire;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Contract number is required')]
    #[Assert\Length(min: 1, max: 255, maxMessage: 'Contract number cannot exceed {{ limit }} characters')]
    private $numero_contrat;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotNull(message: 'Contract signature date is required')]
    #[Assert\Type('\DateTimeInterface', message: 'Contract signature date must be a valid date')]
    private $date_signature;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Assert\Type('\DateTimeInterface', message: 'Contract end date must be a valid date')]
    private $date_fin_contrat;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Contract duration must be greater than or equal to 0')]
    private $duree_contrat;

    #[ORM\Column(type: 'text', nullable: true)]
    private $conditions_particulieres;

    #[ORM\Column(type: 'text', nullable: true)]
    private $exclusions;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2, nullable: true)]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Annual ceiling must be greater than or equal to 0')]
    private $plafond_annuel;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Reimbursement rate must be greater than or equal to 0')]
    #[Assert\LessThanOrEqual(value: 100, message: 'Reimbursement rate cannot exceed 100')]
    private $taux_remboursement;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Waiting period must be greater than or equal to 0')]
    private $delai_carence;

    #[ORM\Column(type: 'text', nullable: true)]
    private $clause_beneficiaire;

    #[ORM\Column(length: 255, nullable: true)]
    private $document_contrat;

    #[ORM\Column(type: 'text', nullable: true)]
    private $amendements;

    #[ORM\Column(length: 255, nullable: true)]
    private $conseiller_attribue;

    #[ORM\Column(type: 'text', nullable: true)]
    private $contacts;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Choice(choices: ['ACTIF', 'INACTIF', 'SUSPENDU', 'RESILIÉ', 'EXPIRÉ'], message: 'Invalid status. Must be one of: ACTIF, INACTIF, SUSPENDU, RESILIÉ, EXPIRÉ')]
    private $statut;

    // Getters and Setters
    public function getId(): ?int { return $this->id; }
    public function setId($val) { $this->id = $val; return $this; }
    public function getAssurance(): ?Assurance { return $this->assurance; }
    public function setAssurance(?Assurance $val) { $this->assurance = $val; return $this; }
    public function getAssuranceId() { return $this->assurance ? $this->assurance->getId() : null; }
    public function setAssuranceId($val) { return $this; }
    public function getUtilisateur(): ?Utilisateur { return $this->utilisateur; }
    public function setUtilisateur(?Utilisateur $val) { $this->utilisateur = $val; return $this; }
    public function getUtilisateurId() { return $this->utilisateur ? $this->utilisateur->getId() : null; }
    public function setUtilisateurId($val) { return $this; }
    public function getCompteBancaire(): ?CompteBancaire { return $this->compteBancaire; }
    public function setCompteBancaire(?CompteBancaire $val) { $this->compteBancaire = $val; return $this; }
    public function getCompteBancaireId() { return $this->compteBancaire ? $this->compteBancaire->getId() : null; }
    public function setCompteBancaireId($val) { return $this; }
    public function getNumeroContrat() { return $this->numero_contrat; }
    public function setNumeroContrat($val) { $this->numero_contrat = $val; return $this; }
    public function getDateSignature() { return $this->date_signature; }
    public function setDateSignature($val) { $this->date_signature = $val; return $this; }
    public function getDateFinContrat() { return $this->date_fin_contrat; }
    public function setDateFinContrat($val) { $this->date_fin_contrat = $val; return $this; }
    public function getDureeContrat() { return $this->duree_contrat; }
    public function setDureeContrat($val) { $this->duree_contrat = $val; return $this; }
    public function getConditionsParticulieres() { return $this->conditions_particulieres; }
    public function setConditionsParticulieres($val) { $this->conditions_particulieres = $val; return $this; }
    public function getExclusions() { return $this->exclusions; }
    public function setExclusions($val) { $this->exclusions = $val; return $this; }
    public function getPlafondAnnuel() { return $this->plafond_annuel; }
    public function setPlafondAnnuel($val) { $this->plafond_annuel = $val; return $this; }
    public function getTauxRemboursement() { return $this->taux_remboursement; }
    public function setTauxRemboursement($val) { $this->taux_remboursement = $val; return $this; }
    public function getDelaiCarence() { return $this->delai_carence; }
    public function setDelaiCarence($val) { $this->delai_carence = $val; return $this; }
    public function getClauseBeneficiaire() { return $this->clause_beneficiaire; }
    public function setClauseBeneficiaire($val) { $this->clause_beneficiaire = $val; return $this; }
    public function getDocumentContrat() { return $this->document_contrat; }
    public function setDocumentContrat($val) { $this->document_contrat = $val; return $this; }
    public function getAmendements() { return $this->amendements; }
    public function setAmendements($val) { $this->amendements = $val; return $this; }
    public function getConseillerAttribue() { return $this->conseiller_attribue; }
    public function setConseillerAttribue($val) { $this->conseiller_attribue = $val; return $this; }
    public function getContacts() { return $this->contacts; }
    public function setContacts($val) { $this->contacts = $val; return $this; }
    public function getStatut() { return $this->statut; }
    public function setStatut($val) { $this->statut = $val; return $this; }
}
