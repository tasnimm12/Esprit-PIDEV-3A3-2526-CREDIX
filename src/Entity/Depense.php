<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\DepenseRepository;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DepenseRepository::class)]
#[ORM\Table(name: 'depense')]
class Depense
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private $id;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'depenses')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id_user', nullable: false)]
    private $utilisateur;

    #[ORM\ManyToOne(targetEntity: CompteBancaire::class)]
    #[ORM\JoinColumn(name: 'compte_id', referencedColumnName: 'id', nullable: false)]
    private $compte_bancaire;

    #[ORM\ManyToOne(targetEntity: Assurance::class, inversedBy: 'depenses')]
    #[ORM\JoinColumn(name: 'assurance_id', referencedColumnName: 'id', nullable: true)]
    private $assurance;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 5000, maxMessage: 'Description cannot exceed {{ limit }} characters')]
    private $description;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    #[Assert\NotNull(message: 'Amount is required')]
    #[Assert\GreaterThan(value: 0, message: 'Amount must be greater than 0')]
    private $montant;

    #[ORM\Column(type: 'date')]
    #[Assert\NotNull(message: 'Expense date is required')]
    #[Assert\Type('\DateTimeInterface', message: 'Expense date must be a valid date')]
    private $date_depense;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\NotBlank(message: 'Expense category is required')]
    #[Assert\Length(min: 2, max: 50, minMessage: 'Category must be at least {{ limit }} characters')]
    private $categorie;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    #[Assert\Choice(choices: ['CARTE', 'VIREMENT', 'CHEQUE', 'ESPECES'], message: 'Invalid payment method')]
    private $mode_paiement;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $created_at;

    // Getters and Setters
    public function getId() { return $this->id; }
    public function setId($val) { $this->id = $val; return $this; }
    
    public function getUtilisateur() { return $this->utilisateur; }
    public function setUtilisateur($val) { $this->utilisateur = $val; return $this; }
    
    public function getCompteBancaire() { return $this->compte_bancaire; }
    public function setCompteBancaire($val) { $this->compte_bancaire = $val; return $this; }

    public function getAssurance() { return $this->assurance; }
    public function setAssurance($val) { $this->assurance = $val; return $this; }
    
    public function getDescription() { return $this->description; }
    public function setDescription($val) { $this->description = $val; return $this; }
    
    public function getMontant() { return $this->montant; }
    public function setMontant($val) { $this->montant = $val; return $this; }
    
    public function getDateDepense() { return $this->date_depense; }
    public function setDateDepense($val) { $this->date_depense = $val; return $this; }
    
    public function getCategorie() { return $this->categorie; }
    public function setCategorie($val) { $this->categorie = $val; return $this; }
    
    public function getModePaiement() { return $this->mode_paiement; }
    public function setModePaiement($val) { $this->mode_paiement = $val; return $this; }

    // Backward compatibility getter for compte_id
    public function getCompteId() { return $this->compte_bancaire ? $this->compte_bancaire->getId() : null; }
    public function setCompteId($val) { 
        // This is kept for backward compatibility but should use setCompteBancaire instead
        return $this; 
    }
    
    public function getCreatedAt() { return $this->created_at; }
    public function setCreatedAt($val) { $this->created_at = $val; return $this; }
}

