<?php
/**
 * Auto-extracted legacy services
 */

if (!function_exists('live_list_revisions')) {
    function live_list_revisions($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $name = $params['name'] ?? '';
        if (!$name)
            return $la->setStatus('error')->notify("Entity name required.", "error");

        $srcDir = SPP_APP_DIR . "/src/{$appContext}/entities";
        $revDir = $srcDir . '/.revisions';
        $revisions = [];

        if (is_dir($revDir)) {
            $files = glob($revDir . '/' . strtolower($name) . '_*.yml');
            foreach ($files as $file) {
                if (preg_match('/_(\d+)\.yml$/', $file, $matches)) {
                    $ts = $matches[1];
                    $revisions[] = [
                        'timestamp' => (int) $ts,
                        'date' => date('Y-m-d H:i:s', $ts)
                    ];
                }
            }
        }
        usort($revisions, function ($a, $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });
        return $la->setData(['revisions' => $revisions]);
    }
}

if (!function_exists('live_ai_parse_scaffold')) {
    function live_ai_parse_scaffold($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $prompt = strtolower($params['prompt'] ?? '');
        if (!$prompt)
            return $la->setStatus('error')->notify("Prompt required.", "error");

        $commands = [];
        if (preg_match('/app(?:lication)? called (\w+)/', $prompt, $matches)) {
            $appName = $matches[1];
            $opts = (strpos($prompt, 'api') !== false) ? '--api' : '';
            $commands[] = ['cmd' => 'make:app', 'target' => $appName, 'opts' => $opts];
        }

        if (preg_match('/module(?: called)? (\w+)/', $prompt, $matches)) {
            $modName = $matches[1];
            $commands[] = ['cmd' => 'make:module', 'target' => ucfirst($modName), 'opts' => ''];
        }

        if (preg_match('/entity(?: called)? (\w+)/', $prompt, $matches)) {
            $entName = $matches[1];
            $commands[] = ['cmd' => 'make:entity', 'target' => ucfirst($entName), 'opts' => ''];
        }

        if (empty($commands)) {
            return $la->setStatus('error')->notify("Could not understand the requested command from prompt.", "error");
        }

        return $la->setData(['commands' => $commands])->notify("Parsed successfully.", "success");

    }
}

if (!function_exists('live_clone_app')) {
    function live_clone_app($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $source = $params['source'] ?? '';
        $target = $params['target'] ?? '';
        if (!$source || !$target)
            return $la->setStatus('error')->notify("Source and target required.", "error");

        // Mock cloning logic for safety in dev environment
        $settingsFile = SPP_BASE_DIR . '/etc/global-settings.yml';
        if (file_exists($settingsFile)) {
            try {
                $settings = \Symfony\Component\Yaml\Yaml::parseFile($settingsFile);
                if (isset($settings['apps'][$source])) {
                    $settings['apps'][$target] = $settings['apps'][$source];
                    $settings['apps'][$target]['base_url'] = '/' . strtolower($target);
                    $settings['apps'][$target]['table_prefix'] = strtolower($target) . '_';
                    $yaml = \Symfony\Component\Yaml\Yaml::dump($settings, 4, 4);
                    file_put_contents($settingsFile, $yaml);
                    return $la->notify("App cloned successfully.", "success");
                }
            } catch (\Exception $e) {
                return $la->setStatus('error')->notify("Failed to clone app: " . $e->getMessage(), "error");
            }
        }
        return $la->setStatus('error')->notify("Source app not found.", "error");
    }
}

if (!function_exists('live_scaffold_template')) {
    function live_scaffold_template($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $template = $params['template'] ?? '';
        if (!$template)
            return $la->setStatus('error')->notify("Template name required.", "error");

        // Create mock app from template
        $settingsFile = SPP_BASE_DIR . '/etc/global-settings.yml';
        if (file_exists($settingsFile)) {
            try {
                $settings = \Symfony\Component\Yaml\Yaml::parseFile($settingsFile);
                if (!isset($settings['apps']))
                    $settings['apps'] = [];
                $settings['apps'][$template . '_app'] = [
                    'type' => 'user',
                    'base_url' => '/' . $template,
                    'table_prefix' => $template . '_',
                    'options_yaml' => "template: {$template}\ncreated_at: " . time()
                ];
                $yaml = \Symfony\Component\Yaml\Yaml::dump($settings, 4, 4);
                file_put_contents($settingsFile, $yaml);
                return $la->notify("Template scaffolded successfully.", "success");
            } catch (\Exception $e) {
                return $la->setStatus('error')->notify("Failed to update settings: " . $e->getMessage(), "error");
            }
        }
        return $la->setStatus('error')->notify("Failed to scaffold template. Configuration file not found.", "error");
    }
}

