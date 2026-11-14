<?php
/**
 * Form Management Service Group for SPP Admin
 */

function live_Forms_List($la, $params) {
    $appname = $params['appname'] ?? $params['context'] ?? ($_SESSION['spp_admin_selected_app'] ?? 'default');
    $forms = \SPP\Scheduler::withContext($appname, function() use ($appname) {
        $candidateDirs = array_filter(array_unique([
            (defined('APP_ETC_DIR') ? APP_ETC_DIR : (defined('SPP_APP_DIR') ? SPP_APP_DIR . '/etc/apps' : '')) . '/' . $appname . '/forms',
            (defined('SPP_APP_DIR') ? SPP_APP_DIR . '/etc/apps/' . $appname . '/forms' : ''),
            (defined('SPP_BASE_DIR') ? SPP_BASE_DIR . '/etc/apps/' . $appname . '/forms' : ''),
            (defined('SPP_APP_DIR') ? SPP_APP_DIR . '/src/' . $appname . '/forms' : ''),
        ]), fn($d) => !empty($d) && is_dir($d));

        $formMap = [];
        foreach ($candidateDirs as $formsDir) {
            $patterns = [$formsDir . '/*.yml', $formsDir . '/*.yaml', $formsDir . '/*.xml'];
            $files = [];
            foreach ($patterns as $pattern) {
                $matched = glob($pattern);
                if ($matched) {
                    $files = array_merge($files, $matched);
                }
            }
            foreach ($files as $file) {
                $name = pathinfo($file, PATHINFO_FILENAME);
                if (str_starts_with($name, '__')) continue;
                if (!isset($formMap[$name])) {
                    $ext = pathinfo($file, PATHINFO_EXTENSION);
                    $formMap[$name] = [
                        'name' => $name,
                        'type' => strtoupper($ext ?: 'YML'),
                        'path' => function_exists('relativizePath') ? relativizePath($file) : $file,
                        'size' => (int)filesize($file),
                        'modified' => date('Y-m-d H:i', filemtime($file)),
                        'content' => file_get_contents($file) ?: ''
                    ];
                }
            }
        }
        return array_values($formMap);
    });
    $la->setData(['forms' => $forms]);
}

if (!function_exists('live_list_forms')) {
    function live_list_forms($la, $params) {
        live_Forms_List($la, $params);
    }
}

function live_Forms_Save($la, $params) {
    $name = trim($params['name'] ?? '');
    $content = $params['content'] ?? '';
    $appname = $params['appname'] ?? $params['context'] ?? ($_SESSION['spp_admin_selected_app'] ?? 'default');
    $type = strtolower($params['type'] ?? 'yml');
    
    if (empty($name) || empty($content)) {
        return $la->setStatus('error')->notify("Name and content required.");
    }

    $baseDir = defined('APP_ETC_DIR') ? APP_ETC_DIR : (defined('SPP_APP_DIR') ? SPP_APP_DIR . '/etc/apps' : SPP_BASE_DIR . '/etc/apps');
    $formsDir = $baseDir . '/' . $appname . '/forms';
    if (!is_dir($formsDir)) mkdir($formsDir, 0777, true);

    $filePath = $formsDir . '/' . strtolower($name) . '.' . $type;
    file_put_contents($filePath, $content);
    $la->notify("Form '$name' saved.");
}

if (!function_exists('live_save_form')) {
    function live_save_form($la, $params) {
        live_Forms_Save($la, $params);
    }
}

function live_Forms_Delete($la, $params) {
    $name = trim($params['name'] ?? '');
    $appname = $params['appname'] ?? $params['context'] ?? ($_SESSION['spp_admin_selected_app'] ?? 'default');
    if (empty($name)) return $la->setStatus('error')->notify("Name required.");

    $candidateDirs = array_filter(array_unique([
        (defined('APP_ETC_DIR') ? APP_ETC_DIR : (defined('SPP_APP_DIR') ? SPP_APP_DIR . '/etc/apps' : '')) . '/' . $appname . '/forms',
        (defined('SPP_APP_DIR') ? SPP_APP_DIR . '/etc/apps/' . $appname . '/forms' : ''),
        (defined('SPP_BASE_DIR') ? SPP_BASE_DIR . '/etc/apps/' . $appname . '/forms' : ''),
        (defined('SPP_APP_DIR') ? SPP_APP_DIR . '/src/' . $appname . '/forms' : ''),
    ]), fn($d) => !empty($d) && is_dir($d));

    $deleted = false;
    foreach ($candidateDirs as $dir) {
        foreach (['.yml', '.yaml', '.xml'] as $ext) {
            $p = $dir . '/' . strtolower($name) . $ext;
            if (file_exists($p)) {
                unlink($p);
                $deleted = true;
            }
        }
    }

    if ($deleted) {
        $la->notify("Form '$name' deleted.");
    } else {
        $la->setStatus('error')->notify("Form not found.");
    }
}

