<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

if (!function_exists(__NAMESPACE__ . '\relativizePath')) {
    function relativizePath($path)
    {
        if (empty($path)) return '';
        $path = normalizePath($path);
        $root = normalizePath(SPP_APP_DIR);
        return str_starts_with($path, $root) ? ltrim(substr($path, strlen($root)), '/') : $path;
    }
}

class AdminLegacyCommand extends Command
{
    protected string $name = 'admin:legacy';
    protected string $description = 'Manage Admin Legacy operations. Usage: admin:legacy <action> [--payload=...] [--json]';

    public function isHidden(): bool { return true; }

    public function execute(array $args): void
    {
        $action = $this->getArgument($args, 0) ?? 'default';
        $payloadRaw = $this->getOption($args, 'payload', '{}');
        $payload = json_decode($payloadRaw, true) ?: [];

        $methodName = 'handle' . str_replace(' ', '', ucwords(str_replace('_', ' ', $action)));
        if (method_exists($this, $methodName)) {
            $this->$methodName($payload, $args);
        } else {
            $this->json(['success' => false, 'error' => "Unknown action: $action"], $args);
        }
    }

    private function handleListRevisions(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
        $name = $payload['name'] ?? '';
        if (!$name)
            $this->json(['success' => false, 'error' => "Entity name required.", "error"], $args); return;
        return;

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
        $this->json(['revisions' => $revisions], $args); return;
        return;
    
    }

    private function handleAiParseScaffold(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
        $prompt = strtolower($payload['prompt'] ?? '');
        if (!$prompt)
            $this->json(['success' => false, 'error' => "Prompt required.", "error"], $args); return;
        return;

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
            $this->json(['success' => false, 'error' => "Could not understand the requested command from prompt.", "error"], $args); return;
        return;
        }

