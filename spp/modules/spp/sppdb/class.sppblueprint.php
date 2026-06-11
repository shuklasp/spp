<?php

namespace SPPMod\SPPMigration;

class SPPBlueprint
{
    protected string $table;
    protected array $columns = [];
    protected array $indexes = [];

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    public function id(string $name = 'id')
    {
        $this->columns[] = "{$name} INT AUTO_INCREMENT PRIMARY KEY";
        return $this;
    }

    public function string(string $name, int $length = 255)
    {
        $this->columns[] = "{$name} VARCHAR({$length})";
        return $this;
    }

    public function text(string $name)
    {
        $this->columns[] = "{$name} TEXT";
        return $this;
    }

    public function integer(string $name)
    {
        $this->columns[] = "{$name} INT";
        return $this;
    }

    public function boolean(string $name)
    {
        $this->columns[] = "{$name} TINYINT(1)";
        return $this;
    }

    public function timestamps()
    {
        $this->columns[] = "created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
        $this->columns[] = "updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
        return $this;
    }

    public function index(string $column)
    {
        $this->indexes[] = "INDEX({$column})";
        return $this;
    }

    public function toSql(): string
    {
        $parts = array_merge($this->columns, $this->indexes);
        $fields = implode(",\n    ", $parts);
        return "CREATE TABLE IF NOT EXISTS {$this->table} (\n    {$fields}\n)";
    }
}

class SPPSchema
{
    public static function create(string $table, \Closure $callback)
    {
        $blueprint = new SPPBlueprint($table);
        $callback($blueprint);
        $sql = $blueprint->toSql();

        $db = new \SPPMod\SPPDB\SPPDB();
        $db->exec($sql);
    }

    public static function dropIfExists(string $table)
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->exec("DROP TABLE IF EXISTS {$table}");
    }
}
