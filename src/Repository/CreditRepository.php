<?php

namespace App\Repository;

use App\Entity\Credit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CreditRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Credit::class);
    }

    /**
     * Search and filter credits with pagination support
     */
    public function findFiltered(
        string $search = '',
        string $status = '',
        float $minAmount = null,
        float $maxAmount = null,
        string $sort = 'id',
        string $order = 'DESC',
        int $limit = null,
        int $offset = 0
    ): array {
        $query = $this->createQueryBuilder('c');

        if ($search) {
            $query->where('c.type_credit LIKE :search OR c.motif_refus LIKE :search')
                  ->setParameter('search', '%' . $search . '%');
        }

        if ($status) {
            $query->andWhere('c.statut_credit = :status')
                  ->setParameter('status', $status);
        }

        if ($minAmount !== null) {
            $query->andWhere('c.montant >= :minAmount')
                  ->setParameter('minAmount', $minAmount);
        }

        if ($maxAmount !== null) {
            $query->andWhere('c.montant <= :maxAmount')
                  ->setParameter('maxAmount', $maxAmount);
        }

        $query->orderBy('c.' . $sort, $order);

        if ($limit) {
            $query->setMaxResults($limit)
                  ->setFirstResult($offset);
        }

        return $query->getQuery()->getResult();
    }

    /**
     * Get credits for a specific user
     */
    public function findByUser($utilisateur, string $status = '', int $limit = null): array
    {
        $query = $this->createQueryBuilder('c')
                      ->where('c.utilisateur = :utilisateur')
                      ->setParameter('utilisateur', $utilisateur);

        if ($status) {
            $query->andWhere('c.statut_credit = :status')
                  ->setParameter('status', $status);
        }

        if ($limit) {
            $query->setMaxResults($limit);
        }

        return $query->getQuery()->getResult();
    }

    /**
     * Count credits with filters
     */
    public function countFiltered(string $search = '', string $status = '', float $minAmount = null, float $maxAmount = null): int
    {
        $query = $this->createQueryBuilder('c')
                      ->select('COUNT(c.id) as total');

        if ($search) {
            $query->where('c.type_credit LIKE :search OR c.motif_refus LIKE :search')
                  ->setParameter('search', '%' . $search . '%');
        }

        if ($status) {
            $query->andWhere('c.statut_credit = :status')
                  ->setParameter('status', $status);
        }

        if ($minAmount !== null) {
            $query->andWhere('c.montant >= :minAmount')
                  ->setParameter('minAmount', $minAmount);
        }

        if ($maxAmount !== null) {
            $query->andWhere('c.montant <= :maxAmount')
                  ->setParameter('maxAmount', $maxAmount);
        }

        return (int) $query->getQuery()->getSingleScalarResult();
    }
}
