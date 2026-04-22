<?php
// Direct test of the search API by simulating the request

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use App\Kernel;

$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__.'/../.env');

$kernel = new Kernel($_ENV['APP_ENV'] ?? 'dev', $_ENV['APP_DEBUG'] ?? false);
$kernel->boot();

$container = $kernel->getContainer();
$em = $container->get('doctrine.orm.entity_manager');

// Test the LIKE query
$repo = $em->getRepository('App\Entity\Abonnement');
$qb = $repo->createQueryBuilder('ab')
    ->where('ab.type_abonnement LIKE :search OR ab.description LIKE :search')
    ->setParameter('search', 'B%')
    ->orderBy('ab.type_abonnement', 'ASC')
    ->setMaxResults(10)
    ->andWhere('ab.is_custom = false');

$plans = $qb->getQuery()->getResult();

echo "Search Results for 'B':\n";
echo "Found: " . count($plans) . " plans\n";
foreach ($plans as $plan) {
    echo "- " . $plan->getTypeAbonnement() . " (€" . $plan->getPrixMensuel() . ")\n";
}

$kernel->shutdown();
?>
