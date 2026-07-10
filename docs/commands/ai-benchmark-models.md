## ai:benchmark:models

**Purpose**: Benchmark configured AI models (Ollama, OpenAI, Anthropic) for tool calling latency, response time, and JSON schema accuracy.

### Synopsis

```bash
php spp.php ai:benchmark:models [--provider=<name>] [--models=<m1,m2>]
```

### Extended Usage

The `ai:benchmark:models` command evaluates the performance and accuracy of various AI models when performing structured tool calls (`SPPAI::callTool()`). It sends a standard prompt and tool schema (sales tax calculation) to each model, tracks the latency in milliseconds, and validates whether the generated output conforms to the expected JSON schema.

Example:
```bash
php spp.php ai:benchmark:models --provider=ollama --models=llama3,mistral,gemma2
```

### Options Available

- `--provider=<name>`: The AI provider driver to benchmark (e.g. `ollama`, `openai`, `anthropic`). Defaults to `ollama`.
- `--models=<m1,m2>`: Comma-separated list of model names to evaluate. Defaults to `llama3,mistral,gemma2`.

### Under the Hood Activity

1. **Driver Instantiation**: Initializes the requested provider driver via `SPPAI::using($provider)`.
2. **Outbound HTTP Calls**: Makes REST API requests to the configured local Ollama daemon (e.g. `http://127.0.0.1:11434/api/chat`) or remote AI provider endpoints.
3. **Validation & Profiling**: Measures response latency using `microtime(true)` and validates the returned tool parameters against the provided JSON schema.
4. **No Database Interaction**: This command operates strictly in memory and does not write to the application database.