if (!function_exists('live_delete_form')) {
    function live_delete_form($la, $params) {
        live_Forms_Delete($la, $params);
    }
}

function live_Forms_ParseYAML($la, $params) {
    $yaml = $params['yaml'] ?? '';
    if (empty($yaml)) return $la->setStatus('error')->notify("YAML required.");
    try {
        $la->setData(['config' => \Symfony\Component\Yaml\Yaml::parse($yaml)]);
    } catch (\Exception $e) {
        $la->setStatus('error')->notify("Parse Error: " . $e->getMessage());
    }
}

function live_Forms_DumpYAML($la, $params) {
    $config = $params['config'] ?? [];
    if (is_string($config)) $config = json_decode($config, true);
    try {
        $la->setData(['yaml' => \Symfony\Component\Yaml\Yaml::dump($config, 10, 2)]);
    } catch (\Exception $e) {
        $la->setStatus('error')->notify("Dump Error: " . $e->getMessage());
    }
}

if (!function_exists('live_parse_form_yaml')) {
    function live_parse_form_yaml($la, $params) {
        live_Forms_ParseYAML($la, $params);
    }
}

if (!function_exists('live_dump_form_yaml')) {
    function live_dump_form_yaml($la, $params) {
        live_Forms_DumpYAML($la, $params);
    }
}

function live_Forms_SaveConfig($la, $params) {
    $name = trim($params['name'] ?? '');
    $appname = $params['appname'] ?? $params['context'] ?? ($_SESSION['spp_admin_selected_app'] ?? 'default');
    $name = preg_replace('/\.ya?ml$/i', '', $name);
    if (empty($name)) {
        return $la->setStatus('error')->notify("Form name is required.");
    }

    $yaml = '';
    if (!empty($params['yaml'])) {
        $yaml = $params['yaml'];
    } elseif (!empty($params['content'])) {
        $yaml = $params['content'];
    } elseif (!empty($params['config'])) {
        $config = $params['config'];
        if (is_string($config)) {
            $config = json_decode($config, true);
        }
        if (is_array($config)) {
            $yaml = \Symfony\Component\Yaml\Yaml::dump($config, 10, 2);
        }
    }

    if (empty($yaml)) {
        return $la->setStatus('error')->notify("Form configuration or YAML is required.");
    }

    $baseDir = defined('APP_ETC_DIR') ? APP_ETC_DIR : (defined('SPP_APP_DIR') ? SPP_APP_DIR . '/etc/apps' : SPP_BASE_DIR . '/etc/apps');
    $formsDir = $baseDir . '/' . $appname . '/forms';
    if (!is_dir($formsDir)) {
        mkdir($formsDir, 0777, true);
    }

    $filePath = $formsDir . '/' . strtolower($name) . '.yml';
    file_put_contents($filePath, $yaml);
    $la->notify("Form '$name' saved successfully.", "success");
}

if (!function_exists('live_save_form_config')) {
    function live_save_form_config($la, $params) {
        live_Forms_SaveConfig($la, $params);
    }
}

function live_Forms_Preview($la, $params) {
    $yaml = $params['yaml'] ?? $params['form'] ?? '';
    $name = $params['name'] ?? '';
    $appname = $params['appname'] ?? $params['context'] ?? ($_SESSION['spp_admin_selected_app'] ?? 'default');

    if (empty($yaml) && !empty($name)) {
        try {
            $formObj = \SPPMod\SPPView\Forms::get($name, $appname);
            if ($formObj) {
                return $la->setData(['html' => $formObj->getHTML(), 'assets' => []])->notify("Form preview generated.", "success");
            }
        } catch (\Throwable $e) {}
    }

    if (empty($yaml)) {
        return $la->setStatus('error')->notify("YAML or form name required for preview.");
    }

    try {
        if (!class_exists(\SPPMod\SPPView\ViewFormBuilder::class)) {
            require_once SPP_BASE_DIR . '/modules/spp/sppview/class.viewformbuilder.php';
        }
        $formObj = \SPPMod\SPPView\ViewFormBuilder::fromString($yaml);
        $html = $formObj->getHTML();
        $la->setData([
            'html' => $html,
            'assets' => []
        ]);
    } catch (\Throwable $e) {
        $la->setStatus('error')->notify("Form Preview Error: " . $e->getMessage());
    }
}

