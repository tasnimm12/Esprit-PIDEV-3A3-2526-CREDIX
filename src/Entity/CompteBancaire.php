<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Repository\CompteBancaireRepository;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CompteBancaireRepository::class)]
#[ORM\Table(name: 'compte_bancaire')]
class CompteBancaire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private $id;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'comptesBancaires')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id_user', nullable: false)]
    private $utilisateur;

    #[ORM\OneToMany(targetEntity: Assurance::class, mappedBy: 'compte_bancaire')]
    private Collection $assurances;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    #[Assert\Length(max: 50, maxMessage: 'Account number cannot exceed {{ limit }} characters')]
    private $numero_compte;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[Assert\Length(max: 100, maxMessage: 'Account holder cannot exceed {{ limit }} characters')]
    private $titulaire;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[Assert\Email(message: 'Invalid email address')]
    private $email;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    #[Assert\Regex(pattern: '/^[0-9+\-\s()]*$/', message: 'Phone number contains invalid characters')]
    private $telephone;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Account balance must be greater than or equal to 0')]
    private $solde;

    #[ORM\Column(type: 'string', length: 10)]
    #[Assert\Choice(choices: ['USD', 'EUR', 'GBP', 'CAD', 'CHF'], message: 'Invalid currency. Must be one of: USD, EUR, GBP, CAD, CHF')]
    private $devise;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\Choice(choices: ['COURANT', 'EPARGNE', 'TITRE', 'CHEQUES'], message: 'Invalid account type. Must be one of: COURANT, EPARGNE, TITRE, CHEQUES')]
    private $type_compte;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $date_creation;

    #[ORM\Column(type: 'boolean')]
    private $actif;

    public function __construct()
    {
        $this->assurances = new ArrayCollection();
    }

    // Getters and Setters
    public function getId() { return $this->id; }
    public function setId($val) { $this->id = $val; return $this; }
    public function getUtilisateur() { return $this->utilisateur; }
    public function setUtilisateur($val) { $this->utilisateur = $val; return $this; }
    public function getUserId() { return $this->utilisateur ? $this->utilisateur->getId() : null; }
    
    public function getAssurances(): Collection { return $this->assurances; }
    public function addAssurance(Assurance $assurance): self {
        if (!$this->assurances->contains($assurance)) {
            $this->assurances->add($assurance);
            $assurance->setCompteBancaire($this);
        }
        return $this;
    }
    public function removeAssurance(Assurance $assurance): self {
        $this->assurances->removeElement($assurance);
        return $this;
    }

    public function getNumeroCompte() { return $this->numero_compte; }
    public function setNumeroCompte($val) { $this->numero_compte = $val; return $this; }
    public function getTitulaire() { return $this->titulaire; }
    public function setTitulaire($val) { $this->titulaire = $val; return $this; }
    public function getEmail() { return $this->email; }
    public function setEmail($val) { $this->email = $val; return $this; }
    public function getTelephone() { return $this->telephone; }
    public function setTelephone($val) { $this->telephone = $val; return $this; }
    public function getSolde() { return $this->solde; }
    public function setSolde($val) { $this->solde = $val; return $this; }
    public function getDevise() { return $this->devise; }
    public function setDevise($val) { $this->devise = $val; return $this; }
    public function getTypeCompte() { return $this->type_compte; }
    public function setTypeCompte($val) { $this->type_compte = $val; return $this; }
    public function getDateCreation() { return $this->date_creation; }
    public function setDateCreation($val) { $this->date_creation = $val; return $this; }
    public function getActif() { return $this->actif; }
    public function setActif($val) { $this->actif = $val; return $this; }
}
