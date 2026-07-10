<?php
/**
 * SPP Report API Controller & Modular Drivers
 * Handles frontend requests for schema introspection, querying, and exporting data.
 * Features smart content negotiation (HTMX / Turbo Streams) and zero inline HTML literals.
 */

namespace SPPMod\SPPReport;

require_once __DIR__ . '/class.sppreport.php';

if (!class_exists('\\SPP\\Core\\ResourceController')) {
    require_once __DIR__ . '/../../../../spp/core/class.resourcecontroller.php';
}

class CsvExportDriver
{
    public function export(\SPPReport $report, array $config): void
    {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="report_export_' . date('Ymd_His') . '.csv"');

        $output = fopen('php://output', 'w');
        $first = true;

        foreach ($report->streamReport($config) as $row) {
            if ($first) {
                fputcsv($output, array_keys($row));
                $first = false;
            }
            $sanitizedRow = array_map(function ($val) {
                return preg_match('/^[=\+\-@]/', (string) $val) ? "'" . $val : $val;
            }, $row);
            fputcsv($output, $sanitizedRow);
        }
        fclose($output);
        exit;
    }
}

class ExcelExportDriver
{
    public function export(\SPPReport $report, array $config, ReportController $controller): void
    {
        header("Content-Type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=\"report_export_" . date('Ymd_His') . ".xls\"");

        echo $controller->renderExternalPartial('partials/export_excel.php', ['generator' => $report->streamReport($config)]);
        exit;
    }
}

class PdfExportDriver
{
    public function export(\SPPReport $report, array $config, ReportController $controller): void
    {
        $result = $report->runReport($config);

        if (class_exists('TCPDF')) {
            $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            $pdf->SetCreator('SPPReport');
            $pdf->SetAuthor('System');
            $pdf->SetTitle('Report Export');
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->AddPage();
            $pdf->SetFont('helvetica', '', 10);

            $html = $controller->renderExternalPartial('partials/export_pdf.php', ['data' => $result['data']]);
            $pdf->writeHTML($html, true, false, true, false, '');
            $pdf->Output('report_export_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } else {
            throw new \Exception("Server-Side PDF rendering requires TCPDF to be installed via composer (`composer require tecnickcom/tcpdf`). For zero-dependency PDF generation, please use the Client-Side 'Print PDF' option.");
        }
    }
}

class ReportController extends \SPP\Core\ResourceController
{
    public function renderExternalPartial(string $view, array $data = []): string
    {
        return $this->renderPartial($view, $data);
    }

    public function handleRequest(): void
    {
        if (class_exists('\\SPPMod\\SPPView\\ViewLocator')) {
            \SPPMod\SPPView\ViewLocator::addPath(__DIR__ . '/views');
            \SPPMod\SPPView\ViewLocator::addPath(__DIR__);
        }

        $isHtmx = isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] === 'true';
        $isTurboStream = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'text/vnd.turbo-stream.html') !== false;

        if (class_exists('\\SPPMod\\SPPAuth\\SPPAuth')) {
            $user = \SPPMod\SPPAuth\SPPAuth::getCurrentUser();
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'POST') {
            $action = $_POST['report_action'] ?? $_GET['report_action'] ?? $_POST['action'] ?? $_GET['action'] ?? '';
            $payload = file_get_contents('php://input');
            if (empty($payload) && isset($_POST['payload'])) {
                $payload = $_POST['payload'];
            }
        } else {
            $action = $_GET['report_action'] ?? $_GET['action'] ?? '';
            $payload = $_GET['payload'] ?? '{}';
        }

        $isSppAdmin = !empty($_SESSION['spp_admin_user']) || !empty($_SESSION['spp_admin_fallback']) || (class_exists('\\SPP\\SPPSession') && \SPP\SPPSession::sessionVarExists('__sppauth_user__'));

