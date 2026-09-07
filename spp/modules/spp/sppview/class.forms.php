<?php

namespace SPPMod\SPPView;

use Symfony\Component\Yaml\Yaml;

/**
 * class Forms
 *
 * Central registry for form definitions in SPP.
 * Loads from APP_ETC_DIR/<appname>/forms.yml.
 *
 * @author Satya Prakash Shukla
 */
class Forms extends \SPP\SPPObject
{
    /** @var array<string,mixed>|null In-memory YAML cache */
    private static ?array $yamlCache = null;

    /**
     * Returns the parsed YAML content of forms.yml.
     * Looks for file in APP_ETC_DIR/<appname>/forms.yml.
     */
    public static function getYaml(): array
    {
        if (self::$yamlCache === null) {
            $appname = \SPP\Scheduler::getContext();
            
            $candidates = [];
            if (class_exists('\SPP\App')) {
                try {
                    $app = \SPP\App::getApp($appname);
                    if ($app) {
                        $appConfDir = $app->getAppConfDir();
                        if ($appConfDir) {
                            $candidates[] = $appConfDir . SPP_DS . 'forms.yml';
                        }
                    }
                } catch (\Throwable $e) {}

                if (defined('SPP_APP_DIR')) {
                    $candidates[] = SPP_APP_DIR . SPP_DS . 'src' . SPP_DS . $appname . SPP_DS . 'etc' . SPP_DS . 'forms.yml';
                }
            }

            $candidates[] = APP_ETC_DIR . SPP_DS . $appname . SPP_DS . 'forms.yml';
            $candidates[] = APP_ETC_DIR . SPP_DS . 'forms.yml';

            $file = null;
            foreach ($candidates as $cand) {
                if (file_exists($cand)) {
                    $file = $cand;
                    break;
                }
            }

            if (!$file) {
                self::$yamlCache = [];
                return [];
            }

            try {
                self::$yamlCache = Yaml::parseFile($file) ?? [];
            } catch (\Symfony\Component\Yaml\Exception\ParseException $e) {
                throw new \SPP\SPPException('Failed to parse forms.yml: ' . $e->getMessage(), 1000, $e);
            }
        }
        return self::$yamlCache;
    }

    /**
     * Returns a specific form configuration by name.
     */
    public static function getFormConfig(string $name): ?array
    {
        $yaml = self::getYaml();
        return $yaml['forms'][$name] ?? null;
    }

    /**
     * Returns all registered form names.
     */
    public static function listForms(): array
    {
        $yaml = self::getYaml();
        return array_keys($yaml['forms'] ?? []);
    }

    /**
     * Clears in-memory cache.
     */
    public static function clearCache(): void
    {
        self::$yamlCache = null;
    }
}
