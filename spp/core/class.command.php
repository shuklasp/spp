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
     * Gets the command description.
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Helpers for CLI output
     */
    protected function line(string $text): void { echo $text . "\n"; }
    protected function info(string $text): void { echo "\033[32mINFO: \033[0m" . $text . "\n"; }
    protected function warn(string $text): void { echo "\033[33mWARN: \033[0m" . $text . "\n"; }
    protected function error(string $text): void { echo "\033[31mERROR: \033[0m" . $text . "\n"; }

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
        } catch (\Exception $e) {}

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