if (!function_exists('live_tail_logs')) {
    function live_tail_logs($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $lines = [];

        // Try PHP error log
        $phpLog = ini_get('error_log');
        if ($phpLog && file_exists($phpLog) && is_readable($phpLog)) {
            $raw = file($phpLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $tail = array_slice($raw, -50);
            $lines = array_merge($lines, $tail);
        }

        // Try SPP internal log
        $sppLog = SPP_BASE_DIR . '/logs/spp.log';
        if (file_exists($sppLog) && is_readable($sppLog)) {
            $raw = file($sppLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $tail = array_slice($raw, -50);
            $lines = array_merge($lines, $tail);
        }

        // Fallback: If no log files found, return a helpful message
        if (empty($lines)) {
            $lines = [
                '[INFO] SPP Log Tail initialized. No log entries found yet.',
                '[INFO] PHP error_log path: ' . ($phpLog ?: '(not configured)'),
                '[INFO] SPP log path: ' . $sppLog,
                '[INFO] Logs will appear here as your application generates them.'
            ];
        }

        return $la->setData(['lines' => $lines]);
    }
}

if (!function_exists('live_export_app_package')) {
    function live_export_app_package($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $appName = $params['app'] ?? '';
        if (!$appName)
            sendResponse(false, [], 'Application name required.');

        $package = [
            'app_name' => $appName,
            'exported_at' => date('c'),
            'config' => [],
            'entities' => [],
            'modules' => []
        ];

        // Load app config
        $settingsFile = SPP_BASE_DIR . '/config/settings.json';
        if (file_exists($settingsFile)) {
            $settings = json_decode(file_get_contents($settingsFile), true);
            $package['config'] = $settings['apps'][$appName] ?? [];
            $package['shared_groups'] = $settings['shared_groups'] ?? [];
        }

        // Load entity YAMLs
        $etcDir = SPP_APP_DIR . "/etc/{$appName}/entities";
        if (is_dir($etcDir)) {
            foreach (glob($etcDir . '/*.yml') as $file) {
                $package['entities'][basename($file)] = file_get_contents($file);
            }
        }

        // Load entity PHP files
        $srcDir = SPP_APP_DIR . "/src/{$appName}/entities";
        if (is_dir($srcDir)) {
            foreach (glob($srcDir . '/*.php') as $file) {
                $package['entities'][basename($file)] = file_get_contents($file);
            }
        }

        sendResponse(true, ['package' => $package], 'Package exported.');
    }
}

if (!function_exists('live_generate_docker')) {
    function live_generate_docker($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $dockerfile = <<<DOCKER
FROM php:8.2-fpm
RUN docker-php-ext-install pdo pdo_mysql
COPY . /var/www/html
WORKDIR /var/www/html
RUN chown -R www-data:www-data /var/www/html
CMD ["php-fpm"]
DOCKER;

        $dockerCompose = <<<YAML
version: '3.8'
services:
  web:
    image: nginx:alpine
    ports:
      - "8080:80"
    volumes:
      - .:/var/www/html
      - ./nginx.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - app
  app:
    build: .
    volumes:
      - .:/var/www/html
  db:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: spp_db
    ports:
      - "3306:3306"
YAML;

        $nginxConf = <<<CONF
server {
    listen 80;
    index index.php index.html;
    server_name localhost;
    error_log  /var/log/nginx/error.log;
    access_log /var/log/nginx/access.log;
    root /var/www/html;
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
    location ~ \.php\$ {
        try_files \$uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)\$;
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param PATH_INFO \$fastcgi_path_info;
    }
}
CONF;

        file_put_contents(SPP_APP_DIR . '/Dockerfile', $dockerfile);
        file_put_contents(SPP_APP_DIR . '/docker-compose.yml', $dockerCompose);
        file_put_contents(SPP_APP_DIR . '/nginx.conf', $nginxConf);

        return $la->notify("Docker deployment files generated.", "success");
    }
}

if (!function_exists('live_compile_workflow')) {
    function live_compile_workflow($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $trigger = $params['trigger'] ?? 'after_save';
        $task = $params['task'] ?? 'log';

        $snippet = "";
        if ($task === 'email') {
            $snippet = "\$to = \$this->email ?? 'admin@example.com';\n        @mail(\$to, 'Workflow Notification', 'Action triggered.');";
        } elseif ($task === 'log') {
            $snippet = "\\SPPMod\\SPPLogger\\SPP_Logger::info(static::class . ' workflow triggered.');";
        } elseif ($task === 'validate') {
            $snippet = "if (empty(\$this->name)) throw new \\Exception('Validation failed in workflow.');";
        } elseif ($task === 'webhook') {
            $snippet = "file_get_contents('https://hook.example.com/?entity=' . static::class);";
        }

        $code = <<<PHP
    public function {$trigger}() {
        // [Workflow Auto-Compiled]
        {$snippet}
        return parent::{$trigger}();
    }
PHP;
        return $la->setData(['code' => "\n" . $code . "\n"]);
    }
}

if (!function_exists('live_generate_sdk')) {
    function live_generate_sdk($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $srcDir = SPP_APP_DIR . "/src/{$appContext}/entities";
        $entities = [];
        if (is_dir($srcDir)) {
            $files = glob($srcDir . '/*.yml');
            foreach ($files as $file) {
                $yaml = file_get_contents($file);
                $config = \Symfony\Component\Yaml\Yaml::parse($yaml);
                if (isset($config['enable_api']) && $config['enable_api'] == true) {
                    $entityName = pathinfo($file, PATHINFO_FILENAME);
                    // CamelCase
                    $entityName = str_replace(' ', '', ucwords(str_replace('_', ' ', $entityName)));
                    $entities[] = $entityName;
                }
            }
        }

        $sdkCode = "/**\n * SPP Auto-Generated JavaScript SDK\n */\n\n";
        $sdkCode .= "class SPPClient {\n";
        $sdkCode .= "    constructor(baseUrl = '/api/v1') {\n";
        $sdkCode .= "        this.baseUrl = baseUrl;\n";
        $sdkCode .= "    }\n\n";

        foreach ($entities as $entity) {
            $eLower = strtolower($entity);
            $sdkCode .= <<<JS
    // {$entity} API
    async get{$entity}s() {
        const res = await fetch(\`\${this.baseUrl}/{$eLower}\`);
        return res.json();
    }
    async get{$entity}(id) {
        const res = await fetch(\`\${this.baseUrl}/{$eLower}?id=\${id}\`);
        return res.json();
    }
    async create{$entity}(data) {
        const res = await fetch(\`\${this.baseUrl}/{$eLower}\`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        return res.json();
    }
    async update{$entity}(id, data) {
        data.id = id;
        const res = await fetch(\`\${this.baseUrl}/{$eLower}\`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        return res.json();
    }
    async delete{$entity}(id) {
        const res = await fetch(\`\${this.baseUrl}/{$eLower}?id=\${id}\`, {
            method: 'DELETE'
        });
        return res.json();
    }


JS;
        }
        $sdkCode .= "}\n\nexport default SPPClient;\n";

        file_put_contents(SPP_APP_DIR . '/src/spp_sdk.js', $sdkCode);

        return $la->notify("SDK generated.", "success");
    }
}

if (!function_exists('live_scaffold_test')) {
    function live_scaffold_test($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $entityName = $params['entityName'] ?? '';
        if (!$entityName)
            return $la->setStatus('error')->notify("Entity name required.", "error");

        $testsDir = SPP_APP_DIR . "/tests";
        if (!is_dir($testsDir))
            @mkdir($testsDir, 0777, true);

        $className = "\\App\\Entities\\{$entityName}";

        $testCode = <<<PHP
<?php

use PHPUnit\Framework\TestCase;

class {$entityName}Test extends TestCase {
    
    public function testCreate() {
        \$entity = new {$className}();
        // Set basic properties if needed
        \$entity->save();
        \$this->assertNotNull(\$entity->id);
        
        return \$entity->id;
    }
    
    /**
     * @depends testCreate
     */
    public function testRead(\$id) {
        \$entity = new {$className}(\$id);
        \$this->assertEquals(\$id, \$entity->id);
        return \$id;
    }
    
    /**
     * @depends testRead
     */
    public function testDelete(\$id) {
        \$entity = new {$className}(\$id);
        \$entity->delete();
        
        \$check = new {$className}(\$id);
        \$this->assertNull(\$check->id);
    }
}
PHP;

        file_put_contents($testsDir . "/{$entityName}Test.php", $testCode);

        return $la->notify("Tests scaffolded.", "success");
    }
}

if (!function_exists('live_scaffold_auth')) {
    function live_scaffold_auth($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $srcDir = SPP_APP_DIR . "/src/{$appContext}/entities";
        if (!is_dir($srcDir))
            @mkdir($srcDir, 0777, true);

        $userYaml = <<<YAML
table: users
id_field: id
extends: Person
login_enabled: true
enable_api: true
attributes:
  username: varchar(50)
  email: varchar(255)
  password_hash: varchar(255)
  role_id: int
relations:
  - { type: belongsTo, entity: Role, field: role_id }
YAML;

        $roleYaml = <<<YAML
table: roles
id_field: id
enable_api: true
attributes:
  name: varchar(50)
  permissions: text
YAML;

        file_put_contents($srcDir . '/user.yml', $userYaml);
        file_put_contents($srcDir . '/role.yml', $roleYaml);

        // Ensure classes are generated
        $configUser = \Symfony\Component\Yaml\Yaml::parse($userYaml);
        \App\Entities\SPPEntity::saveEntityDefinition('User', $appContext, $configUser);

        $configRole = \Symfony\Component\Yaml\Yaml::parse($roleYaml);
        \App\Entities\SPPEntity::saveEntityDefinition('Role', $appContext, $configRole);

        return $la->notify("Auth entities (User, Role) scaffolded.", "success");
    }
}

if (!function_exists('live_ai_generate_logic')) {
    function live_ai_generate_logic($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $prompt = $params['prompt'] ?? '';
        if (!$prompt)
            return $la->setStatus('error')->notify("Prompt required.", "error");

        $promptLower = strtolower($prompt);
        $code = "";

        if (strpos($promptLower, 'email') !== false || strpos($promptLower, 'mail') !== false) {
            $code .= <<<PHP
    public function after_save() {
        // AI Generated: Send email after save
        \$to = \$this->email ?? 'admin@example.com';
        \$subject = "Notification regarding " . static::class;
        \$message = "A new record has been saved with ID: " . \$this->id;
        @mail(\$to, \$subject, \$message);
        return parent::after_save();
    }
PHP;
        } elseif (strpos($promptLower, 'validate') !== false || strpos($promptLower, 'required') !== false) {
            $code .= <<<PHP
    public function rules() {
        // AI Generated: Validation rules
        return [
            // Example: 'name' => 'required',
            // Example: 'email' => 'required|email'
        ];
    }
PHP;
        } elseif (strpos($promptLower, 'log') !== false || strpos($promptLower, 'audit') !== false) {
            $code .= <<<PHP
    public function after_save() {
        // AI Generated: Log save event
        \\SPPMod\\SPPLogger\\SPP_Logger::info(static::class . " saved with ID: " . \$this->id);
        return parent::after_save();
    }
PHP;
        } else {
            $code .= <<<PHP
    public function before_save() {
        // AI Generated Custom Logic Block
        // \$this->status = 'active';
        return parent::before_save();
    }
PHP;
        }

        return $la->setData(['code' => "\n" . $code . "\n"]);
    }
}

if (!function_exists('live_restore_revision')) {
    function live_restore_revision($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $name = $params['name'] ?? '';
        $timestamp = $params['timestamp'] ?? '';
        if (!$name || !$timestamp)
            return $la->setStatus('error')->notify("Name and timestamp required.", "error");

        $srcDir = SPP_APP_DIR . "/src/{$appContext}/entities";
        $revDir = $srcDir . '/.revisions';

        $revYml = $revDir . '/' . strtolower($name) . '_' . $timestamp . '.yml';
        $revPhp = $revDir . '/entity.' . strtolower($name) . '_' . $timestamp . '.php';

        if (!file_exists($revYml)) {
            return $la->setStatus('error')->notify("Revision not found.", "error");
        }

        // Backup current before restoring
        createEntityRevision($appContext, $name);

        $ymlPath = $srcDir . '/' . strtolower($name) . '.yml';
        $phpPath = $srcDir . '/entity.' . strtolower($name) . '.php';

        @copy($revYml, $ymlPath);
        if (file_exists($revPhp)) {
            @copy($revPhp, $phpPath);
        }

        $ymlContent = file_get_contents($ymlPath);
        $phpContent = file_exists($phpPath) ? file_get_contents($phpPath) : '';

        sendResponse(true, ['yaml' => $ymlContent, 'php' => $phpContent], "Restored to " . date('Y-m-d H:i:s', $timestamp));
    }
}

if (!function_exists('live_magic_generate_schema')) {
    function live_magic_generate_schema($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $prompt = $params['prompt'] ?? '';
        if (!$prompt)
            return $la->setStatus('error')->notify("Prompt required.", "error");

        $promptLower = strtolower($prompt);
        $words = preg_split('/[^a-zA-Z0-9_]+/', $promptLower);

        $attributes = [];
        $typeMap = [
            'name' => 'varchar(255)',
            'first_name' => 'varchar(100)',
            'last_name' => 'varchar(100)',
            'email' => 'varchar(255)',
            'dob' => 'date',
            'birthdate' => 'date',
            'age' => 'int',
            'phone' => 'varchar(20)',
            'address' => 'text',
            'bio' => 'text',
            'description' => 'text',
            'price' => 'decimal(10,2)',
            'cost' => 'decimal(10,2)',
            'status' => 'varchar(50)',
            'created_at' => 'datetime',
            'is_active' => 'tinyint(1)',
            'active' => 'tinyint(1)',
            'title' => 'varchar(255)',
            'subject' => 'varchar(255)',
            'category' => 'varchar(100)',
            'url' => 'varchar(255)',
            'image' => 'varchar(255)'
        ];

        // simple keyword matching
        foreach ($words as $w) {
            if (isset($typeMap[$w])) {
                $attributes[$w] = $typeMap[$w];
            }
        }

        if (empty($attributes)) {
            return $la->setStatus('error')->notify("Could not understand any specific fields from the prompt.", "error");
        }

        return $la->setData(['config' => ['attributes' => $attributes]]);
    }
}

if (!function_exists('live_scaffold_dashboard')) {
    function live_scaffold_dashboard($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $entityName = $params['entityName'] ?? '';
        if (!$entityName)
            return $la->setStatus('error')->notify("Entity name required.", "error");

        $lowerName = strtolower($entityName);
        $dashId = "dash_{$lowerName}";

        $jsContent = <<<JS
export default class Dashboard_{$entityName} extends BaseComponent {
    constructor(props) {
        super(props);
        this.state = { data: [], loading: true };
    }

    async onInit() {
        this.fetchData();
    }

    async fetchData() {
        // Assume API endpoint is enabled for this entity
        try {
            const res = await fetch('/api/v1/{$lowerName}');
            const json = await res.json();
            if (json.status === 'success') {
                this.setState({ data: json.data, loading: false });
            } else {
                this.setState({ loading: false });
                SPPUX.notify('Error fetching data: ' + json.message, 'error');
            }
        } catch(e) {
            this.setState({ loading: false });
        }
    }

    render() {
        if (this.state.loading) return SPPUX.html`<div style="padding: 2rem;">Loading Data...</div>`;
        if (!this.state.data.length) return SPPUX.html`<div style="padding: 2rem;">No {$entityName} records found.</div>`;
        
        // Basic dynamic table
        const keys = Object.keys(this.state.data[0]).filter(k => k !== 'id');
        keys.unshift('id'); // Ensure id is first
        
        return SPPUX.html`
            <div class="spp-card" style="padding: 2rem;">
                <h3>{$entityName} Dashboard</h3>
                <div style="overflow-x: auto; margin-top: 1rem;">
                    <table class="spp-table" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                \${keys.map(k => SPPUX.html`<th style="text-align:left; padding:0.5rem; border-bottom:1px solid var(--glass-border);">\${k}</th>`)}
                            </tr>
                        </thead>
                        <tbody>
                            \${this.state.data.map(row => SPPUX.html`
                                <tr>
                                    \${keys.map(k => SPPUX.html`<td style="padding:0.5rem; border-bottom:1px solid var(--glass-border);">\${row[k]}</td>`)}
                                </tr>
                            `)}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }
}
JS;

        // Save JS file
        $jsPath = __DIR__ . "/js/views/{$dashId}.js";
        file_put_contents($jsPath, $jsContent);

        // Update routes.json
        $routesFile = __DIR__ . "/routes.json";
        $routes = file_exists($routesFile) ? json_decode(file_get_contents($routesFile), true) : [];
        if (!$routes)
            $routes = [];

        $routes[$dashId] = [
            "title" => "{$entityName} Dash",
            "icon" => "📊",
            "component" => "views/{$dashId}.js"
        ];

        file_put_contents($routesFile, json_encode($routes, JSON_PRETTY_PRINT));

        return $la->notify("Dashboard scaffolded successfully! Please refresh the admin panel.", "success");
    }
}

if (!function_exists('live_preview_migration')) {
    function live_preview_migration($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $name = $params['name'] ?? '';
        $yamlContent = $params['yaml'] ?? '';
        if (!$name || !$yamlContent)
            return $la->setStatus('error')->notify("Entity name and YAML required.", "error");

        try {
            $config = \Symfony\Component\Yaml\Yaml::parse($yamlContent);
            $table = $config['table'] ?? '';
            if (!$table)
                return $la->setStatus('error')->notify("Table name not defined in YAML.", "error");

            $attributes = $config['attributes'] ?? [];
            $db = new \SPPMod\SPPDB\SPPDB();

            $sqlDiff = [];
            $idField = $config['id_field'] ?? 'id';

            if (!$db->tableExists($table)) {
                $sqlDiff[] = "CREATE TABLE {$table} ({$idField} varchar(20))";
                foreach ($attributes as $col => $type) {
                    $sqlDiff[] = "ALTER TABLE {$table} ADD {$col} {$type}";
                }
            } else {
                foreach ($attributes as $col => $type) {
                    if (!$db->columnExists($table, $col)) {
                        $sqlDiff[] = "ALTER TABLE {$table} ADD {$col} {$type}";
                    }
                }
            }

            return $la->setData(['sql' => $sqlDiff]);
        } catch (\Exception $e) {
            sendResponse(false, [], "Error parsing diff: " . $e->getMessage());
        }
    }
}

if (!function_exists('live_check_auth')) {
    function live_check_auth($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        if (\SPPMod\SPPAuth\SPPAuth::check()) {
            $user = \SPPMod\SPPAuth\SPPAuth::user();
            $userId = (string) \SPPMod\SPPAuth\SPPAuth::guard()->id();
            $username = $userId;
            if ($user) {
                $username = $user->username ?? $user->get('UserName') ?? $userId;
            }
            return $la->setData(['username' => $username, 'user_id' => $userId])->notify("Authenticated.", "success");
        } else {
            return $la->setStatus('error')->notify("Please Authenticate yourself.", "error");
        }
    }
}

if (!function_exists('live_get_profile')) {
    function live_get_profile($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        if (\SPPMod\SPPAuth\SPPAuth::check()) {
            $user = \SPPMod\SPPAuth\SPPAuth::user();
            sendResponse(true, $user->getValues(), "Profile retrieved.");
        }
        return $la->setStatus('error')->notify("Profile not found.", "error");
    }
}

if (!function_exists('live_get_config_all')) {
    function live_get_config_all($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $global = getGlobalSettings();
        $app = [];
        $sys = [
            'spp_version' => '1.1.0',
            'base_path' => SPP_BASE_DIR
        ];
        return $la->setData(['config' => ['global' => $global, 'app' => $app, 'sys' => $sys]])->notify("Config retrieved.", "success");
    }
}

if (!function_exists('live_get_global_settings')) {
    function live_get_global_settings($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $settings = getGlobalSettings();
        $raw = file_get_contents((defined('SPP_ETC_DIR') ? SPP_ETC_DIR : SPP_BASE_DIR . '/etc') . '/global-settings.yml');
        return $la->setData(['parsed' => $settings, 'raw' => $raw]);
    }
}

if (!function_exists('live_save_global_settings')) {
    function live_save_global_settings($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $mode = $params['mode'] ?? 'form';
        if ($mode === 'yaml') {
            $yaml = $params['yaml'] ?? '';
            try {
                $parsed = \Symfony\Component\Yaml\Yaml::parse($yaml);
                saveGlobalSettings($parsed);
                return $la->notify("Global settings saved via YAML.", "success");
            } catch (\Exception $e) {
                return $la->setStatus('error')->notify("YAML Parse Error: " . $e->getMessage());
            }
        } else {
            $data = $params['data'] ?? '';
            $parsed = json_decode($data, true);
            if ($parsed) {
                saveGlobalSettings($parsed);
                return $la->setStatus('true' === 'true' ? 'success' : 'error')->notify("Global settings saved.", "error");
            } else {
                return $la->setStatus('error')->notify("Invalid JSON data.", "error");
            }
        }
    }
}

if (!function_exists('live_save_config_value')) {
    function live_save_config_value($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $key = $params['key'] ?? '';
        $value = $params['value'] ?? '';
        if (!$key)
            return $la->setStatus('error')->notify("Missing key.", "error");

        $parts = explode(':', $key, 2);
        $ns = $parts[0];
        $actualKey = $parts[1] ?? '';

        if ($ns === 'global' && $actualKey) {
            $settings = getGlobalSettings();
            $settings[$actualKey] = ($value === 'true') ? true : (($value === 'false') ? false : $value);
            saveGlobalSettings($settings);
            return $la->notify("Global setting updated.", "success");
        } else {
            return $la->setStatus('error')->notify("Editing namespace '{$ns}' is restricted or key is invalid.", "error");
        }
    }
}

if (!function_exists('live_list_commands')) {
    function live_list_commands($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $coreDir = SPP_BASE_DIR . '/core';
        foreach (['class.command.php', 'class.commandmanager.php'] as $f) {
            if (file_exists($coreDir . '/' . $f))
                require_once $coreDir . '/' . $f;
        }

        $commands = \SPP\CLI\CommandManager::discover();

        if ($action === 'list_commands') {
            $list = [];
            foreach ($commands as $name => $cmd) {
                $prefix = explode(':', $name)[0] ?? 'core';
                if (!isset($list[$prefix]))
                    $list[$prefix] = [];
                $list[$prefix][] = [
                    'name' => $name,
                    'description' => $cmd->getDescription()
                ];
            }
            return $la->setData(['categories' => $list])->notify("Commands retrieved", "success");
        }

        if ($action === 'get_command_ui') {
            $cmdName = $params['command'] ?? '';
            $cmd = $commands[$cmdName] ?? null;
            if (!$cmd)
                return $la->setStatus('error')->notify("Command not found.", "error");
            return $la->setData(['html' => $cmd->renderAdminUI()])->notify("UI retrieved", "success");
        }

        if ($action === 'execute_command') {
            $cmdName = $params['command'] ?? '';
            $argsRaw = $params['args'] ?? '';

            $cmd = $commands[$cmdName] ?? null;
            if (!$cmd)
                return $la->setStatus('error')->notify("Command not found.", "error");

            $sppBin = escapeshellarg(dirname(SPP_BASE_DIR) . '/spp.php');
            $cmdSafe = escapeshellarg($cmdName);

            $argString = '';
            if (!empty($argsRaw)) {
                $segments = explode(' ', $argsRaw);
                $safeSegments = array_map('escapeshellarg', array_filter($segments));
                $argString = implode(' ', $safeSegments);
            }

            $execCmd = "php {$sppBin} {$cmdSafe} {$argString} 2>&1";
            $output = shell_exec($execCmd);

            return $la->setData(['output' => $output])->notify("Command executed", "success");
        }
    }
}

if (!function_exists('live_list_commands')) {
    function live_list_commands($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $list = [];
        foreach ($commands as $name => $cmd) {
            $prefix = explode(':', $name)[0] ?? 'core';
            if (!isset($list[$prefix]))
                $list[$prefix] = [];
            $list[$prefix][] = [
                'name' => $name,
                'description' => $cmd->getDescription()
            ];
        }
        return $la->setData(['categories' => $list])->notify("Commands retrieved", "success");
    }
}

if (!function_exists('live_get_command_ui')) {
    function live_get_command_ui($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $cmdName = $params['command'] ?? '';
        $cmd = $commands[$cmdName] ?? null;
        if (!$cmd)
            return $la->setStatus('error')->notify("Command not found.", "error");
        return $la->setData(['html' => $cmd->renderAdminUI()])->notify("UI retrieved", "success");
    }
}

if (!function_exists('live_execute_command')) {
    function live_execute_command($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $cmdName = $params['command'] ?? '';
        $argsRaw = $params['args'] ?? '';

        $cmd = $commands[$cmdName] ?? null;
        if (!$cmd)
            return $la->setStatus('error')->notify("Command not found.", "error");

        $sppBin = escapeshellarg(dirname(SPP_BASE_DIR) . '/spp.php');
        $cmdSafe = escapeshellarg($cmdName);

        $argString = '';
        if (!empty($argsRaw)) {
            $segments = explode(' ', $argsRaw);
            $safeSegments = array_map('escapeshellarg', array_filter($segments));
            $argString = implode(' ', $safeSegments);
        }

        $execCmd = "php {$sppBin} {$cmdSafe} {$argString} 2>&1";
        $output = shell_exec($execCmd);

        return $la->setData(['output' => $output])->notify("Command executed", "success");
    }
}

if (!function_exists('live_get_builder_context')) {
    function live_get_builder_context($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $savePath = "src/{$appContext}/entities";
        $classes = ['\\SPPMod\\SPPDB\\SPPEntity', '\\SPPMod\\SPPAuth\\SPPUser'];

        // Scan current app entities
        $entDir = SPP_APP_DIR . "/src/{$appContext}/entities";
        if (is_dir($entDir)) {
            foreach (glob($entDir . '/*.php') as $file) {
                $basename = basename($file);
                if (str_starts_with($basename, 'entity.')) {
                    $name = substr($basename, 7, -4);
                } else {
                    $name = substr($basename, 0, -4);
                }
                $content = file_get_contents($file);
                if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
                    $ns = trim($matches[1]);
                    $classes[] = '\\' . $ns . '\\' . $name;
                }
            }
        }

        // Scan modules for potential entity bases
        $modDir = defined('SPP_MOD_DIR') ? SPP_MOD_DIR : SPP_BASE_DIR . '/modules';
        if (is_dir($modDir)) {
            $rit = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($modDir));
            foreach ($rit as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $content = file_get_contents($file->getPathname());
                    if (str_contains($content, 'extends SPPEntity') || str_contains($content, 'extends \\SPPMod\\SPPDB\\SPPEntity')) {
                        if (preg_match('/namespace\s+([^;]+);/', $content, $nsMatches) && preg_match('/class\s+([a-zA-Z0-9_]+)/', $content, $clsMatches)) {
                            $classes[] = '\\' . trim($nsMatches[1]) . '\\' . trim($clsMatches[1]);
                        }
                    }
                }
            }
        }

        $classes = array_values(array_unique($classes));
        sort($classes);
        return $la->setData(['save_path' => $savePath, 'classes' => $classes]);
    }
}

