<?php
namespace Lekhak\Modules\LekhakSeo;

/**
 * Injects dynamic meta tags, open graph data, and SEO headers based on entity context.
 * @configure admin/config/lekhak_seo
 */

class LekhakModuleMetatag
{
    private $name = 'lekhak_seo';
    private $title = 'lekhak_seo';

    public function hook_init()
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        try {
            $db->execute_query("CREATE TABLE IF NOT EXISTS lekhak_metatags (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                entity_type VARCHAR(50),
                entity_id INTEGER,
                tag_name VARCHAR(100),
                tag_value TEXT
            )");

            // Default global metatags
            $res = $db->execute_query("SELECT id FROM lekhak_metatags WHERE entity_type='global'");
            if (empty($res)) {
                $db->execute_query("INSERT INTO lekhak_metatags (entity_type, entity_id, tag_name, tag_value) VALUES (?, ?, ?, ?)", ['global', 0, 'title', '[node:title] | [site:name]']);
                $db->execute_query("INSERT INTO lekhak_metatags (entity_type, entity_id, tag_name, tag_value) VALUES (?, ?, ?, ?)", ['global', 0, 'description', 'Default site description']);
            }
        } catch (\Exception $e) {
        }
        return true;
    }

    /**
     * Injects specific metatag headers based on entity context.
     */
    public function hook_page_meta_alter(&$meta, $context = [])
    {
        $db = new \SPPMod\SPPDB\SPPDB();

        // Load global tags
        $tags = [];
        try {
            $globalTags = $db->execute_query("SELECT tag_name, tag_value FROM lekhak_metatags WHERE entity_type='global'");
            foreach ($globalTags as $t) {
                $tags[$t['tag_name']] = $t['tag_value'];
            }

            // Load entity specific tags if we are viewing a node
            if (isset($context['node']) && isset($context['node']['id'])) {
                $nodeTags = $db->execute_query("SELECT tag_name, tag_value FROM lekhak_metatags WHERE entity_type='node' AND entity_id=?", [$context['node']['id']]);
                foreach ($nodeTags as $t) {
                    $tags[$t['tag_name']] = $t['tag_value'];
                }
            }
        } catch (\Exception $e) {
        }

        // Replace tokens
        $tokenMod = null;
        if (class_exists('\\Lekhak\\Modules\\Token\\LekhakModuleToken')) {
            $tokenMod = new \Lekhak\Modules\Token\LekhakModuleToken();
        }

        foreach ($tags as $name => $value) {
            $finalValue = $tokenMod ? $tokenMod->replaceTokens($value, $context) : $value;

            if ($name === 'title') {
                $meta['title'] = $finalValue;
            } elseif ($name === 'description') {
                $meta['tags'][] = '<meta name="description" content="' . htmlspecialchars($finalValue, ENT_QUOTES) . '">';
            } else {
                // Generic Open Graph / Twitter card etc
                $meta['tags'][] = '<meta name="' . htmlspecialchars($name, ENT_QUOTES) . '" content="' . htmlspecialchars($finalValue, ENT_QUOTES) . '">';
            }
        }
    }

    /**
     * Record custom metatags on entity save
     */
    public function hook_entity_insert($entity)
    {
        if (!empty($entity['metatags']) && is_array($entity['metatags']) && !empty($entity['id'])) {
            $db = new \SPPMod\SPPDB\SPPDB();
            foreach ($entity['metatags'] as $name => $val) {
                $db->execute_query("INSERT INTO lekhak_metatags (entity_type, entity_id, tag_name, tag_value) VALUES (?, ?, ?, ?)", ['node', $entity['id'], $name, $val]);
            }
        }
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
    'machine_name' => 'lekhak_seo',
    'title' => 'lekhak_seo',
    'instance' => new LekhakModuleMetatag()
];
