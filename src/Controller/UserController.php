<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/user')]
class UserController extends AbstractController
{
    #[Route('/', name: 'app_user_list')]
    public function list(Request $request, EntityManagerInterface $em): Response
    {
        $users = $em->getRepository(Utilisateur::class)->findAll();
        
        // Get filter parameters
        $search = $request->query->get('search', '');
        $role = $request->query->get('role', '');
        $sort = $request->query->get('sort', '');
        
        // Filter by search
        if ($search) {
            $users = array_filter($users, function($user) use ($search) {
                return stripos($user->getNom() ?? '', $search) !== false ||
                       stripos($user->getPrenom() ?? '', $search) !== false ||
                       stripos($user->getEmail() ?? '', $search) !== false ||
                       stripos($user->getTelephone() ?? '', $search) !== false;
            });
        }
        
        // Filter by role
        if ($role) {
            $users = array_filter($users, function($user) use ($role) {
                return $user->getRole() === $role;
            });
        }
        
        // Sort
        usort($users, function($a, $b) use ($sort) {
            switch ($sort) {
                case 'name_asc':
                    return (($a->getNom() ?? '') . ' ' . ($a->getPrenom() ?? '')) <=> 
                           (($b->getNom() ?? '') . ' ' . ($b->getPrenom() ?? ''));
                case 'name_desc':
                    return (($b->getNom() ?? '') . ' ' . ($b->getPrenom() ?? '')) <=> 
                           (($a->getNom() ?? '') . ' ' . ($a->getPrenom() ?? ''));
                case 'recent':
                    return ($b->getDateInscription() ?? new \DateTime(0)) <=> 
                           ($a->getDateInscription() ?? new \DateTime(0));
                case 'oldest':
                    return ($a->getDateInscription() ?? new \DateTime(0)) <=> 
                           ($b->getDateInscription() ?? new \DateTime(0));
                default:
                    return 0;
            }
        });

        return $this->render('user/list.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/new', name: 'app_user_new')]
    public function new(Request $request, EntityManagerInterface $em, ValidatorInterface $validator): Response
    {
        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $errors = [];
            
            // Validate name fields
            if (empty($data['prenom'])) {
                $errors[] = 'First name is required';
            } elseif (strlen($data['prenom']) < 2 || strlen($data['prenom']) > 100) {
                $errors[] = 'First name must be between 2 and 100 characters';
            } elseif (!preg_match('/^[a-zA-Z\s\-\']+$/', $data['prenom'])) {
                $errors[] = 'First name can only contain letters, spaces, hyphens and apostrophes';
            }
            
            if (empty($data['nom'])) {
                $errors[] = 'Last name is required';
            } elseif (strlen($data['nom']) < 2 || strlen($data['nom']) > 100) {
                $errors[] = 'Last name must be between 2 and 100 characters';
            } elseif (!preg_match('/^[a-zA-Z\s\-\']+$/', $data['nom'])) {
                $errors[] = 'Last name can only contain letters, spaces, hyphens and apostrophes';
            }
            
            // Validate email
            if (empty($data['email'])) {
                $errors[] = 'Email is required';
            } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email format';
            } elseif (strlen($data['email']) > 255) {
                $errors[] = 'Email must not exceed 255 characters';
            }
            
            // Validate phone format
            if (!empty($data['telephone'])) {
                if (!preg_match('/^[0-9\s\-\+\(\)]{7,20}$/', $data['telephone'])) {
                    $errors[] = 'Invalid phone format';
                }
            }
            
            // Validate password
            if (empty($data['mot_de_passe'])) {
                $errors[] = 'Password is required';
            } elseif (strlen($data['mot_de_passe']) < 8) {
                $errors[] = 'Password must be at least 8 characters';
            } elseif (strlen($data['mot_de_passe']) > 100) {
                $errors[] = 'Password must not exceed 100 characters';
            }
            
            // Validate role
            if (empty($data['role']) || !in_array($data['role'], ['admin', 'client', 'organisateur'])) {
                $errors[] = 'Invalid role selected';
            }
            
            // Check for existing email
            if (empty($errors)) {
                $existingUser = $em->getRepository(Utilisateur::class)->findOneBy(['email' => $data['email']]);
                if ($existingUser) {
                    $errors[] = 'Email already exists';
                }
            }
            
            if (!empty($errors)) {
                $this->addFlash('error', 'Validation failed');
                return $this->render('user/form.html.twig', [
                    'errors' => $errors,
                ]);
            }
            
            $user = new Utilisateur();
            $user->setNom(htmlspecialchars($data['nom']));
            $user->setPrenom(htmlspecialchars($data['prenom']));
            $user->setEmail(htmlspecialchars($data['email']));
            $user->setTelephone(htmlspecialchars($data['telephone'] ?? ''));
            $user->setMotDePasse(password_hash($data['mot_de_passe'], PASSWORD_BCRYPT));
            $user->setRole($data['role']);
            
