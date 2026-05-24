<?php
namespace Lekhak\Modules\LekhakBlocksNested;

/**
 * Provides nested entity reference capability for rich content assembly.
 * @configure admin/config/lekhak_blocks_nested
 */

class LekhakModuleParagraphs {
    private $name = 'lekhak_blocks_nested';
    private $title = 'lekhak_blocks_nested';

    public function hook_init() {
        $db = new \SPPMod\SPPDB\SPPDB();
        try {
            $db->execute_query("CREATE TABLE IF NOT EXISTS lekhak_paragraphs_item (
                item_id INTEGER PRIMARY KEY AUTOINCREMENT,
                parent_id INTEGER NOT NULL,
                parent_type VARCHAR(50) DEFAULT 'node',
                bundle VARCHAR(50) NOT NULL,
                weight INTEGER DEFAULT 0
            )");
            
            $db->execute_query("CREATE TABLE IF NOT EXISTS lekhak_paragraphs_field_data (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                item_id INTEGER,
                field_name VARCHAR(50),
                field_value TEXT,
                FOREIGN KEY(item_id) REFERENCES lekhak_paragraphs_item(item_id) ON DELETE CASCADE
            )");
        } catch (\Exception $e) {}
        
        return true;
    }

    /**
     * Intercepts entity save to process and store any attached paragraphs.
     * Expects $entity->paragraphs to be an array of paragraph item arrays.
     */
    public function hook_entity_insert($entity) {
        if (empty($entity->id) || empty($entity->paragraphs) || !is_array($entity->paragraphs)) {
            return;
        }

        $db = new \SPPMod\SPPDB\SPPDB();
        try {
            // First clear existing paragraphs for this node to handle updates
            $db->execute_query("DELETE FROM lekhak_paragraphs_item WHERE parent_id = ? AND parent_type = ?", [$entity->id, 'node']);
            
            foreach ($entity->paragraphs as $weight => $para) {
                if (empty($para['bundle'])) continue;
                
                $db->execute_query(
                    "INSERT INTO lekhak_paragraphs_item (parent_id, parent_type, bundle, weight) VALUES (?, ?, ?, ?)", 
                    [$entity->id, 'node', $para['bundle'], $weight]
                );
                
                $itemId = $db->getLastInsertId();
                if ($itemId && !empty($para['fields']) && is_array($para['fields'])) {
                    foreach ($para['fields'] as $name => $val) {
                        $db->execute_query(
                            "INSERT INTO lekhak_paragraphs_field_data (item_id, field_name, field_value) VALUES (?, ?, ?)",
                            [$itemId, $name, is_array($val) ? json_encode($val) : $val]
                        );
                    }
                }
            }
        } catch (\Exception $e) {}
    }

    /**
     * Reconstructs paragraphs when an entity is loaded and renders them into the view.
     */
    public function hook_entity_view_alter(&$build, $context = []) {
        if (!isset($context['node']) || empty($context['node']['id'])) return;

        $db = new \SPPMod\SPPDB\SPPDB();
        try {
            $items = $db->execute_query(
                "SELECT * FROM lekhak_paragraphs_item WHERE parent_id = ? AND parent_type = ? ORDER BY weight ASC", 
                [$context['node']['id'], 'node']
            );
            
            if (empty($items)) return;

            $paragraphsHtml = '<div class="paragraphs-wrapper">';
            
            foreach ($items as $item) {
                $fields = $db->execute_query("SELECT field_name, field_value FROM lekhak_paragraphs_field_data WHERE item_id = ?", [$item['item_id']]);
                
                $data = [];
                foreach ($fields as $f) {
                    $data[$f['field_name']] = $f['field_value'];
                }
                
                // Extremely basic rendering logic. 
                // A real system would dispatch to Twig templates per bundle.
                $paragraphsHtml .= '<div class="paragraph-item paragraph-type-' . htmlspecialchars($item['bundle']) . '">';
                
                if ($item['bundle'] === 'text' && !empty($data['text'])) {
                    $paragraphsHtml .= '<div class="text-content">' . $data['text'] . '</div>';
                } elseif ($item['bundle'] === 'image' && !empty($data['url'])) {
                    $alt = htmlspecialchars($data['alt'] ?? '');
                    $paragraphsHtml .= '<div class="image-content"><img src="' . htmlspecialchars($data['url']) . '" alt="' . $alt . '" /></div>';
                } else {
                    // Fallback dump
                    $paragraphsHtml .= '<pre>' . htmlspecialchars(print_r($data, true)) . '</pre>';
                }
                
                $paragraphsHtml .= '</div>';
            }
            
            $paragraphsHtml .= '</div>';
            
            // Append the rendered paragraphs to the entity build array body.
            // Normally this happens inside a dedicated field formatter.
            if (!isset($build['#body_suffix'])) {
                $build['#body_suffix'] = '';
            }
            $build['#body_suffix'] .= $paragraphsHtml;
            
        } catch (\Exception $e) {}
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
    'machine_name' => 'lekhak_blocks_nested',
    'title' => 'lekhak_blocks_nested',
    'instance' => new LekhakModuleParagraphs()
];
