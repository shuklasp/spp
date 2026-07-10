<?php
namespace SPPMod\SPPIntegrations;

use SPP\MVC\Controllers\ViewController;
use SPPMod\SPPAI\SPPAI;

/**
 * Class IntegrationWebhookController
 * 
 * The Ingress Hub. Receives arbitrary, proprietary webhooks from cloud applications
 * and uses SPP AI to dynamically normalize them into the standard SPP Schema for broadcasting.
 */
class IntegrationWebhookController extends ViewController
{
    /**
     * Endpoint: /api/integration/webhook/ingress
     */
    public function handleIngress(): void
    {
        $rawPayload = file_get_contents('php://input');
        if (empty($rawPayload)) {
            echo json_encode(['status' => 'error', 'message' => 'Empty payload']);
            return;
        }

        try {
            // Use SPP's Native AI to inspect the chaotic payload
            $prompt = "
            You are an expert Data Engineer. I am going to give you a raw JSON webhook payload from an unknown third-party application.
            Extract the core user identity and map it to the following JSON schema:
            {
                \"username\": \"string\",
                \"email\": \"string\",
                \"firstname\": \"string\",
                \"lastname\": \"string\"
            }
            Return ONLY the valid JSON map. Do not include markdown or explanations.
            
            Raw Payload: {$rawPayload}
            ";

            if (class_exists(SPPAI::class)) {
                $normalizedJson = SPPAI::callTool($prompt, []);
                $userData = json_decode($normalizedJson, true);

                if (json_last_error() === JSON_ERROR_NONE && !empty($userData['email'])) {
                    // Magically normalized! Broadcast it to the Mesh.
                    IntegrationFactory::broadcastUserSync($userData);
                    echo json_encode(['status' => 'success', 'message' => 'Webhook normalized via AI and broadcasted.']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'AI failed to normalize payload.']);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'SPPAI module not available.']);
            }

        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}
