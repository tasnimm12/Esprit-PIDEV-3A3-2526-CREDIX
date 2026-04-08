<?php

namespace App\Security;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Psr\Log\LoggerInterface;

class GoogleAuthenticator extends AbstractAuthenticator
{
    private $em;
    private $logger;
    private $googleClientId;
    private $googleClientSecret;

    public function __construct(EntityManagerInterface $em, LoggerInterface $logger)
    {
        $this->em = $em;
        $this->logger = $logger;
        $this->googleClientId = $_ENV['OAUTH_GOOGLE_CLIENT_ID'] ?? '';
        $this->googleClientSecret = $_ENV['OAUTH_GOOGLE_CLIENT_SECRET'] ?? '';
    }

    public function supports(Request $request): ?bool
    {
        // Handle Google callback with code parameter
        return $request->getPathInfo() === '/connect/google/check' && $request->query->has('code');
    }

    public function authenticate(Request $request): Passport
    {
        $code = $request->query->get('code');
        
        if (!$code) {
            throw new AuthenticationException('No authorization code received from Google');
        }

        // Exchange code for access token
        $accessToken = $this->getGoogleAccessToken($code);
        
        if (!$accessToken) {
            throw new AuthenticationException('Failed to exchange code for access token');
        }

        // Get user info from Google
        $googleUser = $this->getGoogleUserInfo($accessToken);
        
        if (!$googleUser || empty($googleUser['email'])) {
            throw new AuthenticationException('Failed to retrieve user info from Google');
        }

        $email = $googleUser['email'];

        return new SelfValidatingPassport(
            new UserBadge($email, function() use ($googleUser) {
                $email = $googleUser['email'] ?? '';
                $firstName = $googleUser['given_name'] ?? 'User';
                $lastName = $googleUser['family_name'] ?? '';

                // Look for existing user by email
                $user = $this->em->getRepository(Utilisateur::class)->findOneBy(['email' => $email]);

                if (!$user) {
                    // Create new user from Google data
                    $user = new Utilisateur();
                    $user->setEmail($email);
                    $user->setPrenom($firstName);
                    $user->setNom($lastName);
                    $user->setRole('client');
                    
                    // Set phone verification complete for Google sign-up
                    $user->setPhoneVerified(true);
                    $user->setStatutCompte('ACTIF');
                    
                    // Generate a random password (Google login doesn't use it)
                    $user->setMotDePasse(password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT));
                    
                    // Set a temporary phone number or null
                    $user->setTelephone('0' . rand(10000000, 99999999));
                    
                    $this->em->persist($user);
                } else {
                    // User exists, mark phone as verified on Google login
                    $user->setPhoneVerified(true);
                    if ($user->getStatutCompte() !== 'ACTIF') {
                        $user->setStatutCompte('ACTIF');
                    }
                }

                $this->em->flush();

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return new Response(null, Response::HTTP_FOUND, ['Location' => '/']);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $this->logger->warning('Google authentication failed', ['error' => $exception->getMessage()]);
        return new Response('Authentication failed: ' . $exception->getMessage(), Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Exchange authorization code for access token
     */
    private function getGoogleAccessToken(string $code): ?string
    {
        $ch = curl_init('https://oauth2.googleapis.com/token');
        
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'code' => $code,
                'client_id' => $this->googleClientId,
                'client_secret' => $this->googleClientSecret,
                'redirect_uri' => $this->getRedirectUri(),
                'grant_type' => 'authorization_code'
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return $data['access_token'] ?? null;
        }

        $this->logger->error('Google token exchange failed', ['http_code' => $httpCode, 'response' => $response]);
        return null;
    }

    /**
     * Get user info from Google
     */
    private function getGoogleUserInfo(string $accessToken): ?array
    {
        $ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            return json_decode($response, true);
        }

        $this->logger->error('Google userinfo request failed', ['http_code' => $httpCode]);
        return null;
    }

    /**
     * Get or create user from Google data
     */
    private function getOrCreateUser(string $email, array $googleUser): Utilisateur
    {
        $user = $this->em->getRepository(Utilisateur::class)->findOneBy(['email' => $email]);

        if (!$user) {
            $user = new Utilisateur();
            $user->setEmail($email);
            $user->setNom($googleUser['family_name'] ?? 'User');
            $user->setPrenom($googleUser['given_name'] ?? ucfirst(explode('@', $email)[0]));
            $user->setRole('client');
            $user->setPhoneVerified(true);
            $user->setStatutCompte('ACTIF');
            $user->setMotDePasse(password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT));
            $user->setTelephone(null);

            $this->em->persist($user);
            $this->logger->info('New user created via Google OAuth', ['email' => $email]);
        } else {
            // Update existing user if needed
            if (!$user->isPhoneVerified()) {
                $user->setPhoneVerified(true);
            }
            if ($user->getStatutCompte() !== 'ACTIF') {
                $user->setStatutCompte('ACTIF');
            }
        }

        $this->em->flush();
        return $user;
    }

    private function getRedirectUri(): string
    {
        $request = null;
        return 'http://localhost:8000/connect/google/check';
    }
}