if (!function_exists('live_preview_form')) {
    function live_preview_form($la, $params) {
        live_Forms_Preview($la, $params);
    }
}

// =============================================
// VALIDATOR & RULE STUDIO SERVICES
// =============================================

function live_Forms_ListValidators($la, $params) {
    $appname = $params['appname'] ?? $params['context'] ?? ($_SESSION['spp_admin_selected_app'] ?? 'default');
    
    if (!class_exists(\SPPMod\SPPView\Validation\CustomRuleRegistry::class)) {
        $ruleBase = SPP_BASE_DIR . '/modules/spp/sppview/Validation';
        if (file_exists($ruleBase . '/RuleInterface.php')) require_once $ruleBase . '/RuleInterface.php';
        if (file_exists($ruleBase . '/Rule.php')) require_once $ruleBase . '/Rule.php';
        if (file_exists($ruleBase . '/CustomRuleRegistry.php')) require_once $ruleBase . '/CustomRuleRegistry.php';
    }

    // Auto-discover rules for current app
    if (class_exists(\SPPMod\SPPView\Validation\CustomRuleRegistry::class)) {
        \SPPMod\SPPView\Validation\CustomRuleRegistry::autoDiscover($appname, true);
    }

    // 1. Built-in catalog
    $builtInMetadata = [
        'pan' => ['label' => 'PAN Card (Income Tax)', 'category' => 'identity', 'badge' => '🇮🇳 PAN', 'description' => 'Permanent Account Number: 5 letters, 4 digits, 1 letter'],
        'aadhaar' => ['label' => 'Aadhaar Card (UIDAI)', 'category' => 'identity', 'badge' => '🇮🇳 Aadhaar', 'description' => '12-digit Indian Unique Identification Number'],
        'gstin' => ['label' => 'GSTIN Number', 'category' => 'financial', 'badge' => '🇮🇳 GSTIN', 'description' => '15-character Goods & Services Tax Identification Number'],
        'ifsc' => ['label' => 'IFSC Code', 'category' => 'financial', 'badge' => '🏦 IFSC', 'description' => '11-character Indian Financial System Code (e.g. SBIN0001234)'],
        'pincode' => ['label' => 'Postal PIN Code', 'category' => 'postal', 'badge' => '📍 PIN', 'description' => '6-digit Indian Postal Identification Number'],
        'indmobile' => ['label' => 'Indian Mobile Number', 'category' => 'contact', 'badge' => '📱 Mobile', 'description' => '10-digit Indian mobile number starting with 6, 7, 8, or 9'],
        'passwordstrength' => ['label' => 'Strong Password', 'category' => 'security', 'badge' => '🔐 Strong Pwd', 'description' => 'Min 8 chars, 1 uppercase, 1 lowercase, 1 number, 1 special character'],
        'creditcard' => ['label' => 'Credit Card Number', 'category' => 'financial', 'badge' => '💳 Card', 'description' => '13-19 digit credit card number format'],
        'email' => ['label' => 'Email Address', 'category' => 'standard', 'badge' => '📧 Email', 'description' => 'Valid RFC email address'],
        'url' => ['label' => 'Website URL', 'category' => 'standard', 'badge' => '🌐 URL', 'description' => 'Valid web URL starting with http:// or https://'],
        'unique' => ['label' => 'Database Unique Value', 'category' => 'database', 'badge' => '🗄️ Unique', 'description' => 'Checks if value is unique in a database table (unique:table,column)'],
        'exists' => ['label' => 'Database Record Exists', 'category' => 'database', 'badge' => '🗄️ Exists', 'description' => 'Ensures foreign key or value exists in target table (exists:table,column)'],
    ];

    $builtInRaw = class_exists(\SPPMod\SPPView\Validation\CustomRuleRegistry::class)
        ? \SPPMod\SPPView\Validation\CustomRuleRegistry::getBuiltInClientRules()
        : [];
    $builtInList = [];
    foreach ($builtInRaw as $alias => $ruleData) {
        $meta = $builtInMetadata[$alias] ?? [
            'label' => ucfirst($alias),
            'category' => 'custom',
            'badge' => strtoupper($alias),
            'description' => $ruleData['data-msg'] ?? 'Validation rule'
        ];
        $builtInList[] = array_merge([
            'alias' => $alias,
            'pattern' => $ruleData['pattern'] ?? '',
            'minlength' => $ruleData['minlength'] ?? null,
            'maxlength' => $ruleData['maxlength'] ?? null,
            'message' => $ruleData['data-msg'] ?? '',
            'type' => $ruleData['type'] ?? 'text',
        ], $meta);
    }

    // 2. Read app-specific validators.yml
    $candidateDirs = array_filter(array_unique([
        (defined('APP_ETC_DIR') ? APP_ETC_DIR : (defined('SPP_APP_DIR') ? SPP_APP_DIR . '/etc/apps' : '')) . '/' . $appname,
        (defined('SPP_APP_DIR') ? SPP_APP_DIR . '/etc/apps/' . $appname : ''),
        (defined('SPP_BASE_DIR') ? SPP_BASE_DIR . '/etc/apps/' . $appname : ''),
        (defined('SPP_APP_DIR') ? SPP_APP_DIR . '/src/' . $appname . '/etc' : ''),
    ]), fn($d) => !empty($d) && is_dir($d));

    $appRules = [];
    $valFile = null;
    foreach ($candidateDirs as $dir) {
        $candidate = $dir . '/validators.yml';
        if (file_exists($candidate)) {
            $valFile = $candidate;
            $parsed = \Symfony\Component\Yaml\Yaml::parseFile($candidate);
            $rulesRaw = $parsed['validators'] ?? $parsed['rules'] ?? $parsed ?? [];
            if (is_array($rulesRaw)) {
                foreach ($rulesRaw as $alias => $def) {
                    $aliasStr = strtolower((string)$alias);
                    if (is_string($def)) {
                        $appRules[] = [
                            'alias' => $aliasStr,
                            'type' => 'class',
                            'class' => $def,
                            'message' => '',
                            'pattern' => '',
                            'file' => $candidate
                        ];
                    } elseif (is_array($def)) {
                        $appRules[] = [
                            'alias' => $aliasStr,
                            'type' => isset($def['class']) ? 'class' : (isset($def['pattern']) || isset($def['regex']) ? 'pattern' : (isset($def['in']) ? 'enum' : (isset($def['range']) ? 'range' : 'custom'))),
                            'class' => $def['class'] ?? '',
                            'pattern' => $def['pattern'] ?? ($def['regex'] ?? ''),
                            'message' => $def['message'] ?? '',
                            'minlength' => $def['minlength'] ?? null,
                            'maxlength' => $def['maxlength'] ?? null,
                            'in' => $def['in'] ?? ($def['options'] ?? null),
                            'range' => $def['range'] ?? null,
                            'params' => $def['params'] ?? null,
                            'file' => $candidate
                        ];
                    }
                }
            }
            break;
        }
    }
    // Resolve class_file paths for app_rules
    foreach ($appRules as &$ar) {
        if (!empty($ar['class'])) {
            $parts = explode('\\', ltrim($ar['class'], '\\'));
            if (count($parts) >= 3 && $parts[0] === 'App') {
                $ar['class_file'] = 'src/' . $parts[1] . '/' . implode('/', array_slice($parts, 2)) . '.php';
            }
        }
    }
    unset($ar);

    // 3. Scan for Rule classes in src/{app}/Rules/*.php
    $rulesDir = (defined('SPP_APP_DIR') ? SPP_APP_DIR : SPP_BASE_DIR) . '/src/' . $appname . '/Rules';
    $appClasses = [];
    if (is_dir($rulesDir)) {
        $files = glob($rulesDir . '/*Rule.php') ?: [];
        foreach ($files as $file) {
            $className = pathinfo($file, PATHINFO_FILENAME);
            $relPath = 'src/' . $appname . '/Rules/' . $className . '.php';
            $appClasses[] = [
                'name' => $className,
                'class' => "App\\{$appname}\\Rules\\{$className}",
                'path' => $relPath,
                'class_file' => $relPath,
                'modified' => date('Y-m-d H:i', filemtime($file))
            ];
        }
    }

    $la->setData([
        'built_in' => $builtInList,
        'app_rules' => $appRules,
        'app_classes' => $appClasses,
        'validators_file' => $valFile ? (function_exists('relativizePath') ? relativizePath($valFile) : $valFile) : null,
    ]);
}

