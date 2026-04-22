<?php

namespace App\Repository;

use App\Entity\Remboursement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RemboursementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Remboursement::class);
    }

    /**
     * Get rembourements for a specific user with filters
     */
    public function findByUser(
        $utilisateur,
        bool $active = null,
        string $sort = 'date_remboursement',
        string $order = 'DESC',
        int $limit = null
    ): array {
        $query = $this->createQueryBuilder('r')
                      ->where('r.utilisateur = :utilisateur')
                      ->setParameter('utilisateur', $utilisateur);

        if ($active !== null) {
            $query->andWhere('r.actif = :active')
                  ->setParameter('active', $active);
        }

        $query->orderBy('r.' . $sort, $order);

        if ($limit) {
            $query->setMaxResults($limit);
        }

        return $query->getQuery()->getResult();
    }

    /**
     * Get active rembourements for a user
     */
    public function findActiveByUser($utilisateur, int $limit = null): array
    {
        return $this->findByUser($utilisateur, true, 'date_remboursement', 'DESC', $limit);
    }

    /**
     * Get inactive rembourements for a user
     */
    public function findInactiveByUser($utilisateur, int $limit = null): array
    {
        return $this->findByUser($utilisateur, false, 'date_remboursement', 'DESC', $limit);
    }

    /**
     * Count active rembourements
     */
    public function countActiveByUser($utilisateur): int
    {
        return $this->createQueryBuilder('r')
                    ->select('COUNT(r.id) as total')
                    ->where('r.utilisateur = :utilisateur')
                    ->andWhere('r.actif = :active')
                    ->setParameter('utilisateur', $utilisateur)
                    ->setParameter('active', true)
                    ->getQuery()
                    ->getSingleScalarResult();
    }
}
