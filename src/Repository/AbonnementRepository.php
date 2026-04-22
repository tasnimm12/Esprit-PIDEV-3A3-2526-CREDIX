<?php

namespace App\Repository;

use App\Entity\Abonnement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AbonnementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Abonnement::class);
    }

    /**
     * Search and filter abonnements with pagination support
     */
    public function findFiltered(
        string $search = '',
        float $minPrice = null,
        float $maxPrice = null,
        string $sort = 'id',
        string $order = 'DESC',
        int $limit = null,
        int $offset = 0,
        $currentUser = null
    ): array {
        $query = $this->createQueryBuilder('ab');

        if ($search) {
            $query->where('ab.type_abonnement LIKE :search OR ab.description LIKE :search')
                  ->setParameter('search', '%' . $search . '%');
        }

        if ($minPrice !== null) {
            $query->andWhere('ab.prix_mensuel >= :minPrice')
                  ->setParameter('minPrice', $minPrice);
        }

        if ($maxPrice !== null) {
            $query->andWhere('ab.prix_mensuel <= :maxPrice')
                  ->setParameter('maxPrice', $maxPrice);
        }

        // Exclude custom plans created by other users - only show custom plans created by current user or non-custom plans
        if ($currentUser) {
            $query->andWhere(
                '(ab.is_custom = false) OR (ab.is_custom = true AND ab.createdBy = :currentUser)'
            )->setParameter('currentUser', $currentUser);
        } else {
            // If no user logged in, show only non-custom plans
            $query->andWhere('ab.is_custom = false');
        }

        $query->orderBy('ab.' . $sort, $order);

        if ($limit) {
            $query->setMaxResults($limit)
                  ->setFirstResult($offset);
        }

        return $query->getQuery()->getResult();
    }

    /**
     * Count abonnements with filters
     */
    public function countFiltered(string $search = '', float $minPrice = null, float $maxPrice = null, $currentUser = null): int
    {
        $query = $this->createQueryBuilder('ab')
                      ->select('COUNT(ab.id) as total');

        if ($search) {
            $query->where('ab.type_abonnement LIKE :search OR ab.description LIKE :search')
                  ->setParameter('search', '%' . $search . '%');
        }

        if ($minPrice !== null) {
            $query->andWhere('ab.prix_mensuel >= :minPrice')
                  ->setParameter('minPrice', $minPrice);
        }

        if ($maxPrice !== null) {
            $query->andWhere('ab.prix_mensuel <= :maxPrice')
                  ->setParameter('maxPrice', $maxPrice);
        }

        // Exclude custom plans created by other users
        if ($currentUser) {
            $query->andWhere(
                '(ab.is_custom = false) OR (ab.is_custom = true AND ab.createdBy = :currentUser)'
            )->setParameter('currentUser', $currentUser);
        } else {
            // If no user logged in, show only non-custom plans
            $query->andWhere('ab.is_custom = false');
        }

        return (int) $query->getQuery()->getSingleScalarResult();
    }
}
