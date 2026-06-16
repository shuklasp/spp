<?php
namespace SPPMod\Lekhak\Core;

use SPPMod\SppDb\SPPEntity;

/**
 * Class LekhakTaxonomyTerm
 * Represents a category or tag in a vocabulary.
 */
class LekhakTaxonomyTerm extends SPPEntity
{
    protected ?string $storage_strategy = 'dynamic';
    protected string $table = 'lekhak_taxonomy_terms';
    protected ?string $sequence = 'lekhak_taxonomy_terms_seq';

    public function before_save()
    {
        parent::before_save();
        if (!$this->weight) {
            $this->weight = 0;
        }
        
        if (function_exists('lekhak_invoke_alter')) {
            lekhak_invoke_alter('entity_presave', $this);
        }
    }

    public function after_save()
    {
        parent::after_save();
        if (function_exists('lekhak_invoke_all')) {
            lekhak_invoke_all('entity_insert', [$this]);
        }
    }

    /**
     * Check if a given user has access to a specific operation on this taxonomy term.
     */
    public function checkAccess(string $op, $user = null): bool
    {
        if ($user === null && class_exists('\SPPMod\SPPAuth\SPPAuth')) {
            $user = \SPPMod\SPPAuth\SPPAuth::user();
        }
        
        // Admin user has all access
        if ($user && isset($user->roles) && in_array('administrator', $user->roles)) {
            return true;
        }

        // Everyone can view terms
        if ($op === 'view') {
            return true;
        }

        // Only admins or specific roles can create/update/delete taxonomy terms
        // Assuming 'editor' role can manage taxonomy
        if ($user && isset($user->roles) && in_array('editor', $user->roles)) {
            return true;
        }

        return false;
    }

    public function define_attributes()
    {
        return [
            'vid' => 'varchar(50)',
            'name' => 'varchar(255)',
            'description' => 'longtext',
            'weight' => 'int',
            'parent_id' => 'bigint'
        ];
    }
    
    public function field_metadata()
    {
        return [
            'vid' => [
                'label' => 'Vocabulary ID',
                'type' => 'text',
                'help' => 'The machine name of the vocabulary (e.g. tags, categories).'
            ],
            'name' => [
                'label' => 'Term Name',
                'type' => 'text',
                'help' => 'The display name of the term.'
            ],
            'description' => [
                'label' => 'Description',
                'type' => 'textarea',
                'help' => 'A detailed explanation of the category.'
            ],
            'weight' => [
                'label' => 'Weight',
                'type' => 'number',
                'help' => 'Used for sorting terms. Lower numbers rise to the top.'
            ],
            'parent_id' => [
                'label' => 'Parent Term',
                'type' => 'number',
                'help' => 'The ID of the parent term if this is a sub-category.'
            ]
        ];
    }
}
