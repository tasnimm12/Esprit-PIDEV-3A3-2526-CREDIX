<?php

namespace App\Controller;

use App\Entity\Projet;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use DateTime;

#[Route('/projet')]
class ProjetController extends AbstractController
{
    /**
     * List all projects (users can see all, admins can manage)
     */
    #[Route('/', name: 'app_projet_list')]
    public function list(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Get search, filter, and sort parameters
        $search = $request->query->get('search', '');
        $secteur = $request->query->get('secteur', '');
        $statut = $request->query->get('statut', '');
        $sortBy = $request->query->get('sort', 'date_debut');
        $sortOrder = $request->query->get('order', 'DESC');

        // Validate sort order
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

        // Use repository method for filtering
        /** @var \App\Repository\ProjetRepository $projetRepository */
        $projetRepository = $entityManager->getRepository(Projet::class);
        
        // Map sort parameter
        $sortField = match($sortBy) {
            'name' => 'nomprojet',
            'target' => 'montant_objectif',
            'status' => 'statut_projet',
            'date_debut' => 'date_debut',
            default => 'date_debut',
        };
        
        $projets = $projetRepository->findFiltered($search, $secteur, $statut, $sortField, $sortOrder);

        // Get unique sectors for filter
        $secteurs = $projetRepository->getAllSectors();
        $secteurList = array_map(function($item) {
            return $item['secteur'] ?? $item[1];
        }, $secteurs);

        return $this->render('projet/list.html.twig', [
            'projets' => $projets,
            'isAdmin' => $user instanceof Utilisateur && $user->getRole() === 'admin',
            'search' => $search,
            'secteur' => $secteur,
            'statut' => $statut,
            'sortBy' => $sortBy,
            'sortOrder' => strtolower($sortOrder),
            'secteurs' => $secteurList,
            'statutOptions' => ['ACTIF', 'SUSPENDU', 'TERMINE', 'ANNULE'],
        ]);
    }

    /**
     * View project details
     */
    #[Route('/{id}', name: 'app_projet_view')]
    public function view($id, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $projet = $entityManager->getRepository(Projet::class)->find($id);
        if (!$projet) {
            throw $this->createNotFoundException('Project not found');
        }

        // Get total investments for this project
        $investissementRepository = $entityManager->getRepository(\App\Entity\Investissement::class);
        $investissements = $investissementRepository->findBy(['projet' => $projet]);
        
        $totalInvesti = 0;
        $countInvestisseurs = count($investissements);
        foreach ($investissements as $inv) {
            $totalInvesti += (float)$inv->getMontantinvesti();
        }

        // Calculate progress percentage
        $montantObjectif = (float)$projet->getMontantObjectif();
        $progressPercentage = $montantObjectif > 0 ? ($totalInvesti / $montantObjectif) * 100 : 0;

        return $this->render('projet/view.html.twig', [
            'projet' => $projet,
            'totalInvesti' => $totalInvesti,
            'countInvestisseurs' => $countInvestisseurs,
            'progressPercentage' => min($progressPercentage, 100),
            'isAdmin' => $user instanceof Utilisateur && $user->getRole() === 'admin',
        ]);
    }

    /**
     * Create a new project (Admin only) - MOVED TO ADMIN DASHBOARD
     * 
     * @deprecated Use AdminController::createProjet() instead
     */
    #[Route('/admin/create', name: 'app_projet_create', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): Response
    {
        // Redirect to admin dashboard project creation
        return $this->redirectToRoute('app_admin_projet_create');
    }

    /**
     * Edit a project (Admin only) - MOVED TO ADMIN DASHBOARD
     * 
     * @deprecated Use AdminController::editProjet() instead
     */
    #[Route('/{id}/edit', name: 'app_projet_edit', methods: ['GET', 'POST'])]
    public function edit($id, Request $request, EntityManagerInterface $entityManager): Response
    {
        // Redirect to admin dashboard project edit
        return $this->redirectToRoute('app_admin_projet_edit', ['id' => $id]);
    }

    /**
     * Delete a project (Admin only) - MOVED TO ADMIN DASHBOARD
     * 
     * @deprecated Use AdminController::deleteProjet() instead
     */
    #[Route('/{id}/delete', name: 'app_projet_delete', methods: ['POST'])]
    public function delete($id, Request $request, EntityManagerInterface $entityManager): Response
    {
        // Redirect to admin dashboard project delete
        return $this->redirectToRoute('app_admin_projet_delete', ['id' => $id]);
    }
}
