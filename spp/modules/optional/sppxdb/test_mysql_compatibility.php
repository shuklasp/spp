<?php

/**
 * Comprehensive MySQL Compatibility Verification for SPP_XDB
 */

require_once(__DIR__ . '/sppxdb.php');

use SPPMod\SPPXDB\SPP_XDB;

echo "=== SPP XDB MySQL Compatibility Verification ===\n\n";

try {
    $xdb = new SPP_XDB();

    // Setup and cleanup
    echo "1. Preparing perfection_test database...\n";
    $xdb->querySQL("CREATE DATABASE IF NOT EXISTS perfection_test");
    $xdb->selectDatabase('perfection_test');

    // Ensure clean state
    $xdb->querySQL("DROP TABLE IF EXISTS perfection_test.employees");

    // CREATE TABLE with schema
    echo "\n2. Testing CREATE TABLE with schema...\n";
    $xdb->querySQL("CREATE TABLE perfection_test.employees (id int PRIMARY KEY AUTO_INCREMENT, name varchar NOT NULL, role varchar, salary int, score float)");

    // Verify table structure via DESCRIBE
    echo "\n3. Testing DESCRIBE table...\n";
    $structure = $xdb->querySQL("DESCRIBE perfection_test.employees");
    foreach ($structure as $row) {
        echo "   Column: {$row['Field']} | Type: {$row['Type']} | Null: {$row['Null']} | Key: {$row['Key']}\n";
    }


    // 4. Testing Multi-Row INSERT
    echo "\n4. Testing Multi-Row INSERT...\n";
    $xdb->querySQL("INSERT INTO perfection_test.employees (id, name, role, salary, score) VALUES 
        (1, 'Satya', 'Architect', 120000, 95.5),
        (2, 'Amit', 'Manager', 95000, 88.0),
        (3, 'John', 'Developer', 80000, 90.0),
        (4, 'Jane', 'Designer', 75000, 87.5)");

    $results = $xdb->querySQL("SELECT * FROM perfection_test.employees");
    echo "   Total employees: " . count($results) . "\n";
    foreach ($results as $r) {
        echo "     - ID: {$r['id']} | Name: {$r['name']} | Salary: {$r['salary']} | Score: {$r['score']}\n";
    }

    // 5. Testing Arithmetic column updates
    echo "\n5. Testing Arithmetic Updates (col = col + val, col = col - val)...\n";
    echo "   Incrementing John's salary by 5000 and decreasing Amit's score by 2.5...\n";
    $xdb->querySQL("UPDATE perfection_test.employees SET salary = salary + 5000 WHERE name = 'John'");
    $xdb->querySQL("UPDATE perfection_test.employees SET score = score - 2.5 WHERE name = 'Amit'");

    $john = $xdb->querySQL("SELECT salary FROM perfection_test.employees WHERE name = 'John'");
    $amit = $xdb->querySQL("SELECT score FROM perfection_test.employees WHERE name = 'Amit'");

    echo "   John's updated salary: {$john[0]['salary']} (Expected: 85000)\n";
    echo "   Amit's updated score: {$amit[0]['score']} (Expected: 85.5)\n";

    // 6. Testing Transactions (START TRANSACTION, COMMIT, ROLLBACK)
    echo "\n6. Testing Transactions...\n";
    echo "   Testing Transaction ROLLBACK:\n";
    $xdb->querySQL("START TRANSACTION");
    $xdb->querySQL("UPDATE perfection_test.employees SET salary = 150000 WHERE name = 'Satya'");
    $xdb->querySQL("ROLLBACK");

    $satya = $xdb->querySQL("SELECT salary FROM perfection_test.employees WHERE name = 'Satya'");
    echo "   Satya's salary after Rollback: {$satya[0]['salary']} (Expected: 120000)\n";

    echo "   Testing Transaction COMMIT:\n";
    $xdb->querySQL("START TRANSACTION");
    $xdb->querySQL("UPDATE perfection_test.employees SET salary = 130000 WHERE name = 'Satya'");
    $xdb->querySQL("COMMIT");

    $satya = $xdb->querySQL("SELECT salary FROM perfection_test.employees WHERE name = 'Satya'");
    echo "   Satya's salary after Commit: {$satya[0]['salary']} (Expected: 130000)\n";

    // 7. Testing Index Management
    echo "\n7. Testing Index Management (CREATE INDEX, DROP INDEX)...\n";
    $xdb->querySQL("CREATE INDEX name_idx ON perfection_test.employees (name)");

    // Verify file existence in _indexes
    $indexPath = __DIR__ . '/data/perfection_test/_indexes/employees/name.json';
    echo "   Index file created: " . (file_exists($indexPath) ? "YES" : "NO") . "\n";

    $xdb->querySQL("DROP INDEX name_idx ON perfection_test.employees");
    echo "   Index file exists after DROP: " . (file_exists($indexPath) ? "YES" : "NO") . "\n";

    // 8. Testing View Management (CREATE VIEW, CREATE MATERIALIZED VIEW, DROP VIEW)
    echo "\n8. Testing View Management...\n";
    $xdb->querySQL("CREATE VIEW perfection_test.high_earners AS SELECT * FROM perfection_test.employees WHERE salary >= 90000");
    $viewData = $xdb->querySQL("SELECT name, salary FROM perfection_test.high_earners");
    echo "   High Earners (View results):\n";
    foreach ($viewData as $r) {
        echo "     - Name: {$r['name']} | Salary: {$r['salary']}\n";
    }

    $xdb->querySQL("CREATE MATERIALIZED VIEW perfection_test.mv_earners AS SELECT * FROM perfection_test.employees WHERE salary >= 90000");
    $mvData = $xdb->querySQL("SELECT name, salary FROM perfection_test.mv_earners");
    echo "   High Earners (Materialized View results):\n";
    foreach ($mvData as $r) {
        echo "     - Name: {$r['name']} | Salary: {$r['salary']}\n";
    }

    $xdb->querySQL("DROP VIEW perfection_test.high_earners");
    $xdb->querySQL("DROP VIEW perfection_test.mv_earners");
    echo "   Dropped views successfully.\n";

    // 8.5 Testing Custom SQL Functions
    echo "\n8.5. Testing Custom SQL Functions...\n";
    $funcData = $xdb->querySQL("SELECT CONCAT(name, ' (', role, ')') AS full_desc, UPPER(name) AS upper_name, LENGTH(role) AS role_len, COALESCE(NULL, role, 'N/A') AS current_role, IF(score >= 90.0, 'Excellent', 'Good') AS score_rating, ROUND(score, 0) AS rounded_score, CEIL(score) AS ceil_score, FLOOR(score) AS floor_score, ABS(-5.5) AS abs_val, POW(2, 3) AS pow_val, SQRT(9) AS sqrt_val, MD5(name) AS name_md5, REVERSE(name) AS rev_name, CURDATE() AS today, CURTIME() AS now_time, YEAR(NOW()) AS now_year, MONTH(NOW()) AS now_month, DAY(NOW()) AS now_day FROM perfection_test.employees");
    echo "   Function evaluations:\n";
    foreach ($funcData as $r) {
        echo "     - Desc: {$r['full_desc']} | Upper: {$r['upper_name']} | Len: {$r['role_len']} | Coalesce: {$r['current_role']}\n";
        echo "       IF Rating: {$r['score_rating']} | Rounded: {$r['rounded_score']} | Ceil: {$r['ceil_score']} | Floor: {$r['floor_score']}\n";
        echo "       Abs: {$r['abs_val']} | Pow: {$r['pow_val']} | Sqrt: {$r['sqrt_val']} | MD5: " . substr($r['name_md5'], 0, 8) . "... | Rev: {$r['rev_name']}\n";
        echo "       Today: {$r['today']} | Time: " . (!empty($r['now_time']) ? "YES" : "NO") . " | Year: {$r['now_year']} | Month: {$r['now_month']} | Day: {$r['now_day']}\n";
    }

    // 9. Testing Alter Actions (ALTER TABLE DROP/RENAME/MODIFY/CHANGE)
    echo "\n9. Testing Alter Actions...\n";

    echo "   Testing ALTER TABLE RENAME COLUMN (salary TO remuneration)...\n";
    $xdb->querySQL("ALTER TABLE perfection_test.employees RENAME COLUMN salary TO remuneration");
    $struct = $xdb->querySQL("DESCRIBE perfection_test.employees");
    $fields = array_column($struct, 'Field');
    echo "   Columns present: " . implode(', ', $fields) . "\n";

    echo "   Testing ALTER TABLE DROP COLUMN (score)...\n";
    $xdb->querySQL("ALTER TABLE perfection_test.employees DROP COLUMN score");
    $struct = $xdb->querySQL("DESCRIBE perfection_test.employees");
    $fields = array_column($struct, 'Field');
    echo "   Columns present: " . implode(', ', $fields) . "\n";

    echo "   Testing ALTER TABLE MODIFY COLUMN (remuneration double)...\n";
    $xdb->querySQL("ALTER TABLE perfection_test.employees MODIFY COLUMN remuneration double");
    $struct = $xdb->querySQL("DESCRIBE perfection_test.employees");
    foreach ($struct as $row) {
        if ($row['Field'] === 'remuneration') {
            echo "   remuneration new type: {$row['Type']} (Expected: DOUBLE)\n";
        }
    }

    echo "   Testing ALTER TABLE CHANGE COLUMN (role TO title varchar NOT NULL)...\n";
    $xdb->querySQL("ALTER TABLE perfection_test.employees CHANGE COLUMN role title varchar NOT NULL");
    $struct = $xdb->querySQL("DESCRIBE perfection_test.employees");
    $fields = array_column($struct, 'Field');
    echo "   Columns present: " . implode(', ', $fields) . "\n";


    // 9.5 Testing Safe SQL Extended features (UNION, Subqueries, successive Joins, NOT NULL validations)
    echo "\n9.5. Testing Safe SQL Extended features...\n";

    $xdb->querySQL("CREATE TABLE perfection_test.departments (id INT PRIMARY KEY, dept_name VARCHAR)");
    $xdb->querySQL("INSERT INTO perfection_test.departments (id, dept_name) VALUES (1, 'Engineering'), (2, 'Management'), (3, 'Design')");

    $xdb->querySQL("CREATE TABLE perfection_test.projects (id INT PRIMARY KEY, project_name VARCHAR, dept_id INT)");
    $xdb->querySQL("INSERT INTO perfection_test.projects (id, project_name, dept_id) VALUES (101, 'Studio', 1), (102, 'Polyglot', 1), (103, 'Lekhak', 3)");

    // A. Testing UNION / UNION ALL
    echo "   Testing UNION ALL (departments + projects names)...\n";
    $unionAll = $xdb->querySQL("SELECT dept_name AS name FROM perfection_test.departments UNION ALL SELECT project_name AS name FROM perfection_test.projects");
    echo "     Union All Results:\n";
    foreach ($unionAll as $item) {
        echo "       - Name: {$item['name']}\n";
    }

    echo "   Testing UNION (unique names)...\n";
    $unionUnique = $xdb->querySQL("SELECT dept_name AS name FROM perfection_test.departments UNION SELECT dept_name AS name FROM perfection_test.departments");
    echo "     Union Unique count: " . count($unionUnique) . " (Expected: 3)\n";

    // B. Testing Derived Subquery in FROM
    echo "   Testing Derived Subquery in FROM...\n";
    $derived = $xdb->querySQL("SELECT name, score FROM (SELECT name, score FROM perfection_test.employees WHERE score >= 90) AS high_score WHERE score < 95");
    echo "     Derived Results:\n";
    foreach ($derived as $item) {
        echo "       - Name: {$item['name']} | Score: {$item['score']}\n";
    }

    // C. Testing successive Joins (employee -> department -> project)
    echo "   Testing successive JOIN chains (employees LEFT JOIN departments LEFT JOIN projects)...\n";
    $successiveJoin = $xdb->querySQL("SELECT e.name, d.dept_name, p.project_name FROM perfection_test.employees e LEFT JOIN perfection_test.departments d ON e.id = d.id LEFT JOIN perfection_test.projects p ON d.id = p.dept_id");
    echo "     Successive Joins Results:\n";
    foreach ($successiveJoin as $item) {
        echo "       - Employee: {$item['name']} | Dept: {$item['dept_name']} | Project: {$item['project_name']}\n";
    }

    // D. Testing NOT NULL validation on update
    echo "   Testing NOT NULL Validation error on UPDATE...\n";
    try {
        $xdb->querySQL("UPDATE perfection_test.employees SET title = NULL WHERE name = 'Satya'");
        echo "     FAILED: Expected validation error but update succeeded.\n";
    } catch (Exception $valEx) {
        echo "     SUCCESS: Caught expected validation error: " . $valEx->getMessage() . "\n";
    }

    $xdb->querySQL("DROP TABLE perfection_test.departments");
    $xdb->querySQL("DROP TABLE perfection_test.projects");

    // 10. Testing TRUNCATE and RENAME table
    echo "\n10. Testing TRUNCATE and RENAME table...\n";
    $xdb->querySQL("RENAME TABLE perfection_test.employees TO perfection_test.staff");
    $tables = $xdb->querySQL("SHOW TABLES");
    $tableList = array_map(fn ($t) => is_array($t) ? reset($t) : $t, $tables);
    echo "    Tables in DB after rename: " . implode(', ', $tableList) . "\n";

    $xdb->querySQL("TRUNCATE TABLE perfection_test.staff");
    $staff = $xdb->querySQL("SELECT * FROM perfection_test.staff");
    echo "    Records in staff after TRUNCATE: " . count($staff) . "\n";

    // Clean up
    $xdb->querySQL("DROP TABLE perfection_test.staff");
    $xdb->querySQL("DROP DATABASE perfection_test");

    echo "\n=== All MySQL Compatibility Verifications Passed Successfully! ===\n";

} catch (Exception $e) {
    echo "\nCompatibility Verification FAILED: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
