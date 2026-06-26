<?php
namespace SPP\Tests\Core;

use SPPMod\Parikshak\SPPTestCase;
use SPPMod\SPPDB\QueryBuilder;
use SPPMod\SPPDB\SPPDB;

// Dummy class to bypass real database connection in tests
class DummySPPDB extends SPPDB
{
    public function __construct()
    {
        // Do not call parent constructor to avoid connecting
    }
}

class SPPDBTest extends SPPTestCase
{
    private $db;

    public function setUp(): void
    {
        $this->db = new DummySPPDB();
    }

    public function testSelectAll()
    {
        $builder = new QueryBuilder($this->db, 'users');
        $sql = $builder->toSql();

        $this->assertTrue(strpos($sql, 'SELECT * FROM') !== false, "SQL should contain SELECT * FROM");
    }

    public function testSelectSpecificColumns()
    {
        $builder = new QueryBuilder($this->db, 'users');
        $builder->select(['id', 'name', 'email']);
        $sql = $builder->toSql();

        $this->assertTrue(strpos($sql, 'SELECT id, name, email FROM') !== false, "SQL should contain specific columns");
    }

    public function testWhereClause()
    {
        $builder = new QueryBuilder($this->db, 'users');
        $builder->where('status', 'active');
        $sql = $builder->toSql();
        $bindings = $builder->getBindings();

        $this->assertTrue(strpos($sql, 'WHERE status = ?') !== false, "SQL should contain WHERE clause");
        $this->assertEquals(1, count($bindings));
        $this->assertEquals('active', $bindings[0]);
    }

    public function testMultipleWhereClauses()
    {
        $builder = new QueryBuilder($this->db, 'users');
        $builder->where('status', 'active')
            ->where('age', '>', 18);
        $sql = $builder->toSql();
        $bindings = $builder->getBindings();

        $this->assertTrue(strpos($sql, 'WHERE status = ? AND age > ?') !== false, "SQL should contain multiple WHERE clauses");
        $this->assertEquals(['active', 18], $bindings);
    }

    public function testOrWhereClause()
    {
        $builder = new QueryBuilder($this->db, 'users');
        $builder->where('role', 'admin')
            ->orWhere('role', 'moderator');
        $sql = $builder->toSql();
        $bindings = $builder->getBindings();

        $this->assertTrue(strpos($sql, 'WHERE role = ? OR role = ?') !== false, "SQL should contain OR WHERE clause");
        $this->assertEquals(['admin', 'moderator'], $bindings);
    }

    public function testOrderByAndLimit()
    {
        $builder = new QueryBuilder($this->db, 'posts');
        $builder->orderBy('created_at', 'DESC')
            ->limit(10)
            ->offset(5);
        $sql = $builder->toSql();

        $this->assertTrue(strpos($sql, 'ORDER BY created_at DESC') !== false, "SQL should contain ORDER BY");
        $this->assertTrue(strpos($sql, 'LIMIT 10') !== false, "SQL should contain LIMIT");
        $this->assertTrue(strpos($sql, 'OFFSET 5') !== false, "SQL should contain OFFSET");
    }
}