if (!function_exists('live_list_validators')) {
    function live_list_validators($la, $params) {
        live_Forms_ListValidators($la, $params);
    }
}

function live_Forms_SaveValidator($la, $params) {
    $alias = strtolower(trim($params['alias'] ?? ''));
    if (empty($alias)) {
        return $la->setStatus('error')->notify("Validator alias is required.");
    }

    $appname = $params['appname'] ?? $params['context'] ?? ($_SESSION['spp_admin_selected_app'] ?? 'default');
    $pattern = trim($params['pattern'] ?? '');
    $message = trim($params['message'] ?? '');
    $ruleClass = trim($params['rule_class'] ?? ($params['class'] ?? ($params['class_name'] ?? '')));
    $minlength = isset($params['minlength']) && $params['minlength'] !== '' ? (int)$params['minlength'] : null;
    $maxlength = isset($params['maxlength']) && $params['maxlength'] !== '' ? (int)$params['maxlength'] : null;
    $createClass = !empty($params['create_class']);

    $baseDir = defined('APP_ETC_DIR') ? APP_ETC_DIR : (defined('SPP_APP_DIR') ? SPP_APP_DIR . '/etc/apps' : SPP_BASE_DIR . '/etc/apps');
    $appEtcDir = $baseDir . '/' . $appname;
    if (!is_dir($appEtcDir)) {
        mkdir($appEtcDir, 0777, true);
    }
    $valFile = $appEtcDir . '/validators.yml';

    $existing = [];
    if (file_exists($valFile)) {
        $parsed = \Symfony\Component\Yaml\Yaml::parseFile($valFile);
        if (is_array($parsed)) {
            $existing = $parsed;
        }
    }

    if (!isset($existing['validators']) || !is_array($existing['validators'])) {
        $existing['validators'] = [];
    }

    $ruleDef = [];
    if (!empty($ruleClass)) {
        $ruleDef['class'] = $ruleClass;
        if (!empty($message)) $ruleDef['message'] = $message;
    } elseif (!empty($pattern)) {
        $ruleDef['pattern'] = $pattern;
        if (!empty($message)) $ruleDef['message'] = $message;
        if ($minlength !== null) $ruleDef['minlength'] = $minlength;
        if ($maxlength !== null) $ruleDef['maxlength'] = $maxlength;
    } elseif ($createClass) {
        $cleanName = ucfirst(preg_replace('/[^a-zA-Z0-9]/', '', str_replace('_', ' ', $alias)));
        if (!str_ends_with($cleanName, 'Rule')) $cleanName .= 'Rule';
        $ruleClass = "App\\{$appname}\\Rules\\{$cleanName}";
        $ruleDef['class'] = $ruleClass;
        if (!empty($message)) $ruleDef['message'] = $message;
    } else {
        return $la->setStatus('error')->notify("Either pattern or class name is required.");
    }

    $existing['validators'][$alias] = $ruleDef;
    file_put_contents($valFile, \Symfony\Component\Yaml\Yaml::dump($existing, 10, 2));

    // If create_class is true, scaffold class in src/{app}/Rules/
    $createdClassFile = null;
    if ($createClass) {
        $cleanName = !empty($ruleClass) ? basename(str_replace('\\', '/', $ruleClass)) : ucfirst(preg_replace('/[^a-zA-Z0-9]/', '', str_replace('_', ' ', $alias)));
        if (!str_ends_with($cleanName, 'Rule')) $cleanName .= 'Rule';
        $rulesDir = (defined('SPP_APP_DIR') ? SPP_APP_DIR : SPP_BASE_DIR) . '/src/' . $appname . '/Rules';
        if (!is_dir($rulesDir)) mkdir($rulesDir, 0777, true);
        $classFile = $rulesDir . '/' . $cleanName . '.php';

        $cleanPattern = ltrim(rtrim($pattern ?: '^[A-Za-z0-9_-]+$', '/'), '/');
        $minConstraint = $minlength !== null ? "\n            'minlength' => {$minlength}," : "";
        $maxConstraint = $maxlength !== null ? "\n            'maxlength' => {$maxlength}," : "";
        $classMsg = !empty($message) ? $message : ":attribute format is invalid.";

        $phpCode = <<<PHP
<?php
declare(strict_types=1);

namespace App\\{$appname}\\Rules;

use SPPMod\SPPView\Validation\Rule;

/**
 * Class {$cleanName}
 *
 * Universal Custom Validator for {$alias}.
 * Validates on Server (PHP) and Client (HTML5 / SPP-UX).
 */
class {$cleanName} extends Rule
{
    public function __construct(
        protected string \$pattern = '{$cleanPattern}',
        ?string \$customMessage = null
    ) {
        if (\$customMessage !== null) {
            \$this->withMessage(\$customMessage);
        } else {
            \$this->withMessage('{$classMsg}');
        }
    }

    /**
     * 1. Server-side validation logic (PHP).
     */
    public function passes(string \$attribute, mixed \$value, array \$data = []): bool
    {
        if (\$value === null || \$value === '') {
            return true;
        }

        return (bool)preg_match('/' . \$this->pattern . '/', (string)\$value);
    }

    /**
     * 2. Default error message.
     */
    public function message(string \$attribute): string
    {
        \$label = ucfirst(str_replace(['_', '-'], ' ', \$attribute));
        if (\$this->customMessage !== null) {
            return str_replace([':attribute', ':field'], [\$label, \$attribute], \$this->customMessage);
        }
        return "{\$label} format is invalid.";
    }

    /**
     * 3. Client-side HTML5 & SPP-UX attributes.
     */
    public function clientRule(string \$attribute): array
    {
        return [
            'pattern' => \$this->pattern,{$minConstraint}{$maxConstraint}
            'data-rule' => '{$alias}',
            'data-msg' => \$this->message(\$attribute),
        ];
    }

    /**
     * 4. (Optional) Inline JavaScript validation for real-time client checks.
     */
    public function clientJs(): ?string
    {
        return "return new RegExp('^' + '{\$this->pattern}' + '\$').test(value);";
    }
}
PHP;
        file_put_contents($classFile, $phpCode);
        $createdClassFile = 'src/' . $appname . '/Rules/' . $cleanName . '.php';

        // Also update validators.yml to reference this new class if not already
        $existing['validators'][$alias]['class'] = "App\\{$appname}\\Rules\\{$cleanName}";
        file_put_contents($valFile, \Symfony\Component\Yaml\Yaml::dump($existing, 10, 2));
    }

    // Refresh registry
    if (class_exists(\SPPMod\SPPView\Validation\CustomRuleRegistry::class)) {
        \SPPMod\SPPView\Validation\CustomRuleRegistry::autoDiscover($appname, true);
    }

    // Resolve class file path if custom class was provided directly
    $finalClassFile = $createdClassFile;
    if (!$finalClassFile && !empty($ruleClass)) {
        $parts = explode('\\', ltrim($ruleClass, '\\'));
        if (count($parts) >= 3 && $parts[0] === 'App') {
            $finalClassFile = 'src/' . $parts[1] . '/' . implode('/', array_slice($parts, 2)) . '.php';
        }
    }
    if ($finalClassFile && function_exists('relativizePath')) {
        $finalClassFile = relativizePath($finalClassFile);
    }

    $la->setData([
        'alias' => $alias,
        'rule' => $existing['validators'][$alias],
        'class_file' => $finalClassFile ? str_replace('\\', '/', $finalClassFile) : null
    ])->notify("Validator '{$alias}' saved successfully.", "success");
}

