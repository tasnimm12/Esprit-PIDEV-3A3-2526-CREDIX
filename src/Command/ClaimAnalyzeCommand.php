<?php

namespace App\Command;

use App\Service\DamageDetection\FirstLevelDecisionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'claim:analyze',
    description: 'Analyze an insurance claim and generate first-level decision',
)]
class ClaimAnalyzeCommand extends Command
{
    public function __construct(private FirstLevelDecisionService $decisionService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('insurance_type', InputArgument::REQUIRED, 'Insurance type (AUTO, HOME, HEALTH, TRAVEL, LIABILITY, SCHOOL, PROFESSIONAL)')
            ->addArgument('severity', InputArgument::OPTIONAL, 'Damage severity (MINOR, MEDIUM, MAJOR)', 'MEDIUM')
            ->addArgument('damage_percent', InputArgument::OPTIONAL, 'Damage percentage (0-100)', '50')
            ->addArgument('cost', InputArgument::OPTIONAL, 'Estimated cost in dollars', '5000');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $insuranceType = strtoupper($input->getArgument('insurance_type'));
        $severity = strtoupper($input->getArgument('severity'));
        $damagePercent = (int)$input->getArgument('damage_percent');
        $cost = (int)$input->getArgument('cost');

        $io->title('Insurance Claim Analysis');

        // Create analysis result
        $analysisResult = [
            'damage_percentage' => $damagePercent,
            'estimated_cost' => $cost,
            'severity' => $severity,
            'confidence_score' => 0.82,
            'detected_features' => [
                'significant_damage' => true,
                'repairable' => true,
            ],
        ];

        $io->section('Input Data');
        $io->table(
            ['Property', 'Value'],
            [
                ['Insurance Type', $insuranceType],
                ['Damage Severity', $severity],
                ['Damage Percentage', "$damagePercent%"],
                ['Estimated Cost', "\$$cost"],
            ]
        );

        try {
            // Call the FirstLevelDecisionService
            $decision = $this->decisionService->generateDecision(
                $analysisResult,
                $insuranceType,
                ['analysis_method' => 'console_command']
            );

            $io->section('Decision Results');
            $io->table(
                ['Metric', 'Value'],
                [
                    ['Recommendation', strtoupper($decision['recommendation'])],
                    ['Confidence', $decision['confidence'] . '%'],
                    ['Coverage Applicable', $decision['coverage_applicable'] ? 'Yes' : 'No'],
                    ['Estimated Payout', '$' . number_format($decision['estimated_payout'])],
                ]
            );

            if (!empty($decision['flags_for_review'])) {
                $io->section('Review Flags');
                foreach ($decision['flags_for_review'] as $flag) {
                    $io->writeln("⚠️  $flag");
                }
            }

            $io->section('Reasoning');
            $io->text($decision['reasoning']);

            $io->newLine();
            $io->success('Claim analysis completed successfully');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Error analyzing claim: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
