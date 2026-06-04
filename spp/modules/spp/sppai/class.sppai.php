<?php

namespace SPPMod\SPPAI;

/**
 * SPPAI Engine structurally effortlessly logically seamlessly fluidly efficiently instinctively smartly effectively cleanly instinctively intuitively brilliantly.
 */
class SPPAI extends \SPP\SPPObject
{
    private static ?AIDriverInterface $activeDriver = null;
    private static ?string $selectedProvider = null;
    private static ?string $selectedModel = null;

    /**
     * Set the AI provider to use for the next request.
     * @param string $provider
     * @return self
     */
    public static function using(string $provider): string
    {
        self::$selectedProvider = $provider;
        self::$selectedModel = null;
        return self::class;
    }

    /**
     * Set the specific model to use for the next request.
     * @param string $model
     * @return self
     */
    public static function withModel(string $model): string
    {
        self::$selectedModel = $model;
        return self::class;
    }

    /**
     * Get or initialize the appropriate driver.
     */
    private static function getDriver(): AIDriverInterface
    {
        $provider = self::$selectedProvider ?: \SPP\Module::getConfig('default_provider', 'sppai') ?: 'google';
        $config = \SPP\Module::getConfig('providers', 'sppai')[$provider] ?? [];

        $className = "SPPMod\\SPPAI\\" . ($config['class'] ?? (ucfirst($provider) . "Driver"));

        if (!class_exists($className)) {
            throw new \SPP\SPPException("AI Driver not found: {$className}");
        }

        $driver = new $className($config);
        if (self::$selectedModel) {
            $driver->setModel(self::$selectedModel);
        }

        // Reset temporary selection for next call
        self::$selectedProvider = null;
        self::$selectedModel = null;

        return $driver;
    }

    public static function getRegistry(): array
    {
        $providers = \SPP\Module::getConfig('providers', 'sppai') ?: [];
        $registry = [];

        foreach ($providers as $id => $config) {
            $registry[$id] = [
                'name' => ucfirst($id),
                'default_model' => $config['default_model'] ?? null,
                'models' => []
            ];

            try {
                $driver = self::using($id)::getDriver();
                if (method_exists($driver, 'getSupportedModels')) {
                    $registry[$id]['models'] = $driver->getSupportedModels();
                }
            } catch (\Exception $e) {
                // Skip if driver fails to load
            }
        }

        return $registry;
    }

    public static function complete(string $prompt, array $options = []): string
    {
        return self::getDriver()->complete($prompt, $options);
    }

    public static function chat(array $messages, array $options = []): string
    {
        return self::getDriver()->chat($messages, $options);
    }

    public static function createEmbedding(string $text): array
    {
        return self::getDriver()->embed($text);
    }

    public static function callTool(string $prompt, array $tools, array $options = []): array|string
    {
        return self::getDriver()->callTool($prompt, $tools, $options);
    }

    public static function structured(string $prompt, array $jsonSchema, array $options = []): array|string
    {
        return self::getDriver()->structured($prompt, $jsonSchema, $options);
    }

    /**
     * Dynamically constructs and generates an absolute auto-discoverable standard metadata mapping schema
     * compatible natively with /.well-known/spp-ai-plugin.json patterns.
     */
    public static function generateAiManifest(): array
    {
        $appname = \SPP\Scheduler::getContext();
        return [
            'schema_version' => 'v1',
            'name_for_model' => 'spp_application_framework',
            'name_for_human' => 'SPP Core Enterprise Hub',
            'description_for_model' => 'Pluggable module infrastructure interface enabling tool discovery, layout updates, and zero-trust execution queries.',
            'auth' => ['type' => 'none'],
            'api' => [
                'type' => 'openapi',
                'url' => \SPP\App::getBaseUrl($appname) . '/api.php?__svc=openapi_spec'
            ],
            'logo_url' => \SPP\App::getBaseUrl($appname) . '/spp-logo.png',
            'contact_email' => 'support@sppframework.internal',
            'legal_info_url' => \SPP\App::getBaseUrl($appname) . '/legal'
        ];
    }
}