if (!function_exists('live_save_validator')) {
    function live_save_validator($la, $params) {
        live_Forms_SaveValidator($la, $params);
    }
}

function live_Forms_DeleteValidator($la, $params) {
    $alias = strtolower(trim($params['alias'] ?? ''));
    if (empty($alias)) {
        return $la->setStatus('error')->notify("Validator alias is required.");
    }

    $appname = $params['appname'] ?? $params['context'] ?? ($_SESSION['spp_admin_selected_app'] ?? 'default');
    $baseDir = defined('APP_ETC_DIR') ? APP_ETC_DIR : (defined('SPP_APP_DIR') ? SPP_APP_DIR . '/etc/apps' : SPP_BASE_DIR . '/etc/apps');
    $valFile = $baseDir . '/' . $appname . '/validators.yml';

    if (!file_exists($valFile)) {
        return $la->setStatus('error')->notify("No validators.yml file found for {$appname}.");
    }

    $existing = \Symfony\Component\Yaml\Yaml::parseFile($valFile) ?: [];
    $removed = false;

    if (isset($existing['validators'][$alias])) {
        unset($existing['validators'][$alias]);
        $removed = true;
    } elseif (isset($existing['rules'][$alias])) {
        unset($existing['rules'][$alias]);
        $removed = true;
    } elseif (isset($existing[$alias])) {
        unset($existing[$alias]);
        $removed = true;
    }

    if ($removed) {
        file_put_contents($valFile, \Symfony\Component\Yaml\Yaml::dump($existing, 10, 2));
        if (class_exists(\SPPMod\SPPView\Validation\CustomRuleRegistry::class)) {
            \SPPMod\SPPView\Validation\CustomRuleRegistry::autoDiscover($appname, true);
        }
        $la->notify("Validator '{$alias}' deleted successfully.", "success");
    } else {
        $la->setStatus('error')->notify("Validator '{$alias}' not found in validators.yml.");
    }
}

