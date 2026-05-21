<?php
namespace App\Lekhak\Entities;

use SPPMod\SPPEntity\SPPEntity;

/**
 * Class Term
 * Defines a taxonomy term within a vocabulary, supporting parent-child hierarchy.
 */
class Term extends SPPEntity
{
    protected string $table = 'terms';

    public function define_attributes()
    {
        return [
            'vid' => 'varchar(50)', // Vocabulary machine name or ID
            'name' => 'varchar(255)', // Term name (e.g. "Science")
            'parent_id' => 'int', // Parent term ID for hierarchical support
            'description' => 'text',
            'weight' => 'int'
        ];
    }

    /**
     * Get parent term of this term.
     */
    public function getParent(): ?Term
    {
        $parentId = $this->get('parent_id');
        if (empty($parentId)) {
            return null;
        }
        return self::find_one(['id' => $parentId]);
    }

    /**
     * Get direct children terms of this term.
     */
    public function getChildren(): array
    {
        return self::find_all(['parent_id' => $this->getId()], 'weight ASC');
    }
}
