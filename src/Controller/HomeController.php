<?php

namespace App\Controller;

use App\Service\AssuranceService;
use App\Service\ContratAssuranceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(AssuranceService $assuranceService, ContratAssuranceService $contratService): Response
    {
        $assurances = $assuranceService->findAll();
        $contrats = $contratService->findAll();

        return $this->render('home/index.html.twig', [
            'title' => 'Home',
            'assurances' => $assurances,
            'contrats' => $contrats,
        ]);
    }

    #[Route('/about', name: 'app_about')]
    public function about(): Response
    {
        return $this->render('home/about.html.twig', [
            'title' => 'About Us',
        ]);
    }

    #[Route('/service', name: 'app_service')]
    public function service(): Response
    {
        return $this->render('home/service.html.twig', [
            'title' => 'Services',
        ]);
    }

    #[Route('/project', name: 'app_project')]
    public function project(): Response
    {
        return $this->render('home/project.html.twig', [
            'title' => 'Projects',
        ]);
    }

    #[Route('/feature', name: 'app_feature')]
    public function feature(): Response
    {
        return $this->render('home/feature.html.twig', [
            'title' => 'Features',
        ]);
    }

    #[Route('/team', name: 'app_team')]
    public function team(): Response
    {
        return $this->render('home/team.html.twig', [
            'title' => 'Team',
        ]);
    }

    #[Route('/testimonial', name: 'app_testimonial')]
    public function testimonial(): Response
    {
        return $this->render('home/testimonial.html.twig', [
            'title' => 'Testimonials',
        ]);
    }

    #[Route('/contact', name: 'app_contact')]
    public function contact(): Response
    {
        return $this->render('home/contact.html.twig', [
            'title' => 'Contact',
        ]);
    }
}