if (!function_exists('live_list_entities')) {
    function live_list_entities($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $entDir = SPP_APP_DIR . "/src/{$appContext}/entities";
        $map = [];
        if (is_dir($entDir)) {
            foreach (glob($entDir . '/*.{php,yml,yaml}', GLOB_BRACE) as $file) {
                $basename = basename($file);
                $isPhp = str_ends_with($file, '.php');

                if ($isPhp) {
                    if (str_starts_with($basename, 'entity.')) {
                        $name = substr($basename, 7, -4);
                    } else {
                        $name = substr($basename, 0, -4);
                    }
                } else {
                    $name = pathinfo($file, PATHINFO_FILENAME);
                }

                $key = strtolower($name);
                if (!isset($map[$key])) {
                    $map[$key] = [
                        'name' => $name,
                        'yaml_path' => null,
                        'php_path' => null,
                        'yaml_content' => '',
                        'php_content' => '',
                        'size' => 0
                    ];
                }

                $content = '';
                if (filesize($file) < 500 * 1024) {
                    $content = file_get_contents($file);
                }
                $map[$key]['size'] += filesize($file);

                if ($isPhp) {
                    $map[$key]['php_path'] = relativizePath($file);
                    $map[$key]['php_content'] = $content;
                } else {
                    $map[$key]['name'] = pathinfo($file, PATHINFO_FILENAME); // Keep original case from YAML
                    $map[$key]['yaml_path'] = relativizePath($file);
                    $map[$key]['yaml_content'] = $content;
                }
            }

            // Generate YAML content for pure PHP entities
            foreach ($map as $key => &$entity) {
                if (empty($entity['yaml_content']) && !empty($entity['php_content'])) {
                    $className = "App\\" . ucfirst($appContext) . "\\Entities\\" . ucfirst($entity['name']);
                    if (!class_exists($className)) {
                        require_once SPP_APP_DIR . '/' . $entity['php_path'];
                    }
                    if (class_exists($className)) {
                        try {
                            $instance = new $className();
                            $config = [
                                'table' => method_exists($instance, 'getTable') ? $instance->getTable() : (strtolower($entity['name']) . 's'),
                                'attributes' => method_exists($instance, 'define_attributes') ? $instance->define_attributes() : []
                            ];
                            $ref = new \ReflectionClass($className);
                            $parent = $ref->getParentClass();
                            if ($parent && $parent->getName() !== 'SPPMod\SPPEntity\SPPEntity') {
                                $config['extends'] = '\\' . $parent->getName();
                            }
                            if (class_exists('\Symfony\Component\Yaml\Yaml')) {
                                $entity['yaml_content'] = \Symfony\Component\Yaml\Yaml::dump($config, 4, 2);
                            } else {
                                $yaml = "table: " . $config['table'] . "\n";
                                if (!empty($config['extends']))
                                    $yaml .= "extends: " . $config['extends'] . "\n";
                                $yaml .= "attributes:\n";
                                if (is_array($config['attributes'])) {
                                    foreach ($config['attributes'] as $k => $v) {
                                        $yaml .= "  $k: $v\n";
                                    }
                                }
                                $entity['yaml_content'] = $yaml;
                            }
                        } catch (\Exception $e) {
                            // Silently ignore instantiation errors and provide empty yaml
                            $entity['yaml_content'] = "table: " . (strtolower($entity['name']) . 's') . "\nattributes: []\n";
                        }
                    }
                }
            }
        }
        $entities = array_values($map);
        return $la->setData(['entities' => $entities])->notify("Entities listed.", "success");
    }
}

