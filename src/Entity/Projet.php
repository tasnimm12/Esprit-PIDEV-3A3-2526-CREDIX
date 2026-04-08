<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use DateTime;
use App\Repository\ProjetRepository;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProjetRepository::class)]
#[ORM\Table(name: 'projet')]
class Projet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $idprojet;

    #[ORM\Column(type: 'string', length: 100)]
    #[Assert\NotBlank(message: 'Project name is required')]
    #[Assert\Length(min: 2, max: 100, minMessage: 'Project name must be at least {{ limit }} characters', maxMessage: 'Project name cannot exceed {{ limit }} characters')]
    private $nomprojet;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(min: 10, max: 5000, minMessage: 'Description must be at least {{ limit }} characters', maxMessage: 'Description cannot exceed {{ limit }} characters')]
    private $description;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private $secteur;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    #[Assert\NotNull(message: 'Objective amount is required')]
    #[Assert\GreaterThan(value: 0, message: 'Objective amount must be greater than 0')]
    private $montant_objectif;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2, nullable: true)]
    private $montant_collecte;

    #[ORM\Column(type: 'date')]
    #[Assert\NotNull(message: 'Project start date is required')]
    #[Assert\Type('\DateTimeInterface', message: 'Start date must be a valid date')]
    private $date_debut;

    #[ORM\Column(type: 'date')]
    #[Assert\NotNull(message: 'Project end date is required')]
    #[Assert\Type('\DateTimeInterface', message: 'End date must be a valid date')]
    private $date_fin;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\NotBlank(message: 'Project status is required')]
    #[Assert\Choice(choices: ['EN_COURS', 'COMPLETE', 'ABANDONNE', 'EN_ATTENTE'], message: 'Invalid project status')]
    private $statut_projet;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'admin_id', referencedColumnName: 'id_user', nullable: false)]
    private $adminCreateur;

    #[ORM\Column(type: 'datetime')]
    private $created_at;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $updated_at;

    // Getters and Setters
    public function getIdprojet() { return $this->idprojet; }
    public function setIdprojet($val) { $this->idprojet = $val; return $this; }
    
    public function getNomprojet() { return $this->nomprojet; }
    public function setNomprojet($val) { $this->nomprojet = $val; return $this; }
    
    public function getDescription() { return $this->description; }
    public function setDescription($val) { $this->description = $val; return $this; }
    
    public function getSecteur() { return $this->secteur; }
    public function setSecteur($val) { $this->secteur = $val; return $this; }
    
    public function getMontantObjectif() { return $this->montant_objectif; }
    public function setMontantObjectif($val) { $this->montant_objectif = $val; return $this; }
    
    public function getMontantCollecte() { return $this->montant_collecte ?? 0; }
    public function setMontantCollecte($val) { $this->montant_collecte = $val; return $this; }
    
    public function getDateDebut() { return $this->date_debut; }
    public function setDateDebut($val) { $this->date_debut = $val; return $this; }
    
    public function getDateFin() { return $this->date_fin; }
    public function setDateFin($val) { $this->date_fin = $val; return $this; }
    
    public function getStatutProjet() { return $this->statut_projet; }
    public function setStatutProjet($val) { $this->statut_projet = $val; return $this; }
    
    public function getAdminCreateur() { return $this->adminCreateur; }
    public function setAdminCreateur($val) { $this->adminCreateur = $val; return $this; }
    
    public function getCreatedAt() { return $this->created_at; }
    public function setCreatedAt($val) { $this->created_at = $val; return $this; }
    
    public function getUpdatedAt() { return $this->updated_at; }
    public function setUpdatedAt($val) { $this->updated_at = $val; return $this; }
}
