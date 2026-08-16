<?php

namespace SPP\CLI\Commands;

/**
 * Class MakeServiceCommand
 * Scaffolds a new service class.
 */
class MakeServiceCommand extends BaseMakeCommand
{
    protected string $name = 'make:service';
    protected string $description = 'Create a new service class';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $name = $this->getArgument($args, 0) ?? null;
        if (!$name) {
            echo "Usage: php spp.php make:service <name> [--app=appname] [--lang=python]\n";
            return;
        }

        $app = $this->getContext($args);
        $className = ucfirst($name);
        $namespace = $this->getNamespace('Services', $app);
        
        $lang = 'php';
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--lang=')) {
                $lang = strtolower(substr($arg, 7));
            }
        }

        if ($lang === 'python') {
            // Scaffold Python Service Script
            $pythonCmd = new MakePythonCommand();
            $pythonCmd->execute($args);

            // Scaffold PHP Proxy Class
            $targetDir = $this->getTargetDir('services', $app);
            $targetPath = "{$targetDir}/class." . strtolower($className) . ".php";
            
            // The module name relative to PolyglotBridge if we supply absolute path:
            $pyTargetDir = $this->getTargetDir('services/python', $app);
            // Convert to absolute path with forward slashes for cross-platform compatibility
            $pyTargetPath = str_replace('\\', '/', realpath($pyTargetDir)) . "/service." . strtolower($name) . ".py";

            $success = $this->buildFromStub('polyglot_proxy', $targetPath, [
                'namespace' => $namespace,
                'className' => $className,
                'polyglotLang' => 'python',
                'polyglotModule' => $pyTargetPath
            ]);

            if ($success) {
                echo "Success: PHP Proxy for Python service {$className} created at {$targetPath}\n";
            }
        } elseif ($lang === 'node') {
            // Scaffold Node Service Script
            $nodeCmd = new MakeNodeCommand();
            $nodeCmd->execute($args);

            // Scaffold PHP Proxy Class
            $targetDir = $this->getTargetDir('services', $app);
            $targetPath = "{$targetDir}/class." . strtolower($className) . ".php";
            
            $jsTargetDir = $this->getTargetDir('services/node', $app);
            $jsTargetPath = str_replace('\\', '/', realpath($jsTargetDir)) . "/service." . strtolower($name) . ".js";

            $success = $this->buildFromStub('polyglot_proxy', $targetPath, [
                'namespace' => $namespace,
                'className' => $className,
                'polyglotLang' => 'node',
                'polyglotModule' => $jsTargetPath
            ]);

            if ($success) {
                echo "Success: PHP Proxy for Node.js service {$className} created at {$targetPath}\n";
            }
        } elseif ($lang === 'go') {
            // Scaffold Go Service Script
            $goCmd = new MakeGoCommand();
            $goCmd->execute($args);

            // Scaffold PHP Proxy Class
            $targetDir = $this->getTargetDir('services', $app);
            $targetPath = "{$targetDir}/class." . strtolower($className) . ".php";
            
            $goTargetDir = $this->getTargetDir('services/go', $app);
            $goTargetPath = str_replace('\\', '/', realpath($goTargetDir)) . "/service." . strtolower($name) . ".go";

            $success = $this->buildFromStub('polyglot_proxy', $targetPath, [
                'namespace' => $namespace,
                'className' => $className,
                'polyglotLang' => 'go',
                'polyglotModule' => $goTargetPath
            ]);

            if ($success) {
                echo "Success: PHP Proxy for Go service {$className} created at {$targetPath}\n";
            }
        } elseif ($lang === 'dotnet') {
            // Scaffold DotNet Service Project
            $dotnetCmd = new MakeDotNetCommand();
            $dotnetCmd->execute($args);

            // Scaffold PHP Proxy Class
            $targetDir = $this->getTargetDir('services', $app);
            $targetPath = "{$targetDir}/class." . strtolower($className) . ".php";
            
            $dotnetTargetDir = $this->getTargetDir('services/dotnet', $app);
            $dotnetTargetPath = str_replace('\\', '/', realpath($dotnetTargetDir)) . "/service." . strtolower($name);

            $success = $this->buildFromStub('polyglot_proxy', $targetPath, [
                'namespace' => $namespace,
                'className' => $className,
                'polyglotLang' => 'dotnet',
                'polyglotModule' => $dotnetTargetPath
            ]);

            if ($success) {
                echo "Success: PHP Proxy for .NET service {$className} created at {$targetPath}\n";
            }
        } elseif ($lang === 'perl') {
            // Scaffold Perl Service Script
            $perlCmd = new MakePerlCommand();
            $perlCmd->execute($args);

            // Scaffold PHP Proxy Class
            $targetDir = $this->getTargetDir('services', $app);
            $targetPath = "{$targetDir}/class." . strtolower($className) . ".php";
            
            $perlTargetDir = $this->getTargetDir('services/perl', $app);
            $perlTargetPath = str_replace('\\', '/', realpath($perlTargetDir)) . "/service." . strtolower($name) . ".pl";

            $success = $this->buildFromStub('polyglot_proxy', $targetPath, [
                'namespace' => $namespace,
                'className' => $className,
                'polyglotLang' => 'perl',
                'polyglotModule' => $perlTargetPath
            ]);

            if ($success) {
                echo "Success: PHP Proxy for Perl service {$className} created at {$targetPath}\n";
            }
        } elseif ($lang === 'java') {
            // Scaffold Java Service Script
            $javaCmd = new MakeJavaCommand();
            $javaCmd->execute($args);

            // Scaffold PHP Proxy Class
            $targetDir = $this->getTargetDir('services', $app);
            $targetPath = "{$targetDir}/class." . strtolower($className) . ".php";
            
            $javaTargetDir = $this->getTargetDir('services/java', $app);
            $javaTargetPath = str_replace('\\', '/', realpath($javaTargetDir)) . "/Service" . $className . ".java";

            $success = $this->buildFromStub('polyglot_proxy', $targetPath, [
                'namespace' => $namespace,
                'className' => $className,
                'polyglotLang' => 'java',
                'polyglotModule' => $javaTargetPath
            ]);

            if ($success) {
                echo "Success: PHP Proxy for Java service {$className} created at {$targetPath}\n";
            }
        } else {
            // Scaffold standard PHP service
            $targetDir = $this->getTargetDir('services', $app);
            $targetPath = "{$targetDir}/class." . strtolower($className) . ".php";

            $success = $this->buildFromStub('service', $targetPath, [
                'namespace' => $namespace,
                'className' => $className
            ]);

            if ($success) {
                echo "Success: PHP Service {$className} created at {$targetPath}\n";
            }
        }
    }
}
