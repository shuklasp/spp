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
            echo "Usage: php spp.php make:report <name>\n";
            return;
        }

        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '', strtolower($name));
        $etcDir = SPP_APP_DIR . '/etc/sppreports';
        $yamlFile = $etcDir . '/' . $safeName . '.yml';

        if (!is_dir($etcDir)) {
            mkdir($etcDir, 0777, true);
        }

        if (file_exists($yamlFile)) {
            echo "Error: Report '{$safeName}' already exists.\n";
            return;
        }

        $yamlContent = <<<YAML
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
        $partialsDir = SPP_APP_DIR . '/partials/reports';
        if (!is_dir($partialsDir)) {
            mkdir($partialsDir, 0777, true);
        }
        $partialFile = $partialsDir . '/' . $safeName . '.php';

        if (!file_exists($partialFile)) {
            $partialContent = <<<PHP
<?php
/**
 * Partial View for Report: {$safeName}
 * Rendered without inline HTML logic, securely using PHP template directives.
 */
?>
<div class="spp-report-container" id="report-{$safeName}">
    <h2>Report: <?= htmlspecialchars(\$org_name ?? 'SPP Report') ?></h2>
    <?php if (!empty(\$data)): ?>
        <table class="table table-striped">
            <thead>
                <tr>
                    <?php foreach (array_keys(\$data[0]) as \$col): ?>
                        <th><?= htmlspecialchars(\$col) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach (\$data as \$row): ?>
                    <tr>
                        <?php foreach (\$row as \$val): ?>
                            <td><?= htmlspecialchars((string)\$val) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No data found for this report.</p>
    <?php endif; ?>
    <div class="alert alert-warning">
        <?= htmlspecialchars(\$confidentiality ?? 'Internal Use Only') ?>
    </div>
</div>
PHP;
            file_put_contents($partialFile, $partialContent);
            echo "Success: Report View Partial created at {$partialFile}\n";
        }

        echo "Tip: You can now test your report via the API or dashboard!\n";
    }
}
