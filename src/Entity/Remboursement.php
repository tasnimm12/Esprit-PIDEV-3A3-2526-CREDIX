<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\RemboursementRepository;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RemboursementRepository::class)]
#[ORM\Table(name: 'remboursement')]
class Remboursement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id_remboursement;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id_user', nullable: true)]
    private $utilisateur;

    #[ORM\ManyToOne(targetEntity: Credit::class)]
    #[ORM\JoinColumn(name: 'credit_id', referencedColumnName: 'id_credit', nullable: true)]
    private $credit;

    #[ORM\ManyToOne(targetEntity: CompteBancaire::class)]
    #[ORM\JoinColumn(name: 'compte_id', referencedColumnName: 'id', nullable: false)]
    private $compte;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\NotBlank(message: 'Reimbursement type is required')]
    private $type_remboursement;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    #[Assert\NotNull(message: 'Reimbursement amount is required')]
    #[Assert\GreaterThan(value: 0, message: 'Reimbursement amount must be greater than 0')]
    private $montant;

    #[ORM\Column(type: 'date')]
    #[Assert\NotNull(message: 'Reimbursement date is required')]
    #[Assert\Type('\DateTimeInterface', message: 'Reimbursement date must be a valid date')]
    private $date_remboursement;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\NotBlank(message: 'Status is required')]
    #[Assert\Choice(choices: ['PENDANTE', 'APPROUVEE', 'REJETEE', 'COMPLETEE'], message: 'Invalid status')]
    private $statut;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 5000, maxMessage: 'Description cannot exceed {{ limit }} characters')]
    private $description;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $reference_id;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    private $cashback_pourcentage;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2, nullable: true)]
    private $montant_cashback;

    #[ORM\Column(type: 'datetime')]
    private $created_at;

    // Getters and Setters
    public function getIdRemboursement() { return $this->id_remboursement; }
    public function setIdRemboursement($val) { $this->id_remboursement = $val; return $this; }
    
    public function getUtilisateur() { return $this->utilisateur; }
    public function setUtilisateur($val) { $this->utilisateur = $val; return $this; }
    
    public function getCredit() { return $this->credit; }
    public function setCredit($val) { $this->credit = $val; return $this; }
    
    public function getCompte() { return $this->compte; }
    public function setCompte($val) { $this->compte = $val; return $this; }
    
    public function getTypeRemboursement() { return $this->type_remboursement; }
    public function setTypeRemboursement($val) { $this->type_remboursement = $val; return $this; }
    
    public function getMontant() { return $this->montant; }
    public function setMontant($val) { $this->montant = $val; return $this; }
    
    public function getDateRemboursement() { return $this->date_remboursement; }
    public function setDateRemboursement($val) { $this->date_remboursement = $val; return $this; }
    
    public function getStatut() { return $this->statut; }
    public function setStatut($val) { $this->statut = $val; return $this; }
    
    public function getDescription() { return $this->description; }
    public function setDescription($val) { $this->description = $val; return $this; }
    
    public function getReferenceId() { return $this->reference_id; }
    public function setReferenceId($val) { $this->reference_id = $val; return $this; }
    
    public function getCashbackPourcentage() { return $this->cashback_pourcentage; }
    public function setCashbackPourcentage($val) { $this->cashback_pourcentage = $val; return $this; }
    
    public function getMontantCashback() { return $this->montant_cashback; }
    public function setMontantCashback($val) { $this->montant_cashback = $val; return $this; }
    
    public function getCreatedAt() { return $this->created_at; }
    public function setCreatedAt($val) { $this->created_at = $val; return $this; }
}
