<?php
/**
 * SPP Report API
 * Handles frontend requests for schema introspection, querying, and exporting data.
 */

require_once __DIR__ . '/class.sppreport.php';

function spp_report_api_handler($action, $payload)
{
    header('Content-Type: application/json');

    // --- 1. AUTHENTICATION & SECURITY ---
    // Basic Authentication via cookies / session
    if (class_exists('\\SPPMod\\SPPAuth\\SPPAuth')) {
        $user = \SPPMod\SPPAuth\SPPAuth::getCurrentUser();
        if (!$user || $user['id'] === 0) {
            // http_response_code(401);
            // echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Please log in.']);
            // exit;
        }
    }

    $action = $_GET['report_action'] ?? '';
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

    // Only Admins can execute destructive actions or access version control
    $adminActions = ['save', 'list_versions', 'restore_version', 'save_template'];
    if (in_array($action, $adminActions) && !$isAdmin) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'This action requires Admin privileges.']);
        exit;
    }

    $config = json_decode($payload, true);

    // --- 2. EXTERNAL DSN SECURITY ---
    $externalConfig = null;
    $hasExternalDsn = (!empty($config['external_dsn']) || !empty($_GET['external_dsn']));
    if ($hasExternalDsn) {
        if (!$isAdmin) {
            http_response_code(403);
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

    $report = new SPPReport($externalConfig);

    // Helper closure for RBAC
    $enforceRBAC = function ($cfg) use ($isAdmin, $roleNames) {
        $allowedRoles = $cfg['roles_allowed'] ?? '';
        if (!empty($allowedRoles) && !$isAdmin) {
            $allowed = array_map('trim', explode(',', strtolower($allowedRoles)));
            $myRoles = array_map('strtolower', $roleNames);
            if (empty(array_intersect($allowed, $myRoles))) {
                throw new Exception("You do not have permission to view this report.");
            }
        }
    };

    try {
        switch ($action) {
            case 'schema':
                echo json_encode([
                    'status' => 'success',
                    'driver' => $report->getDriver(),
                    'schema' => $report->getSchema()
                ]);
                break;

            case 'preview':
                if (!$config)
                    throw new Exception("Invalid JSON payload.");
                $enforceRBAC($config);
                $result = $report->runReport($config);
                echo json_encode([
                    'status' => 'success',
                    'data' => $result['data'],
                    'sql' => $result['sql'] // Include for debugging/admin visibility
                ]);
                break;

            case 'export_csv':
                if (!$config)
                    throw new Exception("Invalid JSON payload.");
                $enforceRBAC($config);
                // Remove limit for export
                $config['limit'] = 0;
                $result = $report->runReport($config);

                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="report_export_' . date('Ymd_His') . '.csv"');

                $output = fopen('php://output', 'w');
                if (!empty($result['data'])) {
                    // Headers
                    fputcsv($output, array_keys($result['data'][0]));
                    // Data
                    foreach ($result['data'] as $row) {
                        $sanitizedRow = array_map(function ($val) {
                            return preg_match('/^[=\+\-@]/', (string) $val) ? "'" . $val : $val;
                        }, $row);
                        fputcsv($output, $sanitizedRow);
                    }
                }
                fclose($output);
                exit; // End execution to stream cleanly

            case 'export_xls':
                $config = json_decode($payload, true);
                if (!$config)
                    throw new Exception("Invalid JSON payload.");
                $enforceRBAC($config);
                $config['limit'] = 0;
                $result = $report->runReport($config);

                header("Content-Type: application/vnd.ms-excel");
                header("Content-Disposition: attachment; filename=\"report_export_" . date('Ymd_His') . ".xls\"");

                echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
                echo '<head><meta http-equiv="content-type" content="text/plain; charset=UTF-8"/></head>';
                echo '<body><table border="1">';
                if (!empty($result['data'])) {
                    echo '<tr>';
                    foreach (array_keys($result['data'][0]) as $th) {
                        echo '<th style="background-color:#4CAF50;color:white;">' . htmlspecialchars($th) . '</th>';
                    }
                    echo '</tr>';
                    foreach ($result['data'] as $row) {
                        echo '<tr>';
                        foreach ($row as $val) {
                            $sanitizedVal = preg_match('/^[=\+\-@]/', (string) $val) ? "'" . $val : $val;
                            echo '<td>' . htmlspecialchars((string) $sanitizedVal) . '</td>';
                        }
                        echo '</tr>';
                    }
                }
                echo '</table></body></html>';
                exit;

            case 'export_pdf':
                if (!$config)
                    throw new Exception("Invalid JSON payload.");
                $enforceRBAC($config);
                // Remove limit
                $config['limit'] = 0;
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

                    $html = '<h1>Report Export</h1><table border="1" cellpadding="4"><thead><tr>';
                    if (!empty($result['data'])) {
                        foreach (array_keys($result['data'][0]) as $th) {
                            $html .= '<th style="background-color:#eee;"><b>' . htmlspecialchars($th) . '</b></th>';
                        }
                        $html .= '</tr></thead><tbody>';
                        foreach ($result['data'] as $row) {
                            $html .= '<tr>';
                            foreach ($row as $val) {
                                $html .= '<td>' . htmlspecialchars((string) $val) . '</td>';
                            }
                            $html .= '</tr>';
                        }
                    }
                    $html .= '</tbody></table>';
                    $pdf->writeHTML($html, true, false, true, false, '');
                    $pdf->Output('report_export_' . date('Ymd_His') . '.pdf', 'D');
                    exit;
                } else {
                    throw new Exception("Server-Side PDF rendering requires TCPDF to be installed via composer (`composer require tecnickcom/tcpdf`). For zero-dependency PDF generation, please use the Client-Side 'Print PDF' option.");
                }
                break;

            case 'list':
                $dir = __DIR__ . '/../../../../etc/sppreports';
                if (!is_dir($dir)) {
                    echo json_encode(['status' => 'success', 'reports' => []]);
                    break;
                }
                $files = glob($dir . '/*.yml');
                $reports = array_map(function ($f) {
                    return basename($f, '.yml');
                }, $files);
                echo json_encode(['status' => 'success', 'reports' => $reports]);
                break;

            case 'load':
                $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['name'] ?? '');
                if (!$name)
                    throw new Exception("Invalid report name.");
                $file = __DIR__ . '/../../../../etc/sppreports/' . $name . '.yml';
                if (!file_exists($file))
                    throw new Exception("Report not found.");

                $data = spp_load_yaml_fallback($file);
                $enforceRBAC($data);
                echo json_encode(['status' => 'success', 'config' => $data]);
                break;

            case 'ai_analyze':
                if (!$config)
                    throw new Exception("Invalid JSON payload.");
                $enforceRBAC($config);
                if (!class_exists('\\SPPMod\\SPPAI\\SPPAI')) {
                    throw new Exception("SPPAI module is required for automated insights.");
                }

                // $config contains the report definition. We run it to get the data.
                $config['limit'] = 100; // Limit to 100 rows to avoid token overflow
                $result = $report->runReport($config);

                if (empty($result['data'])) {
                    throw new Exception("No data to analyze.");
                }

                $prompt = "You are an expert Data Analyst. Analyze the following report data and provide a concise, 3-bullet-point executive summary of the key insights, trends, or anomalies.\n\nData (first 100 rows max):\n";
                $prompt .= json_encode($result['data']);

                $analysis = \SPPMod\SPPAI\SPPAI::generate($prompt);

                echo json_encode(['status' => 'success', 'analysis' => $analysis]);
                break;

            case 'save':
                if (!$config)
                    throw new Exception("Invalid JSON payload.");
                $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $config['report_name'] ?? '');
                if (!$name)
                    throw new Exception("Report name is required.");

                $dir = __DIR__ . '/../../../../etc/sppreports';
                if (!is_dir($dir))
                    mkdir($dir, 0777, true);

                $file = "$dir/$name.yml";

                // --- VERSION CONTROL ---
                if (file_exists($file)) {
                    $bakDir = "$dir/history";
                    if (!is_dir($bakDir))
                        mkdir($bakDir, 0777, true);
                    $timestamp = date('Ymd_His');
                    copy($file, "$bakDir/{$name}_{$timestamp}.yml.bak");
                }

                if (function_exists('yaml_emit')) {
                    $yaml = yaml_emit($config);
                } else {
                    $yaml = "cron_schedule: '" . ($config['cron_schedule'] ?? '') . "'\n";
                    $yaml .= "cron_email: '" . ($config['cron_email'] ?? '') . "'\n";
                    $yaml .= "cron_format: '" . ($config['cron_format'] ?? 'html') . "'\n";
                    $yaml .= "webhook_url: '" . ($config['webhook_url'] ?? '') . "'\n";
                    $yaml .= "webhook_condition: '" . ($config['webhook_condition'] ?? '') . "'\n";
                    $yaml .= "json_dump: '" . json_encode($config) . "'\n";
                }

                file_put_contents($file, $yaml);
                echo json_encode(['status' => 'success', 'message' => 'Report saved successfully.']);
                break;

            case 'list_versions':
                $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['name'] ?? '');
                if (!$name)
                    throw new Exception("Report name is required.");
                $dir = __DIR__ . '/../../../../etc/sppreports/history';
                $versions = [];
                if (is_dir($dir)) {
                    $files = glob("$dir/{$name}_*.yml.bak");
                    foreach ($files as $f) {
                        if (preg_match('/_(\d{8}_\d{6})\.yml\.bak$/', $f, $m)) {
                            $versions[] = ['file' => basename($f), 'timestamp' => $m[1]];
                        }
                    }
                }
                // Sort descending by timestamp
                usort($versions, function ($a, $b) {
                    return strcmp($b['timestamp'], $a['timestamp']);
                });
                echo json_encode(['status' => 'success', 'versions' => $versions]);
                break;

            case 'restore_version':
                if (!$config)
                    throw new Exception("Invalid JSON payload.");
                $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $config['report_name'] ?? '');
                $versionFile = preg_replace('/[^a-zA-Z0-9_.-]/', '', $config['version_file'] ?? '');
                if (!$name || !$versionFile)
                    throw new Exception("Name and version_file required.");

                $dir = __DIR__ . '/../../../../etc/sppreports';
                $source = "$dir/history/$versionFile";
                $target = "$dir/$name.yml";

                if (!file_exists($source))
                    throw new Exception("Version not found.");

                // Backup current before restoring
                if (file_exists($target)) {
                    copy($target, "$dir/history/{$name}_" . date('Ymd_His') . ".yml.bak");
                }

                copy($source, $target);
                echo json_encode(['status' => 'success', 'message' => 'Version restored.']);
                break;

            case 'list_templates':
                $dir = __DIR__ . '/../../../../etc/sppreport_templates';
                if (!is_dir($dir)) {
                    echo json_encode(['status' => 'success', 'templates' => []]);
                    break;
                }
                $files = glob($dir . '/*.html');
                $templates = array_map(function ($f) {
                    return basename($f, '.html');
                }, $files);
                echo json_encode(['status' => 'success', 'templates' => $templates]);
                break;

            case 'load_template':
                $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['name'] ?? '');
                if (!$name)
                    throw new Exception("Invalid template name.");
                $file = __DIR__ . '/../../../../etc/sppreport_templates/' . $name . '.html';
                if (!file_exists($file))
                    throw new Exception("Template not found.");

                $html = file_get_contents($file);
                echo json_encode(['status' => 'success', 'html' => $html]);
                break;

            case 'save_template':
                $config = json_decode($payload, true);
                if (!$config)
                    throw new Exception("Invalid JSON payload.");
                $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $config['template_name'] ?? '');
                $html = $config['html'] ?? '';
                if (!$name)
                    throw new Exception("Template name is required.");

                $dir = __DIR__ . '/../../../../etc/sppreport_templates';
                if (!is_dir($dir))
                    mkdir($dir, 0755, true);

                $file = $dir . '/' . $name . '.html';
                file_put_contents($file, $html);
                echo json_encode(['status' => 'success', 'message' => "Template '$name' saved successfully."]);
                break;

            case 'trigger_cron':
                // "Poor Man's Cron": Can be triggered asynchronously via a web request
                // In a production environment, you should secure this or restrict by IP if needed.
                $scriptPath = __DIR__ . '/sppreport_cron.php';
                if (file_exists($scriptPath)) {
                    // Include it and let it run. It should handle its own output or be silent.
                    ob_start();
                    require $scriptPath;
                    $cronOutput = ob_get_clean();
                    echo json_encode(['status' => 'success', 'message' => 'Cron triggered successfully', 'output' => $cronOutput]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Cron script not found.']);
                }
                break;

            case 'ai_build':
                if (!class_exists('\\SPPMod\\SPPAI\\SPPAI')) {
                    throw new Exception("SPPAI module is not installed or enabled. Cannot generate AI reports.");
                }

                $query = $_POST['query'] ?? '';
                if (empty($query)) {
                    $post = json_decode($payload, true);
                    $query = $post['query'] ?? '';
                }
                if (empty($query))
                    throw new Exception("Natural language query is required.");

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
                // Depending on the AI driver, it might return a string or an array. 
                $configObj = is_string($response) ? json_decode($response, true) : $response;
                if (!$configObj)
                    throw new Exception("AI failed to generate a valid report configuration.");

                echo json_encode(['status' => 'success', 'config' => $configObj]);
                break;

            default:
                throw new Exception("Unknown action: " . htmlspecialchars($action));
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// Fallback YAML functions to guarantee zero-dependency
function spp_save_yaml_fallback($file, $data)
{
    if (function_exists('yaml_emit_file')) {
        return yaml_emit_file($file, $data);
    }
    // Very simple YAML dumper for our specific config shape
    $yaml = "";
    foreach ($data as $key => $val) {
        if (is_array($val)) {
            $yaml .= $key . ":\n";
            $yaml .= spp_array_to_yaml($val, 1);
        } else {
            $yaml .= $key . ": " . spp_yaml_escape($val) . "\n";
        }
    }
    file_put_contents($file, $yaml);
}

function spp_array_to_yaml($arr, $indent)
{
    $yaml = "";
    $spaces = str_repeat("  ", $indent);
    $isList = array_keys($arr) === range(0, count($arr) - 1);

    foreach ($arr as $key => $val) {
        if (is_array($val)) {
            if ($isList) {
                $yaml .= $spaces . "- ";
                // For lists of objects, we need to handle the first key carefully
                $firstKey = array_key_first($val);
                if ($firstKey !== null) {
                    $firstVal = $val[$firstKey];
                    if (is_array($firstVal)) {
                        $yaml .= $firstKey . ":\n" . spp_array_to_yaml($firstVal, $indent + 2);
                    } else {
                        $yaml .= $firstKey . ": " . spp_yaml_escape($firstVal) . "\n";
                    }
                    // Remaining keys
                    $rest = array_slice($val, 1, null, true);
                    if (!empty($rest)) {
                        $yaml .= spp_array_to_yaml($rest, $indent + 1);
                    }
                }
            } else {
                $yaml .= $spaces . $key . ":\n";
                $yaml .= spp_array_to_yaml($val, $indent + 1);
            }
        } else {
            if ($isList) {
                $yaml .= $spaces . "- " . spp_yaml_escape($val) . "\n";
            } else {
                $yaml .= $spaces . $key . ": " . spp_yaml_escape($val) . "\n";
            }
        }
    }
    return $yaml;
}

function spp_yaml_escape($val)
{
    if ($val === null)
        return '~';
    if (is_bool($val))
        return $val ? 'true' : 'false';
    if (is_numeric($val))
        return $val;
    if ($val === '')
        return '""';
    // Escape strings with special chars
    if (preg_match('/[:\[\]\{\}\'"\n\r]/', $val) || trim($val) !== $val) {
        return '"' . str_replace('"', '\"', $val) . '"';
    }
    return $val;
}

function spp_load_yaml_fallback($file)
{
    if (function_exists('yaml_parse_file')) {
        return yaml_parse_file($file);
    }
    // If we don't have a parser, fallback to executing Symfony/Yaml if we can find it
    // Wait, let's just attempt a simple dumb parse or include symfony if possible
    // since the user wants standard YML. If neither exist, we try to parse it via regex
    // Since SPP requires loading complex YAML, we can try to find symfony in standard SPP lib
    $symfonyYaml = __DIR__ . '/../../../../lib/vendor/autoload.php';
    if (file_exists($symfonyYaml)) {
        require_once $symfonyYaml;
        if (class_exists('\\Symfony\\Component\\Yaml\\Yaml')) {
            return \Symfony\Component\Yaml\Yaml::parseFile($file);
        }
    }
    // Super basic fallback for our generated format
    return spp_basic_yaml_parse(file_get_contents($file));
}

function spp_basic_yaml_parse($content)
{
    if (preg_match("/json_dump:\s*'(.*)'$/m", $content, $m)) {
        $json = json_decode($m[1], true);
        if ($json)
            return $json;
    }
    throw new Exception("Loading YAML requires the PECL 'yaml' extension or Symfony Yaml component.");
}

// In standard SPP module style, if hit directly or via routing:
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['report_action'] ?? $_GET['report_action'] ?? $_POST['action'] ?? $_GET['action'] ?? '';
    $payload = file_get_contents('php://input');
    // If it's x-www-form-urlencoded
    if (empty($payload) && isset($_POST['payload'])) {
        $payload = $_POST['payload'];
    }
    spp_report_api_handler($action, $payload);
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['report_action'] ?? $_GET['action'] ?? '';
    $payload = $_GET['payload'] ?? '{}';
    spp_report_api_handler($action, $payload);
}
