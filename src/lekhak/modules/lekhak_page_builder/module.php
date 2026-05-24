<?php
namespace Lekhak\Modules\LekhakPageBuilder;

/**
 * A visual, front-end page builder allowing non-technical users to design landing pages.
 * @configure admin/config/lekhak_page_builder
 */

class LekhakModulePanelizer {
    private $name = 'lekhak_page_builder';
    private $title = 'lekhak_page_builder';

    public function hook_init() {
        $db = new \SPPMod\SPPDB\SPPDB();
        try {
            $db->execute_query("CREATE TABLE IF NOT EXISTS lekhak_panelizer_layouts (
                entity_id INTEGER PRIMARY KEY,
                layout_name VARCHAR(100),
                layout_settings TEXT
            )");
        } catch (\Exception $e) {}
        
        return true;
    }

    /**
     * Intercepts node saves to store specific layout assignments.
     */
    public function hook_entity_insert($entity) {
        if (!empty($entity->id) && !empty($entity->layout_override)) {
            $db = new \SPPMod\SPPDB\SPPDB();
            $db->execute_query("REPLACE INTO lekhak_panelizer_layouts (entity_id, layout_name, layout_settings) VALUES (?, ?, ?)", 
                [$entity->id, $entity->layout_override, json_encode($entity->layout_settings ?? [])]);
        }
    }

    /**
     * Modifies the actual render pipeline of the node to wrap it in the chosen layout.
     */
    public function hook_entity_view_alter(&$build, $context = []) {
        if (!isset($context['node']) || empty($context['node']['id'])) return;

        $db = new \SPPMod\SPPDB\SPPDB();
        try {
            $layout = $db->execute_query("SELECT layout_name FROM lekhak_panelizer_layouts WHERE entity_id = ? LIMIT 1", [$context['node']['id']]);
            if (!empty($layout)) {
                $layoutName = $layout[0]['layout_name'];
                
                // Here we would normally load a Twig/Blade layout.
                // For demonstration, we simply wrap the build in a container class.
                $build['#prefix'] = '<div class="panelizer-wrapper layout-' . htmlspecialchars($layoutName) . '">';
                $build['#suffix'] = '</div>';
            }
        } catch (\Exception $e) {}
    }


    // SP Page Builder Extension
    public static function hook_block_render_alter(&$block) {
        // Add SP Page Builder style block animations
        $block["#attributes"]["data-aos"] = "fade-up";
        $block["#attributes"]["class"][] = "sppagebuilder-addon";
    }
    public static function hook_page_bottom() {
        // Inject drag-and-drop frontend editor JS
        if ($_SESSION["spp_admin_fallback"] ?? false) {
            return "<script src=\"modules/panelizer/js/sp_page_builder_dnd.js\"></script>";
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
    'machine_name' => 'lekhak_page_builder',
    'title' => 'lekhak_page_builder',
    'instance' => new LekhakModulePanelizer()
];
