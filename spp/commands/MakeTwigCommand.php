<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

/**
 * Class MakeTwigCommand
 * Scaffolds a new Drishyam Twig Template.
 */
class MakeTwigCommand extends BaseMakeCommand
{
    protected string $name = 'make:twig';
    protected string $description = 'Scaffold a new Twig template (Drishyam Paradigm)';

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
            echo "Usage: php spp.php make:twig <ViewName>\n";
            return;
        }

        $context = $this->getContext($args);
        $targetDir = SPP_APP_DIR . "/resources/views/" . $context;
        
        $fileName = strtolower($name);
        if (strpos($fileName, '.twig') === false) {
            $fileName .= '.twig';
        }
        
        $filePath = $targetDir . "/" . $fileName;

        if (file_exists($filePath)) {
            echo "Error: Twig template {$name} already exists.\n";
            return;
        }

        $content = <<<'TWIG'
{% extends "layouts/app.twig" %}

{% block title %}{{VIEW_NAME}}{% endblock %}

{% block content %}
<div class="drishyam-container">
    <div class="twig-hero">
        <h1>{{VIEW_NAME}}</h1>
        <p>Powered by Drishyam Twig Paradigm</p>
    </div>

    <div class="twig-content">
        <p>This is a generated Twig view for the <strong>{{CONTEXT}}</strong> context.</p>
        <button class="btn btn-primary" onclick="alert('Twig works!')">Interactive Action</button>
    </div>
</div>

<style>
    .drishyam-container { font-family: 'Inter', sans-serif; max-width: 800px; margin: 0 auto; padding: 2rem; }
    .twig-hero { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); color: #333; padding: 3rem; border-radius: 16px; margin-bottom: 2rem; text-align: center; }
    .twig-hero h1 { margin: 0; font-size: 2.5rem; }
    .twig-content { background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
    .btn { padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; }
    .btn-primary { background: #667eea; color: white; }
</style>
{% endblock %}
TWIG;

        $content = str_replace(
            ['{{VIEW_NAME}}', '{{CONTEXT}}'], 
            [ucfirst($name), $context], 
            $content
        );
        
        if (!is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0777, true);
        }
        
        file_put_contents($filePath, $content);
        echo "Success: Twig template created at {$filePath}\n";
    }
}
