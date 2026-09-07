<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use App\SPPDocs\Services\GitAutomationService;

/**
 * Class SPPDocsCiInitCommand
 * Scaffolds CI/CD workflows and Git commit automation hooks for SPPDocs projects.
 */
class SPPDocsCiInitCommand extends Command
{
    protected string $name = 'sppdocs:ci:init';
    protected string $description = 'Scaffold GitHub Actions, GitLab CI, or local Git hooks for SPPDocs automations';

    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $project = 'demo-app';
        $platform = 'github';
        $webhookUrl = 'http://localhost/school1/public/api/git-webhook';
        $secret = bin2hex(random_bytes(16));
        $outputPath = null;

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--project=')) {
                $project = substr($arg, 10);
            } elseif (str_starts_with($arg, '--platform=')) {
                $platform = strtolower(substr($arg, 11));
            } elseif (str_starts_with($arg, '--url=')) {
                $webhookUrl = substr($arg, 6);
            } elseif (str_starts_with($arg, '--secret=')) {
                $secret = substr($arg, 9);
            } elseif (str_starts_with($arg, '--output=')) {
                $outputPath = substr($arg, 9);
            }
        }

        echo "🚀 SPPDocs CI/CD Workflow & Hook Generator\n";
        echo "   Target Project: {$project}\n";
        echo "   CI Platform:    {$platform}\n";
        echo "   Webhook URL:    {$webhookUrl}\n\n";

        if ($platform === 'github') {
            $content = GitAutomationService::generateGitHubActionsWorkflow($project, $webhookUrl, $secret);
            $target = $outputPath ?: (dirname(SPP_BASE_DIR) . '/.github/workflows/sppdocs-ci.yml');
        } elseif ($platform === 'gitlab') {
            $content = GitAutomationService::generateGitLabCiConfig($project, $webhookUrl, $secret);
            $target = $outputPath ?: (dirname(SPP_BASE_DIR) . '/.gitlab-ci.yml');
        } elseif ($platform === 'local') {
            $content = GitAutomationService::generateLocalGitHook($project, $webhookUrl);
            $target = $outputPath ?: (dirname(SPP_BASE_DIR) . '/.git/hooks/post-commit');
        } else {
            echo "❌ Unsupported platform '{$platform}'. Supported: github, gitlab, local\n";
            return;
        }

        $dir = dirname($target);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        if (file_put_contents($target, $content) !== false) {
            if ($platform === 'local') {
                @chmod($target, 0755);
            }
            echo "✅ Workflow successfully generated!\n";
            echo "   File Path: {$target}\n";
            echo "   Secret Token: {$secret}\n\n";
            echo "💡 Configure your repository secrets with: SPPDOCS_WEBHOOK_SECRET={$secret}\n";
        } else {
            echo "❌ Failed to write file to '{$target}'. Check permissions.\n";
        }
    }
}
