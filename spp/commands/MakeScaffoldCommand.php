<?php

namespace SPP\CLI\Commands;

/**
 * Class MakeScaffoldCommand
 * Full stack scaffolding command for SPP.
 */
class MakeScaffoldCommand extends BaseMakeCommand
{
    protected string $name = 'make:scaffold';
    protected string $description = 'Create a full stack scaffold (Entity, DB, Controller, View)';

    public function execute(array $args): void
    {
        $entityName = $args[2] ?? null;
        if (!$entityName) {
            echo "Scaffold Entity Name (e.g. Product): ";
            $entityName = trim(fgets(STDIN));
        }

        if (!$entityName) {
            echo "Error: Entity name is required.\n";
            return;
        }

        $entityName = preg_replace('/[^a-zA-Z0-9_]/', '', $entityName);

        echo "Application/Context [default]: ";
        $appname = trim(fgets(STDIN)) ?: "default";

        echo "Database Table [" . strtolower($entityName) . "s]: ";
        $tableName = trim(fgets(STDIN)) ?: strtolower($entityName) . "s";

        $config = [
            'table' => $tableName,
            'id_field' => 'id',
            'sequence' => $tableName . '_seq',
            'key_type' => 'int',
            'extends' => '',
            'login_enabled' => false,
            'attributes' => [],
            'relations' => []
        ];

        echo "\nAttributes (e.g. name:varchar(255), price:decimal(10,2)):\n";
        echo "Attribute Name: ";
        while ($attrName = trim(fgets(STDIN))) {
            echo "  Type [varchar(255)]: ";
            $attrType = trim(fgets(STDIN)) ?: "varchar(255)";
            $config['attributes'][$attrName] = $attrType;
            echo "Attribute Name: ";
        }

        // 1. Save Entity Definition
        echo "\nSaving Entity Definition... ";
        if (!class_exists('\SPPMod\SPPDB\SPPEntity')) {
            require_once dirname(__DIR__) . '/sppinit.php';
        }
        \SPPMod\SPPDB\SPPEntity::saveEntityDefinition($entityName, $appname, $config);
        echo "OK\n";

        // 2. Generate Controller
        echo "Generating Controller... ";
        $controllerName = ucfirst($entityName) . "Controller";
        $targetDir = SPP_APP_DIR . "/src/" . $appname . "/controllers";
        if (!is_dir($targetDir))
            mkdir($targetDir, 0777, true);

        $stub = file_get_contents(__DIR__ . '/stubs/scaffold_controller.stub');
        $content = str_replace(
            ['{{appname}}', '{{controllerName}}', '{{entityName}}'],
            [ucfirst($appname), $controllerName, ucfirst($entityName)],
            $stub
        );

        file_put_contents($targetDir . "/" . $controllerName . ".php", $content);
        echo "OK\n";

        // 3. Generate Views (Skeleton & External Partials)
        echo "Generating Views and External Partials... ";
        $viewDir = SPP_APP_DIR . "/src/" . $appname . "/views/" . strtolower($entityName);
        $partialDir = SPP_APP_DIR . "/src/" . $appname . "/pages/partials";
        $streamDir = SPP_APP_DIR . "/src/" . $appname . "/pages/streams";
        if (!is_dir($viewDir)) mkdir($viewDir, 0777, true);
        if (!is_dir($partialDir)) mkdir($partialDir, 0777, true);
        if (!is_dir($streamDir)) mkdir($streamDir, 0777, true);

        $indexContent = <<<INDEX
<!-- Scaffolded Index View for {$entityName} -->
<div class="scaffold-container">
    <h1>{$entityName} List</h1>
    <div id="{$entityName}-list-target" hx-get="/{$appname}/{$entityName}/index" hx-trigger="load">
        <!-- External partial partials/{$entityName}_index.html will be inserted here -->
    </div>
    <div id="stream-target-{$entityName}"></div>
</div>
INDEX;
        file_put_contents($viewDir . "/index.php", $indexContent);

        $partialContent = <<<PARTIAL
<!-- External HTML Partial: {$entityName} Index -->
<div class="spp-partial-container" id="partial-{$entityName}-index">
    <div class="partial-header"><h4>{$entityName} Index Partial</h4></div>
    <div class="partial-body"><p>This static HTML partial was rendered externally without inline HTML string literals in controllers.</p></div>
</div>
PARTIAL;
        file_put_contents($partialDir . "/" . strtolower($entityName) . "_index.html", $partialContent);

        $streamContent = <<<STREAM
<?php
/**
 * External Turbo Stream Template: {$entityName} Update
 */
?>
<turbo-stream action="append" target="stream-target-{$entityName}">
    <template>
        <div class="spp-stream-item">
            <p>Live Turbo Stream update for {$entityName} rendered externally.</p>
        </div>
    </template>
</turbo-stream>
STREAM;
        file_put_contents($streamDir . "/" . strtolower($entityName) . "_update.php", $streamContent);
        echo "OK\n";

        // 4. Generate Workflow Scaffolding & Tutorial Comments
        echo "Generating Workflow Definitions... ";
        $workflowDir = SPP_APP_DIR . "/etc/apps/" . $appname . "/workflows";
        if (!is_dir($workflowDir)) mkdir($workflowDir, 0777, true);

        $workflowContent = <<<WORKFLOW
# ##############################################################################
# Scaffolded Workflow Definition for {$entityName}
# Keyed by entity_type (or entity_type.bundle)
#
# TUTORIAL & CONCEPTS:
# - States: Define the valid lifecycle stages for this entity.
# - Transitions: Move the entity between states via WorkflowManager::applyTransition().
# - Parallel Markings: An entity can occupy multiple concurrent states simultaneously.
# - Saga Pattern: Define 'compensations' callbacks to revert actions on rollback().
# - SLA Timeouts: 'timeout' triggers automatic escalation via 'timeout_transition'.
# ##############################################################################
" . strtolower($entityName) . ":
  description: "Automated lifecycle workflow for {$entityName}"
  states:
    - draft
    - pending_approval
    - active
    - archived
  transitions:
    submit:
      from: [draft]
      to: pending_approval
      timeout: "7 days"
      timeout_transition: "auto_archive"
    approve:
      from: [pending_approval]
      to: active
    auto_archive:
      from: [pending_approval]
      to: archived
    archive:
      from: [active]
      to: archived
WORKFLOW;
        file_put_contents($workflowDir . "/" . strtolower($entityName) . ".yml", trim($workflowContent));
        echo "OK\n";

        echo "\nSuccess: Full stack scaffold (with external partials, streams & workflows) for {$entityName} created in {$appname} context.\n";

        // Final Sync hint
        $globalSettingsPath = SPP_ETC_DIR . '/global-settings.yml';
        if (file_exists($globalSettingsPath)) {
            $settings = \Symfony\Component\Yaml\Yaml::parseFile($globalSettingsPath);
            if (($settings['prototyping']['auto_evolution'] ?? 'manual') === 'manual') {
                echo "NOTE: Run 'php spp.php db:sync' to create the database table.\n";
            } else {
                echo "NOTE: Database table was auto-created/updated.\n";
            }
        }
    }
}
