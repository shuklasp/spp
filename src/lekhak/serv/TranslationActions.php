<?php
namespace App\Lekhak\Serv;

/**
 * LiveAction functions for Interface Translation management.
 */

function live_Translation_List($la, $params) {
    $db = new \SPPMod\SPPDB\SPPDB();
    $table = \SPPMod\SPPDB\SPPDB::sppTable('translations');

    if (!$db->tableExists($table)) {
        return $la->setData(['translations' => []]);
    }

    $locale = $params['locale'] ?? 'en';
    
    $sql = "SELECT id, key_code, translation, status FROM {$table} WHERE locale = ? ORDER BY key_code ASC";
    $rows = $db->execute_query($sql, [$locale]);

    $la->setData(['translations' => $rows]);
}

function live_Translation_Save($la, $params) {
    $db = new \SPPMod\SPPDB\SPPDB();
    $table = \SPPMod\SPPDB\SPPDB::sppTable('translations');

    if (!$db->tableExists($table)) {
        // Create schema on demand
        $isSqlite = $db->getDriver() === 'sqlite';
        if ($isSqlite) {
            $db->execute_query("CREATE TABLE {$table} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                key_code VARCHAR(255) NOT NULL,
                locale VARCHAR(10) NOT NULL,
                translation TEXT,
                status VARCHAR(20) DEFAULT 'active',
                UNIQUE(key_code, locale)
            )");
        } else {
            $db->execute_query("CREATE TABLE {$table} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                key_code VARCHAR(255) NOT NULL,
                locale VARCHAR(10) NOT NULL,
                translation TEXT,
                status VARCHAR(20) DEFAULT 'active',
                UNIQUE KEY `idx_key_locale` (`key_code`, `locale`)
            )");
        }
    }

    $id = $params['id'] ?? null;
    $keyCode = $params['key_code'] ?? '';
    $locale = $params['locale'] ?? 'en';
    $translation = $params['translation'] ?? '';
    $status = $params['status'] ?? 'active';

    if (empty($keyCode)) {
        return $la->setStatus('error')->notify("Key code is required.");
    }

    if ($id) {
        $sql = "UPDATE {$table} SET key_code = ?, translation = ?, status = ? WHERE id = ?";
        $db->execute_query($sql, [$keyCode, $translation, $status, $id]);
        $la->notify("Translation updated successfully.", "success");
    } else {
        $sql = "INSERT INTO {$table} (key_code, locale, translation, status) VALUES (?, ?, ?, ?)";
        // Need to handle duplicates
        try {
            $db->execute_query($sql, [$keyCode, $locale, $translation, $status]);
            $la->notify("Translation added successfully.", "success");
        } catch (\Exception $e) {
            $la->setStatus('error')->notify("Error adding translation. Key might already exist for this locale.");
        }
    }
}

function live_Translation_Delete($la, $params) {
    $id = $params['id'] ?? null;
    if (!$id) return $la->setStatus('error')->notify("ID required.");

    $db = new \SPPMod\SPPDB\SPPDB();
    $table = \SPPMod\SPPDB\SPPDB::sppTable('translations');

    if ($db->tableExists($table)) {
        $db->execute_query("DELETE FROM {$table} WHERE id = ?", [$id]);
        $la->notify("Translation deleted.", "success");
    }
}