            $em->persist($user);
            $em->flush();
            
            $this->addFlash('success', 'User created successfully');
            return $this->redirectToRoute('app_user_show', ['id' => $user->getId()]);
        }

        return $this->render('user/form.html.twig', [
            'user' => new Utilisateur(),
        ]);
    }

    #[Route('/{id}', name: 'app_user_show')]
    public function show(Utilisateur $user): Response
    {
        return $this->render('user/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_user_edit')]
    public function edit(Request $request, Utilisateur $user, EntityManagerInterface $em, ValidatorInterface $validator): Response
    {
        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $errors = [];
            
            // Validate name fields
            if (empty($data['prenom'])) {
                $errors[] = 'First name is required';
            } elseif (strlen($data['prenom']) < 2 || strlen($data['prenom']) > 100) {
                $errors[] = 'First name must be between 2 and 100 characters';
            } elseif (!preg_match('/^[a-zA-Z\s\-\']+$/', $data['prenom'])) {
                $errors[] = 'First name can only contain letters, spaces, hyphens and apostrophes';
            }
            
            if (empty($data['nom'])) {
                $errors[] = 'Last name is required';
            } elseif (strlen($data['nom']) < 2 || strlen($data['nom']) > 100) {
                $errors[] = 'Last name must be between 2 and 100 characters';
            } elseif (!preg_match('/^[a-zA-Z\s\-\']+$/', $data['nom'])) {
                $errors[] = 'Last name can only contain letters, spaces, hyphens and apostrophes';
            }
            
            // Validate email
            if (empty($data['email'])) {
                $errors[] = 'Email is required';
            } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email format';
            } elseif (strlen($data['email']) > 255) {
                $errors[] = 'Email must not exceed 255 characters';
            }
            
            // Check for existing email (if changed)
            if (!empty($errors)) {
                $this->addFlash('error', 'Validation failed');
                return $this->render('user/form.html.twig', [
                    'user' => $user,
                    'errors' => $errors,
                ]);
            }
            
            if ($data['email'] !== $user->getEmail()) {
                $existingUser = $em->getRepository(Utilisateur::class)->findOneBy(['email' => $data['email']]);
                if ($existingUser) {
                    $this->addFlash('error', 'Email already exists');
                    return $this->render('user/form.html.twig', [
                        'user' => $user,
                    ]);
                }
            }
            
            // Validate phone format
            if (!empty($data['telephone'])) {
                if (!preg_match('/^[0-9\s\-\+\(\)]{7,20}$/', $data['telephone'])) {
                    $this->addFlash('error', 'Invalid phone format');
                    return $this->render('user/form.html.twig', [
                        'user' => $user,
                    ]);
                }
            }
            
            // Validate password if provided
            if (!empty($data['mot_de_passe'])) {
                if (strlen($data['mot_de_passe']) < 8) {
                    $this->addFlash('error', 'Password must be at least 8 characters');
                    return $this->render('user/form.html.twig', [
                        'user' => $user,
                    ]);
                } elseif (strlen($data['mot_de_passe']) > 100) {
                    $this->addFlash('error', 'Password must not exceed 100 characters');
                    return $this->render('user/form.html.twig', [
                        'user' => $user,
                    ]);
                }
            }
            
            // Validate role
            if (empty($data['role']) || !in_array($data['role'], ['admin', 'client', 'organisateur'])) {
                $this->addFlash('error', 'Invalid role selected');
                return $this->render('user/form.html.twig', [
                    'user' => $user,
                ]);
            }
            
            // Update user
            $user->setNom(htmlspecialchars($data['nom']));
            $user->setPrenom(htmlspecialchars($data['prenom']));
            $user->setEmail(htmlspecialchars($data['email']));
            $user->setTelephone(htmlspecialchars($data['telephone'] ?? ''));
            $user->setRole($data['role']);
            if (!empty($data['mot_de_passe'])) {
                $user->setMotDePasse(password_hash($data['mot_de_passe'], PASSWORD_BCRYPT));
            }
            $user->setActif(isset($data['actif']));
            
            $em->flush();
            $this->addFlash('success', 'User updated successfully');
            return $this->redirectToRoute('app_user_show', ['id' => $user->getId()]);
        }

        return $this->render('user/form.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_user_delete')]
    public function delete(Request $request, Utilisateur $user, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $em->remove($user);
            $em->flush();
            $this->addFlash('success', 'User deleted successfully');
            return $this->redirectToRoute('app_user_list');
        }

        return $this->render('user/delete.html.twig', [
            'user' => $user,
        ]);
    }
}
