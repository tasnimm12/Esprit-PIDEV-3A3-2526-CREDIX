<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\CreditRepository;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CreditRepository::class)]
#[ORM\Table(name: 'credit')]
class Credit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id_credit;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id_user', nullable: false)]
    private $utilisateur;

    #[ORM\ManyToOne(targetEntity: CompteBancaire::class)]
    #[ORM\JoinColumn(name: 'compte_id', referencedColumnName: 'id', nullable: false)]
    private $compte;

    #[ORM\ManyToOne(targetEntity: Abonnement::class)]
    #[ORM\JoinColumn(name: 'abonnement_id', referencedColumnName: 'id_abonnement', nullable: true)]
    private $abonnement;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    #[Assert\NotNull(message: 'Credit amount is required')]
    #[Assert\GreaterThan(value: 0, message: 'Credit amount must be greater than 0')]
    #[Assert\LessThanOrEqual(value: 1000000, message: 'Credit amount cannot exceed $1,000,000')]
    private $montant_demande;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\NotBlank(message: 'Credit type is required')]
    #[Assert\Choice(choices: ['3M', '6M', '12M', '24M', '36M'], message: 'Invalid credit type')]
    private $type_credit;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    #[Assert\NotNull(message: 'Interest rate is required')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Interest rate must be greater than or equal to 0')]
    #[Assert\LessThanOrEqual(value: 100, message: 'Interest rate cannot exceed 100')]
    private $taux_interet;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    private $reduction_pourcentage;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    private $montant_total;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    private $montant_restant;

    #[ORM\Column(type: 'date')]
    private $date_demande;

    #[ORM\Column(type: 'date', nullable: true)]
    private $date_debut;

    #[ORM\Column(type: 'date', nullable: true)]
    private $date_fin;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\NotBlank(message: 'Credit status is required')]
    #[Assert\Choice(choices: ['EN_ATTENTE', 'ACCEPTE', 'REFUSE', 'EN_COURS', 'TERMINE', 'EN_RETARD'], message: 'Invalid credit status')]
    private $statut_credit;

    #[ORM\Column(type: 'text', nullable: true)]
    private $motif_refus;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2, nullable: true)]
    private $mensualite;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $created_at;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $updated_at;

    // Getters and Setters
    public function getIdCredit() { return $this->id_credit; }
    public function setIdCredit($val) { $this->id_credit = $val; return $this; }
    
    public function getUtilisateur() { return $this->utilisateur; }
    public function setUtilisateur($val) { $this->utilisateur = $val; return $this; }
    
    public function getCompte() { return $this->compte; }
    public function setCompte($val) { $this->compte = $val; return $this; }
    
    public function getAbonnement() { return $this->abonnement; }
    public function setAbonnement($val) { $this->abonnement = $val; return $this; }
    
    public function getMontantDemande() { return $this->montant_demande; }
    public function setMontantDemande($val) { $this->montant_demande = $val; return $this; }
    
    public function getTypeCredit() { return $this->type_credit; }
    public function setTypeCredit($val) { $this->type_credit = $val; return $this; }
    
    public function getTauxInteret() { return $this->taux_interet; }
    public function setTauxInteret($val) { $this->taux_interet = $val; return $this; }
    
    public function getReductionPourcentage() { return $this->reduction_pourcentage; }
    public function setReductionPourcentage($val) { $this->reduction_pourcentage = $val; return $this; }
    
    public function getMontantTotal() { return $this->montant_total; }
    public function setMontantTotal($val) { $this->montant_total = $val; return $this; }
    
    public function getMontantRestant() { return $this->montant_restant; }
    public function setMontantRestant($val) { $this->montant_restant = $val; return $this; }
    
    public function getDateDemande() { return $this->date_demande; }
    public function setDateDemande($val) { $this->date_demande = $val; return $this; }
    
    public function getDateDebut() { return $this->date_debut; }
    public function setDateDebut($val) { $this->date_debut = $val; return $this; }
    
    public function getDateFin() { return $this->date_fin; }
    public function setDateFin($val) { $this->date_fin = $val; return $this; }
    
    public function getStatutCredit() { return $this->statut_credit; }
    public function setStatutCredit($val) { $this->statut_credit = $val; return $this; }
    
    public function getMotifRefus() { return $this->motif_refus; }
    public function setMotifRefus($val) { $this->motif_refus = $val; return $this; }
    
    public function getMensualite() { return $this->mensualite; }
    public function setMensualite($val) { $this->mensualite = $val; return $this; }
    
    public function getCreatedAt() { return $this->created_at; }
    public function setCreatedAt($val) { $this->created_at = $val; return $this; }
    
    public function getUpdatedAt() { return $this->updated_at; }
    public function setUpdatedAt($val) { $this->updated_at = $val; return $this; }
}

