<?php

namespace App\Repository;

use App\Entity\CompteBancaire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CompteBancaireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompteBancaire::class);
    }

    /**
     * Get active bank accounts for a specific user
     */
    public function findActiveByUser($utilisateur, int $limit = null): array
    {
        $query = $this->createQueryBuilder('cb')
                      ->where('cb.utilisateur = :utilisateur')
                      ->andWhere('cb.actif = :active')
                      ->setParameter('utilisateur', $utilisateur)
                      ->setParameter('active', true)
                      ->orderBy('cb.id', 'DESC');

        if ($limit) {
            $query->setMaxResults($limit);
        }

        return $query->getQuery()->getResult();
    }

    /**
     * Get all bank accounts for a user
     */
    public function findByUser($utilisateur, bool $active = null): array
    {
        $query = $this->createQueryBuilder('cb')
                      ->where('cb.utilisateur = :utilisateur')
                      ->setParameter('utilisateur', $utilisateur)
                      ->orderBy('cb.id', 'DESC');

        if ($active !== null) {
            $query->andWhere('cb.actif = :active')
                  ->setParameter('active', $active);
        }

        return $query->getQuery()->getResult();
    }

    /**
     * Count active accounts for a user
     */
    public function countActiveByUser($utilisateur): int
    {
        return $this->createQueryBuilder('cb')
                    ->select('COUNT(cb.id) as total')
                    ->where('cb.utilisateur = :utilisateur')
                    ->andWhere('cb.actif = :active')
                    ->setParameter('utilisateur', $utilisateur)
                    ->setParameter('active', true)
                    ->getQuery()
                    ->getSingleScalarResult();
    }

    /**
     * Get total balance for a user across all active accounts
     */
    public function getTotalBalanceByUser($utilisateur, ?string $devise = null): float
    {
        $query = $this->createQueryBuilder('cb')
                      ->select('COALESCE(SUM(cb.solde), 0) as total_balance')
                      ->where('cb.utilisateur = :utilisateur')
                      ->andWhere('cb.actif = :active')
                      ->setParameter('utilisateur', $utilisateur)
                      ->setParameter('active', true);

        if ($devise) {
            $query->andWhere('cb.devise = :devise')
                  ->setParameter('devise', $devise);
        }

        return (float)$query->getQuery()->getSingleScalarResult();
    }

    /**
     * Get account with highest balance for a user
     */
    public function findAccountWithHighestBalance($utilisateur)
    {
        return $this->createQueryBuilder('cb')
                    ->where('cb.utilisateur = :utilisateur')
                    ->andWhere('cb.actif = :active')
                    ->setParameter('utilisateur', $utilisateur)
                    ->setParameter('active', true)
                    ->orderBy('cb.solde', 'DESC')
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();
    }

    /**
     * Get accounts with balance greater than a specific amount
     */
    public function findByMinimumBalance(float $minimumBalance, $utilisateur = null): array
    {
        $query = $this->createQueryBuilder('cb')
                      ->where('cb.solde >= :minBalance')
                      ->andWhere('cb.actif = :active')
                      ->setParameter('minBalance', $minimumBalance)
                      ->setParameter('active', true)
                      ->orderBy('cb.solde', 'DESC');

        if ($utilisateur) {
            $query->andWhere('cb.utilisateur = :utilisateur')
                  ->setParameter('utilisateur', $utilisateur);
        }

        return $query->getQuery()->getResult();
    }

    /**
     * Get accounts with low balance (below specific amount)
     */
    public function findByLowBalance(float $thresholdBalance, $utilisateur = null): array
    {
        $query = $this->createQueryBuilder('cb')
                      ->where('cb.solde < :threshold')
                      ->andWhere('cb.actif = :active')
                      ->setParameter('threshold', $thresholdBalance)
                      ->setParameter('active', true)
                      ->orderBy('cb.solde', 'ASC');

        if ($utilisateur) {
            $query->andWhere('cb.utilisateur = :utilisateur')
                  ->setParameter('utilisateur', $utilisateur);
        }

        return $query->getQuery()->getResult();
    }

    /**
     * Get accounts by balance range
     */
    public function findByBalanceRange(float $minBalance, float $maxBalance, $utilisateur = null): array
    {
        $query = $this->createQueryBuilder('cb')
                      ->where('cb.solde BETWEEN :minBalance AND :maxBalance')
                      ->andWhere('cb.actif = :active')
                      ->setParameter('minBalance', $minBalance)
                      ->setParameter('maxBalance', $maxBalance)
                      ->setParameter('active', true)
                      ->orderBy('cb.solde', 'DESC');

        if ($utilisateur) {
            $query->andWhere('cb.utilisateur = :utilisateur')
                  ->setParameter('utilisateur', $utilisateur);
        }

        return $query->getQuery()->getResult();
    }

    /**
     * Get average balance for a user
     */
    public function getAverageBalanceByUser($utilisateur): float
    {
        return (float)$this->createQueryBuilder('cb')
                           ->select('AVG(cb.solde) as avg_balance')
                           ->where('cb.utilisateur = :utilisateur')
                           ->andWhere('cb.actif = :active')
                           ->setParameter('utilisateur', $utilisateur)
                           ->setParameter('active', true)
                           ->getQuery()
                           ->getSingleScalarResult() ?? 0;
    }

    /**
     * Update account balance by amount (can be positive or negative)
     */
    public function updateBalance($accountId, float $amount): bool
    {
        try {
            $this->createQueryBuilder('cb')
                 ->update()
                 ->set('cb.solde', 'cb.solde + :amount')
                 ->where('cb.id = :id')
                 ->setParameter('id', $accountId)
                 ->setParameter('amount', $amount)
                 ->getQuery()
                 ->execute();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if user has sufficient balance
     */
    public function hasMinimumBalance($utilisateur, float $amount): bool
    {
        $totalBalance = $this->getTotalBalanceByUser($utilisateur);
        return $totalBalance >= $amount;
    }

    /**
     * Get all account balances by user with details
     */
    public function getBalanceDetailsbyUser($utilisateur): array
    {
        return $this->createQueryBuilder('cb')
                    ->select('cb.id', 'cb.numero_compte', 'cb.titulaire', 'cb.solde', 'cb.devise', 'cb.type_compte', 'cb.actif')
                    ->where('cb.utilisateur = :utilisateur')
                    ->setParameter('utilisateur', $utilisateur)
                    ->orderBy('cb.actif', 'DESC')
                    ->addOrderBy('cb.solde', 'DESC')
                    ->getQuery()
                    ->getResult();
    }

    /**
     * Get balance statistics for admin dashboard
     */
    public function getBalanceStatistics(): array
    {
        $result = $this->createQueryBuilder('cb')
                       ->select(
                           'COUNT(cb.id) as total_accounts',
                           'SUM(cb.solde) as total_balance',
                           'AVG(cb.solde) as average_balance',
                           'MAX(cb.solde) as highest_balance',
                           'MIN(cb.solde) as lowest_balance'
                       )
                       ->where('cb.actif = :active')
                       ->setParameter('active', true)
                       ->getQuery()
                       ->getOneOrNullResult();

        return $result ?? [
            'total_accounts' => 0,
            'total_balance' => 0,
            'average_balance' => 0,
            'highest_balance' => 0,
            'lowest_balance' => 0,
        ];
    }
}
