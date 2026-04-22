<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Repository\AssuranceRepository;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AssuranceRepository::class)]
#[ORM\Table(name: 'assurance')]
class Assurance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'assurances')]
    #[ORM\JoinColumn(name: 'utilisateur_id', referencedColumnName: 'id_user', nullable: false, onDelete: 'CASCADE')]
    private $utilisateur;

    #[ORM\OneToMany(targetEntity: ContratAssurance::class, mappedBy: 'assurance', cascade: ['remove'])]
    private Collection $contrats;

    #[ORM\ManyToOne(targetEntity: CompteBancaire::class, inversedBy: 'assurances')]
    #[ORM\JoinColumn(name: 'compte_bancaire_id', referencedColumnName: 'id', nullable: true)]
    private $compte_bancaire;

    #[ORM\OneToMany(targetEntity: Depense::class, mappedBy: 'assurance')]
    private Collection $depenses;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Insurance type is required')]
    #[Assert\Length(min: 2, max: 50, minMessage: 'Insurance type must be at least {{ limit }} characters', maxMessage: 'Insurance type cannot exceed {{ limit }} characters')]
    private $type_assurance;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100, maxMessage: 'Company name cannot exceed {{ limit }} characters')]
    private $compagnie;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100, maxMessage: 'Policy number cannot exceed {{ limit }} characters')]
    private $numero_police;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2, nullable: true)]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Coverage amount must be greater than or equal to 0')]
    private $montant_couverture;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2, nullable: true)]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Deductible must be greater than or equal to 0')]
    private $franchise;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2, nullable: true)]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Annual premium must be greater than or equal to 0')]
    private $prime_annuelle;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2, nullable: true)]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Insurance price must be greater than or equal to 0')]
    private $prix_assurance;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2, nullable: true)]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Monthly premium must be greater than or equal to 0')]
    private $prime_mensuelle;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Assert\Type('\DateTimeInterface', message: 'Start date must be a valid date')]
    private $date_debut;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Assert\Type('\DateTimeInterface', message: 'Expiration date must be a valid date')]
    private $date_echeance;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Choice(choices: ['MENSUEL', 'TRIMESTRIEL', 'SEMESTRIEL', 'ANNUEL'], message: 'Invalid payment frequency. Must be one of: MENSUEL, TRIMESTRIEL, SEMESTRIEL, ANNUEL')]
    private $mode_paiement;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Choice(choices: ['ACTIVE', 'INACTIVE', 'SUSPENDUE', 'RESILIÉE', 'EXPIRÉE'], message: 'Invalid status. Must be one of: ACTIVE, INACTIVE, SUSPENDUE, RESILIÉE, EXPIRÉE')]
    private $statut;

    #[ORM\Column(nullable: true)]
    private $renouvellement_auto;

    #[ORM\Column(type: 'text', nullable: true)]
    private $garanties_incluses;

    public function __construct()
    {
        $this->contrats = new ArrayCollection();
        $this->depenses = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getUtilisateur(): ?Utilisateur { return $this->utilisateur; }
    public function setUtilisateur(?Utilisateur $utilisateur): self { 
        $this->utilisateur = $utilisateur; 
        return $this; 
    }

    // ❌ REMOVED getUtilisateurId() and setUtilisateurId()

    public function getContrats(): Collection { return $this->contrats; }

    public function addContrat(ContratAssurance $contrat): self { 
        if (!$this->contrats->contains($contrat)) {
            $this->contrats->add($contrat);
            $contrat->setAssurance($this);
        }
        return $this;
    }

    public function removeContrat(ContratAssurance $contrat): self { 
        $this->contrats->removeElement($contrat);
        return $this;
    }

    public function getCompteBancaire(): ?CompteBancaire { return $this->compte_bancaire; }
    public function setCompteBancaire(?CompteBancaire $compte): self { 
        $this->compte_bancaire = $compte; 
        return $this; 
    }

    public function getDepenses(): Collection { return $this->depenses; }
    public function addDepense(Depense $depense): self {
        if (!$this->depenses->contains($depense)) {
            $this->depenses->add($depense);
            $depense->setAssurance($this);
        }
        return $this;
    }
    public function removeDepense(Depense $depense): self {
        $this->depenses->removeElement($depense);
        return $this;
    }

    public function getTypeAssurance(): ?string { return $this->type_assurance; }
    public function setTypeAssurance(string $val): self { $this->type_assurance = $val; return $this; }

    public function getCompagnie(): ?string { return $this->compagnie; }
    public function setCompagnie(?string $val): self { $this->compagnie = $val; return $this; }

    public function getNumeroPolice(): ?string { return $this->numero_police; }
    public function setNumeroPolice(?string $val): self { $this->numero_police = $val; return $this; }

    public function getMontantCouverture() { return $this->montant_couverture; }
    public function setMontantCouverture($val): self { $this->montant_couverture = $val; return $this; }

    public function getFranchise() { return $this->franchise; }
    public function setFranchise($val): self { $this->franchise = $val; return $this; }

    public function getPrimeAnnuelle() { return $this->prime_annuelle; }
    public function setPrimeAnnuelle($val): self { $this->prime_annuelle = $val; return $this; }

    public function getPrixAssurance() { return $this->prix_assurance; }
    public function setPrixAssurance($val): self { $this->prix_assurance = $val; return $this; }

    public function getPrimeMensuelle() { return $this->prime_mensuelle; }
    public function setPrimeMensuelle($val): self { $this->prime_mensuelle = $val; return $this; }

    public function getDateDebut(): ?\DateTimeInterface { return $this->date_debut; }
    public function setDateDebut(?\DateTimeInterface $val): self { $this->date_debut = $val; return $this; }

    public function getDateEcheance(): ?\DateTimeInterface { return $this->date_echeance; }
    public function setDateEcheance(?\DateTimeInterface $val): self { $this->date_echeance = $val; return $this; }

    public function getModePaiement(): ?string { return $this->mode_paiement; }
    public function setModePaiement(?string $val): self { $this->mode_paiement = $val; return $this; }

    public function getStatut(): ?string { return $this->statut; }
    public function setStatut(?string $val): self { $this->statut = $val; return $this; }

    public function getRenouvellementAuto() { return $this->renouvellement_auto; }
    public function setRenouvellementAuto($val): self { $this->renouvellement_auto = $val; return $this; }

    public function getGarantiesIncluses(): ?string { return $this->garanties_incluses; }
    public function setGarantiesIncluses(?string $val): self { $this->garanties_incluses = $val; return $this; }
}