<?php

namespace App\Service;

use App\Entity\Sinistre;
use Psr\Log\LoggerInterface;
use Twig\Environment as Twig;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Service for sending claim approval/rejection notifications to users
 * Uses Symfony Mailer for reliable email delivery across all platforms
 */
class ClaimNotificationService
{
    private LoggerInterface $logger;
    private Twig $twig;
    private MailerInterface $mailer;
    private string $senderEmail;
    private string $senderName;

    public function __construct(LoggerInterface $logger, Twig $twig, MailerInterface $mailer)
    {
        $this->logger = $logger;
        $this->twig = $twig;
        $this->mailer = $mailer;
        // Configure sender details
        $this->senderEmail = $_ENV['APP_EMAIL_FROM'] ?? 'noreply@insurance-app.local';
        $this->senderName = $_ENV['APP_EMAIL_NAME'] ?? 'Insurance Claims System';
    }

    /**
     * Send approval notification email
     */
    public function sendApprovalNotification(Sinistre $sinistre): bool
    {
        return $this->sendClaimNotification($sinistre, 'ACCEPTE');
    }

    /**
     * Send rejection notification email
     */
    public function sendRejectionNotification(Sinistre $sinistre): bool
    {
        return $this->sendClaimNotification($sinistre, 'REJETE');
    }

    /**
     * Send claim status change notification
     */
    private function sendClaimNotification(Sinistre $sinistre, string $status): bool
    {
        try {
            // Get user email
            $user = $sinistre->getUtilisateur();
            $userEmail = $user->getEmail();

            if (!$userEmail) {
                $this->logger->warning(sprintf(
                    'Cannot send claim notification: User %s has no email address',
                    $user->getId()
                ));
                return false;
            }

            // Prepare email data
            $subject = $this->getSubject($sinistre, $status);
            $htmlBody = $this->renderTemplate($sinistre, $status);
            
            if (empty($htmlBody)) {
                $this->logger->warning(sprintf(
                    'Failed to render email template for claim #%d',
                    $sinistre->getId()
                ));
                return false;
            }

            // Create and send email using Symfony Mailer
            $email = (new Email())
                ->from($this->senderEmail)
                ->to($userEmail)
                ->subject($subject)
                ->html($htmlBody);

            $this->mailer->send($email);

            $this->logger->info(sprintf(
                'Successfully sent %s notification for claim #%d to %s',
                $status,
                $sinistre->getId(),
                $userEmail
            ));

            return true;

        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'Error sending claim notification: %s',
                $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Get email subject based on status
     */
    private function getSubject(Sinistre $sinistre, string $status): string
    {
        $claimId = $sinistre->getId();
        if ($status === 'ACCEPTE') {
            return "✓ Claim Approved - Your Insurance Claim #{$claimId}";
        } else {
            return "✗ Claim Decision - Your Insurance Claim #{$claimId}";
        }
    }

    /**
     * Render HTML email template
     */
    private function renderTemplate(Sinistre $sinistre, string $status): string
    {
        try {
            return $this->twig->render('email/claim_notification.html.twig', [
                'sinistre' => $sinistre,
                'status' => $status,
                'isApproved' => $status === 'ACCEPTE',
                'isRejected' => $status === 'REJETE',
                'claimNumber' => $sinistre->getId(),
                'description' => $sinistre->getDescription(),
                'adminResponse' => $sinistre->getAdminReponse(),
                'dateResponse' => $sinistre->getDateReponse(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to render email template: ' . $e->getMessage(), [
                'exception' => $e,
                'template' => 'email/claim_notification.html.twig'
            ]);
            return '';
        }
    }
}
