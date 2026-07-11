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

            $userData = [];
            if (class_exists(SPPAI::class)) {
                try {
                    $normalizedJson = SPPAI::callTool($prompt, []);
                    if (!empty($normalizedJson)) {
                        $userData = json_decode($normalizedJson, true) ?? [];
                    }
                } catch (\Exception $e) {
                    // AI failed, fallback to deterministic
                }
            }
            
            // Deterministic Fallback if AI was unavailable or failed
            if (empty($userData['email'])) {
                
                // Security: Prevent ReDoS by refusing to regex evaluate payloads over 50KB
                if (strlen($rawPayload) > 50000) {
                    echo json_encode(['status' => 'error', 'message' => 'Payload exceeds maximum size for regex fallback processing (50KB limit).']);
                    return;
                }

                // Security: Tighten regex quantifiers to {2,64} to prevent catastrophic backtracking
                if (preg_match('/[a-zA-Z0-9._%+-]{1,64}@[a-zA-Z0-9.-]{1,255}\.[a-zA-Z]{2,64}/', $rawPayload, $matches)) {
                    $userData['email'] = $matches[0];
                }
                
                // Attempt to extract name fields securely
                if (preg_match('/"first_?name"\s*:\s*"([^"]{1,100})"/i', $rawPayload, $matches)) {
                    $userData['firstname'] = $matches[1];
                }
                if (preg_match('/"last_?name"\s*:\s*"([^"]{1,100})"/i', $rawPayload, $matches)) {
                    $userData['lastname'] = $matches[1];
                }
            }

            if (!empty($userData['email'])) {
                // Magically normalized! Broadcast it to the Mesh.
                IntegrationFactory::broadcastUserSync($userData);
                echo json_encode(['status' => 'success', 'message' => 'Webhook normalized and broadcasted.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to normalize payload (AI and Fallback both failed).']);
            }

        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}
