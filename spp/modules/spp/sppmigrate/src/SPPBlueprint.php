<?php
namespace SPPMod\SppMigrate;

class SPPBlueprint {
    private $table;
    private $columns = [];

    public function __construct(string $table) {
        $this->table = $table;
    }

    public function id(string $name = 'id') {
        $this->columns[] = "$name INT AUTO_INCREMENT PRIMARY KEY";
        return $this;
    }

    public function string(string $name, int $length = 255) {
        $this->columns[] = "$name VARCHAR($length)";
        return $this;
    }

    public function text(string $name) {
        $this->columns[] = "$name TEXT";
        return $this;
    }

    public function timestamps() {
        $this->columns[] = "created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
        $this->columns[] = "updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
    }

    public function getTable() {
        return $this->table;
    }

    public function buildSql() {
        $cols = implode(",\n    ", $this->columns);
        return "CREATE TABLE IF NOT EXISTS {$this->table} (\n    $cols\n);";
    }
}
