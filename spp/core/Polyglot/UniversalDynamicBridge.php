<?php
declare(strict_types=1);

namespace SPP\Core\Polyglot;

/**
 * Class UniversalDynamicBridge
 *
 * Config-driven polyglot bridge that dynamically prepares and executes any registered language
 * or custom domain-specific language (DSL) defined in LanguageRegistry.
 */
class UniversalDynamicBridge extends DefaultBridge implements PolyglotBridgeInterface
{
    private string $lang;
    private ?array $driver;
    private ?string $compiledBinary = null;

    public function __construct(string $lang)
    {
        $this->lang = strtolower($lang);
        $this->driver = LanguageRegistry::getDriver($this->lang);
    }

    public function prepare(string $module, string $func, array $args, array $runtime, string $sharedDir): ?array
    {
        if (!$this->driver) {
            return null;
        }

        // Handle compiled languages with race-free CompilationManager
        if (($this->driver['category'] ?? '') === 'compiled' && isset($this->driver['execution']['compilation'])) {
            $compilation = $this->driver['execution']['compilation'];
            $cmdTemplate = $compilation['command'] ?? '';
            $compiler = $runtime['path'] ?? ($this->driver['discovery']['binaries']['windows'][0] ?? $this->lang);
            $compilerVersion = $runtime['version'] ?? 'Unknown';

            $moduleAbs = realpath($module) ?: $module;
            $cacheDir = CompilationManager::getCacheDir($this->lang);

            $buildResult = CompilationManager::compile(
                $this->lang,
                $moduleAbs,
                function (string $tempOutputBinary) use ($cmdTemplate, $moduleAbs, $cacheDir) {
                    $cmd = str_replace(
                        ['{source}', '{output_binary}', '{cache_dir}'],
                        [$moduleAbs, $tempOutputBinary, $cacheDir],
                        $cmdTemplate
                    );

                    // Execute compile command safely via ProcessRunner using structured arguments
                    $tokens = self::tokenizeCommand($cmd);
                    return ProcessRunner::run($tokens, dirname($moduleAbs), [], null, 60.0);
                },
                $compiler,
                $compilerVersion,
                $cmdTemplate
            );

            if (!$buildResult['success']) {
                return $buildResult;
            }

            $this->compiledBinary = $buildResult['binary'];
        }

        return null;
    }

    public function getExecutionPlan(string $module, string $func, array $args, array $runtime, string $sharedDir): ExecutionPlan
    {
        $moduleAbs = realpath($module) ?: $module;

        // If we have a compiled binary, return it directly
        if ($this->compiledBinary && file_exists($this->compiledBinary)) {
            return new ExecutionPlan(
                executable: $this->compiledBinary,
                arguments: [$func],
                workingDirectory: dirname($moduleAbs),
                environment: [],
                inputTransport: 'stdin_json',
                outputFormat: 'json',
                timeout: 30.0
            );
        }

        if ($this->driver && isset($this->driver['execution']['ephemeral']['command'])) {
            $binary = $runtime['path'] ?? $this->lang;
            $dispatcher = $this->driver['execution']['daemon']['dispatcher'] ?? '';
            if ($dispatcher) {
                $rawDisp = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $dispatcher);
                if (file_exists(SPP_BASE_DIR . DIRECTORY_SEPARATOR . $rawDisp)) {
                    $dispatcher = SPP_BASE_DIR . DIRECTORY_SEPARATOR . $rawDisp;
                } elseif (file_exists(dirname(SPP_BASE_DIR) . DIRECTORY_SEPARATOR . $rawDisp)) {
                    $dispatcher = dirname(SPP_BASE_DIR) . DIRECTORY_SEPARATOR . $rawDisp;
                } elseif (file_exists(SPP_BASE_DIR . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'polyglot' . DIRECTORY_SEPARATOR . basename($rawDisp))) {
                    $dispatcher = SPP_BASE_DIR . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'polyglot' . DIRECTORY_SEPARATOR . basename($rawDisp);
                }
            }

            $cmdString = str_replace(
                ['{binary}', '{source}', '{func}', '{dispatcher}'],
                [$binary, $moduleAbs, $func, $dispatcher],
                $this->driver['execution']['ephemeral']['command']
            );

            // Parse command string into arguments array while preserving quoted paths with spaces
            $tokens = self::tokenizeCommand($cmdString);
            $executable = array_shift($tokens) ?: $binary;

            $workingDir = is_dir($sharedDir . DIRECTORY_SEPARATOR . 'bridge')
                ? $sharedDir . DIRECTORY_SEPARATOR . 'bridge'
                : dirname($moduleAbs);

            return new ExecutionPlan(
                executable: $executable,
                arguments: $tokens,
                workingDirectory: $workingDir,
                environment: [],
                inputTransport: 'stdin_json',
                outputFormat: 'json',
                timeout: 30.0
            );
        }

        // Fallback to DefaultBridge
        $defaultBridge = new DefaultBridge();
        return $defaultBridge->getExecutionPlan($module, $func, $args, $runtime, $sharedDir);
    }

    public function getCommand(string $module, string $func, array $args, array $runtime, string $sharedDir): string
    {
        return $this->getExecutionPlan($module, $func, $args, $runtime, $sharedDir)->toCommandString();
    }

    /**
     * Splits a command line into arguments while respecting single and double quotes.
     */
    protected static function tokenizeCommand(string $cmd): array
    {
        $tokens = [];
        $pattern = '/(?:"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"|\'([^\'\\\\]*(?:\\\\.[^\'\\\\]*)*)\'|([^\s]+))/';
        if (preg_match_all($pattern, $cmd, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                if (isset($m[1]) && $m[1] !== '') {
                    $tokens[] = str_replace('\"', '"', $m[1]);
                } elseif (isset($m[2]) && $m[2] !== '') {
                    $tokens[] = str_replace("\\'", "'", $m[2]);
                } elseif (isset($m[3]) && $m[3] !== '') {
                    $tokens[] = $m[3];
                }
            }
        }
        return $tokens;
    }
}
