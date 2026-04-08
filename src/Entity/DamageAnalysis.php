<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'damage_analysis')]
class DamageAnalysis
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: SinistrePreuve::class)]
    #[ORM\JoinColumn(name: 'preuve_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private $preuve;

    #[ORM\ManyToOne(targetEntity: Sinistre::class, inversedBy: 'damageAnalyses')]
    #[ORM\JoinColumn(name: 'sinistre_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private $sinistre;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\NotBlank(message: 'Damage severity is required')]
    #[Assert\Choice(choices: ['minor', 'moderate', 'severe', 'critical'], message: 'Invalid severity level')]
    private $severity; // minor, moderate, severe, critical

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'Damage type is required')]
    #[Assert\Length(min: 3, max: 5000, minMessage: 'Damage type must be at least {{ limit }} characters')]
    private $damage_type;

    #[ORM\Column(type: 'integer')]
    #[Assert\NotNull(message: 'Affected area percentage is required')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Affected area must be greater than or equal to 0')]
    #[Assert\LessThanOrEqual(value: 100, message: 'Affected area cannot exceed 100')]
    private $affected_area_percentage;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Estimated cost must be greater than or equal to 0')]
    private $estimated_cost;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'Analysis details are required')]
    #[Assert\Length(min: 10, max: 5000, minMessage: 'Details must be at least {{ limit }} characters')]
    private $analysis_details;

    #[ORM\Column(type: 'datetime')]
    private $analyzed_at;

    #[ORM\Column(type: 'string', length: 50)]
    private $ai_model = 'claude-3-5-sonnet';

    public function __construct()
    {
        $this->analyzed_at = new \DateTime();
    }

    public function getId() { return $this->id; }
    public function setId($val) { $this->id = $val; return $this; }

    public function getPreuve(): ?SinistrePreuve { return $this->preuve; }
    public function setPreuve(?SinistrePreuve $val) { $this->preuve = $val; return $this; }

    public function getSinistre(): ?Sinistre { return $this->sinistre; }
    public function setSinistre(?Sinistre $val) { $this->sinistre = $val; return $this; }

    public function getSeverity() { return $this->severity; }
    public function setSeverity($val) { $this->severity = $val; return $this; }

    public function getDamageType() { return $this->damage_type; }
    public function setDamageType($val) { $this->damage_type = $val; return $this; }

    public function getAffectedAreaPercentage() { return $this->affected_area_percentage; }
    public function setAffectedAreaPercentage($val) { $this->affected_area_percentage = $val; return $this; }

    public function getEstimatedCost() { return $this->estimated_cost; }
    public function setEstimatedCost($val) { $this->estimated_cost = $val; return $this; }

    public function getAnalysisDetails() { return $this->analysis_details; }
    public function setAnalysisDetails($val) { $this->analysis_details = $val; return $this; }

    public function getAnalyzedAt() { return $this->analyzed_at; }
    public function setAnalyzedAt($val) { $this->analyzed_at = $val; return $this; }

    public function getAiModel() { return $this->ai_model; }
    public function setAiModel($val) { $this->ai_model = $val; return $this; }

    // Helper to get severity badge color
    public function getSeverityColor(): string
    {
        return match($this->severity) {
            'minor' => 'success',
            'moderate' => 'warning',
            'severe' => 'danger',
            'critical' => 'dark',
            default => 'secondary'
        };
    }

    // Helper to get severity badge icon
    public function getSeverityIcon(): string
    {
        return match($this->severity) {
            'minor' => 'fa-check-circle',
            'moderate' => 'fa-exclamation-circle',
            'severe' => 'fa-times-circle',
            'critical' => 'fa-skull',
            default => 'fa-question-circle'
        };
    }
}
