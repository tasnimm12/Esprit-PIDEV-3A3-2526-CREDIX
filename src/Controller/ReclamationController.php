<?php

namespace App\Controller;

use App\Entity\Reclamation;
use App\Entity\Abonnement;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class ReclamationController extends AbstractController
{
    #[Route('/reclamation', name: 'app_reclamation_list')]
    #[IsGranted('ROLE_USER')]
    public function list(EntityManagerInterface $em): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        $reclamations = $em->getRepository(Reclamation::class)->findByUser($user);

        return $this->render('reclamation/list.html.twig', [
            'reclamations' => $reclamations,
        ]);
    }

    #[Route('/reclamation/new', name: 'app_reclamation_new')]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        $abonnementId = $request->query->get('abonnement_id');
        $abonnement = null;

        if ($abonnementId) {
            $abonnement = $em->getRepository(Abonnement::class)->find($abonnementId);
        }

        return $this->render('reclamation/form.html.twig', [
            'abonnement' => $abonnement,
        ]);
    }

    #[Route('/reclamation/submit', name: 'app_reclamation_submit', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function submit(Request $request, EntityManagerInterface $em, MailerInterface $mailer): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        $data = $request->request->all();
        $errors = [];

        // Validate CSRF token
        if (!$this->isCsrfTokenValid('reclamation_create', $data['_token'] ?? '')) {
            $this->addFlash('error', 'Security token is invalid. Please try again.');
            return $this->redirectToRoute('app_reclamation_new');
        }

        // Validate required fields
        if (empty($data['subject'])) {
            $errors[] = 'Subject is required';
        } elseif (strlen(trim($data['subject'])) < 5) {
            $errors[] = 'Subject must be at least 5 characters';
        }

        if (empty($data['description'])) {
            $errors[] = 'Description is required';
        } elseif (strlen(trim($data['description'])) < 20) {
            $errors[] = 'Description must be at least 20 characters';
        }

        if (empty($data['category'])) {
            $errors[] = 'Category is required';
        } elseif (!in_array($data['category'], ['general', 'billing', 'technical', 'service'])) {
            $errors[] = 'Invalid category selected';
        }

        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
            return $this->redirectToRoute('app_reclamation_new');
        }

        // Get associated abonnement if provided
        $abonnement = null;
        if (!empty($data['abonnement_id'])) {
            $abonnement = $em->getRepository(Abonnement::class)->find((int)$data['abonnement_id']);
        }

        // Create reclamation
        $reclamation = new Reclamation();
        $reclamation->setUser($user);
        $reclamation->setSubject(trim($data['subject']));
        $reclamation->setDescription(trim($data['description']));
        $reclamation->setCategory($data['category']);
        if ($abonnement) {
            $reclamation->setAbonnement($abonnement);
        }

        $em->persist($reclamation);
        $em->flush();

        // Send email to admin
        $this->sendAdminNotification($reclamation, $mailer);

        $this->addFlash('success', 'Your reclamation has been submitted successfully. We will review it shortly.');
        return $this->redirectToRoute('app_reclamation_list');
    }

    private function sendAdminNotification(Reclamation $reclamation, MailerInterface $mailer): void
    {
        try {
            $adminEmail = $_ENV['APP_EMAIL_FROM'] ?? 'mediounimontassar61@gmail.com'; // Send to yourself for testing

            $subject = sprintf(
                'New Reclamation from %s - %s',
                $reclamation->getUser()->getEmail(),
                $reclamation->getSubject()
            );

            $htmlContent = sprintf(
                '<html>
                <head>
                    <style>
                        body { font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; background: #f8f9fa; padding: 20px; border-radius: 8px; }
                        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 8px 8px 0 0; text-align: center; }
                        .header h2 { margin: 0; font-size: 28px; }
                        .content { background: white; padding: 30px; border-radius: 0 0 8px 8px; }
                        .info-block { margin: 20px 0; padding: 15px; background: #f0f4ff; border-left: 4px solid #667eea; border-radius: 4px; }
                        .info-block strong { color: #667eea; }
                        .section-title { font-size: 18px; font-weight: bold; color: #667eea; margin-top: 25px; margin-bottom: 12px; border-bottom: 2px solid #667eea; padding-bottom: 8px; }
                        .description-box { background: #f9f9f9; padding: 15px; border-radius: 4px; margin: 15px 0; border: 1px solid #e0e0e0; white-space: pre-wrap; word-wrap: break-word; }
                        .cta-button { display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 30px; text-decoration: none; border-radius: 4px; margin-top: 20px; font-weight: bold; }
                        .footer { text-align: center; padding: 20px; font-size: 12px; color: #999; border-top: 1px solid #e0e0e0; margin-top: 20px; }
                        .badge { display: inline-block; background: #667eea; color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <h2>🎯 New Reclamation Submitted</h2>
                        </div>
                        <div class="content">
                            <div class="info-block">
                                <strong>From:</strong> %s<br>
                                <strong>User:</strong> %s
                            </div>
                            
                            <div class="info-block">
                                <strong>Subject:</strong> %s
                            </div>
                            
                            <div class="info-block">
                                <strong>Category:</strong> <span class="badge">%s</span><br>
                                <strong>Related Plan:</strong> %s<br>
                                <strong>Date:</strong> %s
                            </div>
                            
                            <div class="section-title">📝 Description</div>
                            <div class="description-box">%s</div>
                            
                            <center>
                                <a href="http://localhost:8000/admin/reclamation/%d" class="cta-button">View in Dashboard →</a>
                            </center>
                            
                            <div class="footer">
                                <p>This is an automated notification from your Insurance Claims System.</p>
                            </div>
                        </div>
                    </div>
                </body>
                </html>',
                htmlspecialchars($reclamation->getUser()->getEmail()),
                htmlspecialchars($reclamation->getUser()->getNom() ?? 'User'),
                htmlspecialchars($reclamation->getSubject()),
                htmlspecialchars($reclamation->getCategory()),
                $reclamation->getAbonnement() ? htmlspecialchars($reclamation->getAbonnement()->getTypeAbonnement()) : 'N/A',
                $reclamation->getCreatedAt()->format('Y-m-d H:i:s'),
                nl2br(htmlspecialchars($reclamation->getDescription())),
                $reclamation->getId()
            );

            $email = (new Email())
                ->from($_ENV['APP_EMAIL_FROM'] ?? 'mediounimontassar61@gmail.com')
                ->to($adminEmail)
                ->subject($subject)
                ->html($htmlContent);

            $mailer->send($email);
            error_log('Reclamation email sent successfully to ' . $adminEmail);
        } catch (\Exception $e) {
            // Log error but don't fail the reclamation submission
            error_log('Failed to send reclamation email: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
        }
    }
}
