<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use App\Repository\UtilisateurRepository;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
#[ORM\Table(name: 'users')]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer', name: 'id_user')]
    private $id;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private $nom;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private $prenom;

    #[ORM\Column(type: 'string', length: 150)]
    #[Assert\NotBlank(message: 'Email is required')]
    #[Assert\Email(message: 'Invalid email format')]
    private $email;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank(message: 'Password is required')]
    #[Assert\Length(min: 8, minMessage: 'Password must be at least {{ limit }} characters')]
    private $mot_de_passe;

    #[ORM\Column(type: 'string', length: 8)]
    #[Assert\NotBlank(message: 'Phone number is required')]
    #[Assert\Regex(pattern: '/^[0-9+\-\s()]*$/', message: 'Phone number contains invalid characters')]
    private $telephone;

    #[ORM\Column(type: 'date', nullable: true)]
    #[Assert\Type('\DateTimeInterface', message: 'Date of birth must be a valid date')]
    private $date_naissance;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private $role;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private $statut_compte;

    #[ORM\Column(type: 'integer')]
    private $points_fidelite;

    #[ORM\Column(type: 'string', length: 6, nullable: true)]
    private $verification_code;

    #[ORM\Column(type: 'boolean', nullable: false, options: ['default' => false])]
    private $phone_verified = false;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $verification_code_expiry;

    // Relationships (only with proper inverse mappings)
    #[ORM\ManyToMany(targetEntity: Abonnement::class)]
    #[ORM\JoinTable(name: 'user_abonnement', joinColumns: [new ORM\JoinColumn(name: 'id_user', referencedColumnName: 'id_user')], inverseJoinColumns: [new ORM\JoinColumn(name: 'id_abonnement', referencedColumnName: 'id_abonnement')])]
    private Collection $abonnements;

    #[ORM\OneToMany(targetEntity: Assurance::class, mappedBy: 'utilisateur', cascade: ['remove'])]
    private Collection $assurances;

    #[ORM\OneToMany(targetEntity: ContratAssurance::class, mappedBy: 'utilisateur', cascade: ['remove'])]
    private Collection $contrats;

    #[ORM\OneToMany(targetEntity: CompteBancaire::class, mappedBy: 'utilisateur', cascade: ['remove'])]
    private Collection $comptesBancaires;

    #[ORM\OneToMany(targetEntity: Depense::class, mappedBy: 'utilisateur', cascade: ['remove'])]
    private Collection $depenses;

    public function __construct()
    {
        $this->abonnements = new ArrayCollection();
        $this->assurances = new ArrayCollection();
        $this->contrats = new ArrayCollection();
        $this->comptesBancaires = new ArrayCollection();
        $this->depenses = new ArrayCollection();
        $this->points_fidelite = 0;
    }

    public function getId() { return $this->id; }
    public function setId($val) { $this->id = $val; return $this; }

    public function getNom() { return $this->nom; }
    public function setNom($val) { $this->nom = $val; return $this; }

    public function getPrenom() { return $this->prenom; }
    public function setPrenom($val) { $this->prenom = $val; return $this; }

    public function getEmail() { return $this->email; }
    public function setEmail($val) { $this->email = $val; return $this; }

    public function getMotDePasse() { return $this->mot_de_passe; }
    public function setMotDePasse($val) { $this->mot_de_passe = $val; return $this; }

    public function getTelephone() { return $this->telephone; }
    public function setTelephone($val) { $this->telephone = $val; return $this; }

    public function getDateNaissance() { return $this->date_naissance; }
    public function setDateNaissance($val) { $this->date_naissance = $val; return $this; }

    public function getRole() { return $this->role; }
    public function setRole($val) { $this->role = $val; return $this; }

    public function getStatutCompte() { return $this->statut_compte; }
    public function setStatutCompte($val) { $this->statut_compte = $val; return $this; }

    public function getPointsFidelite() { return $this->points_fidelite; }
    public function setPointsFidelite($val) { $this->points_fidelite = $val; return $this; }

    public function getVerificationCode() { return $this->verification_code; }
    public function setVerificationCode($val) { $this->verification_code = $val; return $this; }

    public function isPhoneVerified() { return $this->phone_verified; }
    public function setPhoneVerified($val) { $this->phone_verified = $val; return $this; }

    public function getVerificationCodeExpiry() { return $this->verification_code_expiry; }
    public function setVerificationCodeExpiry($val) { $this->verification_code_expiry = $val; return $this; }

    public function getAssurances(): Collection { return $this->assurances; }
    public function addAssurance(Assurance $assurance) { 
        if (!$this->assurances->contains($assurance)) {
            $this->assurances->add($assurance);
            $assurance->setUtilisateur($this);
        }
        return $this;
    }
    public function removeAssurance(Assurance $assurance) { 
        $this->assurances->removeElement($assurance);
        return $this;
    }

    public function getContrats(): Collection { return $this->contrats; }
    public function addContrat(ContratAssurance $contrat) { 
        if (!$this->contrats->contains($contrat)) {
            $this->contrats->add($contrat);
            $contrat->setUtilisateur($this);
        }
        return $this;
    }
    public function removeContrat(ContratAssurance $contrat) { 
        $this->contrats->removeElement($contrat);
        return $this;
    }

    public function getComptesBancaires(): Collection { return $this->comptesBancaires; }
    public function addCompteBancaire(CompteBancaire $compte) { 
        if (!$this->comptesBancaires->contains($compte)) {
            $this->comptesBancaires->add($compte);
            $compte->setUtilisateur($this);
        }
        return $this;
    }
    public function removeCompteBancaire(CompteBancaire $compte) { 
        $this->comptesBancaires->removeElement($compte);
        return $this;
    }

    // UserInterface method implementations
    public function getRoles(): array
    {
        // Ensure user always has ROLE_USER at minimum
        $roles = [];

        // If role is explicitly set, add it with ROLE_ prefix
        if ($this->role) {
            $roleUpper = strtoupper($this->role);
            $roles[] = 'ROLE_' . $roleUpper;
            
            // Map specific roles to ROLE_USER
            if ($roleUpper === 'CLIENT' || $roleUpper === 'USER') {
                $roles[] = 'ROLE_USER';
            }
        } else {
            // Default to ROLE_USER if no role is set
            $roles[] = 'ROLE_USER';
        }

        return array_unique($roles);
    }

    public function getPassword(): string
    {
        return $this->mot_de_passe ?? '';
    }

    public function getSalt(): ?string
    {
        return null;
    }

    public function getAbonnement(): ?Abonnement
    {
        return $this->abonnements->first() ?: null;
    }

    public function getAbonnements(): Collection
    {
        return $this->abonnements;
    }

    public function addAbonnement(Abonnement $abonnement): self
    {
        if (!$this->abonnements->contains($abonnement)) {
            $this->abonnements->add($abonnement);
        }
        return $this;
    }

    public function removeAbonnement(Abonnement $abonnement): self
    {
        $this->abonnements->removeElement($abonnement);
        return $this;
    }

    public function setAbonnement(?Abonnement $abonnement): self
    {
        // For backward compatibility, clear collection and add the new one
        $this->abonnements->clear();
        if ($abonnement) {
            $this->abonnements->add($abonnement);
        }
        return $this;
    }

    public function getDepenses(): Collection { return $this->depenses; }
    public function addDepense(Depense $depense) { 
        if (!$this->depenses->contains($depense)) {
            $this->depenses->add($depense);
            $depense->setUtilisateur($this);
        }
        return $this;
    }
    public function removeDepense(Depense $depense) { 
        $this->depenses->removeElement($depense);
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email ?? '';
    }

    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }
}
