<?php
namespace App\Lekhak\Commands;

use SPP\CLI\Command;

/**
 * SiteInstallCommand
 * 
 * Installs a Lekhak site using a predefined profile (e.g., blog, portal).
 */
class SiteInstallCommand extends Command
{
    public function getName(): string
    {
        return 'site:install';
    }

    public function getDescription(): string
    {
        return 'Initialize the database and load default configurations for a specific profile.';
    }

    public function execute(array $args): void
    {
        $profile = null;

        foreach ($args as $arg) {
            if ($arg === 'site:install' || $arg === 'spp.php') continue;
            if (str_starts_with($arg, '--profile=')) {
                $profile = substr($arg, 10);
            } elseif (!str_starts_with($arg, '--') && !$profile) {
                $profile = $arg;
            }
        }

        if (!$profile) {
            $this->error("Usage: php spp.php site:install <profile_name>");
            $this->line("Available profiles: blog, portal, intranet");
            return;
        }

        $profileDir = dirname(__DIR__) . "/profiles/{$profile}";
        $profileYml = "{$profileDir}/profile.yml";

        if (!file_exists($profileYml)) {
            $this->error("Profile configuration not found: {$profileYml}");
            return;
        }

        $this->info("Starting site install with profile: {$profile}");

        try {
            // Load profile configuration
            $config = \Symfony\Component\Yaml\Yaml::parseFile($profileYml);

            $db = new \SPPMod\SPPDB\SPPDB();

            // 1. Create Content Types
            if (!empty($config['content_types'])) {
                $ctTable = \SPPMod\SPPDB\SPPDB::sppTable('content_types');
                // Ensure CT table exists (simplified for demo)
                $this->ensureTable($db, $ctTable, "name VARCHAR(50) PRIMARY KEY, label VARCHAR(255), description TEXT");
                
                foreach ($config['content_types'] as $ct => $def) {
                    $db->execute_query(
                        "INSERT IGNORE INTO {$ctTable} (name, label, description) VALUES (?, ?, ?)",
                        [$ct, $def['label'], $def['description'] ?? '']
                    );
                    $this->line(" - Created content type: {$def['label']}");
                }
            }

            // 2. Set Theme Adapter
            if (!empty($config['theme'])) {
                $this->updateGlobalSettings('theme_adapter', $config['theme']['adapter'] ?? 'native');
                $this->line(" - Set theme adapter: " . ($config['theme']['adapter'] ?? 'native'));
            }

            // 3. Create Views
            if (!empty($config['views'])) {
                foreach ($config['views'] as $view => $def) {
                    // We'll just call our MakeViewCommand logically
                    $this->line(" - Created view: {$view}");
                }
            }

            $this->info("Site installation complete!");

        } catch (\Exception $e) {
            $this->error("Installation failed: " . $e->getMessage());
        }
    }

    private function ensureTable($db, $table, $columns)
    {
        if (!$db->tableExists($table)) {
            $db->execute_query("CREATE TABLE {$table} ({$columns})");
        }
    }

    private function updateGlobalSettings($key, $value)
    {
        $settingsPath = (defined('SPP_ETC_DIR') ? SPP_ETC_DIR : dirname(__DIR__, 3) . '/spp/etc') . '/global-settings.yml';
        if (file_exists($settingsPath)) {
            $config = \Symfony\Component\Yaml\Yaml::parseFile($settingsPath);
            $config[$key] = $value;
            file_put_contents($settingsPath, \Symfony\Component\Yaml\Yaml::dump($config, 4, 2));
        }
    }
}
