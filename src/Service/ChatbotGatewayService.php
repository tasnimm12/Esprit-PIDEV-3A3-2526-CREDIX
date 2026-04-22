<?php

namespace App\Service;

class ChatbotGatewayService
{
    public function __construct(
        private readonly LocalChatbotService $localChatbotService,
        private readonly string $mode = 'local'
    ) {
    }

    public function chat(string $message): array
    {
        $mode = strtolower(trim($this->mode));

        if ($mode === 'openai' || $mode === 'auto') {
            return [
                'error' => false,
                'message' => '',
                'response' => 'The chatbot is currently running in local documentation mode. OpenAI mode is unavailable because the Symfony HTTP client dependency is not installed in this project.',
            ];
        }

        return $this->localChatbotService->chat($message);
    }
}
