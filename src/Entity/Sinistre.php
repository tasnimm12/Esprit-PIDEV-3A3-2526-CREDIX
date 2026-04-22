<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Repository\SinistreRepository;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SinistreRepository::class)]
#[ORM\Table(name: 'sinistre')]
class Sinistre
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: ContratAssurance::class)]
    #[ORM\JoinColumn(name: 'contrat_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private $contrat;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'utilisateur_id', referencedColumnName: 'id_user', nullable: false, onDelete: 'CASCADE')]
    private $utilisateur;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'Claim description is required')]
    #[Assert\Length(min: 10, max: 5000, minMessage: 'Description must be at least {{ limit }} characters', maxMessage: 'Description cannot exceed {{ limit }} characters')]
    private $description;

    #[ORM\Column(type: 'date')]
    #[Assert\NotNull(message: 'Claim date is required')]
    #[Assert\Type('\DateTimeInterface', message: 'Claim date must be a valid date')]
    private $date_sinistre;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Assert\Type('\DateTimeInterface', message: 'Claim date must be a valid datetime')]
    private $date_reclamation;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\NotBlank(message: 'Status is required')]
    #[Assert\Choice(choices: ['EN_ATTENTE', 'ACCEPTE', 'REFUSE', 'REMBOURSÉ', 'EN_APPEL', 'REJETE'], message: 'Invalid status. Must be one of: EN_ATTENTE, ACCEPTE, REFUSE, REMBOURSÉ, EN_APPEL, REJETE')]
    private $statut;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 5000, maxMessage: 'Admin response cannot exceed {{ limit }} characters')]
    private $admin_reponse;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Assert\Type('\DateTimeInterface', message: 'Response date must be a valid datetime')]
    private $date_reponse;

    #[ORM\Column(type: 'datetime')]
    private $created_at;

    #[ORM\OneToMany(targetEntity: SinistrePreuve::class, mappedBy: 'sinistre', cascade: ['remove'])]
    private Collection $preuves;

    #[ORM\OneToMany(targetEntity: DamageAnalysis::class, mappedBy: 'sinistre', cascade: ['remove'])]
    private Collection $damageAnalyses;

    #[ORM\Column(type: 'float', nullable: true)]
    private $latitude;

    #[ORM\Column(type: 'float', nullable: true)]
    private $longitude;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $location_address;

    #[ORM\Column(type: 'text', nullable: true)]
    private $signature;

    public function __construct()
    {
        $this->preuves = new ArrayCollection();
        $this->damageAnalyses = new ArrayCollection();
        $this->created_at = new \DateTime();
        $this->statut = 'EN_ATTENTE';
        $this->date_reclamation = new \DateTime();
    }

    // Getters and Setters
    public function getId() { return $this->id; }
    public function setId($val) { $this->id = $val; return $this; }
    
    public function getContrat(): ?ContratAssurance { return $this->contrat; }
    public function setContrat(?ContratAssurance $val) { $this->contrat = $val; return $this; }
    
    public function getContratId() { return $this->contrat ? $this->contrat->getId() : null; }
    
    public function getUtilisateur(): ?Utilisateur { return $this->utilisateur; }
    public function setUtilisateur(?Utilisateur $val) { $this->utilisateur = $val; return $this; }
    
    public function getUtilisateurId() { return $this->utilisateur ? $this->utilisateur->getId() : null; }
    
    public function getDescription() { return $this->description; }
    public function setDescription($val) { $this->description = $val; return $this; }
    
    public function getDateSinistre() { return $this->date_sinistre; }
    public function setDateSinistre($val) { $this->date_sinistre = $val; return $this; }
    
    public function getDateReclamation() { return $this->date_reclamation; }
    public function setDateReclamation($val) { $this->date_reclamation = $val; return $this; }
    
    public function getStatut() { return $this->statut; }
    public function setStatut($val) { $this->statut = $val; return $this; }
    
    public function getAdminReponse() { return $this->admin_reponse; }
    public function setAdminReponse($val) { $this->admin_reponse = $val; return $this; }
    
    public function getDateReponse() { return $this->date_reponse; }
    public function setDateReponse($val) { $this->date_reponse = $val; return $this; }
    
    public function getCreatedAt() { return $this->created_at; }
    public function setCreatedAt($val) { $this->created_at = $val; return $this; }
    
    public function getPreuves(): Collection { return $this->preuves; }
    public function addPreuve(SinistrePreuve $preuve) {
        if (!$this->preuves->contains($preuve)) {
            $this->preuves->add($preuve);
            $preuve->setSinistre($this);
        }
        return $this;
    }
    public function removePreuve(SinistrePreuve $preuve) {
        $this->preuves->removeElement($preuve);
        return $this;
    }

    public function getDamageAnalyses(): Collection { return $this->damageAnalyses; }
    public function addDamageAnalysis(DamageAnalysis $analysis) {
        if (!$this->damageAnalyses->contains($analysis)) {
            $this->damageAnalyses->add($analysis);
            $analysis->setSinistre($this);
        }
        return $this;
    }
    public function removeDamageAnalysis(DamageAnalysis $analysis) {
        $this->damageAnalyses->removeElement($analysis);
        return $this;
    }

    public function getLatitude() { return $this->latitude; }
    public function setLatitude($val) { $this->latitude = $val; return $this; }
    
    public function getLongitude() { return $this->longitude; }
    public function setLongitude($val) { $this->longitude = $val; return $this; }
    
    public function getLocationAddress() { return $this->location_address; }
    public function setLocationAddress($val) { $this->location_address = $val; return $this; }
    
    public function getSignature() { return $this->signature; }
    public function setSignature($val) { $this->signature = $val; return $this; }
}
