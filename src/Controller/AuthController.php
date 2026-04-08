<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AuthController extends AbstractController
{
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(Request $request, EntityManagerInterface $em, TokenStorageInterface $tokenStorage, AuthenticationUtils $authenticationUtils): Response
    {
        // Redirect if already logged in
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $error = $authenticationUtils->getLastAuthenticationError();

        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $password = $request->request->get('password');

            // Find user by email
            $user = $em->getRepository(Utilisateur::class)->findOneBy(['email' => $email]);

            if (!$user) {
                $error = 'Invalid email or password';
                $this->addFlash('error', $error);
            } elseif (!password_verify($password, $user->getMotDePasse())) {
                $error = 'Invalid email or password';
                $this->addFlash('error', $error);
            } else {
                // Login successful - no phone verification required for signin
                // Create and set authentication token
                $token = new UsernamePasswordToken(
                    $user,
                    'main',
                    $user->getRoles()
                );
                $tokenStorage->setToken($token);

                $this->addFlash('success', 'Welcome back, ' . $user->getPrenom() . '!');
                return $this->redirectToRoute('app_home');
            }
        }

        return $this->render('auth/login.html.twig', [
            'error' => $error,
        ]);
    }

    #[Route('/signup', name: 'app_signup', methods: ['GET', 'POST'])]
    public function signup(Request $request, EntityManagerInterface $em, ValidatorInterface $validator, \App\Service\SmsService $smsService): Response
    {
        // Redirect if already logged in
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $errors = [];

        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            // Email validation
            if (empty($data['email'])) {
                $errors[] = 'Email is required';
            } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email format';
            } elseif (strlen($data['email']) > 255) {
                $errors[] = 'Email is too long (max 255 characters)';
            }

            // First name validation
            if (empty($data['prenom'])) {
                $errors[] = 'First name is required';
            } elseif (strlen($data['prenom']) < 2) {
                $errors[] = 'First name must be at least 2 characters';
            } elseif (strlen($data['prenom']) > 100) {
                $errors[] = 'First name is too long (max 100 characters)';
            } elseif (!preg_match('/^[a-zA-Z\s]+$/', $data['prenom'])) {
                $errors[] = 'First name must contain only letters and spaces';
            }

            // Last name validation
            if (empty($data['nom'])) {
                $errors[] = 'Last name is required';
            } elseif (strlen($data['nom']) < 2) {
                $errors[] = 'Last name must be at least 2 characters';
            } elseif (strlen($data['nom']) > 100) {
                $errors[] = 'Last name is too long (max 100 characters)';
            } elseif (!preg_match('/^[a-zA-Z\s]+$/', $data['nom'])) {
                $errors[] = 'Last name must contain only letters and spaces';
            }

            // Password validation
            if (empty($data['mot_de_passe'])) {
                $errors[] = 'Password is required';
            } elseif (strlen($data['mot_de_passe']) < 8) {
                $errors[] = 'Password must be at least 8 characters';
            } elseif (strlen($data['mot_de_passe']) > 100) {
                $errors[] = 'Password is too long (max 100 characters)';
            }

            // Password confirmation
            if (empty($data['confirm_password'])) {
                $errors[] = 'Please confirm your password';
            } elseif ($data['mot_de_passe'] !== $data['confirm_password']) {
                $errors[] = 'Passwords do not match';
            }

            // Phone validation (required, exactly 8 digits)
            if (empty($data['telephone'])) {
                $errors[] = 'Phone number is required';
            } elseif (!preg_match('/^\d{8}$/', $data['telephone'])) {
                $errors[] = 'Phone number must be exactly 8 digits';
            }

            // Terms checkbox
            if (empty($data['terms'])) {
                $errors[] = 'You must agree to the terms and privacy policy';
            }

            // Check if email already exists
            if (empty($errors)) {
                $existingUser = $em->getRepository(Utilisateur::class)->findOneBy(['email' => $data['email']]);
                if ($existingUser) {
                    $errors[] = 'Email already registered';
                }
            }

            if (empty($errors)) {
                // Create new user (not yet verified)
                $user = new Utilisateur();
                $user->setEmail($data['email']);
                $user->setNom($data['nom']);
                $user->setPrenom($data['prenom']);
                $user->setMotDePasse(password_hash($data['mot_de_passe'], PASSWORD_BCRYPT));
                $user->setTelephone($data['telephone'] ?? null);
                $user->setRole('client');
                $user->setPhoneVerified(false);

                // Generate 6-digit verification code
                $verificationCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $user->setVerificationCode($verificationCode);
                
                // Code expires in 15 minutes
                $expiryTime = new \DateTime('+15 minutes');
                $user->setVerificationCodeExpiry($expiryTime);

                // Validate entity
                $validationErrors = $validator->validate($user);
                if (count($validationErrors) > 0) {
                    foreach ($validationErrors as $validationError) {
                        $errors[] = $validationError->getPropertyPath() . ': ' . $validationError->getMessage();
                    }
                } else {
                    // Save user
                    $em->persist($user);
                    $em->flush();

                    // Send verification via SMS/Verify Service (with WhatsApp fallback)
                    $smsSent = $smsService->sendVerificationCode($user->getTelephone(), $verificationCode);
                    
                    // Optional: Also try WhatsApp Direct API if Verify Service not available
                    if (!$smsSent && getenv('WHATSAPP_API_METHOD') === 'api') {
                        $smsSent = $smsService->sendViaWhatsAppApi($user->getTelephone(), $verificationCode);
                    }
                    
                    if (!$smsSent) {
                        $this->addFlash('warning', 'Account created but verification failed. Please contact support.');
                    }

                    // Store user ID in session for verification step
                    $request->getSession()->set('pending_verification_user_id', $user->getId());
                    
                    $this->addFlash('success', 'Account created! A 6-digit verification code has been sent to your phone.');
                    return $this->redirectToRoute('app_verify_phone');
                }
            }

            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
            }
        }

        return $this->render('auth/signup.html.twig', [
            'errors' => $errors,
        ]);
    }

    #[Route('/verify-phone', name: 'app_verify_phone', methods: ['GET', 'POST'])]
    public function verifyPhone(Request $request, EntityManagerInterface $em, TokenStorageInterface $tokenStorage, \App\Service\SmsService $smsService): Response
    {
        // Get pending user ID from session
        $userId = $request->getSession()->get('pending_verification_user_id');
        
        if (!$userId) {
            $this->addFlash('error', 'Verification session expired. Please sign up again.');
            return $this->redirectToRoute('app_signup');
        }

        $user = $em->getRepository(Utilisateur::class)->find($userId);
        
        if (!$user) {
            $this->addFlash('error', 'User not found. Please sign up again.');
            return $this->redirectToRoute('app_signup');
        }

        $errors = [];

        if ($request->isMethod('POST')) {
            $code = $request->request->get('verification_code');

            // Validate code was provided
            if (empty($code)) {
                $errors[] = 'Please enter the verification code';
            }

            // Validate code format (6 digits)
            if (!empty($code) && !preg_match('/^\d{6}$/', $code)) {
                $errors[] = 'Verification code must be 6 digits';
            }

            if (empty($errors)) {
                // Verify code using SMS service (which uses Twilio Verify API)
                $isCodeValid = $smsService->verifyCode($user->getTelephone(), $code);
                
                // If Twilio verification fails, check database code 
                // (this is for WhatsApp Direct API fallback method)
                if (!$isCodeValid) {
                    // Check code against database
                    $storedCode = $user->getVerificationCode();
                    $codeExpiry = $user->getVerificationCodeExpiry();
                    
                    if ($storedCode && $storedCode === $code) {
                        // Check if code has expired
                        $now = new \DateTime();
                        if ($codeExpiry && $now <= $codeExpiry) {
                            $isCodeValid = true;  // Code is valid!
                        } else {
                            $errors[] = 'Verification code has expired';
                        }
                    } else {
                        $errors[] = 'Verification code is incorrect or has expired';
                    }
                }
                
                if ($isCodeValid && empty($errors)) {
                    // Code is valid - mark phone as verified and log user in
                    $user->setPhoneVerified(true);
                    $user->setStatutCompte('ACTIF');  // Set account status to active
                    $user->setVerificationCode(null);
                    $user->setVerificationCodeExpiry(null);
                    
                    $em->persist($user);
                    $em->flush();

                    // Clear pending verification session
                    $request->getSession()->remove('pending_verification_user_id');
                    
                    // Create authentication token (session will be persisted by listener)
                    $token = new UsernamePasswordToken(
                        $user,
                        'main',
                        $user->getRoles()
                    );
                    $tokenStorage->setToken($token);

                    $this->addFlash('success', 'Phone verified successfully! Welcome to Credix, ' . $user->getPrenom() . '!');
                    return $this->redirectToRoute('app_home');
                }
            }

            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
            }
        }

        return $this->render('auth/verify_phone.html.twig', [
            'user' => $user,
            'errors' => $errors,
        ]);
    }

    #[Route('/resend-verification-code', name: 'app_resend_verification_code', methods: ['GET'])]
    public function resendVerificationCode(Request $request, EntityManagerInterface $em, \App\Service\SmsService $smsService): Response
    {
        // Get pending user ID from session
        $userId = $request->getSession()->get('pending_verification_user_id');
        
        if (!$userId) {
            $this->addFlash('error', 'Verification session expired. Please sign up again.');
            return $this->redirectToRoute('app_signup');
        }

        $user = $em->getRepository(Utilisateur::class)->find($userId);
        
        if (!$user) {
            $this->addFlash('error', 'User not found. Please sign up again.');
            return $this->redirectToRoute('app_signup');
        }

        // Generate new 6-digit verification code
        $verificationCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $user->setVerificationCode($verificationCode);
        
        // Code expires in 15 minutes
        $expiryTime = new \DateTime('+15 minutes');
        $user->setVerificationCodeExpiry($expiryTime);

        $em->persist($user);
        $em->flush();

        // Send new verification via SMS/Verify Service (with WhatsApp fallback)
        $smsSent = $smsService->sendVerificationCode($user->getTelephone(), $verificationCode);
        
        // Optional: Also try WhatsApp Direct API if Verify Service not available
        if (!$smsSent && getenv('WHATSAPP_API_METHOD') === 'api') {
            $smsSent = $smsService->sendViaWhatsAppApi($user->getTelephone(), $verificationCode);
        }
        
        if ($smsSent) {
            $this->addFlash('success', 'A new 6-digit verification code has been sent to your phone.');
        } else {
            $this->addFlash('error', 'Failed to send verification code. Please try again.');
        }

        return $this->redirectToRoute('app_verify_phone');
    }

    #[Route('/logout', name: 'app_logout', methods: ['GET', 'POST'])]
    public function logout(Request $request): Response
    {
        $request->getSession()->invalidate();
        $request->getSession()->getFlashBag()->add('success', 'You have been logged out successfully');
        return $this->redirectToRoute('app_login');
    }

    #[Route('/profile', name: 'app_profile')]
    public function profile(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('auth/profile.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/profile/edit', name: 'app_profile_edit', methods: ['GET', 'POST'])]
    public function editProfile(Request $request, EntityManagerInterface $em, ValidatorInterface $validator): Response
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $errors = [];

        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            // First name validation
            if (!empty($data['prenom'])) {
                if (strlen($data['prenom']) < 2) {
                    $errors[] = 'First name must be at least 2 characters';
                } elseif (strlen($data['prenom']) > 100) {
                    $errors[] = 'First name is too long (max 100 characters)';
                } elseif (!preg_match('/^[a-zA-Z\s]+$/', $data['prenom'])) {
                    $errors[] = 'First name must contain only letters and spaces';
                } else {
                    $user->setPrenom($data['prenom']);
                }
            }

            // Last name validation
            if (!empty($data['nom'])) {
                if (strlen($data['nom']) < 2) {
                    $errors[] = 'Last name must be at least 2 characters';
                } elseif (strlen($data['nom']) > 100) {
                    $errors[] = 'Last name is too long (max 100 characters)';
                } elseif (!preg_match('/^[a-zA-Z\s]+$/', $data['nom'])) {
                    $errors[] = 'Last name must contain only letters and spaces';
                } else {
                    $user->setNom($data['nom']);
                }
            }

            // Email validation
            if (!empty($data['email'])) {
                if ($data['email'] !== $user->getEmail()) {
                    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                        $errors[] = 'Invalid email format';
                    } elseif (strlen($data['email']) > 255) {
                        $errors[] = 'Email is too long (max 255 characters)';
                    } else {
                        $existingUser = $em->getRepository(Utilisateur::class)->findOneBy(['email' => $data['email']]);
                        if ($existingUser) {
                            $errors[] = 'Email already registered';
                        } else {
                            $user->setEmail($data['email']);
                        }
                    }
                }
            }

            // Phone validation (required, exactly 8 digits)
            if (empty($data['telephone'])) {
                $errors[] = 'Phone number is required';
            } elseif (!preg_match('/^\d{8}$/', $data['telephone'])) {
                $errors[] = 'Phone number must be exactly 8 digits';
            } else {
                $user->setTelephone($data['telephone']);
            }

            // Handle password change
            if (!empty($data['mot_de_passe'])) {
                if (strlen($data['mot_de_passe']) < 8) {
                    $errors[] = 'New password must be at least 8 characters';
                } elseif (strlen($data['mot_de_passe']) > 100) {
                    $errors[] = 'New password is too long (max 100 characters)';
                } elseif (empty($data['confirm_password'])) {
                    $errors[] = 'Please confirm your new password';
                } elseif ($data['mot_de_passe'] !== $data['confirm_password']) {
                    $errors[] = 'Passwords do not match';
                } else {
                    $user->setMotDePasse(password_hash($data['mot_de_passe'], PASSWORD_BCRYPT));
                }
            } elseif (!empty($data['confirm_password'])) {
                $errors[] = 'New password is required if you enter a confirmation password';
            }

            if (empty($errors)) {
                $em->flush();
                $this->addFlash('success', 'Profile updated successfully');
                return $this->redirectToRoute('app_profile');
            } else {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
            }
        }

        return $this->render('auth/edit_profile.html.twig', [
            'user' => $user,
            'errors' => $errors,
        ]);
    }
}