if (!function_exists('live_parse_entity_yaml')) {
    function live_parse_entity_yaml($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $yml = $params['yaml'] ?? '';
        try {
            $parsed = \Symfony\Component\Yaml\Yaml::parse($yml);
            return $la->setData(['config' => $parsed])->notify("Parsed successfully.", "success");
        } catch (\Exception $e) {
            sendResponse(false, [], "YAML Parse Error: " . $e->getMessage());
        }
    }
}

if (!function_exists('live_dump_entity_yaml')) {
    function live_dump_entity_yaml($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $config = $params['config'] ?? [];
        if (is_string($config))
            $config = json_decode($config, true);
        try {
            $yml = \Symfony\Component\Yaml\Yaml::dump($config, 10, 2);
            return $la->setData(['yaml' => $yml])->notify("Dumped successfully.", "success");
        } catch (\Exception $e) {
            sendResponse(false, [], "YAML Dump Error: " . $e->getMessage());
        }
    }
}

if (!function_exists('live_introspect_table')) {
    function live_introspect_table($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $table = $params['table'] ?? '';
        if (!$table)
            return $la->setStatus('error')->notify("Table name required.", "error");
        try {
            $db = new \SPPMod\SPPDB\SPPDB();
            $driver = strtolower($db->getDriver());
            $attributes = [];

            if ($driver === 'mongodb') {
                $res = $db->query("SELECT * FROM {$table} LIMIT 1");
                if (empty($res))
                    return $la->setStatus('error')->notify("Collection is empty, cannot infer schema.", "error");
                $doc = (array) $res[0];
                foreach ($doc as $k => $v) {
                    if ($k === '_id')
                        continue;
                    $type = is_int($v) ? 'int' : (is_float($v) ? 'float' : (is_bool($v) ? 'boolean' : 'varchar(255)'));
                    $attributes[$k] = $type;
                }
            } elseif ($driver === 'xdb' || $driver === 'sppxdb') {
                try {
                    $schema = $db->getSchema($table);
                    if ($schema && isset($schema['columns'])) {
                        foreach ($schema['columns'] as $col => $meta) {
                            $attributes[$col] = 'varchar(255)';
                        }
                    }
                } catch (\Exception $e) {
                    $res = $db->query("SELECT * FROM {$table} LIMIT 1");
                    if (!empty($res)) {
                        foreach (array_keys((array) $res[0]) as $k) {
                            if ($k === '_id')
                                continue;
                            $attributes[$k] = 'varchar(255)';
                        }
                    } else {
                        return $la->setStatus('error')->notify("Cannot infer schema from empty XDB table.", "error");
                    }
                }
            } else {
                $schema = $db->getSchema($table);
                foreach ($schema['columns'] as $col => $meta) {
                    $t = strtolower($meta['type']);
                    if (strpos($t, 'int') !== false)
                        $attributes[$col] = 'int';
                    elseif (strpos($t, 'datetime') !== false || strpos($t, 'timestamp') !== false)
                        $attributes[$col] = 'datetime';
                    elseif (strpos($t, 'date') !== false)
                        $attributes[$col] = 'date';
                    elseif (strpos($t, 'text') !== false)
                        $attributes[$col] = 'text';
                    elseif (strpos($t, 'tinyint(1)') !== false || strpos($t, 'bool') !== false)
                        $attributes[$col] = 'boolean';
                    else
                        $attributes[$col] = 'varchar(255)';
                }
            }

            $config = [
                'table' => $table,
                'attributes' => $attributes
            ];
            return $la->setData(['config' => $config])->notify("Introspected table {$table}", "success");
        } catch (\Exception $e) {
            sendResponse(false, [], "Introspection Error: " . $e->getMessage());
        }
    }
}

