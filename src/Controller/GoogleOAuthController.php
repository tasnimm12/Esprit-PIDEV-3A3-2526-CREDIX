<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class GoogleOAuthController extends AbstractController
{
    #[Route('/connect/google', name: 'connect_google')]
    public function connectGoogle(): Response
    {
        $clientId = $_ENV['OAUTH_GOOGLE_CLIENT_ID'] ?? '';
        
        if (!$clientId) {
            $this->addFlash('error', 'Google OAuth not configured. Please set OAUTH_GOOGLE_CLIENT_ID in .env.local');
            return $this->redirectToRoute('app_signup');
        }

        $redirectUri = 'http://localhost:8000/connect/google/check';
        $scope = 'openid profile email';

        $googleAuthUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => $scope,
            'access_type' => 'online'
        ]);

        return $this->redirect($googleAuthUrl);
    }

    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function connectGoogleCheck(): Response
    {
        // Route handled by GoogleAuthenticator
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        return $this->redirectToRoute('app_login');
    }
}
