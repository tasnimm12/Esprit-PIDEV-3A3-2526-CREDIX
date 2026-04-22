<?php

namespace App\Repository;

use App\Entity\Depense;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DepenseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Depense::class);
    }

    /**
     * Search and filter depenses with pagination support
     */
    public function findFiltered(
        string $category = '',
        float $minAmount = null,
        float $maxAmount = null,
        \DateTime $dateFrom = null,
        \DateTime $dateTo = null,
        string $sort = 'date_depense',
        string $order = 'DESC',
        int $limit = null,
        int $offset = 0
    ): array {
        $query = $this->createQueryBuilder('d');

        if ($category) {
            $query->where('d.categorie = :category')
                  ->setParameter('category', $category);
        }

        if ($minAmount !== null) {
            $query->andWhere('d.montant >= :minAmount')
                  ->setParameter('minAmount', $minAmount);
        }

        if ($maxAmount !== null) {
            $query->andWhere('d.montant <= :maxAmount')
                  ->setParameter('maxAmount', $maxAmount);
        }

        if ($dateFrom !== null) {
            $query->andWhere('d.date_depense >= :dateFrom')
                  ->setParameter('dateFrom', $dateFrom);
        }

        if ($dateTo !== null) {
            $query->andWhere('d.date_depense <= :dateTo')
                  ->setParameter('dateTo', $dateTo);
        }

        $query->orderBy('d.' . $sort, $order);

        if ($limit) {
            $query->setMaxResults($limit)
                  ->setFirstResult($offset);
        }

        return $query->getQuery()->getResult();
    }

    /**
     * Get depenses for a specific user's bank account
     * If $compteBancaire is null, returns all depenses
     */
    public function findByCompteBancaire($compteBancaire, string $category = '', int $limit = null): array
    {
        $query = $this->createQueryBuilder('d');
        
        // If compteBancaire is provided, filter by it; otherwise return all
        if ($compteBancaire !== null) {
            $query->where('d.compte_bancaire = :compteBancaire')
                  ->setParameter('compteBancaire', $compteBancaire);
        }

        if ($category) {
            $query->andWhere('d.categorie = :category')
                  ->setParameter('category', $category);
        }

        $query->orderBy('d.date_depense', 'DESC');

        if ($limit) {
            $query->setMaxResults($limit);
        }

        return $query->getQuery()->getResult();
    }

    /**
     * Get category statistics
     */
    public function getCategoryStats(): array
    {
        $query = $this->createQueryBuilder('d')
                      ->select('d.categorie, COUNT(d.id) as count, SUM(d.montant) as total')
                      ->groupBy('d.categorie')
                      ->orderBy('total', 'DESC');

        return $query->getQuery()->getResult();
    }

    /**
     * Count depenses with filters
     */
    public function countFiltered(
        string $category = '',
        float $minAmount = null,
        float $maxAmount = null,
        \DateTime $dateFrom = null,
        \DateTime $dateTo = null
    ): int {
        $query = $this->createQueryBuilder('d')
                      ->select('COUNT(d.id) as total');

        if ($category) {
            $query->where('d.categorie = :category')
                  ->setParameter('category', $category);
        }

        if ($minAmount !== null) {
            $query->andWhere('d.montant >= :minAmount')
                  ->setParameter('minAmount', $minAmount);
        }

        if ($maxAmount !== null) {
            $query->andWhere('d.montant <= :maxAmount')
                  ->setParameter('maxAmount', $maxAmount);
        }

        if ($dateFrom !== null) {
            $query->andWhere('d.date_depense >= :dateFrom')
                  ->setParameter('dateFrom', $dateFrom);
        }

        if ($dateTo !== null) {
            $query->andWhere('d.date_depense <= :dateTo')
                  ->setParameter('dateTo', $dateTo);
        }

        return (int) $query->getQuery()->getSingleScalarResult();
    }
}