if (!function_exists('live_scaffold_form')) {
    function live_scaffold_form($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $entityName = $params['entityName'] ?? '';
        $config = $params['config'] ?? [];
        if (is_string($config))
            $config = json_decode($config, true);
        if (!$entityName || empty($config['attributes']))
            return $la->setStatus('error')->notify("Entity configuration required.", "error");

        try {
            $fields = [];
            foreach ($config['attributes'] as $col => $type) {
                if ($col === 'id' || $col === 'created_at' || $col === 'updated_at')
                    continue;
                $fieldType = 'text';
                if ($type === 'int' || strpos($type, 'int') !== false)
                    $fieldType = 'number';
                elseif ($type === 'datetime' || $type === 'date')
                    $fieldType = 'date';
                elseif ($type === 'text')
                    $fieldType = 'textarea';
                elseif ($type === 'boolean')
                    $fieldType = 'checkbox';

                $fields[] = [
                    'name' => $col,
                    'type' => $fieldType,
                    'label' => ucwords(str_replace('_', ' ', $col))
                ];
            }

            $formConfig = [
                'form' => [
                    'name' => strtolower($entityName) . '_form',
                    'type' => 'single'
                ],
                'fields' => $fields
            ];
            return $la->setData(['formConfig' => $formConfig])->notify("Form scaffolded successfully.", "success");
        } catch (\Exception $e) {
            sendResponse(false, [], "Scaffolding Error: " . $e->getMessage());
        }
    }
}