        $this->notify("Parsed successfully.", "success", $args);
        $this->json(['commands' => $commands]); return;
        return;

    
    }

    private function handleCloneApp(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
        $source = $payload['source'] ?? '';
        $target = $payload['target'] ?? '';
        if (!$source || !$target)
            $this->json(['success' => false, 'error' => "Source and target required.", "error"], $args); return;
        return;

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
                    $this->json(['success' => true, 'message' => "App cloned successfully.", "success"], $args); return;
        return;
                }
            } catch (\Exception $e) {
                $this->json(['success' => false, 'error' => "Failed to clone app: " . $e->getMessage(), "error"], $args); return;
        return;
            }
        }
        $this->json(['success' => false, 'error' => "Source app not found.", "error"], $args); return;
        return;
    
    }

    private function handleScaffoldTemplate(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
        $template = $payload['template'] ?? '';
        if (!$template)
            $this->json(['success' => false, 'error' => "Template name required.", "error"], $args); return;
        return;

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
                $this->json(['success' => true, 'message' => "Template scaffolded successfully.", "success"], $args); return;
        return;
            } catch (\Exception $e) {
                $this->json(['success' => false, 'error' => "Failed to update settings: " . $e->getMessage(), "error"], $args); return;
        return;
            }
        }
        $this->json(['success' => false, 'error' => "Failed to scaffold template. Configuration file not found.", "error"], $args); return;
        return;
    
    }

    private function handleTailLogs(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
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

        $this->json(['lines' => $lines], $args); return;
        return;
    
    }

    private function handleExportAppPackage(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
        $appName = $payload['app'] ?? '';
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

    private function handleGenerateDocker(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
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

        $this->json(['success' => true, 'message' => "Docker deployment files generated.", "success"], $args); return;
        return;
    
    }

    private function handleCompileWorkflow(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
        $trigger = $payload['trigger'] ?? 'after_save';
        $task = $payload['task'] ?? 'log';

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
        $this->json(['code' => "\n" . $code . "\n"], $args); return;
        return;
    
    }

    private function handleGenerateSdk(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
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

        $this->json(['success' => true, 'message' => "SDK generated.", "success"], $args); return;
        return;
    
    }

    private function handleScaffoldTest(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
        $entityName = $payload['entityName'] ?? '';
        if (!$entityName)
            $this->json(['success' => false, 'error' => "Entity name required.", "error"], $args); return;
        return;

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

        $this->json(['success' => true, 'message' => "Tests scaffolded.", "success"], $args); return;
        return;
    
    }

    private function handleScaffoldAuth(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
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

        $this->json(['success' => true, 'message' => "Auth entities (User, Role) scaffolded.", "success"], $args); return;
        return;
    
    }

    private function handleAiGenerateLogic(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
        $prompt = $payload['prompt'] ?? '';
        if (!$prompt)
            $this->json(['success' => false, 'error' => "Prompt required.", "error"], $args); return;
        return;

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

        $this->json(['code' => "\n" . $code . "\n"], $args); return;
        return;
    
    }

    private function handleRestoreRevision(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
        $name = $payload['name'] ?? '';
        $timestamp = $payload['timestamp'] ?? '';
        if (!$name || !$timestamp)
            $this->json(['success' => false, 'error' => "Name and timestamp required.", "error"], $args); return;
        return;

        $srcDir = SPP_APP_DIR . "/src/{$appContext}/entities";
        $revDir = $srcDir . '/.revisions';

        $revYml = $revDir . '/' . strtolower($name) . '_' . $timestamp . '.yml';
        $revPhp = $revDir . '/entity.' . strtolower($name) . '_' . $timestamp . '.php';

        if (!file_exists($revYml)) {
            $this->json(['success' => false, 'error' => "Revision not found.", "error"], $args); return;
        return;
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

    private function handleMagicGenerateSchema(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
        $prompt = $payload['prompt'] ?? '';
        if (!$prompt)
            $this->json(['success' => false, 'error' => "Prompt required.", "error"], $args); return;
        return;

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
            $this->json(['success' => false, 'error' => "Could not understand any specific fields from the prompt.", "error"], $args); return;
        return;
        }

        $this->json(['config' => ['attributes' => $attributes]], $args); return;
        return;
    
    }

    private function handleScaffoldDashboard(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
        $entityName = $payload['entityName'] ?? '';
        if (!$entityName)
            $this->json(['success' => false, 'error' => "Entity name required.", "error"], $args); return;
        return;

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

        $this->json(['success' => true, 'message' => "Dashboard scaffolded successfully! Please refresh the admin panel.", "success"], $args); return;
        return;
    
    }

    private function handlePreviewMigration(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
        $name = $payload['name'] ?? '';
        $yamlContent = $payload['yaml'] ?? '';
        if (!$name || !$yamlContent)
            $this->json(['success' => false, 'error' => "Entity name and YAML required.", "error"], $args); return;
        return;

        try {
            $config = \Symfony\Component\Yaml\Yaml::parse($yamlContent);
            $table = $config['table'] ?? '';
            if (!$table)
                $this->json(['success' => false, 'error' => "Table name not defined in YAML.", "error"], $args); return;
        return;

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

            $this->json(['sql' => $sqlDiff], $args); return;
        return;
        } catch (\Exception $e) {
            sendResponse(false, [], "Error parsing diff: " . $e->getMessage());
        }
    
    }

    private function handleCheckAuth(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
        if (\SPPMod\SPPAuth\SPPAuth::check()) {
            $user = \SPPMod\SPPAuth\SPPAuth::user();
            $userId = (string) \SPPMod\SPPAuth\SPPAuth::guard()->id();
            $username = $userId;
            if ($user) {
                $username = $user->username ?? $user->get('UserName') ?? $userId;
            }
            $this->notify("Authenticated.", "success", $args);
        $this->json(['username' => $username, 'user_id' => $userId]); return;
        return;
        } else {
            $this->json(['success' => false, 'error' => "Please Authenticate yourself.", "error"], $args); return;
        return;
        }
    
    }

    private function handleGetProfile(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
        file_put_contents(SPP_BASE_DIR . '/api_debug.log', "[" . date('Y-m-d H:i:s') . "] AdminLegacyCommand::handleGetProfile called\n", FILE_APPEND);
        if (\SPPMod\SPPAuth\SPPAuth::check()) {
            $user = \SPPMod\SPPAuth\SPPAuth::user();
            file_put_contents(SPP_BASE_DIR . '/api_debug.log', "[" . date('Y-m-d H:i:s') . "] AdminLegacyCommand: check() returned true, user: " . print_r($user, true) . "\n", FILE_APPEND);
            $this->json(['success' => true, 'profile' => $user->getValues()], $args); return;
        }
        file_put_contents(SPP_BASE_DIR . '/api_debug.log', "[" . date('Y-m-d H:i:s') . "] AdminLegacyCommand: check() returned false\n", FILE_APPEND);
        $this->json(['success' => false, 'error' => "Profile not found."], $args); return;
    
    }

    private function handleGetConfigAll(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
        $global = getGlobalSettings();
        $app = [];
        $sys = [
            'spp_version' => '1.1.0',
            'base_path' => SPP_BASE_DIR
        ];
        $this->notify("Config retrieved.", "success", $args);
        $this->json(['config' => ['global' => $global, 'app' => $app, 'sys' => $sys]]); return;
        return;
    
    }

    private function handleGetGlobalSettings(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
        $settings = getGlobalSettings();
        $raw = file_get_contents((defined('SPP_ETC_DIR') ? SPP_ETC_DIR : SPP_BASE_DIR . '/etc') . '/global-settings.yml');
        $this->json(['parsed' => $settings, 'raw' => $raw], $args); return;
        return;
    
    }

    private function handleSaveGlobalSettings(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
        $mode = $payload['mode'] ?? 'form';
        if ($mode === 'yaml') {
            $yaml = $payload['yaml'] ?? '';
            try {
                $parsed = \Symfony\Component\Yaml\Yaml::parse($yaml);
                saveGlobalSettings($parsed);
                $this->json(['success' => true, 'message' => "Global settings saved via YAML.", "success"], $args); return;
        return;
            } catch (\Exception $e) {
                $this->json(['success' => false, 'error' => "YAML Parse Error: " . $e->getMessage()], $args); return;
        return;
            }
        } else {
            $data = $payload['data'] ?? '';
            $parsed = json_decode($data, true);
            if ($parsed) {
                saveGlobalSettings($parsed);
                $la->setStatus('true' === 'true' ? 'success' : 'error')->notify("Global settings saved.", "error");
        return;
            } else {
                $this->json(['success' => false, 'error' => "Invalid JSON data.", "error"], $args); return;
        return;
            }
        }
    
    }

    private function handleSaveConfigValue(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
        $key = $payload['key'] ?? '';
        $value = $payload['value'] ?? '';
        if (!$key)
            $this->json(['success' => false, 'error' => "Missing key.", "error"], $args); return;
        return;

        $parts = explode(':', $key, 2);
        $ns = $parts[0];
        $actualKey = $parts[1] ?? '';

        if ($ns === 'global' && $actualKey) {
            $settings = getGlobalSettings();
            $settings[$actualKey] = ($value === 'true') ? true : (($value === 'false') ? false : $value);
            saveGlobalSettings($settings);
            $this->json(['success' => true, 'message' => "Global setting updated.", "success"], $args); return;
        return;
        } else {
            $this->json(['success' => false, 'error' => "Editing namespace '{$ns}' is restricted or key is invalid.", "error"], $args); return;
        return;
        }
    
    }

    private function handleListCommands(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
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
            $this->notify("Commands retrieved", "success", $args);
        $this->json(['categories' => $list]); return;
        return;
        }

        if ($action === 'get_command_ui') {
            $cmdName = $payload['command'] ?? '';
            $cmd = $commands[$cmdName] ?? null;
            if (!$cmd)
                $this->json(['success' => false, 'error' => "Command not found.", "error"], $args); return;
        return;
            $this->notify("UI retrieved", "success", $args);
        $this->json(['html' => $cmd->renderAdminUI()]); return;
        return;
        }

        if ($action === 'execute_command') {
            $cmdName = $payload['command'] ?? '';
            $argsRaw = $payload['args'] ?? '';

            $cmd = $commands[$cmdName] ?? null;
            if (!$cmd)
                $this->json(['success' => false, 'error' => "Command not found.", "error"], $args); return;
        return;

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

            $this->notify("Command executed", "success", $args);
        $this->json(['output' => $output]); return;
        return;
        }
    
    }

    private function handleGetCommandUi(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
        $cmdName = $payload['command'] ?? '';
        $cmd = $commands[$cmdName] ?? null;
        if (!$cmd)
            $this->json(['success' => false, 'error' => "Command not found.", "error"], $args); return;
        return;
        $this->notify("UI retrieved", "success", $args);
        $this->json(['html' => $cmd->renderAdminUI()]); return;
        return;
    
    }

    private function handleExecuteCommand(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
        $cmdName = $payload['command'] ?? '';
        $argsRaw = $payload['args'] ?? '';

        $cmd = $commands[$cmdName] ?? null;
        if (!$cmd)
            $this->json(['success' => false, 'error' => "Command not found.", "error"], $args); return;
        return;

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

        $this->notify("Command executed", "success", $args);
        $this->json(['output' => $output]); return;
        return;
    
    }

    private function handleGetBuilderContext(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
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
        $this->json(['save_path' => $savePath, 'classes' => $classes], $args); return;
        return;
    
    }

    private function handleListEntities(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
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
        $this->notify("Entities listed.", "success", $args);
        $this->json(['entities' => $entities]); return;
        return;
    
    }

    private function handleParseEntityYaml(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
        $yml = $payload['yaml'] ?? '';
        try {
            $parsed = \Symfony\Component\Yaml\Yaml::parse($yml);
            $this->notify("Parsed successfully.", "success", $args);
        $this->json(['config' => $parsed]); return;
        return;
        } catch (\Exception $e) {
            sendResponse(false, [], "YAML Parse Error: " . $e->getMessage());
        }
    
    }

    private function handleDumpEntityYaml(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
        $config = $payload['config'] ?? [];
        if (is_string($config))
            $config = json_decode($config, true);
        try {
            $yml = \Symfony\Component\Yaml\Yaml::dump($config, 10, 2);
            $this->notify("Dumped successfully.", "success", $args);
        $this->json(['yaml' => $yml]); return;
        return;
        } catch (\Exception $e) {
            sendResponse(false, [], "YAML Dump Error: " . $e->getMessage());
        }
    
    }

    private function handleIntrospectTable(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
        $table = $payload['table'] ?? '';
        if (!$table)
            $this->json(['success' => false, 'error' => "Table name required.", "error"], $args); return;
        return;
        try {
            $db = new \SPPMod\SPPDB\SPPDB();
            $driver = strtolower($db->getDriver());
            $attributes = [];

            if ($driver === 'mongodb') {
                $res = $db->query("SELECT * FROM {$table} LIMIT 1");
                if (empty($res))
                    $this->json(['success' => false, 'error' => "Collection is empty, cannot infer schema.", "error"], $args); return;
        return;
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
                        $this->json(['success' => false, 'error' => "Cannot infer schema from empty XDB table.", "error"], $args); return;
        return;
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
            $this->notify("Introspected table {$table}", "success", $args);
        $this->json(['config' => $config]); return;
        return;
        } catch (\Exception $e) {
            sendResponse(false, [], "Introspection Error: " . $e->getMessage());
        }
    
    }

    private function handleScaffoldForm(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
        $entityName = $payload['entityName'] ?? '';
        $config = $payload['config'] ?? [];
        if (is_string($config))
            $config = json_decode($config, true);
        if (!$entityName || empty($config['attributes']))
            $this->json(['success' => false, 'error' => "Entity configuration required.", "error"], $args); return;
        return;

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
            $this->notify("Form scaffolded successfully.", "success", $args);
        $this->json(['formConfig' => $formConfig]); return;
        return;
        } catch (\Exception $e) {
            sendResponse(false, [], "Scaffolding Error: " . $e->getMessage());
        }
    
    }

    private function handleSeedEntity(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
        $entityName = $payload['entityName'] ?? '';
        $count = (int) ($payload['count'] ?? 50);
        if (!$entityName)
            $this->json(['success' => false, 'error' => "Entity name required.", "error"], $args); return;
        return;
        try {
            $class = "\\SPPMod\\SPPEntity\\{$entityName}";
            if (!class_exists($class)) {
                $file = SPP_BASE_DIR . "/modules/spp/sppdb/entities/class." . strtolower($entityName) . ".php";
                if (file_exists($file))
                    require_once $file;
            }
            if (!class_exists($class))
                $this->json(['success' => false, 'error' => "Entity class not found.", "error"], $args); return;
        return;

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
            $this->notify("Seeded {$inserted} records.", "success", $args);
        $this->json(['inserted' => $inserted]); return;
        return;
        } catch (\Exception $e) {
            sendResponse(false, [], "Seed Error: " . $e->getMessage());
        }
    
    }

    private function handleSaveEntityConfig(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
        $name = $payload['name'] ?? '';
        $config = $payload['config'] ?? [];
        if (is_string($config))
            $config = json_decode($config, true);
        if (!$name)
            $this->json(['success' => false, 'error' => "Entity name required.", "error"], $args); return;
        return;

        try {
            if (!class_exists('\SPPMod\SPPDB\SPPEntity')) {
                require_once SPP_BASE_DIR . '/sppinit.php';
            }
            createEntityRevision($appContext, $name);

            if (!empty($config['extends'])) {
                $extendsClass = ltrim($config['extends'], '\\');
                if ($extendsClass !== 'SPPMod\SPPEntity\SPPEntity') {
                    if (!class_exists($extendsClass) || !is_subclass_of($extendsClass, '\SPPMod\SPPDB\SPPEntity')) {
                        $this->json(['success' => false, 'error' => "Error: Extended class '{$config['extends']}' does not exist or does not extend SPPEntity.", "error"], $args); return;
        return;
                    }
                }
            }

            \SPPMod\SPPDB\SPPEntity::saveEntityDefinition($name, $appContext, $config);
            $this->json(['success' => true, 'message' => "Entity saved.", "success"], $args); return;
        return;
        } catch (\Exception $e) {
            sendResponse(false, [], "Save error: " . $e->getMessage());
        }
    
    }

    private function handleSaveEntitySource(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
        $name = $payload['name'] ?? '';
        $source = $payload['source'] ?? '';
        $type = $payload['type'] ?? 'php';
        if (!$name)
            $this->json(['success' => false, 'error' => "Entity name required.", "error"], $args); return;
        return;

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

            $this->json(['success' => true, 'message' => "Entity {$type} source saved and synced.", "success"], $args); return;
        return;
        } catch (\Exception $e) {
            sendResponse(false, [], "Save error: " . $e->getMessage());
        }
    
    }

    private function handleDeleteEntity(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
        $name = $payload['name'] ?? '';
        if (!$name)
            $this->json(['success' => false, 'error' => "Entity name required.", "error"], $args); return;
        return;

        $pathYml = SPP_APP_DIR . "/src/{$appContext}/entities/{$name}.yml";
        $pathPhp = SPP_APP_DIR . "/src/{$appContext}/entities/{$name}.php";
        if (file_exists($pathYml))
            unlink($pathYml);
        if (file_exists($pathPhp))
            unlink($pathPhp);

        $this->json(['success' => true, 'message' => "Entity deleted.", "success"], $args); return;
        return;
    
    }

    private function handleListForms(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
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
        $this->notify("Forms listed.", "success", $args);
        $this->json(['forms' => $forms]); return;
        return;
    
    }

    private function handleParseFormYaml(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
        $yml = $payload['yaml'] ?? '';
        try {
            $parsed = \Symfony\Component\Yaml\Yaml::parse($yml);
            $this->notify("Parsed successfully.", "success", $args);
        $this->json(['config' => $parsed]); return;
        return;
        } catch (\Exception $e) {
            sendResponse(false, [], "YAML Parse Error: " . $e->getMessage());
        }
    
    }

    private function handleDumpFormYaml(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
        $config = $payload['config'] ?? [];
        if (is_string($config))
            $config = json_decode($config, true);
        try {
            $yml = \Symfony\Component\Yaml\Yaml::dump($config, 10, 2);
            $this->notify("Dumped successfully.", "success", $args);
        $this->json(['yaml' => $yml]); return;
        return;
        } catch (\Exception $e) {
            sendResponse(false, [], "YAML Dump Error: " . $e->getMessage());
        }
    
    }

    private function handleSaveFormConfig(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
        $name = $payload['name'] ?? '';
        $yaml = $payload['yaml'] ?? '';
        if (!$name)
            $this->json(['success' => false, 'error' => "Form name required.", "error"], $args); return;
        return;

        $path = SPP_APP_DIR . "/etc/apps/{$appContext}/forms/{$name}.yml";
        if (!is_dir(dirname($path)))
            mkdir(dirname($path), 0777, true);
        try {
            file_put_contents($path, $yaml);
            $this->json(['success' => true, 'message' => "Form saved.", "success"], $args); return;
        return;
        } catch (\Exception $e) {
            sendResponse(false, [], "Save Error: " . $e->getMessage());
        }
    
    }

    private function handleDeleteForm(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
        $name = $payload['name'] ?? '';
        if (!$name)
            $this->json(['success' => false, 'error' => "Form name required.", "error"], $args); return;
        return;

        $path = SPP_APP_DIR . "/etc/apps/{$appContext}/forms/{$name}.yml";
        if (file_exists($path))
            unlink($path);

        $this->json(['success' => true, 'message' => "Form deleted.", "success"], $args); return;
        return;
    
    }

    private function handleGetFormHtml(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
        $yaml = $payload['yaml'] ?? '';
        try {
            $config = \Symfony\Component\Yaml\Yaml::parse($yaml);
            ob_start(); require SPP_APP_DIR . '/spp/admin/partials/generated/adminlegacy_1.php'; $html = ob_get_clean();
            if (isset($config['fields']) && is_array($config['fields'])) {
                foreach ($config['fields'] as $key => $f) {
                    ob_start(); require SPP_APP_DIR . '/spp/admin/partials/generated/adminlegacy_2.php'; $html .= ob_get_clean();
                    $html .= "<label style='display:block;margin-bottom:5px;font-weight:600;'>" . htmlspecialchars($f['label'] ?? $key) . "</label>";
                    $html .= "<input class='spp-form-control' style='width:100%;padding:8px;border-radius:4px;border:1px solid #ccc;' placeholder='" . htmlspecialchars($f['placeholder'] ?? '') . "'>";
                    $html .= "</div>";
                }
            }
            $html .= "<button class='btn btn-primary' style='padding:8px 16px;'>" . htmlspecialchars($config['submit_label'] ?? 'Submit') . "</button>";
            $html .= "</div>";
            $this->notify("Form rendered.", "success", $args);
        $this->json(['html' => $html]); return;
        return;
        } catch (\Exception $e) {
            sendResponse(false, [], "Render Error: " . $e->getMessage());
        }
    
    }

    private function handleSaveAppConfig(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
        $targetApp = $payload['target_app'] ?? $appContext;
        $config = $payload['config'] ?? [];
        if (is_string($config))
            $config = json_decode($config, true);
        $appConfigFile = SPP_APP_DIR . "/etc/apps/{$targetApp}/config.yml";
        try {
            if (!is_dir(dirname($appConfigFile)))
                mkdir(dirname($appConfigFile), 0777, true);
            file_put_contents($appConfigFile, \Symfony\Component\Yaml\Yaml::dump($config, 10, 2));
            $this->json(['success' => true, 'message' => "App config saved.", "success"], $args); return;
        return;
        } catch (\Exception $e) {
            sendResponse(false, [], "Save Error: " . $e->getMessage());
        }
    
    }

    private function handleListModules(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
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
        $this->json(['modules' => $modules]); return;
        } catch (\Exception $e) {
            sendResponse(false, [], "Error listing modules: " . $e->getMessage());
        }
    
    }

    private function handleToggleModule(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
        $modname = $payload['modname'] ?? '';
        $status = $payload['status'] ?? 'inactive';

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
            $this->json(['success' => true, 'message' => "Module {$modname} status updated.", "success"], $args); return;
        return;
        } catch (\Exception $e) {
            sendResponse(false, [], "Toggle Error: " . $e->getMessage());
        }
    
    }

    private function handleExecuteScaffold(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
        $command = $payload['command'] ?? '';
        $target = $payload['target'] ?? '';
        $optionsRaw = $payload['options'] ?? '';

        if (!$command || !$target) {
            $this->json(['success' => false, 'error' => "Command and Target are required.", "error"], $args); return;
        return;
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
            $this->json(['success' => false, 'error' => "Invalid or unauthorized scaffold command.", "error"], $args); return;
        return;
        }

        $optionsStr = '';
        if ($optionsRaw) {
            // Very simple sanitization: only allow alphanumeric, dashes, equals, spaces, and commas
            if (preg_match('/^[a-zA-Z0-9_=\-\s,:]+$/', $optionsRaw)) {
                $parts = preg_split('/\s+/', trim($optionsRaw));
                $safeParts = array_map('escapeshellarg', $parts);
                $optionsStr = " " . implode(" ", $safeParts);
            } else {
                $this->json(['success' => false, 'error' => "Invalid characters in options. Only alphanumeric, -, _, =, :, commas, and spaces allowed.", "error"], $args); return;
        return;
            }
        }

        try {
            $cmdLine = "php " . escapeshellarg(SPP_BASE_DIR . '/spp.php') . " " . escapeshellarg($command) . " " . escapeshellarg($target) . $optionsStr . " 2>&1";
            $output = shell_exec($cmdLine);
            $this->notify("Command executed successfully.", "success", $args);
        $this->json(['output' => $output]); return;
        return;
        } catch (\Exception $e) {
            $la->setStatus('error')->setData(['output' => $e->getMessage()])->notify("Command execution failed.", "error");
        return;
        }
    
    }

    private function handleGetDiBindings(array $payload, array $args): void {

        $appContext = $payload['appname'] ?? 'default';
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
            $this->notify("Bindings retrieved.", "success", $args);
        $this->json(['bindings' => $bindings]); return;
        return;
        } catch (\Exception $e) {
            sendResponse(false, [], "DI Error: " . $e->getMessage());
        }
    
    }

}
