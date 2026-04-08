<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class SessionPersistenceListener implements EventSubscriberInterface
{
    public function __construct(
        private TokenStorageInterface $tokenStorage
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onResponse', -100],
        ];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        
        if (!$request->hasSession()) {
            return;
        }

        $session = $request->getSession();
        $token = $this->tokenStorage->getToken();

        // If token exists and has a user, ensure user data is in session
        if ($token) {
            $user = $token->getUser();
            if ($user && method_exists($user, 'getId')) {
                $session->set('user_id', $user->getId());
                $session->set('user_name', $user->getPrenom() . ' ' . $user->getNom());
                $session->save();
            }
        }
    }
}