if (!function_exists('live_seed_entity')) {
    function live_seed_entity($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $entityName = $params['entityName'] ?? '';
        $count = (int) ($params['count'] ?? 50);
        if (!$entityName)
            return $la->setStatus('error')->notify("Entity name required.", "error");
        try {
            $class = "\\SPPMod\\SPPEntity\\{$entityName}";
            if (!class_exists($class)) {
                $file = SPP_BASE_DIR . "/modules/spp/sppdb/entities/class." . strtolower($entityName) . ".php";
                if (file_exists($file))
                    require_once $file;
            }
            if (!class_exists($class))
                return $la->setStatus('error')->notify("Entity class not found.", "error");

            $inst = new $class();
            $attrs = $inst->define_attributes();

            $db = new \SPPMod\SPPDB\SPPDB();
            $table = $inst::getTable();

            $inserted = 0;
            for ($i = 0; $i < $count; $i++) {
                $row = [];
                foreach ($attrs as $col => $type) {
                    if ($col === 'id')
                        continue;
                    if ($type === 'int')
                        $row[$col] = rand(1, 1000);
                    elseif ($type === 'datetime')
                        $row[$col] = date('Y-m-d H:i:s', time() - rand(0, 31536000));
                    elseif ($type === 'boolean')
                        $row[$col] = rand(0, 1);
                    elseif ($type === 'text')
                        $row[$col] = "Mock text for {$col} " . rand(1000, 9999);
                    else
                        $row[$col] = "Mock {$col} " . rand(1, 100);
                }
                if ($db->insertValues($table, $row)) {
                    $inserted++;
                }
            }
            return $la->setData(['inserted' => $inserted])->notify("Seeded {$inserted} records.", "success");
        } catch (\Exception $e) {
            sendResponse(false, [], "Seed Error: " . $e->getMessage());
        }
    }
}

if (!function_exists('live_save_entity_config')) {
    function live_save_entity_config($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $name = $params['name'] ?? '';
        $config = $params['config'] ?? [];
        if (is_string($config))
            $config = json_decode($config, true);
        if (!$name)
            return $la->setStatus('error')->notify("Entity name required.", "error");

        try {
            if (!class_exists('\SPPMod\SPPDB\SPPEntity')) {
                require_once SPP_BASE_DIR . '/sppinit.php';
            }
            createEntityRevision($appContext, $name);

            if (!empty($config['extends'])) {
                $extendsClass = ltrim($config['extends'], '\\');
                if ($extendsClass !== 'SPPMod\SPPEntity\SPPEntity') {
                    if (!class_exists($extendsClass) || !is_subclass_of($extendsClass, '\SPPMod\SPPDB\SPPEntity')) {
                        return $la->setStatus('error')->notify("Error: Extended class '{$config['extends']}' does not exist or does not extend SPPEntity.", "error");
                    }
                }
            }

            \SPPMod\SPPDB\SPPEntity::saveEntityDefinition($name, $appContext, $config);
            return $la->notify("Entity saved.", "success");
        } catch (\Exception $e) {
            sendResponse(false, [], "Save error: " . $e->getMessage());
        }
    }
}

