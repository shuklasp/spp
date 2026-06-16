<?php

namespace SPPMod\SPPMigrate\Migrations;

use SPPMod\SPPDB\SPPDB;
use SPPMod\SPPMigrate\Migration;

class CreateSppRouterTables extends Migration
{
    public function up(): void
    {
        $db = new SPPDB();
        $isSqlite = ($db->getDriver() === 'sqlite');

        if ($isSqlite) {
            $db->execute_query('CREATE TABLE IF NOT EXISTS ' . SPPDB::sppTable('spprouter_pages') . ' (
                id    INTEGER PRIMARY KEY AUTOINCREMENT,
                name  VARCHAR(255) NOT NULL UNIQUE,
                url   VARCHAR(500) NOT NULL
            )');

            $db->execute_query('CREATE TABLE IF NOT EXISTS ' . SPPDB::sppTable('spprouter_defaults') . ' (
                id     INTEGER PRIMARY KEY AUTOINCREMENT,
                defkey VARCHAR(100) NOT NULL UNIQUE,
                defval VARCHAR(500) NOT NULL
            )');

            $db->execute_query('CREATE TABLE IF NOT EXISTS ' . SPPDB::sppTable('spprouter_specials') . ' (
                id     INTEGER PRIMARY KEY AUTOINCREMENT,
                name   VARCHAR(100) NOT NULL UNIQUE,
                method VARCHAR(100) NOT NULL
            )');
        } else {
            $db->execute_query('CREATE TABLE IF NOT EXISTS ' . SPPDB::sppTable('spprouter_pages') . ' (
                id    INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
                name  VARCHAR(255) NOT NULL UNIQUE,
                url   VARCHAR(500) NOT NULL
            )');

            $db->execute_query('CREATE TABLE IF NOT EXISTS ' . SPPDB::sppTable('sppview_defaults') . ' (
                id     INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
                defkey VARCHAR(100) NOT NULL UNIQUE,
                defval VARCHAR(500) NOT NULL
            )');

            $db->execute_query('CREATE TABLE IF NOT EXISTS ' . SPPDB::sppTable('sppview_specials') . ' (
                id     INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
                name   VARCHAR(100) NOT NULL UNIQUE,
                method VARCHAR(100) NOT NULL
            )');
        }
    }

    public function down(): void
    {
        $db = new SPPDB();
        $db->execute_query('DROP TABLE IF EXISTS ' . SPPDB::sppTable('spprouter_pages'));
        $db->execute_query('DROP TABLE IF EXISTS ' . SPPDB::sppTable('spprouter_defaults'));
        $db->execute_query('DROP TABLE IF EXISTS ' . SPPDB::sppTable('spprouter_specials'));
    }
}
