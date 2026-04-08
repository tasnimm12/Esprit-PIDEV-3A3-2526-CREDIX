<?php

namespace App\EventListener;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class SessionUserListener implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TokenStorageInterface $tokenStorage
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            // Priority 100 to run AFTER session listener (which runs at 128)
            KernelEvents::REQUEST => ['onKernelRequest', 100],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        
        // Only proceed if session is started
        if (!$request->hasSession()) {
            return;
        }
        
        $session = $request->getSession();
        
        // Check if there's already a valid token
        $token = $this->tokenStorage->getToken();
        if ($token && $token->isAuthenticated()) {
            return; // Already authenticated
        }
        
        // Try to load user from session safely
        try {
            // Check if user_id is stored in session
            if ($session->has('user_id')) {
                $userId = $session->get('user_id');
                
                // Load user from database
                $user = $this->entityManager->getRepository(Utilisateur::class)->find($userId);
                
                if ($user) {
                    // Create and set authentication token with firewall name 'main'
                    $token = new UsernamePasswordToken(
                        $user,
                        'main',
                        $user->getRoles()
                    );
                    $this->tokenStorage->setToken($token);
                } else {
                    // User not found, clear session
                    $session->remove('user_id');
                }
            }
        } catch (\Throwable $e) {
            // If session deserialization fails, just skip user loading
            // This can happen if session data contains references to deleted entities
            // Log the error but don't fail the request
            error_log('Session loading error: ' . $e->getMessage());
        }
    }
}
