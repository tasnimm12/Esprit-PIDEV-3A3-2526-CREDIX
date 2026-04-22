<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\AbonnementRepository;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AbonnementRepository::class)]
#[ORM\Table(name: 'abonnement')]
class Abonnement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id_abonnement;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\NotBlank(message: 'Subscription type is required')]
    #[Assert\Length(min: 2, max: 50, minMessage: 'Type must be at least {{ limit }} characters')]
    private $type_abonnement;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotNull(message: 'Monthly price is required')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Monthly price must be greater than or equal to 0')]
    private $prix_mensuel;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotNull(message: 'Annual price is required')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Annual price must be greater than or equal to 0')]
    private $prix_annuel;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\NotBlank(message: 'Duration is required')]
    #[Assert\Length(min: 2, max: 50, minMessage: 'Duration must be at least {{ limit }} characters')]
    private $duree;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(min: 10, max: 5000, minMessage: 'Description must be at least {{ limit }} characters', maxMessage: 'Description cannot exceed {{ limit }} characters')]
    private $description;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(min: 10, max: 5000, minMessage: 'Benefits must be at least {{ limit }} characters', maxMessage: 'Benefits cannot exceed {{ limit }} characters')]
    private $avantages;

    #[ORM\Column(type: 'boolean')]
    private $actif;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    private $reduction_pourcentage;

    #[ORM\Column(type: 'boolean')]
    private $is_custom = false;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: 'id_user', nullable: true, onDelete: 'CASCADE')]
    private $createdBy;

    // Getters and Setters
    public function getIdAbonnement() { return $this->id_abonnement; }
    public function setTypeAbonnement($val) { $this->type_abonnement = $val; return $this; }
    public function getTypeAbonnement() { return $this->type_abonnement; }
    public function setPrixMensuel($val) { $this->prix_mensuel = $val; return $this; }
    public function getPrixMensuel() { return $this->prix_mensuel; }
    public function setPrixAnnuel($val) { $this->prix_annuel = $val; return $this; }
    public function getPrixAnnuel() { return $this->prix_annuel; }
    public function setDuree($val) { $this->duree = $val; return $this; }
    public function getDuree() { return $this->duree; }
    public function setDescription($val) { $this->description = $val; return $this; }
    public function getDescription() { return $this->description; }
    public function setAvantages($val) { $this->avantages = $val; return $this; }
    public function getAvantages() { return $this->avantages; }
    public function setActif($val) { $this->actif = $val; return $this; }
    public function isActif() { return $this->actif; }
    public function setReductionPourcentage($val) { $this->reduction_pourcentage = $val; return $this; }
    public function getReductionPourcentage() { return $this->reduction_pourcentage; }
    
    public function isCustom() { return $this->is_custom; }
    public function setIsCustom($val) { $this->is_custom = $val; return $this; }
    
    public function getCreatedBy() { return $this->createdBy; }
    public function setCreatedBy($val) { $this->createdBy = $val; return $this; }

    // Aliasing getters for template compatibility
    public function getNomAbonnement() { return $this->type_abonnement; }
    public function getPrixAbonnement() { return $this->prix_mensuel; }
    public function getDescriptionAbonnement() { return $this->description; }
}
