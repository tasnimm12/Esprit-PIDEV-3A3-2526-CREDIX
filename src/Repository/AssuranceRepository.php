<?php

namespace App\Repository;

use App\Entity\Assurance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AssuranceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Assurance::class);
    }

    /**
     * Search and filter assurances with pagination support
     */
    public function findFiltered(
        string $search = '',
        string $status = '',
        string $sort = 'id',
        string $order = 'DESC',
        int $limit = null,
        int $offset = 0
    ): array {
        $query = $this->createQueryBuilder('a');

        if ($search) {
            $query->where('a.compagnie LIKE :search OR a.numero_police LIKE :search OR a.type_assurance LIKE :search')
                  ->setParameter('search', '%' . $search . '%');
        }

        if ($status) {
            $query->andWhere('a.statut = :status')
                  ->setParameter('status', $status);
        }

        $query->orderBy('a.' . $sort, $order);

        if ($limit) {
            $query->setMaxResults($limit)
                  ->setFirstResult($offset);
        }

        return $query->getQuery()->getResult();
    }

    /**
     * Get assurances for a specific user
     */
    public function findByUser($utilisateur, string $search = '', string $status = '', int $limit = null): array
    {
        $query = $this->createQueryBuilder('a')
                      ->where('a.utilisateur = :utilisateur')
                      ->setParameter('utilisateur', $utilisateur);

        if ($search) {
            $query->andWhere('a.compagnie LIKE :search OR a.numero_police LIKE :search OR a.type_assurance LIKE :search')
                  ->setParameter('search', '%' . $search . '%');
        }

        if ($status) {
            $query->andWhere('a.statut = :status')
                  ->setParameter('status', $status);
        }

        if ($limit) {
            $query->setMaxResults($limit);
        }

        return $query->getQuery()->getResult();
    }

    /**
     * Count assurances with filters
     */
    public function countFiltered(string $search = '', string $status = ''): int
    {
        $query = $this->createQueryBuilder('a')
                      ->select('COUNT(a.id) as total');

        if ($search) {
            $query->where('a.compagnie LIKE :search OR a.numero_police LIKE :search OR a.type_assurance LIKE :search')
                  ->setParameter('search', '%' . $search . '%');
        }

        if ($status) {
            $query->andWhere('a.statut = :status')
                  ->setParameter('status', $status);
        }

        return (int) $query->getQuery()->getSingleScalarResult();
    }

    /**
     * Get all available statuses
     */
    public function getAllStatuses(): array
    {
        $query = $this->createQueryBuilder('a')
                      ->select('DISTINCT a.statut')
                      ->orderBy('a.statut', 'ASC');

        return $query->getQuery()->getResult();
    }

    /**
     * Get all available companies
     */
    public function getAllCompagnies(): array
    {
        $query = $this->createQueryBuilder('a')
                      ->select('DISTINCT a.compagnie')
                      ->orderBy('a.compagnie', 'ASC');

        return $query->getQuery()->getResult();
    }
}
