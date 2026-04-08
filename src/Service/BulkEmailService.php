<?php

namespace App\Service;

use App\Entity\Utilisateur;
use Psr\Log\LoggerInterface;
use Twig\Environment as Twig;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Service for sending bulk emails to multiple clients
 */
class BulkEmailService
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
        $this->senderEmail = $_ENV['APP_EMAIL_FROM'] ?? 'noreply@insurance-app.local';
        $this->senderName = $_ENV['APP_EMAIL_NAME'] ?? 'Insurance Claims System';
    }

    /**
     * Send bulk email to multiple clients
     * 
     * @param array $recipients Array of Utilisateur objects or email addresses
     * @param string $subject Email subject
     * @param string $body Email body (HTML)
     * @param array $options Additional options
     * @return array Results with sent count and failures
     */
    public function sendBulkEmail(array $recipients, string $subject, string $body, array $options = []): array
    {
        $sent = 0;
        $failed = 0;
        $failedEmails = [];

        // Filter recipients to only get valid emails
        $validRecipients = [];
        foreach ($recipients as $recipient) {
            if ($recipient instanceof Utilisateur) {
                $email = $recipient->getEmail();
                if ($email) {
                    $validRecipients[] = [
                        'email' => $email,
                        'name' => $recipient->getPrenom() . ' ' . $recipient->getNom(),
                    ];
                }
            } elseif (is_string($recipient) && filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                $validRecipients[] = [
                    'email' => $recipient,
                    'name' => null,
                ];
            }
        }

        if (empty($validRecipients)) {
            $this->logger->warning('No valid recipients found for bulk email');
            return [
                'sent' => 0,
                'failed' => 0,
                'failedEmails' => [],
                'totalRecipients' => count($recipients),
            ];
        }

        $this->logger->info(sprintf(
            'Starting bulk email campaign with %d recipients',
            count($validRecipients)
        ));

        // Send emails (in batches to avoid overwhelming the mail server)
        foreach ($validRecipients as $recipient) {
            try {
                $email = (new Email())
                    ->from($this->senderEmail)
                    ->to($recipient['email'])
                    ->subject($subject)
                    ->html($body);

                $this->mailer->send($email);
                $sent++;

                $this->logger->debug(sprintf(
                    'Bulk email sent to %s',
                    $recipient['email']
                ));

            } catch (\Exception $e) {
                $failed++;
                $failedEmails[] = $recipient['email'];

                $this->logger->error(sprintf(
                    'Failed to send bulk email to %s: %s',
                    $recipient['email'],
                    $e->getMessage()
                ));
            }
        }

        $this->logger->info(sprintf(
            'Bulk email campaign completed: %d sent, %d failed',
            $sent,
            $failed
        ));

        return [
            'sent' => $sent,
            'failed' => $failed,
            'failedEmails' => $failedEmails,
            'totalRecipients' => count($validRecipients),
        ];
    }

    /**
     * Send bulk email to all clients
     */
    public function sendBulkEmailToAllClients(string $subject, string $body, array $clients): array
    {
        return $this->sendBulkEmail($clients, $subject, $body);
    }

    /**
     * Send bulk email to clients by role
     */
    public function sendBulkEmailByRole(string $subject, string $body, array $recipients, string $role = 'client'): array
    {
        // Filter recipients by role
        $filteredRecipients = array_filter($recipients, function($user) use ($role) {
            return $user instanceof Utilisateur && $user->getRole() === $role;
        });

        return $this->sendBulkEmail(array_values($filteredRecipients), $subject, $body);
    }
}
