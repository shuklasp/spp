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
     * Resolves the full path to a form definition file.
     */
    public static function findFormFile(string $name, ?string $appname = null): ?string
    {
        if (file_exists($name)) {
            return realpath($name) ?: $name;
        }

        $app = $appname ?? \SPP\Scheduler::getContext();
        if (empty($app)) {
            $app = 'default';
        }
        $safeName = strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '', pathinfo($name, PATHINFO_FILENAME)));

        $candidateDirs = array_filter(array_unique([
            defined('SPP_APP_DIR') ? SPP_APP_DIR . '/etc/apps/' . $app . '/forms' : '',
            defined('APP_ETC_DIR') ? APP_ETC_DIR . '/' . $app . '/forms' : '',
            defined('SPP_APP_DIR') ? SPP_APP_DIR . '/src/' . $app . '/etc/forms' : '',
            defined('SPP_BASE_DIR') ? SPP_BASE_DIR . '/etc/apps/' . $app . '/forms' : '',
            defined('SPP_APP_DIR') ? SPP_APP_DIR . '/etc/apps/default/forms' : '',
            defined('APP_ETC_DIR') ? APP_ETC_DIR . '/default/forms' : '',
            defined('SPP_APP_DIR') ? SPP_APP_DIR . '/src/default/etc/forms' : '',
            defined('SPP_BASE_DIR') ? SPP_BASE_DIR . '/etc/apps/default/forms' : '',
            defined('SPP_BASE_DIR') ? SPP_BASE_DIR . '/src/default/etc/forms' : '',
            defined('APP_ETC_DIR') ? APP_ETC_DIR . '/forms' : '',
            defined('SPP_BASE_DIR') ? SPP_BASE_DIR . '/etc/forms' : '',
        ]), fn($d) => !empty($d) && is_dir($d));

        foreach ($candidateDirs as $dir) {
            foreach (['.yml', '.yaml', '.xml'] as $ext) {
                $p = $dir . '/' . $safeName . $ext;
                if (file_exists($p)) {
                    return $p;
                }
            }
        }
        return null;
    }

    /**
     * Returns a specific form configuration by name.
     */
    public static function getFormConfig(string $name, ?string $appname = null): ?array
    {
        $file = self::findFormFile($name, $appname);
        if ($file) {
            try {
                return Yaml::parseFile($file) ?? null;
            } catch (\Throwable $e) {}
        }

        $yaml = self::getYaml();
        return $yaml['forms'][$name] ?? null;
    }

    /**
     * Resolves and builds a ViewForm instance by form name.
     */
    public static function get(string $name, ?string $appname = null): ?ViewForm
    {
        $file = self::findFormFile($name, $appname);
        if ($file) {
            return ViewFormBuilder::fromYaml($file);
        }

        $config = self::getFormConfig($name, $appname);
        if ($config) {
            return ViewFormBuilder::fromArray($config, $name);
        }

        // Check if a programmatic Form class exists (e.g. \App\Forms\UserForm or \App\<app>\Forms\UserForm)
        $app = $appname ?? \SPP\Scheduler::getContext();
        $classCandidates = [
            "\\App\\{$app}\\Forms\\" . ucfirst($name) . "Form",
            "\\App\\{$app}\\Forms\\" . ucfirst($name),
            "\\Forms\\" . ucfirst($name) . "Form",
            "\\Forms\\" . ucfirst($name)
        ];
        foreach ($classCandidates as $cls) {
            if (class_exists($cls) && method_exists($cls, 'build')) {
                $res = $cls::build();
                if ($res instanceof ViewForm) {
                    return $res;
                }
            }
        }

        return null;
    }

    /**
     * Returns all registered form names for the current app context.
     */
    public static function listForms(?string $appname = null): array
    {
        $app = $appname ?? \SPP\Scheduler::getContext();
        $forms = [];

        $candidateDirs = array_filter(array_unique([
            defined('SPP_APP_DIR') ? SPP_APP_DIR . '/etc/apps/' . $app . '/forms' : '',
            defined('APP_ETC_DIR') ? APP_ETC_DIR . '/' . $app . '/forms' : '',
            defined('SPP_APP_DIR') ? SPP_APP_DIR . '/src/' . $app . '/etc/forms' : '',
            defined('SPP_BASE_DIR') ? SPP_BASE_DIR . '/etc/apps/' . $app . '/forms' : '',
        ]), fn($d) => !empty($d) && is_dir($d));

        foreach ($candidateDirs as $dir) {
            foreach (glob($dir . '/*.{yml,yaml,xml}', GLOB_BRACE) ?: [] as $f) {
                $name = pathinfo($f, PATHINFO_FILENAME);
                if (!str_starts_with($name, '__')) {
                    $forms[$name] = $name;
                }
            }
        }

        $yaml = self::getYaml();
        foreach (array_keys($yaml['forms'] ?? []) as $name) {
            $forms[$name] = $name;
        }

        return array_values($forms);
    }

    /**
     * Clears in-memory cache.
     */
    public static function clearCache(): void
    {
        self::$yamlCache = null;
    }

    /**
     * Inspects an SPPEntity or database table and generates a complete declarative Form schema.
     *
     * @param string $entityOrTable Entity name/class or database table
     * @param string|null $appname App context
     * @return array The synthesized form schema array
     */
    public static function generateSchemaFromEntity(string $entityOrTable, ?string $appname = null): array
    {
        $app = $appname ?? (\SPP\Scheduler::getContext() ?: 'default');
        $rawName = trim($entityOrTable);
        $cleanName = strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '', str_replace(['Form', 'Entity'], '', basename(str_replace('\\', '/', $rawName)))));
        $formId = $cleanName . '_form';
        $title = ucwords(str_replace(['_', '-'], ' ', $cleanName));

        // 1. Resolve Entity class or schema
        $classCandidates = [
            $rawName,
            "\\App\\" . ucfirst($app) . "\\Entities\\" . ucfirst($rawName),
            "\\App\\" . ucfirst($app) . "\\" . ucfirst($rawName),
            "\\App\\Entities\\" . ucfirst($rawName),
            "\\SPPMod\\SPPDB\\Entities\\" . ucfirst($rawName),
        ];

        $entityClass = null;
        foreach ($classCandidates as $cand) {
            if (class_exists($cand)) {
                $entityClass = $cand;
                break;
            }
        }

        // Check if entity file exists in src/{app}/entities/
        if (!$entityClass && defined('SPP_APP_DIR')) {
            $entFile = SPP_APP_DIR . "/src/{$app}/entities/entity." . strtolower($rawName) . ".php";
            if (file_exists($entFile)) {
                require_once $entFile;
                foreach ($classCandidates as $cand) {
                    if (class_exists($cand)) {
                        $entityClass = $cand;
                        break;
                    }
                }
            }
        }

        $rawFields = [];
        $tableName = strtolower($rawName) . 's';

        if ($entityClass && method_exists($entityClass, 'getSchema')) {
            try {
                $rawFields = $entityClass::getSchema();
                if (method_exists($entityClass, 'getTable')) {
                    $inst = new $entityClass();
                    $tableName = $inst->getTable();
                }
            } catch (\Throwable $e) {}
        }

        // Fallback: introspect database table if DB is available
        if (empty($rawFields)) {
            try {
                $db = new \SPPMod\SPPDB\SPPDB();
                $candidates = array_unique(array_filter([
                    \SPPMod\SPPDB\SPPDB::sppTable($rawName),
                    \SPPMod\SPPDB\SPPDB::sppTable($tableName),
                    $rawName,
                    $tableName,
                    \SPPMod\SPPDB\SPPDB::sppTable(rtrim($rawName, 's')),
                    \SPPMod\SPPDB\SPPDB::sppTable(ltrim($rawName, 'spp_')),
                    ltrim($rawName, 'spp_')
                ]));

                foreach ($candidates as $candTable) {
                    $cols = $db->execute_query("PRAGMA table_info({$candTable})");
                    if (!empty($cols)) {
                        $tableName = $candTable;
                        foreach ($cols as $col) {
                            $colName = $col['name'] ?? '';
                            $colType = strtolower($col['type'] ?? 'text');
                            $notNull = !empty($col['notnull']);
                            $isPk = !empty($col['pk']);
                            if ($isPk || in_array($colName, ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                                continue;
                            }
                            $rawFields[$colName] = [
                                'type' => $colType,
                                'nullable' => !$notNull
                            ];
                        }
                        break;
                    }
                }
            } catch (\Throwable $e) {}
        }

        // If still empty, check entity YAML in entities/ or schemas/
        if (empty($rawFields) && defined('SPP_APP_DIR')) {
            $yamlCandidates = [
                SPP_APP_DIR . "/src/{$app}/etc/entities/{$cleanName}.yml",
                SPP_APP_DIR . "/src/{$app}/etc/schemas/{$cleanName}.yml",
                SPP_APP_DIR . "/etc/apps/{$app}/entities/{$cleanName}.yml",
            ];
            foreach ($yamlCandidates as $yf) {
                if (file_exists($yf)) {
                    try {
                        $parsed = \Symfony\Component\Yaml\Yaml::parseFile($yf);
                        $rawFields = $parsed['fields'] ?? ($parsed['attributes'] ?? []);
                        if (!empty($rawFields)) break;
                    } catch (\Throwable $e) {}
                }
            }
        }

        // Fallback default sample fields if entity has no declared schema yet
        if (empty($rawFields)) {
            $rawFields = [
                'name' => ['type' => 'string', 'nullable' => false],
                'email' => ['type' => 'email', 'nullable' => false],
                'status' => ['type' => 'enum', 'options' => ['active', 'inactive']],
            ];
        }

        // 2. Synthesize Form elements & validation rules
        $elements = [];
        foreach ($rawFields as $fieldName => $def) {
            if (in_array(strtolower($fieldName), ['id', 'created_at', 'updated_at', 'deleted_at', 'remember_token', 'password_hash'])) {
                continue;
            }

            $label = ucwords(str_replace(['_', '-'], ' ', $fieldName));
            $colType = is_array($def) ? strtolower($def['type'] ?? 'string') : strtolower((string)$def);
            $nullable = is_array($def) ? ($def['nullable'] ?? true) : false;
            $length = is_array($def) ? ($def['length'] ?? null) : null;
            $options = is_array($def) ? ($def['options'] ?? null) : null;

            $fType = 'text';
            $fRules = [];

            if (!$nullable) {
                $fRules[] = 'required';
            }

            // Type inference
            $lowerField = strtolower($fieldName);
            if (str_contains($lowerField, 'email')) {
                $fType = 'email';
                $fRules[] = 'email';
            } elseif (str_contains($lowerField, 'password')) {
                $fType = 'password';
                $fRules[] = 'min:8';
            } elseif (str_contains($lowerField, 'phone') || str_contains($lowerField, 'mobile')) {
                $fType = 'tel';
                $fRules[] = 'indmobile';
            } elseif (str_contains($lowerField, 'pan')) {
                $fType = 'text';
                $fRules[] = 'pan';
            } elseif (str_contains($lowerField, 'aadhaar')) {
                $fType = 'text';
                $fRules[] = 'aadhaar';
            } elseif (str_contains($lowerField, 'gstin')) {
                $fType = 'text';
                $fRules[] = 'gstin';
            } elseif (str_contains($lowerField, 'pincode') || str_contains($lowerField, 'postal_code')) {
                $fType = 'text';
                $fRules[] = 'pincode';
            } elseif (in_array($colType, ['int', 'integer', 'bigint', 'numeric', 'decimal', 'float', 'double'])) {
                $fType = 'number';
                $fRules[] = 'numeric';
            } elseif (in_array($colType, ['text', 'longtext', 'mediumtext'])) {
                $fType = 'textarea';
            } elseif ($colType === 'date') {
                $fType = 'date';
            } elseif (in_array($colType, ['datetime', 'timestamp'])) {
                $fType = 'datetime-local';
            } elseif (in_array($colType, ['boolean', 'bool', 'tinyint(1)'])) {
                $fType = 'checkbox';
            } elseif ($colType === 'enum' || !empty($options) || str_ends_with($lowerField, '_id')) {
                $fType = 'select';
            }

            if ($length && $length > 0 && $fType === 'text') {
                $fRules[] = "max:{$length}";
            }

            $elementDef = [
                'type' => $fType,
                'label' => $label,
                'col' => ($fType === 'textarea') ? 12 : 6,
            ];

            if ($fType === 'select') {
                if (!empty($options) && is_array($options)) {
                    $optMap = [];
                    foreach ($options as $k => $v) {
                        $optKey = is_numeric($k) ? $v : $k;
                        $optMap[$optKey] = ucwords(str_replace(['_', '-'], ' ', (string)$v));
                    }
                    $elementDef['options'] = $optMap;
                } else {
                    $elementDef['options'] = [
                        '1' => 'Option 1',
                        '2' => 'Option 2'
                    ];
                }
            }

            if (!empty($fRules)) {
                $elementDef['validation'] = implode('|', array_unique($fRules));
            }

            $elements[$fieldName] = $elementDef;
        }

        $elements['submit_btn'] = [
            'type' => 'submit',
            'label' => "Save {$title}",
            'class' => 'btn btn-primary',
            'col' => 12
        ];

        return [
            'form' => [
                'name' => $cleanName,
                'form_id' => $formId,
                'title' => "{$title} Form",
                'action' => "/{$cleanName}s",
                'method' => 'POST'
            ],
            'elements' => $elements,
            'fields' => $elements
        ];
    }
}
