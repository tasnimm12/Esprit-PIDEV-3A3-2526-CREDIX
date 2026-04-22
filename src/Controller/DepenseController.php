<?php

namespace App\Controller;

use App\Entity\Depense;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/depense')]
class DepenseController extends AbstractController
{
    #[Route('/', name: 'app_depense_list')]
    public function list(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('User not logged in');
        }

        // Get filter parameters
        $category = $request->query->get('category', '');
        $minAmount = $request->query->get('minAmount', '');
        $maxAmount = $request->query->get('maxAmount', '');
        $dateFrom = $request->query->get('dateFrom', '');
        $dateTo = $request->query->get('dateTo', '');
        $sort = $request->query->get('sort', 'date_depense');
        $order = $request->query->get('order', 'DESC');

        // Convert date strings to DateTime objects
        $dateFromObj = null;
        $dateToObj = null;
        if ($dateFrom) {
            try {
                $dateFromObj = new \DateTime($dateFrom);
            } catch (\Exception $e) {}
        }
        if ($dateTo) {
            try {
                $dateToObj = new \DateTime($dateTo);
            } catch (\Exception $e) {}
        }

        // Use repository method for filtering
        /** @var \App\Repository\DepenseRepository $depenseRepository */
        $depenseRepository = $entityManager->getRepository(Depense::class);
        
        $minAmountVal = !empty($minAmount) && is_numeric($minAmount) ? (float)$minAmount : null;
        $maxAmountVal = !empty($maxAmount) && is_numeric($maxAmount) ? (float)$maxAmount : null;
        
        // Get filtered depenses (admin sees all, users only see unfiltered repository results)
        $depenses = $depenseRepository->findByCompteBancaire(null, $category);
        
        // Filter by user - show depenses that belong to the user either directly or through their bank account
        $depenses = array_filter($depenses, function($d) use ($user) {
            return $d->getUtilisateur() === $user || ($d->getCompteBancaire() && $d->getCompteBancaire()->getUtilisateur() === $user);
        });
        
        // Apply amount filters
        if ($minAmountVal !== null) {
            $depenses = array_filter($depenses, function($d) use ($minAmountVal) {
                return (float)$d->getMontant() >= $minAmountVal;
            });
        }
        if ($maxAmountVal !== null) {
            $depenses = array_filter($depenses, function($d) use ($maxAmountVal) {
                return (float)$d->getMontant() <= $maxAmountVal;
            });
        }
        
        // Apply date filters
        if ($dateFromObj) {
            $depenses = array_filter($depenses, function($d) use ($dateFromObj) {
                return $d->getDateDepense() >= $dateFromObj;
            });
        }
        if ($dateToObj) {
            $depenses = array_filter($depenses, function($d) use ($dateToObj) {
                return $d->getDateDepense() <= $dateToObj;
            });
        }
        
        // Sort the results
        usort($depenses, function($a, $b) use ($sort, $order) {
            // Convert snake_case to camelCase for method name
            $methodName = 'get' . str_replace('_', '', ucwords($sort, '_'));
            $aVal = $a->{$methodName}() ?? 0;
            $bVal = $b->{$methodName}() ?? 0;
            
            $result = $aVal <=> $bVal;
            return $order === 'ASC' ? $result : -$result;
        });

        // Calculate statistics
        $totalSpent = 0;
        $maxExpense = 0;
        $averageExpense = 0;
        $categoryStats = [];
        foreach ($depenses as $depense) {
            $amount = (float)$depense->getMontant();
            $totalSpent += $amount;
            if ($amount > $maxExpense) {
                $maxExpense = $amount;
            }
            $cat = $depense->getCategorie();
            if (!isset($categoryStats[$cat])) {
                $categoryStats[$cat] = 0;
            }
            $categoryStats[$cat] += $amount;
        }
        
        if (count($depenses) > 0) {
            $averageExpense = $totalSpent / count($depenses);
        }

        // Get all categories for filter
        $allDepenses = $depenseRepository->findByCompteBancaire(null);
        $allDepenses = array_filter($allDepenses, function($d) use ($user) {
            return $d->getUtilisateur() === $user || ($d->getCompteBancaire() && $d->getCompteBancaire()->getUtilisateur() === $user);
        });
        $categories = [];
        foreach ($allDepenses as $dep) {
            if (!in_array($dep->getCategorie(), $categories)) {
                $categories[] = $dep->getCategorie();
            }
        }

        return $this->render('depense/list.html.twig', [
            'depenses' => $depenses,
            'totalSpent' => $totalSpent,
            'maxExpense' => $maxExpense,
            'averageExpense' => $averageExpense,
            'categoryStats' => $categoryStats,
            'categories' => $categories,
            'category' => $category,
            'minAmount' => $minAmount,
            'maxAmount' => $maxAmount,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'sort' => $sort,
            'order' => $order,
        ]);
    }




}
