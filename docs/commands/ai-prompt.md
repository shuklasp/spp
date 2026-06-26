# NAME

`ai:prompt`

# SYNOPSIS

`php spp.php ai:prompt "<prompt>" [--app=<appname>] [--provider=<provider>] [--model=<model>]`

# PURPOSE

Sends a natural language prompt directly to the configured AI provider and displays the completion response in the console.

# OPTIONS AVAILABLE

- `"<prompt>"` : The textual prompt to send to the AI provider. Must be enclosed in quotes if it contains spaces.
- `--app=<appname>` : The specific SPP Application context to load. Defaults to `default`.
- `--provider=<provider>` : Explicitly define which AI provider service to utilize (e.g., openai, anthropic).
- `--model=<model>` : Specify the exact model identifier to request from the provider (e.g., gpt-4, claude-3-opus).

# UNDER THE HOOD ACTIVITY

The command begins by iterating through the standard CLI `$args` array to extract the prompt string and any `--app`, `--provider`, or `--model` flags. To isolate the execution environment, the application logic is fully wrapped inside an `\SPP\Scheduler::withContext()` callback using the target application name.

Once the context is instantiated, the command attempts to dynamically load the `sppai` module via `\SPP\Module::loadModule('sppai')` and verifies the presence of the `\SPPMod\SPPAI\SPPAI` core class. The AI service client is subsequently built dynamically using static factory methods: calling `SPPAI::using($provider)` if a provider was overridden, and `SPPAI::withModel($model)` if a model was explicitly chosen. The command makes a synchronous completion request using `$ai::complete($prompt)` and echoes the parsed string response to standard output, catching and reporting any exceptions thrown by the provider.

# EXAMPLES

Send a simple prompt to the default AI provider:
```bash
php spp.php ai:prompt "What is the capital of France?"
```

Target a specific AI provider and model:
```bash
php spp.php ai:prompt "Write a short poem" --provider=openai --model=gpt-4
```
