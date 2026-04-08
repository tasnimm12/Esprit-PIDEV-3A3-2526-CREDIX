<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'login_historique')]
class LoginHistorique
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $user_id;

    #[ORM\Column(type: 'datetime')]
    #[Assert\NotNull(message: 'Login date is required')]
    #[Assert\Type('\DateTimeInterface', message: 'Login date must be a valid datetime')]
    private $login_at;

    // Getters and Setters
    public function getId() { return $this->id; }
    public function setId($val) { $this->id = $val; return $this; }
    public function getUserId() { return $this->user_id; }
    public function setUserId($val) { $this->user_id = $val; return $this; }
    public function getLoginAt() { return $this->login_at; }
    public function setLoginAt($val) { $this->login_at = $val; return $this; }
}
