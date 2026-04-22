<?php
// Test OpenAI connection and chatbot setup
require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

// Load environment variables
$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/../.env');

echo "=== Chatbot Diagnostics ===\n\n";

// Check API key
$apiKey = $_ENV['OPENAI_API_KEY'] ?? '';
$apiKey = trim($apiKey, '"\'');

echo "1. API Key Status:\n";
echo "   - Key present: " . (!empty($apiKey) ? "YES" : "NO") . "\n";
echo "   - Key length: " . strlen($apiKey) . " characters\n";
if (!empty($apiKey)) {
    echo "   - Key starts with: " . substr($apiKey, 0, 20) . "...\n";
    echo "   - Key format valid: " . (str_contains($apiKey, 'sk-proj-') ? "YES" : "NO") . "\n";
}

echo "\n2. Project Documentation:\n";
$docsPath = __DIR__ . '/docs';
if (is_dir($docsPath)) {
    $files = glob($docsPath . '/*.md');
    echo "   - Docs directory found\n";
    echo "   - Markdown files: " . count($files) . "\n";
} else {
    echo "   - Docs directory NOT found\n";
}

echo "\n3. Testing OpenAI Connection:\n";
if (!empty($apiKey) && str_contains($apiKey, 'sk-proj-')) {
    try {
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'user', 'content' => 'Hello'],
            ],
            'max_tokens' => 10,
        ]));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        echo "   - Connection: " . ($error ? "FAILED - $error" : "SUCCESS") . "\n";
        echo "   - HTTP Status: " . $httpCode . "\n";
        
        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['error'])) {
                echo "   - OpenAI Error: " . $data['error']['message'] . "\n";
            } elseif (isset($data['choices'][0]['message']['content'])) {
                echo "   - OpenAI Response: OK\n";
            } else {
                echo "   - Response: " . substr($response, 0, 100) . "\n";
            }
        }
    } catch (Exception $e) {
        echo "   - Exception: " . $e->getMessage() . "\n";
    }
} else {
    echo "   - SKIPPED: Invalid API key\n";
}

echo "\n=== End Diagnostics ===\n";
