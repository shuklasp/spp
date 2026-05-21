<?php
namespace App\Lekhak\Commands;

/**
 * ThemeActivateCommand
 *
 * Switches the active theme adapter and/or the active theme within an adapter.
 *
 * Usage:
 *   php spp.php theme:activate native              # Switch to native Lekhak theme
 *   php spp.php theme:activate wp                   # Switch to WordPress adapter
 *   php spp.php theme:activate joomla               # Switch to Joomla adapter
 *   php spp.php theme:activate native --theme=premium  # Set native theme to "premium"
 *   php spp.php theme:activate wp --theme=twentytwentyfour
 */
class ThemeActivateCommand extends \SPP\CLI\Command
{
    public function getName(): string
    {
        return 'theme:activate';
    }

    public function getDescription(): string
    {
        return 'Switch the active theme adapter (native/wp/joomla) and optionally set the theme name';
    }

    public function execute(array $args): void
    {
        $options    = $this->parseOptions($args);
        $positional = $this->parsePositional($args);
        $adapter    = $positional[0] ?? null;
        $themeName  = $options['theme'] ?? null;

        if (!$adapter) {
            $this->showCurrentTheme();
            $this->line("\nUsage: php spp.php theme:activate <native|wp|joomla> [--theme=<name>]");
            return;
        }

        $validAdapters = ['native', 'wp', 'wordpress', 'joomla'];
        if (!in_array(strtolower($adapter), $validAdapters)) {
            $this->error("Unknown adapter: {$adapter}. Valid options: native, wp, joomla");
            return;
        }

        $normalized = match (strtolower($adapter)) {
            'wp', 'wordpress' => 'wp',
            'joomla'          => 'joomla',
            default           => 'native',
        };

        $settingsPath = (defined('SPP_ETC_DIR') ? SPP_ETC_DIR : dirname(__DIR__, 3) . '/spp/etc')
            . '/global-settings.yml';

        if (!file_exists($settingsPath)) {
            $this->error("Settings file not found: {$settingsPath}");
            return;
        }

        try {
            $content = file_get_contents($settingsPath);
            $config = \Symfony\Component\Yaml\Yaml::parse($content);

            $config['theme_adapter'] = $normalized;

            if ($themeName) {
                $themeKey = match ($normalized) {
                    'wp'     => 'wp_theme',
                    'joomla' => 'joomla_template',
                    default  => 'native_theme',
                };
                $config[$themeKey] = $themeName;
            }

            $yaml = \Symfony\Component\Yaml\Yaml::dump($config, 4, 2);
            file_put_contents($settingsPath, $yaml);

            $this->info("Theme adapter set to: {$normalized}");
            if ($themeName) {
                $this->info("Theme name set to: {$themeName}");
            }

            // Clear the compiled config cache
            if (class_exists('\\SPP\\SPPConfig')) {
                $appname = class_exists('\\SPP\\Scheduler') ? \SPP\Scheduler::getContext() : 'default';
                \SPP\SPPConfig::clearCompiled($appname);
                $this->line("Configuration cache cleared.");
            }

        } catch (\Exception $e) {
            $this->error("Failed to update settings: " . $e->getMessage());
        }
    }

    private function showCurrentTheme(): void
    {
        $adapter = 'native';
        if (class_exists('\\SPP\\SPPConfig')) {
            $adapter = \SPP\SPPConfig::get('theme_adapter') ?: 'native';
        }

        $themeKey = match ($adapter) {
            'wp'     => 'wp_theme',
            'joomla' => 'joomla_template',
            default  => 'native_theme',
        };

        $themeName = 'default';
        if (class_exists('\\SPP\\SPPConfig')) {
            $themeName = \SPP\SPPConfig::get($themeKey) ?: 'default';
        }

        $this->info("Current theme adapter: {$adapter}");
        $this->info("Current theme: {$themeName}");
    }

    private function parseOptions(array $args): array
    {
        $options = [];
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--')) {
                $parts = explode('=', substr($arg, 2), 2);
                $options[$parts[0]] = $parts[1] ?? true;
            }
        }
        return $options;
    }

    private function parsePositional(array $args): array
    {
        $positional = [];
        foreach ($args as $arg) {
            if ($arg === 'theme:activate' || $arg === 'spp.php') continue;
            if (str_starts_with($arg, '--')) continue;
            $positional[] = $arg;
        }
        return $positional;
    }
}