if (!function_exists('live_save_entity_source')) {
    function live_save_entity_source($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $name = $params['name'] ?? '';
        $source = $params['source'] ?? '';
        $type = $params['type'] ?? 'php';
        if (!$name)
            return $la->setStatus('error')->notify("Entity name required.", "error");

        try {
            $srcDir = SPP_APP_DIR . "/src/{$appContext}/entities";
            if (!is_dir($srcDir)) {
                mkdir($srcDir, 0777, true);
            }

            createEntityRevision($appContext, $name);

            if ($type === 'yaml') {
                $fileName = strtolower($name) . ".yml";
                $filePath = $srcDir . '/' . $fileName;
                file_put_contents($filePath, $source);

                // Generate corresponding PHP if it doesn't exist
                $phpFileName = "entity." . strtolower($name) . ".php";
                $phpPath = $srcDir . '/' . $phpFileName;
                if (!file_exists($phpPath)) {
                    if (!class_exists('\SPPMod\SPPDB\SPPEntity')) {
                        require_once SPP_BASE_DIR . '/sppinit.php';
                    }
                    $config = \Symfony\Component\Yaml\Yaml::parse($source);
                    \SPPMod\SPPDB\SPPEntity::saveEntityDefinition($name, $appContext, $config);
                }

            } else {
                $fileName = "entity." . strtolower($name) . ".php";
                $filePath = $srcDir . '/' . $fileName;

                // If the old format (just class name) exists, overwrite it instead
                if (file_exists($srcDir . '/' . $name . '.php') && !file_exists($filePath)) {
                    $filePath = $srcDir . '/' . $name . '.php';
                }

                file_put_contents($filePath, $source);

                // Generate corresponding YAML
                $ymlFileName = strtolower($name) . ".yml";
                $ymlPath = $srcDir . '/' . $ymlFileName;

                $className = "App\\" . ucfirst($appContext) . "\\Entities\\" . ucfirst($name);
                require_once $filePath;
                if (class_exists($className)) {
                    try {
                        $instance = new $className();
                        $config = [
                            'table' => method_exists($instance, 'getTable') ? $instance->getTable() : (strtolower($name) . 's'),
                            'attributes' => method_exists($instance, 'define_attributes') ? $instance->define_attributes() : []
                        ];
                        $ref = new \ReflectionClass($className);
                        $parent = $ref->getParentClass();
                        if ($parent && $parent->getName() !== 'SPPMod\SPPEntity\SPPEntity') {
                            $config['extends'] = '\\' . $parent->getName();
                        }
                        if (class_exists('\Symfony\Component\Yaml\Yaml')) {
                            $yaml = \Symfony\Component\Yaml\Yaml::dump($config, 4, 2);
                            file_put_contents($ymlPath, $yaml);
                        }
                    } catch (\Exception $e) {
                    }
                }
            }

            return $la->notify("Entity {$type} source saved and synced.", "success");
        } catch (\Exception $e) {
            sendResponse(false, [], "Save error: " . $e->getMessage());
        }
    }
}

if (!function_exists('live_delete_entity')) {
    function live_delete_entity($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $name = $params['name'] ?? '';
        if (!$name)
            return $la->setStatus('error')->notify("Entity name required.", "error");

        $pathYml = SPP_APP_DIR . "/src/{$appContext}/entities/{$name}.yml";
        $pathPhp = SPP_APP_DIR . "/src/{$appContext}/entities/{$name}.php";
        if (file_exists($pathYml))
            unlink($pathYml);
        if (file_exists($pathPhp))
            unlink($pathPhp);

        return $la->notify("Entity deleted.", "success");
    }
}

if (!function_exists('live_list_forms')) {
    function live_list_forms($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $forms = [];
        $formDir = SPP_APP_DIR . "/etc/apps/{$appContext}/forms";
        if (is_dir($formDir)) {
            foreach (glob($formDir . '/*.{yml,yaml}', GLOB_BRACE) as $file) {
                $forms[] = [
                    'name' => pathinfo($file, PATHINFO_FILENAME),
                    'path' => relativizePath($file),
                    'size' => filesize($file)
                ];
            }
        }
        return $la->setData(['forms' => $forms])->notify("Forms listed.", "success");
    }
}

if (!function_exists('live_parse_form_yaml')) {
    function live_parse_form_yaml($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $yml = $params['yaml'] ?? '';
        try {
            $parsed = \Symfony\Component\Yaml\Yaml::parse($yml);
            return $la->setData(['config' => $parsed])->notify("Parsed successfully.", "success");
        } catch (\Exception $e) {
            sendResponse(false, [], "YAML Parse Error: " . $e->getMessage());
        }
    }
}

if (!function_exists('live_dump_form_yaml')) {
    function live_dump_form_yaml($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $config = $params['config'] ?? [];
        if (is_string($config))
            $config = json_decode($config, true);
        try {
            $yml = \Symfony\Component\Yaml\Yaml::dump($config, 10, 2);
            return $la->setData(['yaml' => $yml])->notify("Dumped successfully.", "success");
        } catch (\Exception $e) {
            sendResponse(false, [], "YAML Dump Error: " . $e->getMessage());
        }
    }
}

if (!function_exists('live_save_form_config')) {
    function live_save_form_config($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $name = $params['name'] ?? '';
        $yaml = $params['yaml'] ?? '';
        if (!$name)
            return $la->setStatus('error')->notify("Form name required.", "error");

        $path = SPP_APP_DIR . "/etc/apps/{$appContext}/forms/{$name}.yml";
        if (!is_dir(dirname($path)))
            mkdir(dirname($path), 0777, true);
        try {
            file_put_contents($path, $yaml);
            return $la->notify("Form saved.", "success");
        } catch (\Exception $e) {
            sendResponse(false, [], "Save Error: " . $e->getMessage());
        }
    }
}

if (!function_exists('live_delete_form')) {
    function live_delete_form($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $name = $params['name'] ?? '';
        if (!$name)
            return $la->setStatus('error')->notify("Form name required.", "error");

        $path = SPP_APP_DIR . "/etc/apps/{$appContext}/forms/{$name}.yml";
        if (file_exists($path))
            unlink($path);

        return $la->notify("Form deleted.", "success");
    }
}

if (!function_exists('live_get_form_html')) {
    function live_get_form_html($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $yaml = $params['yaml'] ?? '';
        try {
            $config = \Symfony\Component\Yaml\Yaml::parse($yaml);
            $html = "<div class='spp-form-preview-wrapper'>";
            if (isset($config['fields']) && is_array($config['fields'])) {
                foreach ($config['fields'] as $key => $f) {
                    $html .= "<div class='spp-form-group' style='margin-bottom:15px;'>";
                    $html .= "<label style='display:block;margin-bottom:5px;font-weight:600;'>" . htmlspecialchars($f['label'] ?? $key) . "</label>";
                    $html .= "<input class='spp-form-control' style='width:100%;padding:8px;border-radius:4px;border:1px solid #ccc;' placeholder='" . htmlspecialchars($f['placeholder'] ?? '') . "'>";
                    $html .= "</div>";
                }
            }
            $html .= "<button class='btn btn-primary' style='padding:8px 16px;'>" . htmlspecialchars($config['submit_label'] ?? 'Submit') . "</button>";
            $html .= "</div>";
            return $la->setData(['html' => $html])->notify("Form rendered.", "success");
        } catch (\Exception $e) {
            sendResponse(false, [], "Render Error: " . $e->getMessage());
        }
    }
}

