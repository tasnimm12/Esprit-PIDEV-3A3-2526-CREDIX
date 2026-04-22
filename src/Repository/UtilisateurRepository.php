<?php

namespace App\Repository;

use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UtilisateurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Utilisateur::class);
    }

    /**
     * Search and filter users with pagination support
     */
    public function findFiltered(
        string $search = '',
        string $role = '',
        string $sort = 'id',
        string $order = 'DESC',
        int $limit = null,
        int $offset = 0
    ): array {
        $query = $this->createQueryBuilder('u');

        if ($search) {
            $query->where('u.nom LIKE :search OR u.prenom LIKE :search OR u.email LIKE :search')
                  ->setParameter('search', '%' . $search . '%');
        }

        if ($role) {
            $query->andWhere('u.role = :role')
                  ->setParameter('role', $role);
        }

        $query->orderBy('u.' . $sort, $order);

        if ($limit) {
            $query->setMaxResults($limit)
                  ->setFirstResult($offset);
        }

        return $query->getQuery()->getResult();
    }

    /**
     * Count users with filters
     */
    public function countFiltered(string $search = '', string $role = ''): int
    {
        $query = $this->createQueryBuilder('u')
                      ->select('COUNT(u.id) as total');

        if ($search) {
            $query->where('u.nom LIKE :search OR u.prenom LIKE :search OR u.email LIKE :search')
                  ->setParameter('search', '%' . $search . '%');
        }

        if ($role) {
            $query->andWhere('u.role = :role')
                  ->setParameter('role', $role);
        }

        return (int) $query->getQuery()->getSingleScalarResult();
    }

    /**
     * Get all available roles
     */
    public function getAllRoles(): array
    {
        $query = $this->createQueryBuilder('u')
                      ->select('DISTINCT u.role')
                      ->orderBy('u.role', 'ASC');

        return $query->getQuery()->getResult();
    }

    /**
     * Get all admin users that have an email address.
     */
    public function findAdminsWithEmail(): array
    {
        return $this->createQueryBuilder('u')
            ->where('LOWER(u.role) = :role')
            ->andWhere('u.email IS NOT NULL')
            ->andWhere('u.email <> :empty')
            ->setParameter('role', 'admin')
            ->setParameter('empty', '')
            ->orderBy('u.email', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
