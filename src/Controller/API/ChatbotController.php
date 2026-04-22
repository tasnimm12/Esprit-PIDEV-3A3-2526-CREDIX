<?php

namespace App\Controller\API;

use App\Service\ChatbotGatewayService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/chatbot', name: 'app_api_chatbot_')]
class ChatbotController extends AbstractController
{
    #[Route('/chat', name: 'chat', methods: ['POST'])]
    public function chat(Request $request, ChatbotGatewayService $chatbotGatewayService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $message = $data['message'] ?? '';

        if (empty($message)) {
            return $this->json([
                'error' => true,
                'message' => 'Message is required'
            ], 400);
        }

        // Prevent message injection attacks
        $message = substr(trim($message), 0, 1000);

        $result = $chatbotGatewayService->chat($message);

        return $this->json($result);
    }
}
