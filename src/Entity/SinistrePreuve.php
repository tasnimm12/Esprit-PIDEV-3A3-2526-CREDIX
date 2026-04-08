<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'sinistre_preuve')]
class SinistrePreuve
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: Sinistre::class, inversedBy: 'preuves')]
    #[ORM\JoinColumn(name: 'sinistre_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private $sinistre;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank(message: 'File path is required')]
    #[Assert\Length(max: 255, maxMessage: 'File path cannot exceed {{ limit }} characters')]
    private $fichier;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\NotBlank(message: 'File type is required')]
    #[Assert\Choice(choices: ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'video/mp4'], message: 'Invalid file type')]
    private $type_fichier;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank(message: 'File name is required')]
    #[Assert\Length(max: 255, maxMessage: 'File name cannot exceed {{ limit }} characters')]
    private $nom_fichier;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private $damage_type;

    #[ORM\Column(type: 'datetime')]
    private $created_at;

    public function __construct()
    {
        $this->created_at = new \DateTime();
    }

    // Getters and Setters
    public function getId() { return $this->id; }
    public function setId($val) { $this->id = $val; return $this; }
    
    public function getSinistre(): ?Sinistre { return $this->sinistre; }
    public function setSinistre(?Sinistre $val) { $this->sinistre = $val; return $this; }
    
    public function getSinistreId() { return $this->sinistre ? $this->sinistre->getId() : null; }
    
    public function getFichier() { return $this->fichier; }
    public function setFichier($val) { $this->fichier = $val; return $this; }
    
    public function getTypeFichier() { return $this->type_fichier; }
    public function setTypeFichier($val) { $this->type_fichier = $val; return $this; }
    
    public function getNomFichier() { return $this->nom_fichier; }
    public function setNomFichier($val) { $this->nom_fichier = $val; return $this; }
    
    public function getDamageType() { return $this->damage_type; }
    public function setDamageType($val) { $this->damage_type = $val; return $this; }
    
    public function getCreatedAt() { return $this->created_at; }
    public function setCreatedAt($val) { $this->created_at = $val; return $this; }
}
