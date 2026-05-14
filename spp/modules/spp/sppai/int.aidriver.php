<?php
namespace SPPMod\SPPAI;

/**
 * Interface AIDriverInterface
 * Standardizes AI provider implementations.
 */
interface AIDriverInterface
{
    /**
     * Single completion request.
     * @param string $prompt
     * @param array $options
     * @return string
     */
    public function complete(string $prompt, array $options = []): string;

    /**
     * Multi-turn chat request.
     * @param array $messages
     * @param array $options
     * @return string
     */
    public function chat(array $messages, array $options = []): string;

    /**
     * Generate text embeddings.
     * @param string $text
     * @return array
     */
    public function embed(string $text): array;

    /**
     * Set the specific model to use for this driver instance.
     * @param string $model
     * @return self
     */
    public function setModel(string $model): self;

    /**
     * Get the list of models supported by this driver.
     * @return array
     */
    public function getSupportedModels(): array;

    /**
     * Executes single/multi-turn Agentic tool calls autonomously.
     * @param string $prompt
     * @param array $tools
     * @param array $options
     * @return array|string
     */
    public function callTool(string $prompt, array $tools, array $options = []): array|string;

    /**
     * Enforces strict structured generation against a specified JSON Schema.
     * @param string $prompt
     * @param array $jsonSchema
     * @param array $options
     * @return array|string
     */
    public function structured(string $prompt, array $jsonSchema, array $options = []): array|string;
}
