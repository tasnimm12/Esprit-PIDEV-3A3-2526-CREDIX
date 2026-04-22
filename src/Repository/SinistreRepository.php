<?php

namespace App\Repository;

use App\Entity\Sinistre;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SinistreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Sinistre::class);
    }

    /**
     * Get sinistres for admin (all) or user (only their own)
     */
    public function findFiltered(
        string $sort = 'created_at',
        string $order = 'DESC',
        int $limit = null,
        int $offset = 0,
        $utilisateur = null
    ): array {
        $query = $this->createQueryBuilder('s');

        // If utilisateur is provided (non-admin), filter by user
        if ($utilisateur !== null) {
            $query->where('s.utilisateur = :utilisateur')
                  ->setParameter('utilisateur', $utilisateur);
        }

        $query->orderBy('s.' . $sort, $order);

        if ($limit) {
            $query->setMaxResults($limit)
                  ->setFirstResult($offset);
        }

        return $query->getQuery()->getResult();
    }

    /**
     * Advanced filtering for admin and user list
     */
    public function findWithFilters(
        array $filters = [],
        $utilisateur = null,
        string $sort = 'created_at',
        string $order = 'DESC',
        int $limit = null,
        int $offset = 0
    ): array {
        $query = $this->createQueryBuilder('s')
                      ->leftJoin('s.utilisateur', 'u')
                      ->leftJoin('s.contrat', 'c');

        // If utilisateur is provided (non-admin), filter by user
        if ($utilisateur !== null) {
            $query->where('s.utilisateur = :utilisateur')
                  ->setParameter('utilisateur', $utilisateur);
        }

        // Filter by status
        if (isset($filters['status']) && !empty($filters['status'])) {
            $statuses = is_array($filters['status']) ? $filters['status'] : [$filters['status']];
            $query->andWhere('s.statut IN (:statuses)')
                  ->setParameter('statuses', $statuses);
        }

        // Filter by date range (incident date)
        if (isset($filters['date_from']) && !empty($filters['date_from'])) {
            $dateFrom = new \DateTime($filters['date_from']);
            $query->andWhere('s.date_sinistre >= :dateFrom')
                  ->setParameter('dateFrom', $dateFrom);
        }

        if (isset($filters['date_to']) && !empty($filters['date_to'])) {
            $dateTo = new \DateTime($filters['date_to']);
            $dateTo->setTime(23, 59, 59); // End of day
            $query->andWhere('s.date_sinistre <= :dateTo')
                  ->setParameter('dateTo', $dateTo);
        }

        // Filter by date filed range
        if (isset($filters['filed_from']) && !empty($filters['filed_from'])) {
            $filedFrom = new \DateTime($filters['filed_from']);
            $query->andWhere('s.created_at >= :filedFrom')
                  ->setParameter('filedFrom', $filedFrom);
        }

        if (isset($filters['filed_to']) && !empty($filters['filed_to'])) {
            $filedTo = new \DateTime($filters['filed_to']);
            $filedTo->setTime(23, 59, 59); // End of day
            $query->andWhere('s.created_at <= :filedTo')
                  ->setParameter('filedTo', $filedTo);
        }

        // Search by user name or email (admin only)
        if (isset($filters['search']) && !empty($filters['search']) && $utilisateur === null) {
            $search = '%' . $filters['search'] . '%';
            $query->andWhere('(u.prenom LIKE :search OR u.nom LIKE :search OR u.email LIKE :search OR s.description LIKE :search)')
                  ->setParameter('search', $search);
        }

        // Search by description or claim ID (user can also search)
        if (isset($filters['search']) && !empty($filters['search']) && $utilisateur !== null) {
            $search = '%' . $filters['search'] . '%';
            $query->andWhere('s.description LIKE :search')
                  ->setParameter('search', $search);
        }

        // Filter by contract (admin only)
        if (isset($filters['contract_id']) && !empty($filters['contract_id']) && $utilisateur === null) {
            $query->andWhere('s.contrat = :contractId')
                  ->setParameter('contractId', $filters['contract_id']);
        }

        // Sort options
        $sortField = 'created_at';
        switch ($sort) {
            case 'user':
                $sortField = 'u.nom';
                if ($utilisateur === null) { // Only for admin
                    $query->addOrderBy($sortField, $order);
                    $query->addOrderBy('s.created_at', 'DESC');
                    break;
                }
            case 'status':
                $sortField = 's.statut';
                break;
            case 'incident_date':
                $sortField = 's.date_sinistre';
                break;
            case 'created_at':
            default:
                $sortField = 's.created_at';
        }

        $query->orderBy($sortField, $order);

        // Pagination
        if ($limit) {
            $query->setMaxResults($limit)
                  ->setFirstResult($offset);
        }

        return $query->getQuery()->getResult();
    }

    /**
     * Count filtered sinistres
     */
    public function countWithFilters(
        array $filters = [],
        $utilisateur = null
    ): int {
        $query = $this->createQueryBuilder('s')
                      ->select('COUNT(s.id)')
                      ->leftJoin('s.utilisateur', 'u');

        // If utilisateur is provided (non-admin), filter by user
        if ($utilisateur !== null) {
            $query->where('s.utilisateur = :utilisateur')
                  ->setParameter('utilisateur', $utilisateur);
        }

        // Filter by status
        if (isset($filters['status']) && !empty($filters['status'])) {
            $statuses = is_array($filters['status']) ? $filters['status'] : [$filters['status']];
            $query->andWhere('s.statut IN (:statuses)')
                  ->setParameter('statuses', $statuses);
        }

        // Filter by date range (incident date)
        if (isset($filters['date_from']) && !empty($filters['date_from'])) {
            $dateFrom = new \DateTime($filters['date_from']);
            $query->andWhere('s.date_sinistre >= :dateFrom')
                  ->setParameter('dateFrom', $dateFrom);
        }

        if (isset($filters['date_to']) && !empty($filters['date_to'])) {
            $dateTo = new \DateTime($filters['date_to']);
            $dateTo->setTime(23, 59, 59);
            $query->andWhere('s.date_sinistre <= :dateTo')
                  ->setParameter('dateTo', $dateTo);
        }

        // Search
        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            if ($utilisateur === null) { // Admin search
                $query->andWhere('(u.prenom LIKE :search OR u.nom LIKE :search OR u.email LIKE :search OR s.description LIKE :search)')
                      ->setParameter('search', $search);
            } else { // User search
                $query->andWhere('s.description LIKE :search')
                      ->setParameter('search', $search);
            }
        }

        return $query->getQuery()->getSingleScalarResult();
    }

    /**
     * Get sinistres for a specific user
     */
    public function findByUser($utilisateur, int $limit = null): array
    {
        $query = $this->createQueryBuilder('s')
                      ->where('s.utilisateur = :utilisateur')
                      ->setParameter('utilisateur', $utilisateur)
                      ->orderBy('s.created_at', 'DESC');

        if ($limit) {
            $query->setMaxResults($limit);
        }

        return $query->getQuery()->getResult();
    }

    /**
     * Count sinistres
     */
    public function countByUser($utilisateur): int
    {
        return $this->createQueryBuilder('s')
                    ->select('COUNT(s.id) as total')
                    ->where('s.utilisateur = :utilisateur')
                    ->setParameter('utilisateur', $utilisateur)
                    ->getQuery()
                    ->getSingleScalarResult();
    }

    /**
     * Get recent sinistres (admin only)
     */
    public function getRecent(int $limit = 5): array
    {
        return $this->createQueryBuilder('s')
                    ->orderBy('s.created_at', 'DESC')
                    ->setMaxResults($limit)
                    ->getQuery()
                    ->getResult();
    }

    public function countPendingClaims(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.statut = :status')
            ->setParameter('status', 'EN_ATTENTE')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getRecentPendingClaims(int $limit = 5): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.utilisateur', 'u')
            ->addSelect('u')
            ->where('s.statut = :status')
            ->setParameter('status', 'EN_ATTENTE')
            ->orderBy('s.created_at', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
