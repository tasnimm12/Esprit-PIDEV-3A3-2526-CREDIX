<?php

namespace App\Repository;

use App\Entity\ContratAssurance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ContratAssuranceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContratAssurance::class);
    }

    /**
     * Search and filter contrats with pagination support
     */
    public function findFiltered(
        string $search = '',
        string $status = '',
        string $sort = 'id',
        string $order = 'DESC',
        int $limit = null,
        int $offset = 0,
        $utilisateur = null
    ): array {
        $query = $this->createQueryBuilder('ca');

        if ($search) {
            $query->where('ca.numero_contrat LIKE :search')
                  ->setParameter('search', '%' . $search . '%');
        }

        if ($status) {
            $query->andWhere('ca.statut = :status')
                  ->setParameter('status', $status);
        }

        // If utilisateur is provided (non-admin), filter by user
        if ($utilisateur !== null) {
            $query->andWhere('ca.utilisateur = :utilisateur')
                  ->setParameter('utilisateur', $utilisateur);
        }

        $query->orderBy('ca.' . $sort, $order);

        if ($limit) {
            $query->setMaxResults($limit)
                  ->setFirstResult($offset);
        }

        return $query->getQuery()->getResult();
    }

    /**
     * Get contrats for a specific user
     */
    public function findByUser($utilisateur, string $status = '', int $limit = null): array
    {
        $query = $this->createQueryBuilder('ca')
                      ->where('ca.utilisateur = :utilisateur')
                      ->setParameter('utilisateur', $utilisateur);

        if ($status) {
            $query->andWhere('ca.statut = :status')
                  ->setParameter('status', $status);
        }

        if ($limit) {
            $query->setMaxResults($limit);
        }

        return $query->getQuery()->getResult();
    }

    /**
     * Get active contrats for a user
     */
    public function findActiveByUser($utilisateur): array
    {
        return $this->createQueryBuilder('ca')
                    ->where('ca.utilisateur = :utilisateur')
                    ->andWhere('ca.statut = :status')
                    ->setParameter('utilisateur', $utilisateur)
                    ->setParameter('status', 'ACTIF')
                    ->getQuery()
                    ->getResult();
    }

    /**
     * Count contrats with filters
     */
    public function countFiltered(string $search = '', string $status = '', $utilisateur = null): int
    {
        $query = $this->createQueryBuilder('ca')
                      ->select('COUNT(ca.id) as total');

        if ($search) {
            $query->where('ca.numero_contrat LIKE :search')
                  ->setParameter('search', '%' . $search . '%');
        }

        if ($status) {
            $query->andWhere('ca.statut = :status')
                  ->setParameter('status', $status);
        }

        if ($utilisateur !== null) {
            $query->andWhere('ca.utilisateur = :utilisateur')
                  ->setParameter('utilisateur', $utilisateur);
        }

        return (int) $query->getQuery()->getSingleScalarResult();
    }
}
