<?php

namespace App\Controller;

use App\Entity\CompteBancaire;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/compte-bancaire')]
class CompteBancaireController extends AbstractController
{

    #[Route('/', name: 'app_compte_bancaire_index')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $comptes = $entityManager->getRepository(CompteBancaire::class)->findBy(['utilisateur' => $user]);

        return $this->render('compte_bancaire/index.html.twig', [
            'comptes' => $comptes,
        ]);
    }

    #[Route('/new', name: 'app_compte_bancaire_new')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $errors = [];

        if ($request->isMethod('POST')) {
            // Get and sanitize input
            $numeroCompte = trim($request->request->get('numero_compte', ''));
            $titulaire = trim($request->request->get('titulaire', ''));
            $email = trim($request->request->get('email', ''));
            $telephone = trim($request->request->get('telephone', ''));
            $solde = $request->request->get('solde', '0');
            $devise = trim($request->request->get('devise', 'USD'));
            $typeCompte = trim($request->request->get('type_compte', ''));

            // Validate required fields
            if (empty($numeroCompte) || strlen($numeroCompte) < 5 || strlen($numeroCompte) > 34) {
                $errors[] = 'Le numéro de compte doit faire entre 5 et 34 caractères';
            }
            if (empty($titulaire) || strlen($titulaire) < 2 || strlen($titulaire) > 100) {
                $errors[] = 'Le nom du titulaire doit faire entre 2 et 100 caractères';
            }
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Veuillez fournir une adresse e-mail valide';
            }
            if (empty($telephone) || !preg_match('/^[+]?[(]?[0-9]{1,4}[)]?[-\s\.]?[(]?[0-9]{1,4}[)]?[-\s\.]?[0-9]{1,9}$/', $telephone)) {
                $errors[] = 'Veuillez fournir un numéro de téléphone valide';
            }
            if (empty($solde) || !is_numeric($solde) || $solde < 0 || $solde > 999999999) {
                $errors[] = 'Le solde doit être un nombre valide entre 0 et 999999999';
            }
            if (empty($devise) || strlen($devise) < 2 || strlen($devise) > 10) {
                $errors[] = 'La devise doit faire entre 2 et 10 caractères';
            }
            if (empty($typeCompte) || !in_array($typeCompte, ['Courant', 'Épargne', 'Titre'])) {
                $errors[] = 'Veuillez sélectionner un type de compte valide';
            }

            if (empty($errors)) {
                $compte = new CompteBancaire();
                $compte->setUtilisateur($user);
                $compte->setNumeroCompte($numeroCompte);
                $compte->setTitulaire($titulaire);
                $compte->setEmail($email);
                $compte->setTelephone($telephone);
                $compte->setSolde((float)$solde);
                $compte->setDevise($devise);
                $compte->setTypeCompte($typeCompte);
                $compte->setDateCreation(new \DateTime());
                $compte->setActif(true);

                $entityManager->persist($compte);
                $entityManager->flush();

                $this->addFlash('success', 'Compte bancaire créé avec succès!');
                return $this->redirectToRoute('app_compte_bancaire_index');
            }
        }

        return $this->render('compte_bancaire/new.html.twig', [
            'errors' => $errors,
        ]);
    }

    #[Route('/{id<\d+>}', name: 'app_compte_bancaire_show')]
    public function show(CompteBancaire $compte, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user || $compte->getUtilisateur()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Access denied');
        }

        return $this->render('compte_bancaire/show.html.twig', [
            'compte' => $compte,
        ]);
    }

    #[Route('/{id<\d+>}/edit', name: 'app_compte_bancaire_edit')]
    public function edit(CompteBancaire $compte, Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user || $compte->getUtilisateur()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Access denied');
        }

        $errors = [];

        if ($request->isMethod('POST')) {
            // Get and sanitize input
            $titulaire = trim($request->request->get('titulaire', ''));
            $email = trim($request->request->get('email', ''));
            $telephone = trim($request->request->get('telephone', ''));
            $solde = $request->request->get('solde', '');
            $devise = trim($request->request->get('devise', ''));
            $typeCompte = trim($request->request->get('type_compte', ''));

            // Validate fields
            if (empty($titulaire) || strlen($titulaire) < 2 || strlen($titulaire) > 100) {
                $errors[] = 'Account holder name must be between 2 and 100 characters';
            }
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Please provide a valid email address';
            }
            if (empty($telephone) || !preg_match('/^[+]?[(]?[0-9]{1,4}[)]?[-\s\.]?[(]?[0-9]{1,4}[)]?[-\s\.]?[0-9]{1,9}$/', $telephone)) {
                $errors[] = 'Please provide a valid phone number';
            }
            if (empty($solde) || !is_numeric($solde) || $solde < 0 || $solde > 999999999) {
                $errors[] = 'Balance must be a valid number between 0 and 999999999';
            }
            if (empty($devise) || strlen($devise) < 2 || strlen($devise) > 10) {
                $errors[] = 'Currency must be between 2 and 10 characters';
            }
            if (empty($typeCompte) || !in_array($typeCompte, ['Courant', 'Épargne', 'Titre'])) {
                $errors[] = 'Please select a valid account type';
            }

            if (empty($errors)) {
                $compte->setTitulaire($titulaire);
                $compte->setEmail($email);
                $compte->setTelephone($telephone);
                $compte->setSolde((float)$solde);
                $compte->setDevise($devise);
                $compte->setTypeCompte($typeCompte);
                $compte->setActif((bool)$request->request->get('actif'));

                $entityManager->flush();
                $this->addFlash('success', 'Compte bancaire mis à jour!');
                return $this->redirectToRoute('app_compte_bancaire_show', ['id' => $compte->getId()]);
            }
        }

        return $this->render('compte_bancaire/edit.html.twig', [
            'compte' => $compte,
            'errors' => $errors,
        ]);
    }

    #[Route('/{id<\d+>}/delete', name: 'app_compte_bancaire_delete')]
    public function delete(CompteBancaire $compte, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user || $compte->getUtilisateur()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Access denied');
        }

        $entityManager->remove($compte);
        $entityManager->flush();
        $this->addFlash('success', 'Compte bancaire supprimé!');
        return $this->redirectToRoute('app_compte_bancaire_index');
    }
}
