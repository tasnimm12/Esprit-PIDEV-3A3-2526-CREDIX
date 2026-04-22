<?php

namespace App\Controller;

use App\Entity\LoginHistorique;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/login-history')]
class AdminLoginHistoryController extends AbstractController
{
    #[Route('', name: 'app_admin_login_history')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || $this->getUser()->getRole() !== 'admin') {
            throw $this->createAccessDeniedException('Access denied');
        }

        /** @var \App\Repository\LoginHistoriqueRepository $repository */
        $repository = $entityManager->getRepository(LoginHistorique::class);
        $logs = $repository->findLatest(200);

        return $this->render('admin/login_history.html.twig', [
            'logs' => $logs,
        ]);
    }
}
