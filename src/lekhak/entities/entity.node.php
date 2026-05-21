<?php
namespace App\Lekhak\Entities;

use SPPMod\Lekhak\Core\LekhakNode;

/**
 * Entity registration for LekhakNode.
 */
class Node extends LekhakNode 
{
    // This allows the framework to recognize it as an entity of the 'lekhak' app

    public function after_save()
    {
        parent::after_save();

        if (class_exists('\\App\\Lekhak\\Services\\NodeRevisionStorage')) {
            $storage = new \App\Lekhak\Services\NodeRevisionStorage();
            $msg = $_POST['revision_log'] ?? 'Auto-saved revision';
            try {
                $storage->saveRevision($this, $msg);
            } catch (\Exception $e) {
                error_log("Failed to save revision for Node {$this->id}: " . $e->getMessage());
            }
        }
    }
}
