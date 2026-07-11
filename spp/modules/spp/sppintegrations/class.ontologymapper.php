<?php
namespace SPPMod\SPPIntegrations;

use SPPMod\SPPAI\SPPAI;

/**
 * Class OntologyMapper
 * 
 * The universal translation engine. Translates proprietary guest app schemas
 * into the Universal SPP Entity Schema (e.g., Moodle Course -> SPP Product).
 */
class OntologyMapper
{
    /**
     * Translates a raw payload into a standard SPP array.
     */
    public static function normalize(string $sourceApp, string $targetType, array $rawPayload): array
    {
        // 1. Try static known rule mappings (Fast Path)
        $mapped = self::applyStaticRules($sourceApp, $targetType, $rawPayload);
        if ($mapped !== null) {
            return $mapped;
        }

        // 2. Fallback to AI Ontology Mapping
        return self::applyAiMapping($sourceApp, $targetType, $rawPayload);
    }

    private static function applyStaticRules(string $sourceApp, string $targetType, array $payload): ?array
    {
        if ($sourceApp === 'moodle' && $targetType === 'product') {
            return [
                'title'       => $payload['fullname'] ?? 'Unknown Course',
                'description' => $payload['summary'] ?? '',
                'price'       => 0.00, // Default business logic for course cross-sync
                'sku'         => 'MDL-' . ($payload['id'] ?? uniqid()),
            ];
        }
        
        if ($sourceApp === 'wordpress' && $targetType === 'post') {
            return [
                'title'   => $payload['post_title'] ?? '',
                'content' => $payload['post_content'] ?? '',
                'author'  => $payload['post_author'] ?? 'Admin',
            ];
        }

        return null;
    }

    private static function applyAiMapping(string $sourceApp, string $targetType, array $payload): array
    {
        $prompt = "You are the SPP Ontology Mapper. Translate this JSON payload from '$sourceApp' into a standard '$targetType' array schema. Return only valid JSON. Payload: " . json_encode($payload);
        
        try {
            $aiResponse = SPPAI::callTool($prompt, []);
            $decoded = json_decode($aiResponse, true);
            
            return is_array($decoded) ? $decoded : $payload; // Fallback to raw payload on bad JSON
        } catch (\Throwable $e) {
            // AI is completely unavailable (e.g. offline, no API key).
            // Graceful degradation: return the raw unmapped payload so the pipeline doesn't break.
            return $payload;
        }
    }
}
