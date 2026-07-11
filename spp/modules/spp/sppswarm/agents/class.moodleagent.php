<?php
namespace SPPMod\SPPSwarm\Agents;

use SPPMod\SPPSwarm\AutonomousAgent;
use SPPMod\SPPSwarm\SwarmHub;

/**
 * Class MoodleAgent
 * 
 * The Autonomous AI Agent for the Moodle LMS Guest App.
 */
class MoodleAgent extends AutonomousAgent
{
    public function __construct()
    {
        parent::__construct('Moodle_Agent');
    }

    public function handleIntent(string $intentName, array $contextData): void
    {
        if ($intentName === 'user_abandoned_cart') {
            $this->evaluateAbandonedCart($contextData['user_id'], (float)$contextData['cart_total']);
        }
    }

    private function evaluateAbandonedCart(int $userId, float $cartTotal)
    {
        // 1. Let the AI think about a strategy.
        $prompt = "A user abandoned a shopping cart worth \${$cartTotal}. Should we offer them a free $20 'Intro to Products' mini-course to regain their trust? Respond YES or NO.";
        $decision = trim($this->think($prompt));
        
        // GRACEFUL DEGRADATION: If AI is offline, fallback to deterministic business rules
        if ($decision === 'AI_OFFLINE_FALLBACK') {
            // Static rule: If cart value is over $50, always offer the $20 course.
            $decision = ($cartTotal > 50.00) ? 'YES' : 'NO';
        }

        if ($decision === 'YES') {
            $financialRisk = 20.00; // The value of the free course

            // 2. CHECK THE KERNEL GUARDRAIL
            if (SwarmHub::requestFinancialExecution($this->agentName, 'Provision Free Course', $financialRisk)) {
                $this->provisionCourse($userId);
            } else {
                // The SwarmHub blocked it and requested human review because financial risk > 0.
            }
        }
    }

    private function provisionCourse(int $userId)
    {
        // Code to natively inject a free course enrollment into Moodle
    }
}
