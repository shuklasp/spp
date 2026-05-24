<?php
namespace Lekhak\Modules\LekhakForms;

/**
 * A powerful form builder for creating custom surveys, contact forms, and data collection tools.
 * @configure admin/config/lekhak_forms
 */

class LekhakModuleWebform {
    private $name = 'lekhak_forms';
    private $title = 'lekhak_forms';

    public function hook_init() {
        $db = new \SPPMod\SPPDB\SPPDB();
        try {
            $db->execute_query("CREATE TABLE IF NOT EXISTS lekhak_webforms (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                machine_name VARCHAR(100) UNIQUE,
                title VARCHAR(255),
                elements TEXT,
                settings TEXT
            )");
            
            $db->execute_query("CREATE TABLE IF NOT EXISTS lekhak_webform_submissions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                webform_id INTEGER,
                data TEXT,
                submitted_at DATETIME,
                ip_address VARCHAR(45),
                FOREIGN KEY(webform_id) REFERENCES lekhak_webforms(id) ON DELETE CASCADE
            )");
            
            // Seed a contact form
            $res = $db->execute_query("SELECT id FROM lekhak_webforms LIMIT 1");
            if (empty($res)) {
                $elements = json_encode([
                    'name' => ['type' => 'text', 'title' => 'Your Name', 'required' => true],
                    'email' => ['type' => 'email', 'title' => 'Your Email', 'required' => true],
                    'message' => ['type' => 'textarea', 'title' => 'Message', 'required' => true]
                ]);
                $db->execute_query("INSERT INTO lekhak_webforms (machine_name, title, elements) VALUES (?, ?, ?)", 
                    ['contact', 'Contact Us', $elements]);
            }
        } catch (\Exception $e) {}
        
        return true;
    }

    /**
     * Renders a form directly based on the machine name.
     */
    public function renderForm($machine_name) {
        $db = new \SPPMod\SPPDB\SPPDB();
        try {
            $formDef = $db->execute_query("SELECT * FROM lekhak_webforms WHERE machine_name = ? LIMIT 1", [$machine_name]);
            if (empty($formDef)) return '';
            
            $form = $formDef[0];
            $elements = json_decode($form['elements'], true);
            if (!is_array($elements)) return '';
            
            $html = '<form class="lekhak-webform" method="POST" action="/lekhak/webform/submit/' . htmlspecialchars($machine_name) . '">';
            $html .= '<input type="hidden" name="webform_id" value="' . $form['id'] . '">';
            $html .= '<input type="hidden" name="spp_csrf_token" value="' . htmlspecialchars($_SESSION['spp_csrf_token'] ?? '') . '">';
            
            foreach ($elements as $key => $el) {
                $req = !empty($el['required']) ? 'required' : '';
                $html .= '<div class="form-group" style="margin-bottom: 1rem;">';
                $html .= '<label style="display:block; margin-bottom: .5rem; font-weight: 500;">' . htmlspecialchars($el['title']) . '</label>';
                
                if ($el['type'] === 'textarea') {
                    $html .= '<textarea name="data[' . htmlspecialchars($key) . ']" class="form-control" style="width:100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" ' . $req . '></textarea>';
                } else {
                    $html .= '<input type="' . htmlspecialchars($el['type']) . '" name="data[' . htmlspecialchars($key) . ']" class="form-control" style="width:100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" ' . $req . '>';
                }
                $html .= '</div>';
            }
            
            $html .= '<div class="form-actions"><button type="submit" class="btn" style="background: #2563eb; color: #fff; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">Submit</button></div>';
            $html .= '</form>';
            
            return $html;
        } catch (\Exception $e) {
            return '<!-- Error loading form -->';
        }
    }

    /**
     * Intercept requests to handle form submissions
     */
    public function hook_request_init() {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $uri = parse_url($uri, PHP_URL_PATH);
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($uri, '/lekhak/webform/submit/') === 0) {
            $machine_name = basename($uri);
            $webform_id = $_POST['webform_id'] ?? null;
            $data = $_POST['data'] ?? [];
            
            if ($webform_id && is_array($data)) {
                $db = new \SPPMod\SPPDB\SPPDB();
                try {
                    $db->execute_query(
                        "INSERT INTO lekhak_webform_submissions (webform_id, data, submitted_at, ip_address) VALUES (?, ?, ?, ?)",
                        [$webform_id, json_encode($data), date('Y-m-d H:i:s'), $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']
                    );
                    
                    // Simple redirect back with success query param.
                    // A true implementation would use the session to store a flash message.
                    $referer = $_SERVER['HTTP_REFERER'] ?? '/';
                    $redirect = $referer . (strpos($referer, '?') !== false ? '&' : '?') . 'webform_success=1';
                    header("Location: " . $redirect);
                    exit;
                } catch (\Exception $e) {
                    die("Form submission error: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Expose webforms as placeable blocks
     */
    public function hook_block_alter(&$blocks) {
        $db = new \SPPMod\SPPDB\SPPDB();
        try {
            $forms = $db->execute_query("SELECT machine_name, title FROM lekhak_webforms");
            foreach ($forms as $f) {
                $blocks['webform_' . $f['machine_name']] = [
                    'title' => 'Webform: ' . $f['title'],
                    'handler' => function() use ($f) {
                        return $this->renderForm($f['machine_name']);
                    }
                ];
            }
        } catch (\Exception $e) {}
    }


    // RSForm! Pro Extension
    public static function hook_webform_render_alter(&$form) {
        // Add multi-page pagination wrapper and conditional logic JS
        $form['#attributes']['class'][] = "rsform-multipage-enabled";
        $form['#attached']['js'][] = "modules/webform/js/conditional_logic.js";
    }


    /**
     * Defines the configuration form schema for this module.
     */
    public static function hook_config_form(): array
    {
        return [
  'enabled' => 
  [
    'type' => 'checkbox',
    'title' => 'Enable advanced features',
    'default' => true,
  ],
  'log_level' => 
  [
    'type' => 'select',
    'title' => 'Log Level',
    'options' => 
    [
      'info' => 'Info',
      'warning' => 'Warning',
      'error' => 'Error',
    ],
    'default' => 'warning',
  ],
];
    }
}

return [
    'status' => 'enabled',
    'machine_name' => 'lekhak_forms',
    'title' => 'lekhak_forms',
    'instance' => new LekhakModuleWebform()
];
