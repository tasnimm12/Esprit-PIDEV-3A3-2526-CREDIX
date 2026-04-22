<?php

namespace App\Repository;

use App\Entity\Projet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ProjetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Projet::class);
    }

    /**
     * Search and filter projets with pagination support
     */
    public function findFiltered(
        string $search = '',
        string $sector = '',
        string $status = '',
        string $sort = 'id',
        string $order = 'DESC',
        int $limit = null,
        int $offset = 0
    ): array {
        $query = $this->createQueryBuilder('p');

        if ($search) {
            $query->where('p.nomprojet LIKE :search OR p.description LIKE :search OR p.secteur LIKE :search')
                  ->setParameter('search', '%' . $search . '%');
        }

        if ($sector) {
            $query->andWhere('p.secteur = :sector')
                  ->setParameter('sector', $sector);
        }

        if ($status) {
            $query->andWhere('p.statut_projet = :status')
                  ->setParameter('status', $status);
        }

        $query->orderBy('p.' . $sort, $order);

        if ($limit) {
            $query->setMaxResults($limit)
                  ->setFirstResult($offset);
        }

        return $query->getQuery()->getResult();
    }

    /**
     * Get all available sectors
     */
    public function getAllSectors(): array
    {
        $query = $this->createQueryBuilder('p')
                      ->select('DISTINCT p.secteur')
                      ->where('p.secteur IS NOT NULL')
                      ->orderBy('p.secteur', 'ASC');

        return $query->getQuery()->getResult();
    }

    /**
     * Count projets with filters
     */
    public function countFiltered(string $search = '', string $sector = '', string $status = ''): int
    {
        $query = $this->createQueryBuilder('p')
                      ->select('COUNT(p.id) as total');

        if ($search) {
            $query->where('p.nomprojet LIKE :search OR p.description LIKE :search OR p.secteur LIKE :search')
                  ->setParameter('search', '%' . $search . '%');
        }

        if ($sector) {
            $query->andWhere('p.secteur = :sector')
                  ->setParameter('sector', $sector);
        }

        if ($status) {
            $query->andWhere('p.statut_projet = :status')
                  ->setParameter('status', $status);
        }

        return (int) $query->getQuery()->getSingleScalarResult();
    }
}
