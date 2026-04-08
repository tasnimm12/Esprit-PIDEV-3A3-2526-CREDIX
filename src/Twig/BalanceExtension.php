<?php

namespace App\Twig;

use App\Repository\CompteBancaireRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class BalanceExtension extends AbstractExtension implements GlobalsInterface
{
    private CompteBancaireRepository $compteBancaireRepository;
    private Security $security;

    public function __construct(CompteBancaireRepository $compteBancaireRepository, Security $security)
    {
        $this->compteBancaireRepository = $compteBancaireRepository;
        $this->security = $security;
    }

    public function getGlobals(): array
    {
        $user = $this->security->getUser();
        $balanceKpi = [
            'totalBalance' => 0,
            'accountCount' => 0,
            'averageBalance' => 0,
            'highestBalance' => 0,
            'userBalance' => 0,
            'userAccountCount' => 0,
        ];

        if ($user) {
            try {
                // Get individual user balance stats
                $balanceKpi['userBalance'] = $this->compteBancaireRepository->getTotalBalanceByUser($user);
                $balanceKpi['userAccountCount'] = count($this->compteBancaireRepository->findBy(['utilisateur' => $user]));

                // Get global stats for display
                $stats = $this->compteBancaireRepository->getBalanceStatistics();
                $balanceKpi['totalBalance'] = $stats['totalBalance'] ?? 0;
                $balanceKpi['accountCount'] = $stats['accountCount'] ?? 0;
                $balanceKpi['averageBalance'] = $stats['averageBalance'] ?? 0;
                $balanceKpi['highestBalance'] = $stats['highestBalance'] ?? 0;
            } catch (\Exception $e) {
                // If database query fails, return default empty balanceKpi
            }
        }

        return [
            'balanceKpi' => $balanceKpi,
        ];
    }
}