if (!function_exists('live_save_app_config')) {
    function live_save_app_config($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $targetApp = $params['target_app'] ?? $appContext;
        $config = $params['config'] ?? [];
        if (is_string($config))
            $config = json_decode($config, true);
        $appConfigFile = SPP_APP_DIR . "/etc/apps/{$targetApp}/config.yml";
        try {
            if (!is_dir(dirname($appConfigFile)))
                mkdir(dirname($appConfigFile), 0777, true);
            file_put_contents($appConfigFile, \Symfony\Component\Yaml\Yaml::dump($config, 10, 2));
            return $la->notify("App config saved.", "success");
        } catch (\Exception $e) {
            sendResponse(false, [], "Save Error: " . $e->getMessage());
        }
    }
}

if (!function_exists('live_list_modules')) {
    function live_list_modules($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $modules = [];
        try {
            $modulesFile = defined('SPP_ETC_DIR') ? SPP_ETC_DIR . '/modules.yml' : SPP_BASE_DIR . '/etc/modules.yml';
            $activeConfig = file_exists($modulesFile) ? \Symfony\Component\Yaml\Yaml::parseFile($modulesFile) : [];
            if (!is_array($activeConfig))
                $activeConfig = [];

            $activeMap = [];
            if (isset($activeConfig['modules']) && is_array($activeConfig['modules'])) {
                foreach ($activeConfig['modules'] as $mc) {
                    if (isset($mc['name']) && isset($mc['status']) && $mc['status'] === 'active') {
                        $activeMap[$mc['name']] = true;
                    }
                }
            }

            $manifests = \SPP\Module::scanModules();
            foreach ($manifests as $manifestPath) {
                try {
                    $mod = new \SPP\Module($manifestPath);
                    $modName = $mod->InternalName;
                    $modules[] = [
                        'name' => $modName,
                        'public_name' => $mod->PublicName ?? $modName,
                        'type' => $mod->ModuleType ?? 'user',
                        'active' => isset($activeMap[$modName]),
                        'version' => $mod->Version ?? '1.0.0',
                        'path' => $manifestPath,
                        'module_category' => $mod->ModuleCategory ?? 'App Modules',
                        'description' => $mod->PublicDesc ?? '',
                        'dependencies' => $mod->Dependencies ?? [],
                        'has_config' => !empty($mod->ConfigFile) || !empty($mod->ConfigVariables) || !empty($mod->Settings)
                    ];
                } catch (\Exception $e) {
                }
            }
            return $la->setData(['modules' => $modules])->notify("Modules listed.", "success");
        } catch (\Exception $e) {
            sendResponse(false, [], "Error listing modules: " . $e->getMessage());
        }
    }
}

if (!function_exists('live_toggle_module')) {
    function live_toggle_module($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $modname = $params['modname'] ?? '';
        $status = $params['status'] ?? 'inactive';

        $modulesFile = defined('SPP_ETC_DIR') ? SPP_ETC_DIR . '/modules.yml' : SPP_BASE_DIR . '/etc/modules.yml';
        $config = file_exists($modulesFile) ? \Symfony\Component\Yaml\Yaml::parseFile($modulesFile) : [];
        if (!is_array($config))
            $config = [];

        if (!isset($config['modules']) || !is_array($config['modules'])) {
            $config['modules'] = [];
        }

        $found = false;
        foreach ($config['modules'] as &$mc) {
            if (isset($mc['name']) && $mc['name'] === $modname) {
                $mc['status'] = $status;
                $found = true;
                break;
            }
        }

        if (!$found) {
            $path = 'spp/' . $modname;
            $manifests = \SPP\Module::scanModules();
            foreach ($manifests as $mPath) {
                if (basename(dirname($mPath)) === $modname) {
                    $path = str_replace(SPP_BASE_DIR . DIRECTORY_SEPARATOR, '', dirname($mPath));
                    $path = str_replace('\\', '/', $path);
                    break;
                }
            }
            $config['modules'][] = [
                'name' => $modname,
                'path' => $path,
                'status' => $status
            ];
        }

        try {
            if (!is_dir(dirname($modulesFile)))
                mkdir(dirname($modulesFile), 0777, true);
            file_put_contents($modulesFile, \Symfony\Component\Yaml\Yaml::dump($config, 4, 2));
            return $la->notify("Module {$modname} status updated.", "success");
        } catch (\Exception $e) {
            sendResponse(false, [], "Toggle Error: " . $e->getMessage());
        }
    }
}

if (!function_exists('live_execute_scaffold')) {
    function live_execute_scaffold($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $command = $params['command'] ?? '';
        $target = $params['target'] ?? '';
        $optionsRaw = $params['options'] ?? '';

        if (!$command || !$target) {
            return $la->setStatus('error')->notify("Command and Target are required.", "error");
        }

        $allowedCommands = [
            'make:app',
            'make:module',
            'make:entity',
            'make:controller',
            'make:scaffold',
            'make:service',
            'make:blade',
            'make:twig',
            'make:sppview',
            'make:mixed-paradigm',
            'make:ux'
        ];

        if (!in_array($command, $allowedCommands)) {
            return $la->setStatus('error')->notify("Invalid or unauthorized scaffold command.", "error");
        }

        $optionsStr = '';
        if ($optionsRaw) {
            // Very simple sanitization: only allow alphanumeric, dashes, equals, spaces, and commas
            if (preg_match('/^[a-zA-Z0-9_=\-\s,:]+$/', $optionsRaw)) {
                $parts = preg_split('/\s+/', trim($optionsRaw));
                $safeParts = array_map('escapeshellarg', $parts);
                $optionsStr = " " . implode(" ", $safeParts);
            } else {
                return $la->setStatus('error')->notify("Invalid characters in options. Only alphanumeric, -, _, =, :, commas, and spaces allowed.", "error");
            }
        }

        try {
            $cmdLine = "php " . escapeshellarg(SPP_BASE_DIR . '/spp.php') . " " . escapeshellarg($command) . " " . escapeshellarg($target) . $optionsStr . " 2>&1";
            $output = shell_exec($cmdLine);
            return $la->setData(['output' => $output])->notify("Command executed successfully.", "success");
        } catch (\Exception $e) {
            return $la->setStatus('error')->setData(['output' => $e->getMessage()])->notify("Command execution failed.", "error");
        }
    }
}

if (!function_exists('live_get_di_bindings')) {
    function live_get_di_bindings($la, $params)
    {
        $appContext = $params['appname'] ?? 'default';
        $bindings = [];
        try {
            $procObj = \SPP\Scheduler::getProcObj();
            $ref = new \ReflectionClass($procObj);

            // Try to find how services are stored. usually in $services or something similar in SPP\App
            if ($ref->hasProperty('services')) {
                $prop = $ref->getProperty('services');
                $prop->setAccessible(true);
                $services = $prop->getValue($procObj) ?? [];

                foreach ($services as $key => $inst) {
                    $bindings[] = [
                        'abstract' => $key,
                        'concrete' => is_object($inst) ? get_class($inst) : (is_string($inst) ? $inst : 'unknown'),
                        'shared' => true,
                        'instantiated' => is_object($inst)
                    ];
                }
            } else if (method_exists($procObj, 'getServices')) {
                $services = $procObj->getServices();
                foreach ($services as $key => $inst) {
                    $bindings[] = [
                        'abstract' => $key,
                        'concrete' => is_object($inst) ? get_class($inst) : (is_string($inst) ? $inst : 'unknown'),
                        'shared' => true,
                        'instantiated' => is_object($inst)
                    ];
                }
            }
            return $la->setData(['bindings' => $bindings])->notify("Bindings retrieved.", "success");
        } catch (\Exception $e) {
            sendResponse(false, [], "DI Error: " . $e->getMessage());
        }
    }
}