if (!function_exists('live_delete_validator')) {
    function live_delete_validator($la, $params) {
        live_Forms_DeleteValidator($la, $params);
    }
}

function live_Forms_GenerateRule($la, $params) {
    $name = trim($params['name'] ?? '');
    if (empty($name)) {
        return $la->setStatus('error')->notify("Rule name is required.");
    }
    $params['alias'] = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', preg_replace('/Rule$/i', '', $name)));
    $params['create_class'] = true;
    return live_Forms_SaveValidator($la, $params);
}

if (!function_exists('live_generate_rule')) {
    function live_generate_rule($la, $params) {
        live_Forms_GenerateRule($la, $params);
    }
}

/**
 * Endpoint to load form validation schema for client auto-binding.
 */
function live_Forms_GetFormValidators($la, $params) {
    $formId = trim($params['form_id'] ?? ($params['name'] ?? ''));
    $appname = trim($params['appname'] ?? ($params['app'] ?? ''));
    if (empty($appname)) {
        $appname = class_exists('\\SPP\\Scheduler') ? \SPP\Scheduler::getContext() : 'default';
    }
    if (empty($formId)) {
        return $la->setStatus('error')->notify("Form ID is required.");
    }

    $payload = \SPPMod\SPPView\Validation\CustomRuleRegistry::getFormSchemaPayload($formId, $appname);
    return $la->setData($payload);
}

