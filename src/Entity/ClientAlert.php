<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'client_alert')]
class ClientAlert
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $user_id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank(message: 'Alert title is required')]
    #[Assert\Length(min: 3, max: 255, minMessage: 'Title must be at least {{ limit }} characters')]
    private $title;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'Alert message is required')]
    #[Assert\Length(min: 10, max: 5000, minMessage: 'Message must be at least {{ limit }} characters')]
    private $message;

    #[ORM\Column(type: 'datetime')]
    private $created_at;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private $is_read;

    // Getters and Setters
    public function getId() { return $this->id; }
    public function setId($val) { $this->id = $val; return $this; }
    public function getUserId() { return $this->user_id; }
    public function setUserId($val) { $this->user_id = $val; return $this; }
    public function getTitle() { return $this->title; }
    public function setTitle($val) { $this->title = $val; return $this; }
    public function getMessage() { return $this->message; }
    public function setMessage($val) { $this->message = $val; return $this; }
    public function getCreatedAt() { return $this->created_at; }
    public function setCreatedAt($val) { $this->created_at = $val; return $this; }
    public function getIsRead() { return $this->is_read; }
    public function setIsRead($val) { $this->is_read = $val; return $this; }
}
