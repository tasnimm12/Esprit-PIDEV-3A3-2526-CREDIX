<?php
// Quick test of search API

$url = 'http://localhost:8000/api/abonnement/search?q=B';
$response = @file_get_contents($url);
$httpCode = $response !== false ? 200 : 0;

echo "Testing API: $url\n";
echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";
?>
