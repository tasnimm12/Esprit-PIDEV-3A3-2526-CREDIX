<?php
// Test to debug custom plan creation issue

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use App\Kernel;

// Load .env
$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__.'/../.env');

// Create kernel
$kernel = new Kernel($_ENV['APP_ENV'] ?? 'dev', $_ENV['APP_DEBUG'] ?? false);
$kernel->boot();

$container = $kernel->getContainer();
$em = $container->get('doctrine.orm.entity_manager');

// Check custom plans
$repo = $em->getRepository('App\Entity\Abonnement');

echo "=== Database Check ===\n";
echo "Total abonnements: " . $repo->count([]) . "\n";

// Check custom plans
$qb = $repo->createQueryBuilder('ab');
$qb->where('ab.is_custom = true');
$customPlans = $qb->getQuery()->getResult();
echo "Custom plans: " . count($customPlans) . "\n";

foreach ($customPlans as $plan) {
    echo "  - ID: " . $plan->getIdAbonnement() . ", Name: " . $plan->getTypeAbonnement() . ", CreatedBy: " . ($plan->getCreatedBy() ? $plan->getCreatedBy()->getEmail() : 'NULL') . "\n";
}

// Check if there's any issue with the is_custom field
$conn = $em->getConnection();
$stmt = $conn->executeQuery('SELECT id_abonnement, type_abonnement, is_custom, created_by FROM abonnement ORDER BY id_abonnement DESC LIMIT 5');
$result = $stmt->fetchAllAssociative();

echo "\n=== Last 5 Plans in Database ===\n";
foreach ($result as $row) {
    echo "ID: " . $row['id_abonnement'] . ", Type: " . $row['type_abonnement'] . ", IsCustom: " . $row['is_custom'] . ", CreatedBy: " . $row['created_by'] . "\n";
}

$kernel->shutdown();
?>
