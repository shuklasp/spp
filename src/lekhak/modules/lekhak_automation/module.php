<?php
namespace Lekhak\Modules\LekhakAutomation;

/**
 * Triggers automated actions and workflows based on predefined events and conditions.
 * @configure admin/config/lekhak_automation
 */

class LekhakModuleRules {
    private $name = 'lekhak_automation';
    private $title = 'lekhak_automation';

    public function hook_init() {
        $db = new \SPPMod\SPPDB\SPPDB();
        try {
            $db->execute_query("CREATE TABLE IF NOT EXISTS lekhak_rules (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                machine_name VARCHAR(100) UNIQUE,
                event_name VARCHAR(100),
                conditions TEXT,
                actions TEXT,
                status INTEGER DEFAULT 1
            )");
            
            // Seed a test rule
            $res = $db->execute_query("SELECT id FROM lekhak_rules LIMIT 1");
            if (empty($res)) {
                $conditions = json_encode([
                    ['type' => 'data_is', 'data' => '[node:status]', 'value' => 'published']
                ]);
                $actions = json_encode([
                    ['type' => 'system_message', 'message' => 'Node successfully published via Rules!']
                ]);
                $db->execute_query("INSERT INTO lekhak_rules (machine_name, event_name, conditions, actions) VALUES (?, ?, ?, ?)", 
                    ['notify_publish', 'entity_insert', $conditions, $actions]);
            }
        } catch (\Exception $e) {}
        return true;
    }

    /**
     * Engine evaluator that checks if all conditions pass.
     */
    private function evaluateConditions($conditionsJson, $context) {
        $conditions = json_decode($conditionsJson, true);
        if (empty($conditions)) return true;

        $tokenMod = null;
        if (class_exists('\\Lekhak\\Modules\\Token\\LekhakModuleToken')) {
            $tokenMod = new \Lekhak\Modules\Token\LekhakModuleToken();
        }

        foreach ($conditions as $cond) {
            if ($cond['type'] === 'data_is') {
                $val1 = $tokenMod ? $tokenMod->replaceTokens($cond['data'], $context) : $cond['data'];
                if ($val1 !== $cond['value']) return false;
            }
        }
        return true;
    }

    /**
     * Executes actions when conditions are met.
     */
    private function executeActions($actionsJson, $context) {
        $actions = json_decode($actionsJson, true);
        if (empty($actions)) return;

        $tokenMod = null;
        if (class_exists('\\Lekhak\\Modules\\Token\\LekhakModuleToken')) {
            $tokenMod = new \Lekhak\Modules\Token\LekhakModuleToken();
        }

        foreach ($actions as $action) {
            if ($action['type'] === 'system_message') {
                $msg = $tokenMod ? $tokenMod->replaceTokens($action['message'], $context) : $action['message'];
                // Write to session flash messages
                $_SESSION['spp_messages'][] = ['type' => 'info', 'text' => $msg];
            } elseif ($action['type'] === 'send_email') {
                // Mock email action
                $to = $tokenMod ? $tokenMod->replaceTokens($action['to'] ?? '', $context) : ($action['to'] ?? '');
                $subj = $tokenMod ? $tokenMod->replaceTokens($action['subject'] ?? '', $context) : ($action['subject'] ?? '');
                error_log("RULES ENGINE -> Sending Email to $to: $subj");
            }
        }
    }

    /**
     * Generic Event Dispatcher. We hook into common CMS events.
     */
    private function dispatchEvent($event_name, $context) {
        $db = new \SPPMod\SPPDB\SPPDB();
        try {
            $rules = $db->execute_query("SELECT * FROM lekhak_rules WHERE event_name = ? AND status = 1", [$event_name]);
            foreach ($rules as $rule) {
                if ($this->evaluateConditions($rule['conditions'], $context)) {
                    $this->executeActions($rule['actions'], $context);
                }
            }
        } catch (\Exception $e) {}
    }

    /**
     * Intercept entity insertion
     */
    public function hook_entity_insert($entity) {
        // Expose entity as node context
        $this->dispatchEvent('entity_insert', ['node' => (array)$entity]);
    }

    /**
     * Intercept entity update/presave
     */
    public function hook_entity_presave(&$entity) {
        $this->dispatchEvent('entity_presave', ['node' => (array)$entity]);
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
    'machine_name' => 'lekhak_automation',
    'title' => 'lekhak_automation',
    'instance' => new LekhakModuleRules()
];
