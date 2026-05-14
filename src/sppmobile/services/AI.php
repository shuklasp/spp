<?php
/**
 * AI Service for Satya Studio
 * Bridges the Studio UI to the SPPAI Framework Module.
 */

use SPPMod\SPPAI\SPPAI;

function live_Mobile_AiComplete($la, $req) {
    $prompt = $req['prompt'] ?? '';
    $provider = $req['provider'] ?? null;
    $model = $req['model'] ?? null;
    $context = $req['context'] ?? '';

    if (empty($prompt)) return $la->setStatus('error')->notify('Prompt is required.');

    try {
        $ai = SPPAI::class;
        if ($provider) $ai = $ai::using($provider);
        if ($model) $ai = $ai::withModel($model);

        // Enhance prompt with context if provided
        $fullPrompt = $prompt;
        if (!empty($context)) {
            $fullPrompt = "Context: $context\n\nTask: $prompt";
        }

        $result = $ai::complete($fullPrompt);
        $la->setData(['result' => $result])->setStatus('success');
    } catch (\Exception $e) {
        $la->setStatus('error')->notify('AI Engine Error: ' . $e->getMessage());
    }
}

function live_Mobile_AiGenerateBlueprint($la, $req) {
    $description = $req['description'] ?? '';
    $provider = $req['provider'] ?? null;

    if (empty($description)) return $la->setStatus('error')->notify('Description is required.');

    $systemPrompt = \SPP\Module::getConfig('system_prompts', 'sppai')['app_builder'] ?? '';
    
    try {
        $ai = SPPAI::class;
        if ($provider) $ai = $ai::using($provider);
        
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Generate a JSON blueprint for: $description. Include 'type', 'props', and 'children'."]
        ];

        $result = $ai::chat($messages, ['temperature' => 0.3]);
        
        // Extract JSON if AI wrapped it in markdown
        if (preg_match('/```json\s*(.*?)\s*```/s', $result, $matches)) {
            $result = $matches[1];
        }

        $blueprint = json_decode($result, true);
        if (!$blueprint) {
             return $la->setStatus('error')->notify('AI generated invalid JSON. Raw: ' . substr($result, 0, 100));
        }

        $la->setData(['blueprint' => $blueprint])->setStatus('success');
    } catch (\Exception $e) {
        $la->setStatus('error')->notify('AI Blueprint Error: ' . $e->getMessage());
    }
}

function live_Mobile_GetAiRegistry($la, $req) {
    try {
        $registry = SPPAI::getRegistry();
        $la->setData(['registry' => $registry])->setStatus('success');
    } catch (\Exception $e) {
        $la->setStatus('error')->notify('Registry Error: ' . $e->getMessage());
    }
}

function live_Mobile_AiFixLogic($la, $req) {
    $code = $req['code'] ?? '';
    $instruction = $req['instruction'] ?? 'Fix any errors and optimize this code.';
    $provider = $req['provider'] ?? null;

    if (empty($code)) return $la->setStatus('error')->notify('Code is required.');

    $systemPrompt = \SPP\Module::getConfig('system_prompts', 'sppai')['logic_cleaner'] ?? '';

    try {
        $ai = SPPAI::class;
        if ($provider) $ai = $ai::using($provider);

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Instruction: $instruction\n\nCode:\n$code"]
        ];

        $result = $ai::chat($messages, ['temperature' => 0.1]);

        if (preg_match('/```javascript\s*(.*?)\s*```/s', $result, $matches)) {
            $result = $matches[1];
        }

        $la->setData(['code' => $result])->setStatus('success');
    } catch (\Exception $e) {
        $la->setStatus('error')->notify('AI Logic Error: ' . $e->getMessage());
    }
}
