<?php
/**
 * AI Management Service for SPP Admin
 */

function live_AI_GetRegistry($la, $params) {
    \SPP\Module::loadModule('sppai');
    $registry = \SPPMod\SPPAI\SPPAI::getRegistry();
    $la->setData(['registry' => $registry]);
}

function live_AI_Prompt($la, $params) {
    \SPP\Module::loadModule('sppai');
    $provider = $params['provider'] ?? null;
    $model = $params['model'] ?? null;
    $prompt = $params['prompt'] ?? '';
    
    if (!$prompt) return $la->setStatus('error')->notify("Prompt cannot be empty.");
    
    $ai = \SPPMod\SPPAI\SPPAI::class;
    if ($provider) $ai = $ai::using($provider);
    if ($model) $ai = $ai::withModel($model);
    
    try {
        $result = $ai::complete($prompt);
        $la->setData(['response' => $result]);
    } catch (\Exception $e) {
        $la->setStatus('error')->notify("AI Error: " . $e->getMessage());
    }
}
