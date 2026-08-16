<?php

namespace SPP\CLI;

/**
 * Abstract Class Command
 * Base class for all CLI commands in the SPP framework.
 */
abstract class Command
{
    /** @var string Command name (e.g. 'ui:serv') */
    protected string $name = '';

    /** @var string Command description */
    protected string $description = '';

    /** @var bool Hidden status */
    protected bool $hidden = false;

    /**
     * Executes the command.
     *
     * @param array $args Command line arguments
     */
    abstract public function execute(array $args): void;

    /**
     * Gets the command name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Indicates whether this command should be hidden from the public command list.
     */
    public function isHidden(): bool
    {
        return $this->hidden;
    }

    /**
     * Gets the command description.
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Indicates whether this command is strictly restricted to CLI environment execution.
     */
    public function isCLIOnly(): bool
    {
        return false;
    }

    /**
     * Helper to safely validate and escape SQL DDL identifiers.
     */
    protected function escapeIdentifier(string $identifier): string
    {
        if (!class_exists('\SPP\Core\SchemaValidator')) {
            require_once __DIR__ . '/class.schemavalidator.php';
        }
        return \SPP\Core\SchemaValidator::escapeIdentifier($identifier);
    }

    /**
     * Helpers for CLI output
     */
    protected function line(string $text): void
    {
        echo $text . "\n";
    }
    protected function info(string $text): void
    {
        echo "\033[32mINFO: \033[0m" . $text . "\n";
    }
    protected function warn(string $text): void
    {
        echo "\033[33mWARN: \033[0m" . $text . "\n";
    }
    protected function error(string $text): void
    {
        echo "\033[31mERROR: \033[0m" . $text . "\n";
    }

    /**
     * Helper to output JSON data for Admin UI proxying.
     * Automatically sets proper header if not in CLI (though usually this output is caught by ob_start).
     */
    protected function json($data, array $args = []): void
    {
        // If the command wants to conditionally return JSON based on --json flag
        if (!empty($args) && !$this->hasFlag($args, 'json')) {
            // Not in json mode, could potentially just dump it or skip
            // But usually if you call json(), you intend to output JSON.
            // We just output JSON string.
        }
        
        if (ob_get_level() > 0) {
            ob_clean();
        }
        echo json_encode($data);
    }

    // --- Legacy UI Polyfills ($la -> $this) ---
    protected string $_currentStatus = 'success';

    public function setStatus(string $status): self {
        $this->_currentStatus = $status;
        return $this;
    }
    public function setData($data): self {
        $this->json(is_array($data) ? $data : ['data' => $data]);
        return $this;
    }
    public function notify(string $message, string $type = 'success'): self {
        $this->json(['success' => ($this->_currentStatus !== 'error'), 'message' => $message]);
        return $this;
    }
    public function modal(string $title, string $html, array $buttons = []): self {
        $this->json(['success' => true, 'modal' => ['title' => $title, 'html' => $html, 'buttons' => $buttons]]);
        return $this;
    }
    public function closeModal(): self { return $this; }
    public function refresh(): self { return $this; }
    public function redirect(string $url): self { return $this; }
    public function executeClientCode(string $code): self { return $this; }
    public function addInstruction(array $instruction): self { return $this; }
    public function dispatch(string $event): self { return $this; }
    // ------------------------------------------

    /**
     * Helper to prompt the user for input.
     */
    protected function prompt(string $message, string $default = ''): string
    {
        echo $message . ($default ? " [{$default}]: " : "");
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);
        $result = trim($line !== false ? $line : '');
        return $result === '' ? $default : $result;
    }

    /**
     * Extracts an option using the ArgParser.
     * @param array $args The raw args array passed to execute()
     * @param string $name The option name (without --)
     * @param mixed $default
     */
    protected function getOption(array $args, string $name, $default = null)
    {
        if (!class_exists('\SPP\CLI\ArgParser')) {
            require_once __DIR__ . '/class.argparser.php';
        }
        $parsed = \SPP\CLI\ArgParser::parse($args);
        return $parsed['options'][$name] ?? $default;
    }

    /**
     * Extracts a positional argument.
     * @param array $args The raw args array passed to execute()
     * @param int $index 0-based index of the positional argument (after the command name)
     * @param mixed $default
     */
    protected function getArgument(array $args, int $index, $default = null)
    {
        if (!class_exists('\SPP\CLI\ArgParser')) {
            require_once __DIR__ . '/class.argparser.php';
        }
        $parsed = \SPP\CLI\ArgParser::parse($args);
        // Note: The first parsed argument is often the command itself.
        // Index 0 generally maps to the first argument *after* the command.
        // Depending on ArgParser behavior, we may need to offset by 1 if the command name is retained.
        $actualIndex = $index;
        if (!empty($parsed['arguments']) && $parsed['arguments'][0] === $this->getName()) {
            $actualIndex++;
        }
        return $parsed['arguments'][$actualIndex] ?? $default;
    }

    /**
     * Checks if a boolean flag is present.
     */
    protected function hasFlag(array $args, string $name): bool
    {
        if (!class_exists('\SPP\CLI\ArgParser')) {
            require_once __DIR__ . '/class.argparser.php';
        }
        $parsed = \SPP\CLI\ArgParser::parse($args);
        return isset($parsed['options'][$name]);
    }

    /**
     * Extracts and formats the command help text and usage.
     */
    public function getHelp(): string
    {
        $helpText = '';
        try {
            $ref = new \ReflectionClass($this);
            $method = $ref->getMethod('execute');
            if ($method->getFileName()) {
                $filename = $method->getFileName();
                $start = $method->getStartLine() - 1;
                $len = $method->getEndLine() - $start;
                $source = implode("", array_slice(file($filename), $start, $len));
                if (preg_match('/(?:echo|print)\s+["\'](Usage:\s*.*?)["\']/is', $source, $m)) {
                    $helpText = trim($m[1]);
                    $helpText = str_replace(['\n', '\r'], ["\n", ""], $helpText);
                }
            }
        } catch (\Exception $e) {
        }

        return $helpText;
    }

    /**
     * Renders an Admin UI form for this command.
     * Can be overridden by specific commands for custom, complex UIs.
     */
    public function renderAdminUI(): string
    {
        $name = htmlspecialchars($this->getName());
        $desc = htmlspecialchars($this->getDescription());
        $help = htmlspecialchars($this->getHelp());

        $html = '<div class="command-ui-container">';
        $html .= '  <h3>Command: <code>' . $name . '</code></h3>';
        $html .= '  <p>' . $desc . '</p>';
        if ($help) {
            $html .= '  <div class="command-help" style="margin-top: 15px; padding: 15px; background: var(--glass-bg); border-left: 4px solid var(--primary); border-radius: 6px; font-family: monospace; font-size: 0.9em; white-space: pre-wrap; color: var(--text);">' . $help . '</div>';
        }
        $html .= '  <hr>';
        $html .= '  <div class="form-group">';
        $html .= '    <label>Arguments (space separated):</label>';
        $html .= '    <input type="text" id="cmdArgs" class="spp-input" placeholder="e.g. --full myapp" style="background:var(--bg-color-alt); color:var(--text); border:1px solid var(--border-color);">';
        $html .= '  </div>';
        $html .= '  <button class="spp-btn" onclick="executeCommand(\'' . $name . '\')">Execute ' . $name . '</button>';
        $html .= '</div>';

        return $html;
    }
}
