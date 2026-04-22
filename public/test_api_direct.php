<?php
// Test search API directly with curl simulation
$searchTerm = 'B';
$url = "http://localhost:8000/api/abonnement/search?q=" . urlencode($searchTerm);

// Use file_get_contents as a simple test
$options = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 5
    ]
]);

$response = @file_get_contents($url, false, $options);
$response_data = json_decode($response, true);

echo "Search API Test\n";
echo "URL: $url\n";
echo "Query: '$searchTerm'\n";
echo "Response Status: " . ($response !== false ? "SUCCESS" : "FAILED") . "\n";
echo "Results Count: " . (is_array($response_data) ? count($response_data) : "0") . "\n";
echo "Response: " . json_encode($response_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
?>
