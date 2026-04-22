<?php

namespace App\Twig;

use App\Repository\SinistreRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class AdminNotificationExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly SinistreRepository $sinistreRepository,
        private readonly Security $security
    ) {
    }

    public function getGlobals(): array
    {
        $data = [
            'pendingCount' => 0,
            'recentPendingClaims' => [],
        ];

        $user = $this->security->getUser();
        if (!$user || strtolower((string) $user->getRole()) !== 'admin') {
            return [
                'adminNotifications' => $data,
            ];
        }

        try {
            $data['pendingCount'] = $this->sinistreRepository->countPendingClaims();
            $data['recentPendingClaims'] = $this->sinistreRepository->getRecentPendingClaims(5);
        } catch (\Throwable $e) {
            // Keep admin layout resilient if notification queries fail.
        }

        return [
            'adminNotifications' => $data,
        ];
    }
}
