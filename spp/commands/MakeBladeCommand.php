<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

/**
 * Class MakeBladeCommand
 * Scaffolds a new Drishyam Blade Template.
 */
class MakeBladeCommand extends BaseMakeCommand
{
    protected string $name = 'make:blade';
    protected string $description = 'Scaffold a new Blade template (Drishyam Paradigm)';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $name = null;
        
        foreach ($args as $i => $arg) {
            if (strpos(strtolower($arg), '--name=') === 0) {
                $name = substr($arg, 7);
            } elseif ($i === 0 && strpos($arg, '--') !== 0) {
                $name = $arg;
            }
        }

        if (!$name) {
            echo "Usage: php spp.php make:blade <ViewName>\n";
            return;
        }

        $context = $this->getContext($args);
        $targetDir = SPP_APP_DIR . "/resources/views/" . $context;
        
        $fileName = strtolower($name);
        if (strpos($fileName, '.blade.php') === false) {
            $fileName .= '.blade.php';
        }
        
        $filePath = $targetDir . "/" . $fileName;

        if (file_exists($filePath)) {
            echo "Error: Blade template {$name} already exists.\n";
            return;
        }

        $content = <<<'BLADE'
@extends('layouts.app')

@section('title', '{{VIEW_NAME}}')

@section('content')
<div class="drishyam-container">
    <div class="blade-hero">
        <h1>{{VIEW_NAME}}</h1>
        <p>Powered by Drishyam Blade Paradigm</p>
    </div>

    <div class="blade-content">
        <p>This is a generated Blade view for the <strong>{{CONTEXT}}</strong> context.</p>
        <button class="btn btn-primary" onclick="alert('Blade works!')">Interactive Action</button>
    </div>
</div>

<style>
    .drishyam-container { font-family: 'Inter', sans-serif; max-width: 800px; margin: 0 auto; padding: 2rem; }
    .blade-hero { background: linear-gradient(135deg, #f5576c 0%, #f093fb 100%); color: white; padding: 3rem; border-radius: 16px; margin-bottom: 2rem; text-align: center; }
    .blade-hero h1 { margin: 0; font-size: 2.5rem; }
    .blade-content { background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
    .btn { padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; }
    .btn-primary { background: #f5576c; color: white; }
</style>
@endsection
BLADE;

        $content = str_replace(
            ['{{VIEW_NAME}}', '{{CONTEXT}}'], 
            [ucfirst($name), $context], 
            $content
        );
        
        if (!is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0777, true);
        }
        
        file_put_contents($filePath, $content);
        echo "Success: Blade template created at {$filePath}\n";
    }
}
