<?php

namespace App\Repository;

use App\Entity\LoginHistorique;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LoginHistoriqueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoginHistorique::class);
    }

    public function findLatest(int $limit = 100): array
    {
        return $this->createQueryBuilder('l')
            ->orderBy('l.login_at', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
