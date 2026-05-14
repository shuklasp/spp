<?php
namespace SPPMod\Lekhak\Core;

/**
 * Class LandingPage
 * A specialized LekhakNode that serves as a landing page with blocks.
 */
class LandingPage extends LekhakNode
{
    protected string $table = 'landing_pages';
    
    public function after_creation()
    {
        parent::after_creation();
        $this->bundle = 'landing_page';
    }

    public function define_attributes()
    {
        $attrs = parent::define_attributes();
        $attrs['is_default'] = 'tinyint(1)';
        $attrs['layout_id'] = 'varchar(50)';
        return $attrs;
    }

    /**
     * Get all blocks for this page.
     */
    public function getBlocks(): array
    {
        if (!$this->id) return [];
        return LandingBlock::find_all(['page_id' => $this->id], 'weight ASC');
    }

    /**
     * Set this page as the default homepage.
     */
    public function setAsDefault(): void
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        $table = \SPPMod\SPPDB\SPPDB::sppTable($this->table);
        $db->execute_query("UPDATE {$table} SET is_default = 0");
        $this->is_default = 1;
        $this->save();
    }

    /**
     * Find the default landing page.
     */
    public static function getDefault(): ?LandingPage
    {
        return static::find_one(['is_default' => 1]);
    }

    public function field_metadata()
    {
        $meta = parent::field_metadata();
        $meta = array_merge($meta, [
            'title' => [
                'label' => 'Page Title',
                'placeholder' => 'e.g. Summer Marketing Campaign',
                'help' => 'Enter a descriptive title for this landing page.',
                'validations' => [
                    ['type' => 'required', 'message' => 'Page title is required.']
                ]
            ],
            'alias' => [
                'label' => 'URL Alias',
                'placeholder' => 'e.g. summer-sale',
                'help' => 'The unique URL path for this page (e.g. /lekhak/summer-sale).',
                'validations' => [
                    ['type' => 'required', 'message' => 'URL alias is required.'],
                    ['type' => 'unique', 'table' => 'landing_pages', 'column' => 'alias', 'message' => 'This URL alias is already in use.']
                ]
            ],
            'is_default' => [
                'label' => 'Set as Homepage',
                'type' => 'toggle',
                'help' => 'If enabled, this landing page will become the main entry point for your site.'
            ],
            'layout_id' => [
                'label' => 'Layout Template',
                'type' => 'select',
                'options' => [
                    'standard' => 'Standard Glass (Full width)',
                    'sidebar' => 'Content with Sidebar',
                    'minimal' => 'Minimal / Focused'
                ]
            ],
            // Internal fields that should be hidden from the form
            'author_id' => ['type' => 'hidden'],
            'bundle' => ['type' => 'hidden'],
            'created' => ['type' => 'hidden'],
            'changed' => ['type' => 'hidden'],
            'translation_id' => ['type' => 'hidden']
        ]);
        return $meta;
    }
}
