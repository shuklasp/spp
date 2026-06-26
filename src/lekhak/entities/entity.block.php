<?php
namespace App\Lekhak\Entities;

use SPPMod\SPPDB\SPPEntity;

/**
 * Class Block
 * Defines a regional layout block entity with visibility rules.
 */
class Block extends SPPEntity
{
    protected string $table = 'blocks';

    public function define_attributes()
    {
        return [
            'name' => 'varchar(50)', // Machine name (e.g. "recent_articles")
            'title' => 'varchar(255)', // Custom block title
            'region' => 'varchar(50)', // Target theme region (e.g. "sidebar_first")
            'visibility_paths' => 'text', // Path match patterns (newline separated, e.g. "/news/*")
            'content' => 'longtext', // HTML body markup
            'type' => 'varchar(20)', // "html" or "view"
            'weight' => 'int'
        ];
    }
}
