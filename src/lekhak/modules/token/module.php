<?php
namespace Lekhak\Modules\Token;

/**
 * Provides a central API for replacing placeholders (tokens) with dynamic data values.
 * @configure admin/config/token
 */

class LekhakModuleToken {
    private $name = 'token';
    private $title = 'Token';

    /**
     * Initializes the module and ensures database schema exists.
     */
    public function hook_init() {
        $db = new \SPPMod\SPPDB\SPPDB();
        try {
            $db->execute_query("CREATE TABLE IF NOT EXISTS lekhak_token_registry (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                token_type VARCHAR(50),
                token_name VARCHAR(100),
                description TEXT
            )");
        } catch (\Exception $e) {}
        
        // Register default site tokens
        $this->registerToken('site', 'name', 'The name of the website.');
        $this->registerToken('site', 'url', 'The base URL of the website.');
        $this->registerToken('node', 'title', 'The title of the current node.');
        $this->registerToken('node', 'id', 'The ID of the current node.');
        
        return true;
    }

    /**
     * Registers a token in the registry.
     */
    public function registerToken($type, $name, $desc) {
        $db = new \SPPMod\SPPDB\SPPDB();
        $exists = $db->execute_query("SELECT id FROM lekhak_token_registry WHERE token_type=? AND token_name=?", [$type, $name]);
        if (empty($exists)) {
            $db->execute_query("INSERT INTO lekhak_token_registry (token_type, token_name, description) VALUES (?, ?, ?)", [$type, $name, $desc]);
        }
    }

    /**
     * Core API Function: Replaces tokens in a given string.
     * 
     * @param string $text The text containing tokens like [node:title].
     * @param array $data Contextual data (e.g. ['node' => $nodeObject]).
     * @return string The parsed string.
     */
    public function replaceTokens($text, $data = []) {
        if (empty($text)) return $text;

        preg_match_all('/\[([^\]:]+):([^\]]+)\]/', $text, $matches);
        if (empty($matches[0])) return $text;

        $replacements = [];
        foreach ($matches[0] as $index => $fullToken) {
            $type = $matches[1][$index];
            $name = $matches[2][$index];
            $replacement = $fullToken; // Default to unreplaced

            if ($type === 'site') {
                if ($name === 'name') $replacement = 'Lekhak CMS';
                if ($name === 'url') $replacement = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
            } elseif ($type === 'node' && isset($data['node'])) {
                $node = $data['node'];
                if ($name === 'title' && isset($node['title'])) $replacement = $node['title'];
                if ($name === 'id' && isset($node['id'])) $replacement = $node['id'];
            }

            // Fire hook_token_replace for other modules to supply values
            if (function_exists('lekhak_invoke_all')) {
                $custom_replacements = lekhak_invoke_all('token_replace', [$type, $name, $data]);
                foreach ($custom_replacements as $cr) {
                    if ($cr !== null) $replacement = $cr;
                }
            }

            $replacements[$fullToken] = $replacement;
        }

        return strtr($text, $replacements);
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
    'machine_name' => 'token',
    'title' => 'Token',
    'instance' => new LekhakModuleToken()
];
