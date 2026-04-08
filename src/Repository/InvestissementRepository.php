<?php

namespace App\Repository;

use App\Entity\Investissement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class InvestissementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Investissement::class);
    }

    /**
     * Search and filter investissements with pagination support
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
        $query = $this->createQueryBuilder('i')
                      ->leftJoin('i.projet', 'p')
                      ->select('i');

        if ($search) {
            $query->where('p.nomprojet LIKE :search OR p.secteur LIKE :search')
                  ->setParameter('search', '%' . $search . '%');
        }

        if ($status) {
            $query->andWhere('i.statut_investissement = :status')
                  ->setParameter('status', $status);
        }

        // If utilisateur is provided (non-admin), filter by user
        if ($utilisateur !== null) {
            $query->andWhere('i.utilisateur = :utilisateur')
                  ->setParameter('utilisateur', $utilisateur);
        }

        $query->orderBy('i.' . $sort, $order);

        if ($limit) {
            $query->setMaxResults($limit)
                  ->setFirstResult($offset);
        }

        return $query->getQuery()->getResult();
    }

    /**
     * Get investissements for a specific user
     */
    public function findByUser($utilisateur, string $search = '', int $limit = null): array
    {
        $query = $this->createQueryBuilder('i')
                      ->leftJoin('i.projet', 'p')
                      ->where('i.utilisateur = :utilisateur')
                      ->setParameter('utilisateur', $utilisateur)
                      ->orderBy('i.dateinves', 'DESC');

        if ($search) {
            $query->andWhere('p.nomprojet LIKE :search OR p.secteur LIKE :search')
                  ->setParameter('search', '%' . $search . '%');
        }

        if ($limit) {
            $query->setMaxResults($limit);
        }

        return $query->getQuery()->getResult();
    }

    /**
     * Count investissements with filters
     */
    public function countFiltered(string $search = '', string $status = '', $utilisateur = null): int
    {
        $query = $this->createQueryBuilder('i')
                      ->leftJoin('i.projet', 'p')
                      ->select('COUNT(i.id) as total');

        if ($search) {
            $query->where('p.nomprojet LIKE :search OR p.secteur LIKE :search')
                  ->setParameter('search', '%' . $search . '%');
        }

        if ($status) {
            $query->andWhere('i.statut_investissement = :status')
                  ->setParameter('status', $status);
        }

        if ($utilisateur !== null) {
            $query->andWhere('i.utilisateur = :utilisateur')
                  ->setParameter('utilisateur', $utilisateur);
        }

        return (int) $query->getQuery()->getSingleScalarResult();
    }
}
