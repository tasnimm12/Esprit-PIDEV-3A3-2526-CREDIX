<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\InvestissementRepository;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InvestissementRepository::class)]
#[ORM\Table(name: 'investissement')]
class Investissement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $idinves;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id_user', nullable: false)]
    private $utilisateur;

    #[ORM\ManyToOne(targetEntity: Projet::class)]
    #[ORM\JoinColumn(name: 'idprojet', referencedColumnName: 'idprojet', nullable: false)]
    private $projet;

    #[ORM\ManyToOne(targetEntity: CompteBancaire::class)]
    #[ORM\JoinColumn(name: 'compte_id', referencedColumnName: 'id', nullable: false)]
    private $compte;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    #[Assert\NotNull(message: 'Investment amount is required')]
    #[Assert\GreaterThan(value: 0, message: 'Investment amount must be greater than 0')]
    private $montantinvesti;

    #[ORM\Column(type: 'date')]
    #[Assert\NotNull(message: 'Investment date is required')]
    #[Assert\Type('\DateTimeInterface', message: 'Investment date must be a valid date')]
    private $dateinves;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\NotBlank(message: 'Payment mode is required')]
    private $modepaiement;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\NotBlank(message: 'Investment status is required')]
    #[Assert\Choice(choices: ['EN_ATTENTE', 'ACCEPTE', 'REFUSE', 'EN_COURS'], message: 'Invalid investment status')]
    private $statut_investissement;

    #[ORM\Column(type: 'datetime')]
    private $created_at;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $updated_at;

    // Getters and Setters
    public function getIdinves() { return $this->idinves; }
    public function setIdinves($val) { $this->idinves = $val; return $this; }
    
    public function getUtilisateur() { return $this->utilisateur; }
    public function setUtilisateur($val) { $this->utilisateur = $val; return $this; }
    
    public function getProjet() { return $this->projet; }
    public function setProjet($val) { $this->projet = $val; return $this; }
    
    public function getCompte() { return $this->compte; }
    public function setCompte($val) { $this->compte = $val; return $this; }
    
    public function getMontantinvesti() { return $this->montantinvesti; }
    public function setMontantinvesti($val) { $this->montantinvesti = $val; return $this; }
    
    public function getDateinves() { return $this->dateinves; }
    public function setDateinves($val) { $this->dateinves = $val; return $this; }
    
    public function getModepaiement() { return $this->modepaiement; }
    public function setModepaiement($val) { $this->modepaiement = $val; return $this; }
    
    public function getStatutInvestissement() { return $this->statut_investissement; }
    public function setStatutInvestissement($val) { $this->statut_investissement = $val; return $this; }
    
    public function getCreatedAt() { return $this->created_at; }
    public function setCreatedAt($val) { $this->created_at = $val; return $this; }
    
    public function getUpdatedAt() { return $this->updated_at; }
    public function setUpdatedAt($val) { $this->updated_at = $val; return $this; }
}
