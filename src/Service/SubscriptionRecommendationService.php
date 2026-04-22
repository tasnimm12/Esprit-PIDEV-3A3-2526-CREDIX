<?php

namespace App\Service;

use App\Entity\Abonnement;
use App\Entity\Depense;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;

class SubscriptionRecommendationService
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * Analyze user expenses and recommend the best subscription plan
     * Returns an array with recommendation details
     */
    public function recommendPlan(Utilisateur $user, array $abonnements): array
    {
        // Get user expenses for the last 6 months
        $sixMonthsAgo = new \DateTime();
        $sixMonthsAgo->modify('-6 months');
        
        $depenseRepository = $this->em->getRepository(Depense::class);
        $expenses = $depenseRepository->createQueryBuilder('d')
            ->where('d.utilisateur = :user')
            ->andWhere('d.date_depense >= :dateStart')
            ->setParameter('user', $user)
            ->setParameter('dateStart', $sixMonthsAgo)
            ->getQuery()
            ->getResult();
        
        // Calculate expense statistics
        $stats = $this->calculateExpenseStats($expenses);
        
        // If no expenses, return no recommendation
        if ($stats['totalExpenses'] === 0) {
            return [
                'hasRecommendation' => false,
                'message' => 'No expense data available. Start tracking expenses to get personalized recommendations.',
                'recommendation' => null,
                'stats' => $stats
            ];
        }
        
        // Find the best matching plan
        $recommendation = $this->findBestPlan($stats, $abonnements);
        
        return [
            'hasRecommendation' => true,
            'message' => "Based on your spending patterns, we recommend the " . $recommendation['plan']->getTypeAbonnement() . " plan.",
            'recommendation' => $recommendation,
            'stats' => $stats
        ];
    }

    /**
     * Calculate expense statistics from user expenses
     */
    private function calculateExpenseStats(array $expenses): array
    {
        if (count($expenses) === 0) {
            return [
                'totalExpenses' => 0,
                'averageMonthly' => 0,
                'averageDaily' => 0,
                'minExpense' => 0,
                'maxExpense' => 0,
                'categoryBreakdown' => [],
                'trend' => 'stable'
            ];
        }

        $totalExpenses = 0;
        $categoryBreakdown = [];
        $monthlyTotals = [];
        $allAmounts = [];

        // Process expenses
        foreach ($expenses as $expense) {
            $amount = (float)$expense->getMontant();
            $totalExpenses += $amount;
            $allAmounts[] = $amount;
            
            // Category breakdown
            $category = $expense->getCategorie();
            if (!isset($categoryBreakdown[$category])) {
                $categoryBreakdown[$category] = 0;
            }
            $categoryBreakdown[$category] += $amount;
            
            // Monthly breakdown
            $month = $expense->getDateDepense()->format('Y-m');
            if (!isset($monthlyTotals[$month])) {
                $monthlyTotals[$month] = 0;
            }
            $monthlyTotals[$month] += $amount;
        }

        // Calculate averages
        $months = count($monthlyTotals) > 0 ? count($monthlyTotals) : 1;
        $days = count($expenses) > 0 ? ceil(count($expenses) / 30) : 1;
        
        $averageMonthly = $totalExpenses / $months;
        $averageDaily = $totalExpenses / max($days, 1);

        // Determine trend
        $monthlyValues = array_values($monthlyTotals);
        $trend = $this->determineTrend($monthlyValues);

        // Sort categories by amount (descending)
        arsort($categoryBreakdown);

        return [
            'totalExpenses' => $totalExpenses,
            'averageMonthly' => $averageMonthly,
            'averageDaily' => $averageDaily,
            'minExpense' => min($allAmounts),
            'maxExpense' => max($allAmounts),
            'categoryBreakdown' => $categoryBreakdown,
            'trend' => $trend,
            'expenseCount' => count($expenses),
            'monthsCovered' => $months
        ];
    }

    /**
     * Determine expense trend (increasing, decreasing, stable)
     */
    private function determineTrend(array $monthlyValues): string
    {
        if (count($monthlyValues) < 2) {
            return 'stable';
        }

        $firstHalf = array_slice($monthlyValues, 0, (int)ceil(count($monthlyValues) / 2));
        $secondHalf = array_slice($monthlyValues, (int)ceil(count($monthlyValues) / 2));

        if (count($firstHalf) === 0 || count($secondHalf) === 0) {
            return 'stable';
        }

        $avgFirst = array_sum($firstHalf) / count($firstHalf);
        $avgSecond = array_sum($secondHalf) / count($secondHalf);

        $percentageChange = (($avgSecond - $avgFirst) / $avgFirst) * 100;

        if ($percentageChange > 10) {
            return 'increasing';
        } elseif ($percentageChange < -10) {
            return 'decreasing';
        }

        return 'stable';
    }

    /**
     * Find the best matching subscription plan based on expenses
     */
    private function findBestPlan(array $stats, array $abonnements): array
    {
        $averageMonthly = $stats['averageMonthly'];
        
        // Filter active plans
        $activePlans = array_filter($abonnements, function($plan) {
            return $plan->isActif();
        });

        if (count($activePlans) === 0) {
            return [
                'plan' => reset($abonnements),
                'reasoning' => 'No active plans available.',
                'matchScore' => 0,
                'isMatch' => false
            ];
        }

        // Score each plan based on how well it fits the user's spending
        $scores = [];
        
        foreach ($activePlans as $plan) {
            $planPrice = (float)$plan->getPrixMensuel();
            
            // Calculate how well the plan matches the spending
            // Perfect match is when the plan price is 50-100% of average expenses
            $score = $this->calculatePlanScore($averageMonthly, $planPrice, $stats['trend']);
            
            $scores[$plan->getIdAbonnement()] = [
                'plan' => $plan,
                'score' => $score,
                'reasoning' => $this->generateRecommendationReason($plan, $averageMonthly, $score, $stats)
            ];
        }

        // Sort by score (highest first)
        usort($scores, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        $best = reset($scores);

        return [
            'plan' => $best['plan'],
            'reasoning' => $best['reasoning'],
            'matchScore' => round($best['score'], 2),
            'isMatch' => $best['score'] >= 70
        ];
    }

    /**
     * Calculate match score for a plan
     * Score: 0-100
     */
    private function calculatePlanScore(float $averageExpenses, float $planPrice, string $trend): float
    {
        // Base score: how close the plan price is to user's spending
        // Target: plan should be 50-100% of user's average monthly spending
        
        if ($averageExpenses === 0) {
            return 50; // Neutral score if no expenses
        }

        $ratio = $planPrice / $averageExpenses;
        
        // Score calculation:
        // - Ratio 0.5-1.0: Excellent (100-80 points)
        // - Ratio 1.0-1.5: Good (80-60 points)
        // - Ratio 1.5-2.0: Moderate (60-40 points)
        // - Ratio > 2.0: Poor
        
        if ($ratio >= 0.5 && $ratio <= 1.0) {
            // Perfect fit - scales from 80 to 100
            $score = 80 + (20 * ((1 - $ratio) / 0.5));
        } elseif ($ratio > 1.0 && $ratio <= 1.5) {
            // Good fit - scales from 60 to 80
            $score = 60 + (20 * ((1.5 - $ratio) / 0.5));
        } elseif ($ratio > 1.5 && $ratio <= 2.0) {
            // Moderate fit - scales from 40 to 60
            $score = 40 + (20 * ((2.0 - $ratio) / 0.5));
        } else {
            // Too expensive or too cheap
            $score = max(20, 40 - ($ratio * 10));
        }

        // Adjust for trend
        if ($trend === 'increasing') {
            // If spending is increasing, favor higher-tier plans
            $score += 10;
        } elseif ($trend === 'decreasing') {
            // If spending is decreasing, favor lower-tier plans
            $score -= 5;
        }

        return min(100, max(0, $score));
    }

    /**
     * Generate human-readable recommendation reasoning
     */
    private function generateRecommendationReason(Abonnement $plan, float $averageExpenses, float $score, array $stats): string
    {
        $planPrice = (float)$plan->getPrixMensuel();
        $ratio = $planPrice / $averageExpenses;

        $reason = "Your average monthly spending is €" . number_format($averageExpenses, 2) . ". ";
        
        if ($ratio >= 0.5 && $ratio <= 1.0) {
            $reason .= "The " . $plan->getTypeAbonnement() . " plan (€" . number_format($planPrice, 2) . "/month) perfectly matches your spending profile. ";
        } elseif ($ratio > 1.0 && $ratio <= 1.5) {
            $reason .= "The " . $plan->getTypeAbonnement() . " plan (€" . number_format($planPrice, 2) . "/month) is a good fit for your budget. ";
        } elseif ($ratio > 1.5) {
            $reason .= "The " . $plan->getTypeAbonnement() . " plan (€" . number_format($planPrice, 2) . "/month) may be higher than your typical spending, but offers premium features. ";
        } else {
            $reason .= "The " . $plan->getTypeAbonnement() . " plan (€" . number_format($planPrice, 2) . "/month) is an affordable option. ";
        }

        // Add trend insight
        if ($stats['trend'] === 'increasing') {
            $reason .= "Since your spending is increasing, this plan provides good value. ";
        } elseif ($stats['trend'] === 'decreasing') {
            $reason .= "Your spending is decreasing, so this economical plan works well. ";
        }

        // Add top category insight
        if (!empty($stats['categoryBreakdown'])) {
            $topCategory = array_key_first($stats['categoryBreakdown']);
            $topAmount = $stats['categoryBreakdown'][$topCategory];
            $percentage = round(($topAmount / $stats['totalExpenses']) * 100);
            $reason .= "Your top spending category is " . strtolower($topCategory) . " (" . $percentage . "%).";
        }

        return $reason;
    }
}
