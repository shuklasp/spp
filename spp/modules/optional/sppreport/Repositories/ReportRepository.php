<?php
namespace SPPMod\SPPReport\Repositories;

use Symfony\Component\Yaml\Yaml;

class ReportRepository
{
    private string $configDir;

    public function __construct(string $configDir = null)
    {
        $app = class_exists('\\SPP\\Scheduler') ? (\SPP\Scheduler::getContext() ?: 'default') : 'default';
        if (empty($app)) $app = 'default';
        $defaultDir = defined('SPP_APP_DIR') ? SPP_APP_DIR . "/etc/apps/{$app}/reports" : (defined('APP_ROOT') ? APP_ROOT . '/etc/sppreports' : __DIR__ . '/../../etc/sppreports');
        $this->configDir = $configDir ?? $defaultDir;
        if (!is_dir($this->configDir)) {
            @mkdir($this->configDir, 0755, true);
        }
    }

    public function findReportFile(string $reportName, ?string $appname = null): ?string
    {
        $app = (!empty($appname)) ? $appname : (class_exists('\\SPP\\Scheduler') ? (\SPP\Scheduler::getContext() ?: 'default') : 'default');
        if (empty($app)) $app = 'default';
        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '', $reportName);

        $candidates = array_filter(array_unique([
            $this->configDir . '/' . $safeName . '.yml',
            $this->configDir . '/' . $safeName . '.yaml',
            defined('SPP_APP_DIR') ? SPP_APP_DIR . "/etc/apps/{$app}/reports/{$safeName}.yml" : '',
            defined('SPP_APP_DIR') ? SPP_APP_DIR . "/etc/apps/{$app}/reports/{$safeName}.yaml" : '',
            defined('SPP_APP_DIR') ? SPP_APP_DIR . "/etc/apps/default/reports/{$safeName}.yml" : '',
            defined('SPP_APP_DIR') ? SPP_APP_DIR . "/etc/apps/default/reports/{$safeName}.yaml" : '',
            defined('SPP_APP_DIR') ? SPP_APP_DIR . "/src/{$app}/etc/reports/{$safeName}.yml" : '',
            defined('SPP_APP_DIR') ? SPP_APP_DIR . "/src/default/etc/reports/{$safeName}.yml" : '',
            defined('APP_ROOT') ? APP_ROOT . "/etc/sppreports/{$safeName}.yml" : '',
            defined('APP_ETC_DIR') ? APP_ETC_DIR . "/sppreports/{$safeName}.yml" : '',
            __DIR__ . "/../../etc/sppreports/{$safeName}.yml"
        ]), fn($p) => !empty($p) && file_exists($p));

        return reset($candidates) ?: null;
    }

    public function load(string $reportName, ?string $appname = null): array
    {
        $file = $this->findReportFile($reportName, $appname);
        if (!$file) {
            throw new \Exception("Report configuration not found: $reportName");
        }

        return Yaml::parseFile($file) ?? [];
    }

    public function save(string $reportName, array $config): void
    {
        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '', $reportName);
        $file = $this->configDir . '/' . $safeName . '.yml';
        
        if (file_exists($file)) {
            $historyDir = $this->configDir . '/history';
            if (!is_dir($historyDir)) {
                @mkdir($historyDir, 0755, true);
            }
            copy($file, $historyDir . "/{$safeName}_" . date('Ymd_His') . ".yml.bak");
        }
        
        file_put_contents($file, Yaml::dump($config, 4, 2));

        if (class_exists('\\SPPMod\\SPPWorkflow\\CQRS\\EventStore')) {
            \SPPMod\SPPWorkflow\CQRS\EventStore::appendEvent('ReportConfigUpdated', [
                'report_name' => $safeName,
                'timestamp' => date('Y-m-d H:i:s'),
                'schema_version' => '1.0'
            ]);
        }
    }

    public function listAll(): array
    {
        $reports = [];
        foreach (glob($this->configDir . '/*.yml') as $file) {
            $name = basename($file, '.yml');
            $config = Yaml::parseFile($file);
            $reports[] = [
                'name' => $name,
                'title' => $config['title'] ?? ucfirst(str_replace('_', ' ', $name)),
                'description' => $config['description'] ?? '',
                'updated_at' => filemtime($file)
            ];
        }
        return $reports;
    }

    public function listVersions(string $reportName): array
    {
        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '', $reportName);
        $historyDir = $this->configDir . '/history';
        $versions = [];
        if (is_dir($historyDir)) {
            $files = glob("$historyDir/{$safeName}_*.yml.bak");
            foreach ($files as $f) {
                if (preg_match('/_(\d{8}_\d{6})\.yml\.bak$/', $f, $m)) {
                    $versions[] = ['file' => basename($f), 'timestamp' => $m[1]];
                }
            }
        }
        usort($versions, function ($a, $b) {
            return strcmp($b['timestamp'], $a['timestamp']);
        });
        return $versions;
    }

    public function restoreVersion(string $reportName, string $versionFile): void
    {
        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '', $reportName);
        $safeVersion = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $versionFile);
        
        $source = $this->configDir . "/history/$safeVersion";
        if (!file_exists($source)) {
            throw new \Exception("Version not found.");
        }
        
        $target = $this->configDir . "/{$safeName}.yml";
        if (file_exists($target)) {
            $historyDir = $this->configDir . '/history';
            if (!is_dir($historyDir)) {
                @mkdir($historyDir, 0755, true);
            }
            copy($target, $historyDir . "/{$safeName}_" . date('Ymd_His') . ".yml.bak");
        }
        copy($source, $target);

        if (class_exists('\\SPPMod\\SPPWorkflow\\CQRS\\EventStore')) {
            \SPPMod\SPPWorkflow\CQRS\EventStore::appendEvent('ReportVersionRestored', [
                'report_name' => $safeName,
                'restored_version' => $safeVersion,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }
    }
}