if (!function_exists('live_get_form_validators')) {
    function live_get_form_validators($la, $params) {
        live_Forms_GetFormValidators($la, $params);
    }
}

/**
 * Endpoint to test validating arbitrary form data against a YAML schema.
 */
function live_Forms_TestValidateForm($la, $params) {
    $formId = trim($params['form_id'] ?? ($params['name'] ?? ''));
    $appname = trim($params['appname'] ?? ($params['app'] ?? ''));
    if (empty($appname)) {
        $appname = class_exists('\\SPP\\Scheduler') ? \SPP\Scheduler::getContext() : 'default';
    }
    if (empty($formId)) {
        return $la->setStatus('error')->notify("Form ID is required.");
    }

    $formData = $params['data'] ?? [];
    if (is_string($formData)) {
        $decoded = json_decode($formData, true);
        if (is_array($decoded)) $formData = $decoded;
    }

    return \SPP\Scheduler::withContext($appname, function() use ($la, $formId, $formData) {
        try {
            $controller = new class extends \SPPMod\SPPView\ViewController {};
            $validation = $controller->validateForm($formId, $formData);

            return $la->setData([
                'success' => true,
                'form_id' => $formId,
                'passes' => $validation->passes(),
                'fails' => $validation->fails(),
                'errors' => $validation->errors(),
                'validated' => $validation->validated()
            ]);
        } catch (\Throwable $e) {
            return $la->setStatus('error')->notify($e->getMessage());
        }
    });
}

if (!function_exists('live_test_validate_form')) {
    function live_test_validate_form($la, $params) {
        live_Forms_TestValidateForm($la, $params);
    }
}

/**
 * Real-time single field validation endpoint for remote/debounced client checks.
 */
