<?php
namespace SPPMod\SPPSwarm;

/**
 * Class SwarmHub
 * 
 * The central communication bus for the Autonomous AI Agent Swarm.
 * Allows Agents to broadcast intents and listen for business events.
 */
class SwarmHub
{
    /** @var AutonomousAgent[] */
    private static $registeredAgents = [];
    
    // Global AI Toggle (Allows completely disabling AI to save costs or run locally)
    private static $aiEnabled = true;

    // The AI Decision Cache Directory
    private static $cacheDir = __DIR__ . '/../../cache/ai/';
    private static $cacheTtlSeconds = 86400; // Default 24 hours

    public static function setAiEnabled(bool $enabled)
    {
        self::$aiEnabled = $enabled;
    }

    public static function isAiEnabled(): bool
    {
        return self::$aiEnabled;
    }

    /**
     * Checks if a decision for a specific prompt exists in the cache and is valid.
     */
    public static function getCachedAiDecision(string $prompt): ?string
    {
        if (!self::$aiEnabled) return 'AI_OFFLINE_FALLBACK';

        $hash = md5($prompt);
        $file = self::$cacheDir . $hash . '.cache';

        if (file_exists($file)) {
            $mtime = filemtime($file);
            if ((time() - $mtime) <= self::$cacheTtlSeconds) {
                return file_get_contents($file);
            }
            unlink($file); // Expired
        }
        return null;
    }

    /**
     * Saves an AI decision to the cache.
     */
    public static function cacheAiDecision(string $prompt, string $decision): void
    {
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0777, true);
        }
        $hash = md5($prompt);
        file_put_contents(self::$cacheDir . $hash . '.cache', $decision);
    }

    /**
     * Clears the entire AI decision cache manually.
     */
    public static function clearAiCache(): void
    {
        if (is_dir(self::$cacheDir)) {
            $files = glob(self::$cacheDir . '*.cache');
            foreach ($files as $file) {
                unlink($file);
            }
        }
    }

    /**
     * Registers an agent to listen to the Swarm.
     */
    public static function registerAgent(AutonomousAgent $agent)
    {
        self::$registeredAgents[] = $agent;
    }

    /**
     * Broadcasts an intent to all registered agents in the Swarm.
     * 
     * @param string $intentName e.g., 'user_abandoned_cart'
     * @param array $contextData e.g., ['user_id' => 123, 'cart_total' => 10.00]
     */
    public static function broadcastIntent(string $intentName, array $contextData)
    {
        foreach (self::$registeredAgents as $agent) {
            // Asynchronous event dispatching in reality.
            // Executing synchronously here for architectural demonstration.
            $agent->handleIntent($intentName, $contextData);
        }
    }

    /**
     * The Financial Guardrail.
     * Before an Agent can execute an action with financial value, it MUST clear this guardrail.
     */
    public static function requestFinancialExecution(string $agentName, string $proposedAction, float $financialValue): bool
    {
        if ($financialValue > 0) {
            // Strict Guardrail: Flag for Human Approval
            self::logForHumanApproval($agentName, $proposedAction, $financialValue);
            return false; // Blocks immediate execution
        }
        
        return true; // Safe to execute zero-value actions
    }

    private static function logForHumanApproval(string $agentName, string $proposedAction, float $value)
    {
        // Mock logging to a dashboard database table.
        // echo "[GUARDRAIL] Blocked $agentName from executing '$proposedAction'. Financial risk: \$$value. Flagged for human review.\n";
    }
}
