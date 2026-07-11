<?php
namespace SPPMod\SPPSwarm\Agents;

use SPPMod\SPPSwarm\AutonomousAgent;
use SPPMod\SPPSwarm\SwarmHub;

/**
 * Class MagentoAgent
 * 
 * The Autonomous AI Agent for the Magento eCommerce Guest App.
 */
class MagentoAgent extends AutonomousAgent
{
    public function __construct()
    {
        parent::__construct('Magento_Agent');
    }

    public function handleIntent(string $intentName, array $contextData): void
    {
        // Magento might listen for 'user_registered' to proactively create a wishlist.
    }

    /**
     * This method would be triggered by a Cron/Daemon monitoring the SPP VirtualPDO or EventStore.
     */
    public function detectAbandonedCart(int $userId, float $cartTotal)
    {
        // Proactively broadcast to the Swarm so other apps can help close the sale!
        SwarmHub::broadcastIntent('user_abandoned_cart', [
            'user_id' => $userId,
            'cart_total' => $cartTotal
        ]);
    }
}