function live_Forms_ValidateField($la, $params) {
    $field = trim($params['field'] ?? '');
    $value = $params['value'] ?? null;
    $formId = trim($params['form_id'] ?? ($params['name'] ?? ''));
    $rules = trim($params['rules'] ?? '');
    $appname = trim($params['appname'] ?? ($params['app'] ?? ''));
    if (empty($appname)) {
        $appname = class_exists('\\SPP\\Scheduler') ? \SPP\Scheduler::getContext() : 'default';
    }

    if (empty($field)) {
        return $la->setStatus('error')->notify("Field name is required.");
    }

    return \SPP\Scheduler::withContext($appname, function() use ($la, $formId, $field, $value, $rules, $appname) {
        try {
            $fieldRules = $rules;
            if (empty($fieldRules) && !empty($formId)) {
                $formConfig = \SPPMod\SPPView\Forms::getFormConfig($formId, $appname);
                $elements = $formConfig['fields'] ?? ($formConfig['elements'] ?? []);
                if (isset($elements[$field])) {
                    $def = $elements[$field];
                    $fieldRules = $def['rules'] ?? ($def['validation'] ?? ($def['validations'] ?? ''));
                }
            }

            if (empty($fieldRules)) {
                return $la->setData([
                    'success' => true,
                    'valid' => true,
                    'field' => $field,
                    'message' => ''
                ]);
            }

            $controller = new class extends \SPPMod\SPPView\ViewController {
                public function executeValidation(array $rules, ?array $data = null): \SPPMod\SPPView\ValidationResult {
                    return $this->validate($rules, $data);
                }
            };
            $validation = $controller->executeValidation([$field => $fieldRules], [$field => $value]);

            $valid = $validation->passes();
            $errors = $validation->getErrors();
            $msg = '';
            if (!$valid && !empty($errors[$field])) {
                $msg = is_array($errors[$field]) ? reset($errors[$field]) : (string)$errors[$field];
            }

            return $la->setData([
                'success' => true,
                'valid' => $valid,
                'field' => $field,
                'message' => $msg,
                'errors' => $errors
            ]);
        } catch (\Throwable $e) {
            return $la->setData([
                'success' => false,
                'valid' => false,
                'field' => $field,
                'message' => $e->getMessage()
            ]);
        }
    });
}

if (!function_exists('live_validate_field')) {
    function live_validate_field($la, $params) {
        live_Forms_ValidateField($la, $params);
    }
}

/**
 * 1-Click Form Synthesis from an SPPEntity or database table schema.
 */
function live_Forms_GenerateFromEntity($la, $params) {
    $entity = trim($params['entity'] ?? ($params['table'] ?? ''));
    $appname = trim($params['appname'] ?? ($params['app'] ?? ''));
    if (empty($appname)) {
        $appname = class_exists('\\SPP\\Scheduler') ? \SPP\Scheduler::getContext() : 'default';
    }

    if (empty($entity)) {
        return $la->setStatus('error')->notify("Entity or table name required.");
    }

    return \SPP\Scheduler::withContext($appname, function() use ($la, $entity, $appname, $params) {
        try {
            $schemaData = \SPPMod\SPPView\Forms::generateSchemaFromEntity($entity, $appname);
            $cleanName = strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '', str_replace(['Form', 'Entity'], '', basename(str_replace('\\', '/', $entity)))));
            $formName = trim($params['form_name'] ?? $cleanName);

            $baseDir = defined('SPP_APP_DIR') ? SPP_APP_DIR : (defined('SPP_BASE_DIR') ? dirname(SPP_BASE_DIR) : dirname(__DIR__, 3));
            $yamlDir = defined('APP_ETC_DIR') ? APP_ETC_DIR . "/{$appname}/forms" : "{$baseDir}/etc/apps/{$appname}/forms";
            if (!is_dir($yamlDir)) {
                mkdir($yamlDir, 0755, true);
            }
            $targetPath = "{$yamlDir}/{$formName}.yml";

            $schemaData['form']['name'] = $formName;
            $schemaData['form']['form_id'] = $formName;
            $schemaData['form']['action'] = "/{$formName}s";

            file_put_contents($targetPath, \Symfony\Component\Yaml\Yaml::dump($schemaData, 6, 2));

            \SPPMod\SPPView\Forms::clearCache();

            return $la->setData([
                'success' => true,
                'form_name' => $formName,
                'path' => $targetPath,
                'schema' => $schemaData
            ])->notify("Form '{$formName}' successfully synthesized from Entity '{$entity}'!");
        } catch (\Throwable $e) {
            return $la->setStatus('error')->notify("Form generation failed: " . $e->getMessage());
        }
    });
}

if (!function_exists('live_generate_form_from_entity')) {
    function live_generate_form_from_entity($la, $params) {
        live_Forms_GenerateFromEntity($la, $params);
    }
}

if (!function_exists('live_generate_from_entity')) {
    function live_generate_from_entity($la, $params) {
        live_Forms_GenerateFromEntity($la, $params);
    }
}


