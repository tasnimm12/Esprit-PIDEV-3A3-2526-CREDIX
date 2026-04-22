<?php

namespace App\Service;

use App\Entity\LoginHistorique;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;

class LoginHistoryService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function recordLogin(Utilisateur $user): void
    {
        $history = new LoginHistorique();
        $history->setUserId($user->getId());
        $history->setEmail($user->getEmail());
        $history->setRole($user->getRole() ?: implode(', ', $user->getRoles()));
        $history->setLoginAt(new \DateTime());

        $this->entityManager->persist($history);
        $this->entityManager->flush();
    }
}
