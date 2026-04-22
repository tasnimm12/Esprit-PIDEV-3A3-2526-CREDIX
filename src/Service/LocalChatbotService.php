<?php

namespace App\Service;

use App\Entity\Abonnement;
use App\Entity\Assurance;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class LocalChatbotService
{
    private const MAX_DOC_SIZE_BYTES = 500000;

    private array $documents = [];
    private array $fallbackKnowledgeBase = [];
    private array $plans = [];
    private array $insurances = [];

    public function __construct(
        private readonly ParameterBagInterface $params,
        private readonly EntityManagerInterface $entityManager
    ) {
        $this->loadDocumentation();
        $this->loadSiteKnowledge();
        $this->loadFallbackKnowledgeBase();
    }

    public function chat(string $userMessage): array
    {
        $normalizedMessage = trim($userMessage);

        if ($normalizedMessage === '') {
            return [
                'error' => true,
                'message' => 'Please ask a question.',
                'response' => null,
            ];
        }

        $siteAnswer = $this->answerSiteQuestion($normalizedMessage);

        if ($siteAnswer !== null) {
            return [
                'error' => false,
                'message' => '',
                'response' => $siteAnswer,
            ];
        }

        $matches = $this->searchDocumentation($normalizedMessage);

        if ($matches !== []) {
            return [
                'error' => false,
                'message' => '',
                'response' => $this->buildDocumentationResponse($normalizedMessage, $matches),
            ];
        }

        return [
            'error' => false,
            'message' => '',
            'response' => $this->buildFallbackResponse($normalizedMessage),
        ];
    }

    private function loadSiteKnowledge(): void
    {
        $plans = $this->entityManager->getRepository(Abonnement::class)->findBy([], ['type_abonnement' => 'ASC']);

        foreach ($plans as $plan) {
            $this->plans[] = [
                'id' => $plan->getIdAbonnement(),
                'name' => $this->sanitizeUtf8((string) $plan->getTypeAbonnement()),
                'monthly' => (float) $plan->getPrixMensuel(),
                'annual' => (float) $plan->getPrixAnnuel(),
                'duration' => $this->sanitizeUtf8((string) $plan->getDuree()),
                'active' => (bool) $plan->isActif(),
                'custom' => (bool) $plan->isCustom(),
                'description' => $this->sanitizeUtf8((string) ($plan->getDescription() ?? '')),
                'benefits' => $this->sanitizeUtf8((string) ($plan->getAvantages() ?? '')),
            ];
        }

        $insurances = $this->entityManager->getRepository(Assurance::class)->findBy([], ['compagnie' => 'ASC']);

        foreach ($insurances as $insurance) {
            $this->insurances[] = [
                'id' => $insurance->getId(),
                'company' => $this->sanitizeUtf8((string) ($insurance->getCompagnie() ?? '')),
                'type' => $this->sanitizeUtf8((string) ($insurance->getTypeAssurance() ?? '')),
                'price' => $insurance->getPrixAssurance() !== null ? (float) $insurance->getPrixAssurance() : null,
                'monthly' => $insurance->getPrimeMensuelle() !== null ? (float) $insurance->getPrimeMensuelle() : null,
                'annual' => $insurance->getPrimeAnnuelle() !== null ? (float) $insurance->getPrimeAnnuelle() : null,
                'status' => $this->sanitizeUtf8((string) ($insurance->getStatut() ?? '')),
            ];
        }
    }

    private function answerSiteQuestion(string $message): ?string
    {
        $normalized = $this->normalizeText($message);

        $planAnswer = $this->answerPlanQuestion($normalized);

        if ($planAnswer !== null) {
            return $planAnswer;
        }

        $insuranceAnswer = $this->answerInsuranceQuestion($normalized);

        if ($insuranceAnswer !== null) {
            return $insuranceAnswer;
        }

        if (str_contains($normalized, 'abonnement') || str_contains($normalized, 'subscription') || str_contains($normalized, 'plan')) {
            return $this->buildPlanOverview();
        }

        if (str_contains($normalized, 'assurance') || str_contains($normalized, 'insurance')) {
            return $this->buildInsuranceOverview();
        }

        return null;
    }

    private function answerPlanQuestion(string $normalizedMessage): ?string
    {
        $matchedPlans = [];

        foreach ($this->plans as $plan) {
            $planName = $this->normalizeText($plan['name']);

            if ($planName !== '' && str_contains($normalizedMessage, $planName)) {
                $matchedPlans[] = $plan;
            }
        }

        if ($matchedPlans === []) {
            return null;
        }

        if (str_contains($normalizedMessage, 'how much')
            || str_contains($normalizedMessage, 'price')
            || str_contains($normalizedMessage, 'cost')
            || str_contains($normalizedMessage, 'prix')
            || str_contains($normalizedMessage, 'monthly')
            || str_contains($normalizedMessage, 'annual')
        ) {
            $lines = [];

            foreach ($matchedPlans as $plan) {
                $lines[] = sprintf(
                    '%s plan: $%0.2f per month and $%0.2f per year. Duration: %s. Status: %s.',
                    $plan['name'],
                    $plan['monthly'],
                    $plan['annual'],
                    $plan['duration'],
                    $plan['active'] ? 'active' : 'inactive'
                );
            }

            $lines[] = 'Users can view plans on /abonnement or /abonnements, open details from the plan card, and subscribe from the detail page.';

            return $this->sanitizeUtf8(implode("\n", $lines));
        }

        $plan = $matchedPlans[0];
        $response = sprintf(
            '%s plan costs $%0.2f per month and $%0.2f per year. Duration: %s. Status: %s.',
            $plan['name'],
            $plan['monthly'],
            $plan['annual'],
            $plan['duration'],
            $plan['active'] ? 'active' : 'inactive'
        );

        if ($plan['description'] !== '') {
            $response .= ' Description: ' . $plan['description'];
        }

        return $this->sanitizeUtf8($response);
    }

    private function answerInsuranceQuestion(string $normalizedMessage): ?string
    {
        $matches = [];

        foreach ($this->insurances as $insurance) {
            $company = $this->normalizeText($insurance['company']);
            $type = $this->normalizeText($insurance['type']);

            if (($company !== '' && str_contains($normalizedMessage, $company))
                || ($type !== '' && str_contains($normalizedMessage, $type))
            ) {
                $matches[] = $insurance;
            }
        }

        if ($matches === []) {
            return null;
        }

        $lines = [];

        foreach ($matches as $insurance) {
            $line = sprintf(
                '%s %s insurance',
                $insurance['company'] !== '' ? $insurance['company'] : 'This',
                $insurance['type'] !== '' ? $insurance['type'] : ''
            );

            $details = [];

            if ($insurance['price'] !== null) {
                $details[] = sprintf('price $%0.2f', $insurance['price']);
            }

            if ($insurance['monthly'] !== null) {
                $details[] = sprintf('monthly premium $%0.2f', $insurance['monthly']);
            }

            if ($insurance['annual'] !== null) {
                $details[] = sprintf('annual premium $%0.2f', $insurance['annual']);
            }

            if ($insurance['status'] !== '') {
                $details[] = 'status ' . strtolower($insurance['status']);
            }

            $lines[] = rtrim($line) . ': ' . implode(', ', $details) . '.';
        }

        $lines[] = 'Users can browse insurance products on /assurance and open each policy detail page to see coverage, premiums, deductible, and status.';

        return $this->sanitizeUtf8(implode("\n", $lines));
    }

    private function buildPlanOverview(): string
    {
        $activePlans = array_values(array_filter(
            $this->plans,
            static fn (array $plan): bool => $plan['active'] && !$plan['custom']
        ));

        if ($activePlans === []) {
            return 'I could not find active subscription plans right now.';
        }

        $lines = ['Available subscription plans on the site:'];

        foreach ($activePlans as $plan) {
            $lines[] = sprintf(
                '- %s: $%0.2f/month, $%0.2f/year, duration %s.',
                $plan['name'],
                $plan['monthly'],
                $plan['annual'],
                $plan['duration']
            );
        }

        $lines[] = 'Users can browse plans on /abonnement or /abonnements, use the filters, view details, and subscribe from the plan detail page.';

        return $this->sanitizeUtf8(implode("\n", $lines));
    }

    private function buildInsuranceOverview(): string
    {
        if ($this->insurances === []) {
            return 'I could not find insurance products right now.';
        }

        $lines = ['Available insurance products on the site:'];

        foreach ($this->insurances as $insurance) {
            $lines[] = sprintf(
                '- %s %s: price %s, monthly premium %s, annual premium %s, status %s.',
                $insurance['company'] !== '' ? $insurance['company'] : 'Unknown company',
                $insurance['type'] !== '' ? $insurance['type'] : 'insurance',
                $insurance['price'] !== null ? '$' . number_format($insurance['price'], 2, '.', '') : 'N/A',
                $insurance['monthly'] !== null ? '$' . number_format($insurance['monthly'], 2, '.', '') : 'N/A',
                $insurance['annual'] !== null ? '$' . number_format($insurance['annual'], 2, '.', '') : 'N/A',
                $insurance['status'] !== '' ? strtolower($insurance['status']) : 'unknown'
            );
        }

        $lines[] = 'Users can browse these on /assurance after login.';

        return $this->sanitizeUtf8(implode("\n", $lines));
    }

    private function loadDocumentation(): void
    {
        $projectDir = (string) $this->params->get('kernel.project_dir');
        $docsPath = $projectDir . '/docs';

        if (!is_dir($docsPath)) {
            return;
        }

        $files = glob($docsPath . '/*.md') ?: [];

        foreach ($files as $file) {
            if (!is_file($file) || filesize($file) > self::MAX_DOC_SIZE_BYTES) {
                continue;
            }

            $content = $this->sanitizeUtf8((string) file_get_contents($file));
            $sections = $this->splitIntoSections($content, basename($file));

            foreach ($sections as $section) {
                if (($section['content'] ?? '') === '') {
                    continue;
                }

                $section['normalized'] = $this->normalizeText(
                    $section['title'] . ' ' . $section['content'] . ' ' . $section['source']
                );

                $this->documents[] = $section;
            }
        }
    }

    private function splitIntoSections(string $markdown, string $source): array
    {
        $lines = preg_split('/\R/', $markdown) ?: [];
        $sections = [];
        $currentTitle = 'Overview';
        $buffer = [];

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            if (preg_match('/^#{1,6}\s+(.+)$/', $trimmedLine, $matches) === 1) {
                $this->appendSection($sections, $currentTitle, $buffer, $source);
                $currentTitle = trim($matches[1]);
                $buffer = [];
                continue;
            }

            $buffer[] = $line;
        }

        $this->appendSection($sections, $currentTitle, $buffer, $source);

        return $sections;
    }

    private function appendSection(array &$sections, string $title, array $buffer, string $source): void
    {
        $content = trim(implode("\n", $buffer));

        if ($content === '') {
            return;
        }

        $sections[] = [
            'title' => $title,
            'content' => $this->cleanSectionContent($content),
            'source' => $source,
        ];
    }

    private function cleanSectionContent(string $content): string
    {
        $content = $this->sanitizeUtf8($content);
        $content = preg_replace('/```.*?```/s', ' ', $content) ?? $content;
        $content = preg_replace('/`([^`]+)`/', '$1', $content) ?? $content;
        $content = preg_replace('/\|/', ' ', $content) ?? $content;
        $content = preg_replace('/^\s*[-*]\s+/m', '- ', $content) ?? $content;
        $content = preg_replace('/\s+/', ' ', $content) ?? $content;

        return trim($content);
    }

    private function searchDocumentation(string $message): array
    {
        $keywords = $this->extractKeywords($message);

        if ($keywords === []) {
            return [];
        }

        $scoredMatches = [];

        foreach ($this->documents as $document) {
            $score = $this->scoreDocument($document, $keywords, $message);

            if ($score <= 0) {
                continue;
            }

            $document['score'] = $score;
            $scoredMatches[] = $document;
        }

        usort(
            $scoredMatches,
            static fn (array $left, array $right): int => $right['score'] <=> $left['score']
        );

        return array_slice($scoredMatches, 0, 3);
    }

    private function extractKeywords(string $message): array
    {
        $normalized = $this->normalizeText($message);
        $parts = preg_split('/\s+/', $normalized) ?: [];
        $keywords = [];
        $stopWords = [
            'a', 'about', 'an', 'and', 'are', 'can', 'do', 'does', 'explain', 'for', 'from',
            'give', 'how', 'i', 'if', 'in', 'is', 'it', 'me', 'my', 'of', 'on', 'or', 'show',
            'tell', 'the', 'this', 'to', 'what', 'where', 'which', 'with', 'you',
        ];

        foreach ($parts as $part) {
            if (strlen($part) < 3 || in_array($part, $stopWords, true)) {
                continue;
            }

            $keywords[] = $part;
        }

        return array_values(array_unique($keywords));
    }

    private function normalizeText(string $text): string
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return trim($text);
    }

    private function scoreDocument(array $document, array $keywords, string $message): int
    {
        $score = 0;
        $normalizedDocument = $document['normalized'] ?? '';
        $normalizedTitle = $this->normalizeText((string) ($document['title'] ?? ''));
        $normalizedMessage = $this->normalizeText($message);

        foreach ($keywords as $keyword) {
            if (str_contains($normalizedTitle, $keyword)) {
                $score += 5;
            }

            if (str_contains($normalizedDocument, $keyword)) {
                $score += 2;
            }
        }

        if ($normalizedTitle !== '' && str_contains($normalizedMessage, $normalizedTitle)) {
            $score += 4;
        }

        return $score;
    }

    private function buildDocumentationResponse(string $message, array $matches): string
    {
        $topMatch = $matches[0];
        $intro = $this->buildIntro($message, $topMatch);
        $sections = [];

        foreach ($matches as $match) {
            $excerpt = $this->summarizeSection((string) $match['content']);
            $sections[] = sprintf(
                "- %s: %s (Source: %s)",
                $match['title'],
                $excerpt,
                $match['source']
            );
        }

        return $this->sanitizeUtf8($intro . "\n\n" . implode("\n", $sections));
    }

    private function buildIntro(string $message, array $topMatch): string
    {
        $question = strtolower($message);

        if (str_contains($question, 'how')) {
            return sprintf(
                'Based on the local project documentation, the most relevant guidance is in "%s" from %s.',
                $topMatch['title'],
                $topMatch['source']
            );
        }

        if (str_contains($question, 'api') || str_contains($question, 'endpoint')) {
            return sprintf(
                'The local docs point to "%s" in %s as the best starting point for that API-related question.',
                $topMatch['title'],
                $topMatch['source']
            );
        }

        return sprintf(
            'I found a relevant answer in the local project docs under "%s" in %s.',
            $topMatch['title'],
            $topMatch['source']
        );
    }

    private function summarizeSection(string $content): string
    {
        $sentences = preg_split('/(?<=[.!?])\s+/', $content) ?: [];
        $summary = [];

        foreach ($sentences as $sentence) {
            $trimmed = trim($sentence);

            if ($trimmed === '') {
                continue;
            }

            $summary[] = $trimmed;

            if (count($summary) >= 2) {
                break;
            }
        }

        if ($summary === []) {
            return substr($content, 0, 220) . (strlen($content) > 220 ? '...' : '');
        }

        return implode(' ', $summary);
    }

    private function loadFallbackKnowledgeBase(): void
    {
        $this->fallbackKnowledgeBase = [
            'claim' => 'Claims can be filed from the insurance/reclamation area. Include a clear subject, detailed description, category, and any supporting information or documents.',
            'reclamation' => 'A reclamation needs a subject, a description of at least 20 characters, and a valid category such as general, billing, technical, or service.',
            'insurance' => 'The application manages insurance plans, claims, and claim review workflows for several insurance-related features.',
            'decision' => 'The project includes a first-level decision system that can analyze claim-related inputs and return APPROVE, REVIEW, or DENY recommendations.',
            'damage' => 'The documentation includes a damage detection and first-level decision flow, with guidance in the docs folder for integration and API usage.',
            'credit' => 'The platform also covers finance-related features such as credit workflows and account management.',
        ];
    }

    private function buildFallbackResponse(string $message): string
    {
        $normalizedMessage = $this->normalizeText($message);

        foreach ($this->fallbackKnowledgeBase as $keyword => $response) {
            if (str_contains($normalizedMessage, $keyword)) {
                return $this->sanitizeUtf8($response . ' If you want, ask with a feature name, endpoint, or document title for a more precise answer.');
            }
        }

        return 'I can answer from the local project documentation in the docs folder. Try asking about claim decisions, damage detection, reclamations, endpoints, configuration, or a specific document title.';
    }

    private function sanitizeUtf8(string $text): string
    {
        $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $text);

        if ($converted !== false) {
            return $converted;
        }

        return preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', ' ', $text) ?? $text;
    }
}