        $user = class_exists('\\SPPMod\\SPPAuth\\SPPAuth') ? \SPPMod\SPPAuth\SPPAuth::user() : null;
        $roleNames = [];
        if ($user && method_exists($user, 'getRoles')) {
            $roleIds = $user->getRoles();
            if (!empty($roleIds)) {
                $db = new \SPPMod\SPPDB\SPPDB();
                if ($db->tableExists('roles')) {
                    $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
                    $roles = $db->execute_query("SELECT name FROM " . \SPPMod\SPPDB\SPPDB::sppTable('roles') . " WHERE id IN ($placeholders)", $roleIds);
                    $roleNames = array_column($roles, 'name');
                }
            }
        }
        $isAdmin = $isSppAdmin || in_array('admin', $roleNames);

        $adminActions = ['save', 'list_versions', 'restore_version', 'save_template'];
        if (in_array($action, $adminActions) && !$isAdmin) {
            http_response_code(403);
            if (!$isHtmx && !$isTurboStream) header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'This action requires Admin privileges.']);
            exit;
        }

        $config = json_decode($payload, true);

        $externalConfig = null;
        $hasExternalDsn = (!empty($config['external_dsn']) || !empty($_GET['external_dsn']));
        if ($hasExternalDsn) {
            if (!$isAdmin) {
                http_response_code(403);
                if (!$isHtmx && !$isTurboStream) header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'External DSN connections require Admin privileges to prevent SSRF.']);
                exit;
            }
            $extDsn = $config['external_dsn'] ?? $_GET['external_dsn'];
            $extUser = $config['external_user'] ?? $_GET['external_user'] ?? null;
            $extPass = $config['external_pass'] ?? $_GET['external_pass'] ?? null;
            $externalConfig = [
                'dsn' => $extDsn,
                'user' => $extUser,
                'pass' => $extPass
            ];
        }

        $report = new \SPPReport($externalConfig);

        $enforceRBAC = function ($cfg) use ($isAdmin, $roleNames) {
            $allowedRoles = $cfg['roles_allowed'] ?? '';
            if (!empty($allowedRoles) && !$isAdmin) {
                $allowed = array_map('trim', explode(',', strtolower($allowedRoles)));
                $myRoles = array_map('strtolower', $roleNames);
                if (empty(array_intersect($allowed, $myRoles))) {
                    throw new \Exception("You do not have permission to view this report.");
                }
            }
        };

        try {
            switch ($action) {
                case 'dashboard':
                    if ($isHtmx) {
                        echo $this->renderExternalPartial('partials/report_dashboard.html', []);
                        break;
                    }
                    if (!$isHtmx && !$isTurboStream) header('Content-Type: application/json');
                    echo json_encode(['status' => 'success', 'message' => 'Dashboard partial ready.']);
                    break;

                case 'schema':
                    if ($isHtmx) {
                        $table = $_GET['table'] ?? $_POST['table'] ?? '';
                        echo $this->renderExternalPartial('partials/report_configurator.php', [
                            'driver' => $report->getDriver(),
                            'schema' => $report->getSchema(),
                            'table' => $table
                        ]);
                        break;
                    }
                    if (!$isHtmx && !$isTurboStream) header('Content-Type: application/json');
                    echo json_encode([
                        'status' => 'success',
                        'driver' => $report->getDriver(),
                        'schema' => $report->getSchema()
                    ]);
                    break;

                case 'preview':
                    if (!$config && isset($_POST['table'])) {
                        $config = [
                            'table' => $_POST['table'],
                            'columns' => $_POST['columns'] ?? [],
                            'order_by' => (!empty($_POST['order_by']) ? ['field' => $_POST['order_by'], 'direction' => $_POST['order_direction'] ?? 'ASC'] : null),
                            'limit' => 100
                        ];
                        if (!empty($_POST['filter_field'])) {
                            $conditions = [];
                            foreach ($_POST['filter_field'] as $idx => $field) {
                                if ($field !== '' && isset($_POST['filter_value'][$idx]) && $_POST['filter_value'][$idx] !== '') {
                                    $conditions[] = [
                                        'field' => $field,
                                        'operator' => $_POST['filter_operator'][$idx] ?? '=',
                                        'value' => $_POST['filter_value'][$idx]
                                    ];
                                }
                            }
                            if (!empty($conditions)) {
                                $config['filters'] = ['logic' => 'AND', 'conditions' => $conditions];
                            }
                        }
                    }
                    if (!$config) {
                        throw new \Exception("Invalid JSON payload or form data.");
                    }
                    $enforceRBAC($config);
                    $result = $report->runReport($config);

                    if ($isTurboStream) {
                        $this->stream('streams/report_update.php', [
                            'data' => $result['data'],
                            'target' => $_GET['stream_target'] ?? 'spp-configurator-container',
                            'action' => $_GET['stream_action'] ?? 'replace',
                            'interval' => $_GET['stream_interval'] ?? 5000,
                            'widget_type' => $_GET['widget_type'] ?? 'grid',
                            'widget_title' => $_GET['widget_title'] ?? 'Live Analytics Stream'
                        ]);
                        break;
                    } elseif ($isHtmx) {
                        $templateName = $_POST['template_name'] ?? $_GET['template_name'] ?? 'partials/report_preview.php';
                        echo $this->renderExternalPartial($templateName, [
                            'data' => $result['data'],
                            'sql' => $result['sql'],
                            'config_payload' => json_encode($config),
                            'org_name' => 'SPP Global Enterprise Solutions',
                            'confidentiality' => 'CONFIDENTIAL - FOR INTERNAL USE ONLY. DO NOT DISTRIBUTE.'
                        ]);
                        break;
                    }
                    if (!$isHtmx && !$isTurboStream) header('Content-Type: application/json');
                    echo json_encode([
                        'status' => 'success',
                        'data' => $result['data'],
                        'sql' => $result['sql']
                    ]);
                    break;

                case 'render_template':
                    $templateName = $_POST['template_name'] ?? $_GET['template_name'] ?? 'partials/report_template_dual_mode.php';
                    if (!$config && isset($_GET['payload'])) {
                        $config = json_decode($_GET['payload'], true);
                    }
                    if (!$config) {
                        $config = ['limit' => 100];
                    }
                    $enforceRBAC($config);
                    $result = $report->runReport($config);
                    echo $this->renderExternalPartial($templateName, [
                        'data' => $result['data'],
                        'sql' => $result['sql'],
                        'config_payload' => json_encode($config),
                        'org_name' => 'SPP Global Enterprise Solutions',
                        'confidentiality' => 'CONFIDENTIAL - FOR INTERNAL USE ONLY. DO NOT DISTRIBUTE.'
                    ]);
                    break;

                case 'export_csv':
                    if (!$config && isset($_GET['payload'])) {
                        $config = json_decode($_GET['payload'], true);
                    }
                    if (!$config) {
                        throw new \Exception("Invalid JSON payload.");
                    }
                    $enforceRBAC($config);
                    $config['limit'] = 0;
                    (new CsvExportDriver())->export($report, $config);
                    break;

                case 'export_xls':
                    if (!$config && isset($_GET['payload'])) {
                        $config = json_decode($_GET['payload'], true);
                    }
                    if (!$config) {
                        throw new \Exception("Invalid JSON payload.");
                    }
                    $enforceRBAC($config);
                    $config['limit'] = 0;
                    (new ExcelExportDriver())->export($report, $config, $this);
                    break;

                case 'export_pdf':
                    if (!$config && isset($_GET['payload'])) {
                        $config = json_decode($_GET['payload'], true);
                    }
                    if (!$config) {
                        throw new \Exception("Invalid JSON payload.");
                    }
                    $enforceRBAC($config);
                    $config['limit'] = 0;
                    (new PdfExportDriver())->export($report, $config, $this);
                    break;

                case 'list':
                    $dir = __DIR__ . '/../../../../etc/sppreports';
                    if (!is_dir($dir)) {
                        if (!$isHtmx && !$isTurboStream) header('Content-Type: application/json');
                        echo json_encode(['status' => 'success', 'reports' => []]);
                        break;
                    }
                    $files = array_merge(glob($dir . '/*.yml'), glob($dir . '/*.json'));
                    $reports = array_unique(array_map(function ($f) {
                        return pathinfo($f, PATHINFO_FILENAME);
                    }, $files));
                    if (!$isHtmx && !$isTurboStream) header('Content-Type: application/json');
                    echo json_encode(['status' => 'success', 'reports' => array_values($reports)]);
                    break;

                case 'load':
                    $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['name'] ?? '');
                    if (!$name) {
                        throw new \Exception("Invalid report name.");
                    }
                    $dir = __DIR__ . '/../../../../etc/sppreports';
                    $jsonFile = $dir . '/' . $name . '.json';
                    $ymlFile = $dir . '/' . $name . '.yml';

                    $data = null;
                    if (file_exists($jsonFile)) {
                        $data = json_decode(file_get_contents($jsonFile), true);
                    } elseif (file_exists($ymlFile)) {
                        $data = $this->parseLegacyYaml($ymlFile);
                    } else {
                        throw new \Exception("Report not found.");
                    }

                    $enforceRBAC($data);
                    if (!$isHtmx && !$isTurboStream) header('Content-Type: application/json');
                    echo json_encode(['status' => 'success', 'config' => $data]);
                    break;

                case 'ai_analyze':
                    if (!$config) {
                        throw new \Exception("Invalid JSON payload.");
                    }
                    $enforceRBAC($config);
                    if (!class_exists('\\SPPMod\\SPPAI\\SPPAI')) {
                        throw new \Exception("SPPAI module is required for automated insights.");
                    }

                    $config['limit'] = 100;
                    $result = $report->runReport($config);

                    if (empty($result['data'])) {
                        throw new \Exception("No data to analyze.");
                    }

                    $prompt = "You are an expert Data Analyst. Analyze the following report data and provide a concise, 3-bullet-point executive summary of the key insights, trends, or anomalies.\n\nData (first 100 rows max):\n";
                    $prompt .= json_encode($result['data']);

                    $analysis = \SPPMod\SPPAI\SPPAI::generate($prompt);

                    if (!$isHtmx && !$isTurboStream) header('Content-Type: application/json');
                    echo json_encode(['status' => 'success', 'analysis' => $analysis]);
                    break;

                case 'save':
                    if (!$config) {
                        throw new \Exception("Invalid JSON payload.");
                    }
                    $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $config['report_name'] ?? '');
                    if (!$name) {
                        throw new \Exception("Report name is required.");
                    }

                    $dir = __DIR__ . '/../../../../etc/sppreports';
                    if (!is_dir($dir)) {
                        mkdir($dir, 0777, true);
                    }

                    $jsonFile = "$dir/$name.json";
                    $ymlFile = "$dir/$name.yml";

                    if (file_exists($jsonFile)) {
                        $bakDir = "$dir/history";
                        if (!is_dir($bakDir)) {
                            mkdir($bakDir, 0777, true);
                        }
                        $timestamp = date('Ymd_His');
                        copy($jsonFile, "$bakDir/{$name}_{$timestamp}.json.bak");
                    } elseif (file_exists($ymlFile)) {
                        $bakDir = "$dir/history";
                        if (!is_dir($bakDir)) {
                            mkdir($bakDir, 0777, true);
                        }
                        $timestamp = date('Ymd_His');
                        copy($ymlFile, "$bakDir/{$name}_{$timestamp}.yml.bak");
                    }

                    file_put_contents($jsonFile, json_encode($config, JSON_PRETTY_PRINT));
                    
                    if (function_exists('yaml_emit')) {
                        @file_put_contents($ymlFile, yaml_emit($config));
                    } else {
                        $yaml = "cron_schedule: '" . ($config['cron_schedule'] ?? '') . "'\n";
                        $yaml .= "cron_email: '" . ($config['cron_email'] ?? '') . "'\n";
                        $yaml .= "cron_format: '" . ($config['cron_format'] ?? 'html') . "'\n";
                        $yaml .= "webhook_url: '" . ($config['webhook_url'] ?? '') . "'\n";
                        $yaml .= "webhook_condition: '" . ($config['webhook_condition'] ?? '') . "'\n";
                        $yaml .= "json_dump: '" . json_encode($config) . "'\n";
                        @file_put_contents($ymlFile, $yaml);
                    }

                    if (!$isHtmx && !$isTurboStream) header('Content-Type: application/json');
                    echo json_encode(['status' => 'success', 'message' => 'Report saved successfully.']);
                    break;

                case 'list_versions':
                    $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['name'] ?? '');
                    if (!$name) {
                        throw new \Exception("Report name is required.");
                    }
                    $dir = __DIR__ . '/../../../../etc/sppreports/history';
                    $versions = [];
                    if (is_dir($dir)) {
                        $files = array_merge(glob("$dir/{$name}_*.json.bak"), glob("$dir/{$name}_*.yml.bak"));
                        foreach ($files as $f) {
                            if (preg_match('/_(\d{8}_\d{6})\.(json|yml)\.bak$/', $f, $m)) {
                                $versions[] = ['file' => basename($f), 'timestamp' => $m[1]];
                            }
                        }
                    }
                    usort($versions, function ($a, $b) {
                        return strcmp($b['timestamp'], $a['timestamp']);
                    });
                    if (!$isHtmx && !$isTurboStream) header('Content-Type: application/json');
                    echo json_encode(['status' => 'success', 'versions' => $versions]);
                    break;

                case 'restore_version':
                    if (!$config) {
                        throw new \Exception("Invalid JSON payload.");
                    }
                    $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $config['report_name'] ?? '');
                    $versionFile = preg_replace('/[^a-zA-Z0-9_.-]/', '', $config['version_file'] ?? '');
                    if (!$name || !$versionFile) {
                        throw new \Exception("Name and version_file required.");
                    }

                    $dir = __DIR__ . '/../../../../etc/sppreports';
                    $source = "$dir/history/$versionFile";

                    if (!file_exists($source)) {
                        throw new \Exception("Version not found.");
                    }

                    $ext = str_ends_with($versionFile, '.json.bak') ? '.json' : '.yml';
                    $target = "$dir/$name$ext";

                    if (file_exists($target)) {
                        copy($target, "$dir/history/{$name}_" . date('Ymd_His') . "$ext.bak");
                    }

                    copy($source, $target);
                    if (!$isHtmx && !$isTurboStream) header('Content-Type: application/json');
                    echo json_encode(['status' => 'success', 'message' => 'Version restored.']);
                    break;

                case 'list_templates':
                    $dir = __DIR__ . '/../../../../etc/sppreport_templates';
                    if (!is_dir($dir)) {
                        if (!$isHtmx && !$isTurboStream) header('Content-Type: application/json');
                        echo json_encode(['status' => 'success', 'templates' => []]);
                        break;
                    }
                    $files = glob($dir . '/*.html');
                    $templates = array_map(function ($f) {
                        return basename($f, '.html');
                    }, $files);
                    if (!$isHtmx && !$isTurboStream) header('Content-Type: application/json');
                    echo json_encode(['status' => 'success', 'templates' => $templates]);
                    break;

                case 'load_template':
                    $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['name'] ?? '');
                    if (!$name) {
                        throw new \Exception("Invalid template name.");
                    }
                    $file = __DIR__ . '/../../../../etc/sppreport_templates/' . $name . '.html';
                    if (!file_exists($file)) {
                        throw new \Exception("Template not found.");
                    }

                    $html = file_get_contents($file);
                    if (!$isHtmx && !$isTurboStream) header('Content-Type: application/json');
                    echo json_encode(['status' => 'success', 'html' => $html]);
                    break;

                case 'save_template':
                    if (!$config) {
                        throw new \Exception("Invalid JSON payload.");
                    }
                    $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $config['template_name'] ?? '');
                    $html = $config['html'] ?? '';
                    if (!$name) {
                        throw new \Exception("Template name is required.");
                    }

                    $dir = __DIR__ . '/../../../../etc/sppreport_templates';
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }

                    $file = $dir . '/' . $name . '.html';
                    file_put_contents($file, $html);
                    if (!$isHtmx && !$isTurboStream) header('Content-Type: application/json');
                    echo json_encode(['status' => 'success', 'message' => "Template '$name' saved successfully."]);
                    break;

                case 'ai_build':
                    if (!class_exists('\\SPPMod\\SPPAI\\SPPAI')) {
                        throw new \Exception("SPPAI module is not installed or enabled. Cannot generate AI reports.");
                    }

                    $query = $_POST['query'] ?? '';
                    if (empty($query)) {
                        $post = json_decode($payload, true);
                        $query = $post['query'] ?? '';
                    }
                    if (empty($query)) {
                        throw new \Exception("Natural language query is required.");
                    }

                    $schema = $report->getSchema();
                    $schemaJson = json_encode($schema);

                    $prompt = "You are an AI that converts natural language to a JSON report configuration for SPPReport BI.\n";
                    $prompt .= "Database Schema: $schemaJson\n";
                    $prompt .= "User Request: $query\n";
                    $prompt .= "You must generate a strictly valid JSON configuration with the following structure. Do NOT include markdown blocks.\n";

                    $jsonSchema = [
                        'type' => 'object',
                        'properties' => [
                            'table' => ['type' => 'string', 'description' => 'The base table name'],
                            'columns' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'field' => ['type' => 'string'],
                                        'aggregate' => ['type' => 'string', 'enum' => ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX', 'CUSTOM', '']],
                                        'alias' => ['type' => 'string']
                                    ]
                                ]
                            ],
                            'joins' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'table' => ['type' => 'string'],
                                        'type' => ['type' => 'string', 'enum' => ['LEFT JOIN', 'INNER JOIN']],
                                        'on' => ['type' => 'string']
                                    ]
                                ]
                            ]
                        ],
                        'required' => ['table', 'columns']
                    ];

                    $response = \SPPMod\SPPAI\SPPAI::structured($prompt, $jsonSchema);
                    $configObj = is_string($response) ? json_decode($response, true) : $response;
                    if (!$configObj) {
                        throw new \Exception("AI failed to generate a valid report configuration.");
                    }

                    if ($isHtmx) {
                        echo $this->renderExternalPartial('partials/report_configurator.php', [
                            'driver' => $report->getDriver(),
                            'schema' => $report->getSchema(),
                            'table' => $configObj['table'] ?? '',
                            'columns' => $configObj['columns'] ?? []
                        ]);
                        break;
                    }

                    if (!$isHtmx && !$isTurboStream) header('Content-Type: application/json');
                    echo json_encode(['status' => 'success', 'config' => $configObj]);
                    break;

                default:
                    throw new \Exception("Unknown action: " . htmlspecialchars($action));
            }
        } catch (\Exception $e) {
            http_response_code(400);
            if ($isHtmx) {
                echo $this->renderExternalPartial('partials/report_preview.php', [
                    'error' => $e->getMessage()
                ]);
            } else {
                if (!$isHtmx && !$isTurboStream) header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'error',
                    'message' => $e->getMessage()
                ]);
            }
        }
        exit;
    }

    private function parseLegacyYaml(string $file): array
    {
        if (function_exists('yaml_parse_file')) {
            return yaml_parse_file($file);
        }
        $symfonyYaml = __DIR__ . '/../../../../lib/vendor/autoload.php';
        if (file_exists($symfonyYaml)) {
            require_once $symfonyYaml;
            if (class_exists('\\Symfony\\Component\\Yaml\\Yaml')) {
                return \Symfony\Component\Yaml\Yaml::parseFile($file);
            }
        }
        $content = file_get_contents($file);
        if (preg_match("/json_dump:\s*'(.*)'$/m", $content, $m)) {
            $json = json_decode($m[1], true);
            if ($json) {
                return $json;
            }
        }
        throw new \Exception("Loading legacy YAML requires PECL yaml extension or Symfony Yaml.");
    }
}
