<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ChatbotService
{
    private string $projectContext = '';
    private string $apiKey;

    public function __construct(
        private readonly ParameterBagInterface $params,
        ?string $openaiApiKey = null
    ) {
        // Try to get API key from constructor injection, environment variables, or $_ENV
        $this->apiKey = $openaiApiKey 
            ?? ($_ENV['OPENAI_API_KEY'] ?? '')
            ?? (getenv('OPENAI_API_KEY') ?: '')
            ?? ($_SERVER['OPENAI_API_KEY'] ?? '');
        
        // Remove quotes if present (from .env file parsing)
        $this->apiKey = trim($this->apiKey, '"\'');
        
        error_log('ChatbotService initialized. API Key length: ' . strlen($this->apiKey) . ' chars');
        if (!empty($this->apiKey)) {
            error_log('ChatbotService: API Key starts with: ' . substr($this->apiKey, 0, 20) . '...');
        }
        
        $this->loadProjectContext();
    }

    private function loadProjectContext(): void
    {
        try {
            $projectDir = $this->params->get('kernel.project_dir');
            $docsPath = $projectDir . '/docs';
            
            error_log('Chatbot: Looking for docs at: ' . $docsPath);
            
            if (is_dir($docsPath)) {
                $files = glob($docsPath . '/*.md');
                error_log('Chatbot: Found ' . count($files) . ' documentation files');
                
                $context = '';
                foreach ($files as $file) {
                    if (is_file($file) && filesize($file) < 500000) {
                        $context .= "File: " . basename($file) . "\n";
                        $context .= file_get_contents($file) . "\n\n";
                    }
                }
                
                // Truncate to reasonable size for API
                $this->projectContext = substr($context, 0, 12000);
                error_log('Chatbot: Loaded project context, size: ' . strlen($this->projectContext));
            } else {
                error_log('Chatbot: Docs directory not found at ' . $docsPath);
            }
        } catch (\Exception $e) {
            error_log('Chatbot: Error loading project context: ' . $e->getMessage());
        }
    }

    public function chat(string $userMessage): array
    {
        error_log('=== Chatbot Request Start ===');
        error_log('Chatbot: Received message: ' . substr($userMessage, 0, 100));
        error_log('Chatbot: API Key present: ' . (!empty($this->apiKey) ? 'YES' : 'NO'));
        error_log('Chatbot: API Key length: ' . strlen($this->apiKey));
        
        // Validate API key
        if (empty($this->apiKey)) {
            error_log('Chatbot: API key is empty');
            return [
                'error' => true,
                'message' => 'Chatbot API key is not configured',
                'response' => null
            ];
        }

        if (strlen($this->apiKey) < 20 || !str_contains($this->apiKey, 'sk-')) {
            error_log('Chatbot: API key format appears invalid');
            return [
                'error' => true,
                'message' => 'OpenAI API key appears to be invalid',
                'response' => null
            ];
        }

        $systemPrompt = "You are a helpful AI assistant for an Insurance and Finance Management application called Credix. 

KEY INFORMATION:
- The application helps users manage insurance policies, claims, credits, and financial accounts
- Users can file insurance claims (sinistres), manage subscriptions, and handle financial transactions
- Answer questions about how to use the application features

" . ($this->projectContext ?: "Application features include: Insurance Policies, Claims Management, Credit Applications, Financial Planning, Subscription Plans, and User Account Management.") . "

INSTRUCTIONS:
- ONLY answer questions related to this insurance/finance application
- Be helpful, concise, and use simple language
- If asked about something unrelated, politely redirect to application topics
- Provide clear, actionable answers
- When unsure, ask clarifying questions";

        try {
            error_log('Chatbot: Preparing API request');
            
            $requestData = [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt
                    ],
                    [
                        'role' => 'user',
                        'content' => $userMessage
                    ]
                ],
                'temperature' => 0.7,
                'max_tokens' => 500,
            ];

            error_log('Chatbot: Sending request to OpenAI');
            
            $apiResponse = $this->sendOpenAiRequest($requestData);
            $statusCode = $apiResponse['status_code'];
            error_log('Chatbot: OpenAI returned status code: ' . $statusCode);

            $data = $apiResponse['data'];
            error_log('Chatbot: OpenAI response: ' . json_encode($data));

            // Check for API errors
            if (isset($data['error'])) {
                $errorMsg = $data['error']['message'] ?? 'Unknown error';
                error_log('Chatbot: OpenAI API error: ' . $errorMsg);
                return [
                    'error' => true,
                    'message' => 'OpenAI error: ' . $errorMsg,
                    'response' => null
                ];
            }

            // Check for successful response
            if (isset($data['choices'][0]['message']['content'])) {
                $content = $data['choices'][0]['message']['content'];
                error_log('Chatbot: Got response: ' . substr($content, 0, 100));
                error_log('=== Chatbot Request Success ===');
                
                return [
                    'error' => false,
                    'message' => '',
                    'response' => $content
                ];
            }

            error_log('Chatbot: Response format invalid: ' . json_encode($data));
            return [
                'error' => true,
                'message' => 'Invalid response format from OpenAI',
                'response' => null
            ];

        } catch (\Throwable $e) {
            $errorTrace = $e->getTraceAsString();
            error_log('Chatbot Exception: ' . get_class($e));
            error_log('Chatbot Message: ' . $e->getMessage());
            error_log('Chatbot Trace: ' . $errorTrace);
            error_log('=== Chatbot Request Failed ===');
            
            return [
                'error' => true,
                'message' => 'Error: ' . $e->getMessage(),
                'response' => null
            ];
        }
    }

    private function sendOpenAiRequest(array $requestData): array
    {
        $payload = json_encode($requestData, JSON_THROW_ON_ERROR);

        if (function_exists('curl_init')) {
            $ch = curl_init('https://api.openai.com/v1/chat/completions');

            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->apiKey,
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_TIMEOUT => 30,
            ]);

            $body = curl_exec($ch);

            if ($body === false) {
                $error = curl_error($ch);
                curl_close($ch);

                throw new \RuntimeException('cURL error: ' . $error);
            }

            $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return [
                'status_code' => $statusCode,
                'data' => json_decode($body, true, 512, JSON_THROW_ON_ERROR),
            ];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'Authorization: Bearer ' . $this->apiKey,
                    'Content-Type: application/json',
                ]),
                'content' => $payload,
                'timeout' => 30,
                'ignore_errors' => true,
            ],
        ]);

        $body = @file_get_contents('https://api.openai.com/v1/chat/completions', false, $context);

        if ($body === false) {
            throw new \RuntimeException('Unable to connect to OpenAI.');
        }

        $statusCode = 0;
        $responseHeaders = $http_response_header ?? [];

        if (isset($responseHeaders[0]) && preg_match('/\s(\d{3})\s/', $responseHeaders[0], $matches) === 1) {
            $statusCode = (int) $matches[1];
        }

        return [
            'status_code' => $statusCode,
            'data' => json_decode($body, true, 512, JSON_THROW_ON_ERROR),
        ];
    }
}

