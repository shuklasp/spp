<?php
namespace SPPMod\SPPSwarm;

use SPPMod\SPPAI\SPPAI;

/**
 * Class AutonomousAgent
 * 
 * The abstract base class for all WebOS AI Agents.
 */
abstract class AutonomousAgent
{
    protected string $agentName;

    public function __construct(string $agentName)
    {
        $this->agentName = $agentName;
        SwarmHub::registerAgent($this);
    }

    /**
     * Determines how the agent reacts to an intent broadcast on the SwarmHub.
     */
    abstract public function handleIntent(string $intentName, array $contextData): void;

    /**
     * Helper to invoke the SPP AI Module for intelligent decision making.
     */
    protected function think(string $prompt): string
    {
        // 1. Check if AI is disabled globally or if we have a cached decision
        $cached = SwarmHub::getCachedAiDecision($prompt);
        if ($cached !== null) {
            return $cached;
        }

        try {
            // 2. Wrapper for the native SPPAI tool calling.
            $decision = SPPAI::callTool($prompt, []);
            
            // 3. Cache the decision to save future tokens
            SwarmHub::cacheAiDecision($prompt, $decision);
            
            return $decision;
        } catch (\Throwable $e) {
            // Graceful degradation: If AI is completely offline, return a specific flag
            // so the child agent knows to fallback to deterministic rule-based logic.
            return 'AI_OFFLINE_FALLBACK';
        }
    }
}
