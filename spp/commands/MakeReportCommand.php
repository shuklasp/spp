<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

/**
 * Class MakeReportCommand
 * Scaffolds a new SPPReport YAML configuration and optional partial view.
 */
class MakeReportCommand extends Command
{
    protected string $name = 'make:report';
    protected string $description = 'Scaffold a new SPPReport YAML configuration';

    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $name = $this->getArgument($args, 0) ?? null;
        if (!$name) {
            echo "Usage: php spp.php make:report <name> [--app=appname]\n";
            return;
        }

        $app = null;
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--app=')) {
                $app = substr($arg, 6);
            }
        }
        $app = $app ?? (class_exists('\\SPP\\Scheduler') ? \SPP\Scheduler::getContext() : 'default');

        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '', strtolower($name));
        $etcDir = defined('SPP_APP_DIR') ? SPP_APP_DIR . "/etc/apps/{$app}/reports" : 'etc/apps/' . $app . '/reports';
        $yamlFile = $etcDir . '/' . $safeName . '.yml';

        if (!is_dir($etcDir)) {
            mkdir($etcDir, 0777, true);
        }

        if (file_exists($yamlFile)) {
            echo "Error: Report '{$safeName}' already exists at {$yamlFile}.\n";
            return;
        }

        $yamlContent = <<<YAML
# =====================================================================================
# SPP Framework - Declarative Report Definition: {$safeName}
# =====================================================================================
# Core Reporting Concepts:
# 1. table: The primary database table to query.
# 2. columns: Selected fields with optional aggregates (COUNT, SUM, AVG, MIN, MAX).
# 3. joins: Relations to include (type: LEFT/INNER, table: related, on: join condition).
# 4. filters: AND/OR boolean conditions evaluated securely with parameterized queries.
# 5. group_by: Dimension fields for aggregated cross-tabulation and metric calculations.
# 6. order_by: Default sort column and direction (ASC or DESC).
#
# USAGE IN APPS:
# - In Controller: return \$this->report('{$safeName}', ['status' => 'active']);
# - In Blade Template: @sppreport('{$safeName}')
# - In BI Dashboard: @sppreport_chart('{$safeName}', ['type' => 'bar'])

table: 'users'
columns:
  - field: 'id'
    aggregate: ''
    alias: 'User ID'
  - field: 'email'
    aggregate: ''
    alias: 'Email Address'
  - field: 'created_at'
    aggregate: 'COUNT'
    alias: 'Total Signups'
joins: []
filters:
  logic: 'AND'
  conditions:
    - field: 'status'
      operator: '='
      value: 'active'
group_by:
  - 'created_at'
order_by:
  field: 'created_at'
  direction: 'DESC'
limit: 100
roles_allowed: 'admin,manager'
cron_schedule: ''
cron_email: ''
cron_format: 'html'
webhook_url: ''
webhook_condition: ''
YAML;

        file_put_contents($yamlFile, $yamlContent);
        echo "Success: SPPReport YAML configuration created at {$yamlFile}\n";

        // Generate partials file
        $partialsDir = defined('SPP_APP_DIR') ? SPP_APP_DIR . "/src/{$app}/pages/partials/reports" : "src/{$app}/pages/partials/reports";
        if (!is_dir($partialsDir)) {
            mkdir($partialsDir, 0777, true);
        }
        $partialFile = $partialsDir . '/' . $safeName . '.blade.php';

        if (!file_exists($partialFile)) {
            $partialContent = <<<PHP
{{-- External Report Partial: {$safeName} --}}
<div class="spp-report-container" id="report-{$safeName}">
    <h3>Report: {{ \$report_name ?? '{$safeName}' }}</h3>
    @if(!empty(\$data))
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    @foreach(array_keys(\$data[0]) as \$col)
                        <th>{{ \$col }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach(\$data as \$row)
                    <tr>
                        @foreach(\$row as \$val)
                            <td>{{ \$val }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="text-muted">No matching records found.</p>
    @endif
</div>
PHP;
            file_put_contents($partialFile, $partialContent);
            echo "Success: External Report Partial created at {$partialFile}\n";
        }

        echo "Tip: You can now view your report via \$this->report('{$safeName}') or @sppreport('{$safeName}')!\n";
    }
}
