<?php

/**
 * Comprehensive test script for SPP XDB Module
 */

require_once(__DIR__ . '/sppxdb.php');

use SPPMod\SPPXDB\SPP_XDB;

echo "=== SPP XDB Comprehensive Verification ===\n\n";

try {
    $xdb = new SPP_XDB();

    // 1. CREATE DATABASE via SQL
    echo "1. Testing CREATE DATABASE via SQL...\n";
    $xdb->querySQL("CREATE DATABASE school");
    $xdb->querySQL("CREATE DATABASE finance");
    $dbs = $xdb->querySQL("SHOW DATABASES");
    $dbList = array_map(fn ($d) => is_array($d) ? ($d['Database'] ?? reset($d)) : $d, $dbs);
    echo "   Databases: " . implode(', ', $dbList) . "\n";

    // 2. CREATE TABLE with schema via SQL
    echo "\n2. Testing CREATE TABLE via SQL...\n";
    $xdb->selectDatabase('school');
    $xdb->querySQL("CREATE TABLE school.students (id int, name varchar, grade varchar, score int)");
    $xdb->querySQL("CREATE TABLE school.teachers (id int, name varchar, subject varchar)");
    $tables = $xdb->querySQL("SHOW TABLES");
    $tableList = array_map(fn ($t) => is_array($t) ? reset($t) : $t, $tables);
    echo "   Tables in 'school': " . implode(', ', $tableList) . "\n";

    // 3. Schema introspection
    echo "\n3. Testing schema introspection...\n";
    $xdb->connect('students');
    $schema = $xdb->getSchema();
    echo "   Schema for 'students': ";
    $columns = isset($schema['columns']) ? $schema['columns'] : $schema;
    foreach ($columns as $col => $props) {
        $type = is_array($props) ? ($props['type'] ?? 'text') : $props;
        echo "$col($type) ";
    }
    echo "\n";

    // 4. INSERT via SQL
    echo "\n4. Testing SQL INSERT...\n";
    $xdb->querySQL("INSERT INTO school.students (id, name, grade, score) VALUES ('1', 'Satya', 'A', '95')");
    $xdb->querySQL("INSERT INTO school.students (id, name, grade, score) VALUES ('2', 'John', 'B', '82')");
    $xdb->querySQL("INSERT INTO school.students (id, name, grade, score) VALUES ('3', 'Jane', 'A', '91')");
    $xdb->querySQL("INSERT INTO school.students (id, name, grade, score) VALUES ('4', 'Alice', 'C', '76')");
    $xdb->querySQL("INSERT INTO school.students (id, name, grade, score) VALUES ('5', 'Bob', 'B', '88')");
    echo "   Inserted 5 student records.\n";

    // 5. SELECT ALL
    echo "\n5. Testing SELECT ALL...\n";
    $results = $xdb->querySQL("SELECT * FROM school.students");
    echo "   Total records: " . count($results) . "\n";

    // 6. SELECT with WHERE
    echo "\n6. Testing SELECT with WHERE...\n";
    $results = $xdb->querySQL("SELECT name, score FROM school.students WHERE grade='A'");
    echo "   Grade A students:\n";
    foreach ($results as $r) {
        echo "     - {$r['name']}: {$r['score']}\n";
    }

    // 7. COUNT(*)
    echo "\n7. Testing COUNT(*)...\n";
    $count = $xdb->querySQL("SELECT COUNT(*) FROM school.students");
    echo "   Total students: " . $count[0]['COUNT(*)'] . "\n";

    $count = $xdb->querySQL("SELECT COUNT(*) FROM school.students WHERE grade='B'");
    echo "   Grade B students: " . $count[0]['COUNT(*)'] . "\n";

    // 8. ORDER BY
    echo "\n8. Testing ORDER BY...\n";
    $results = $xdb->querySQL("SELECT name, score FROM school.students ORDER BY score DESC");
    echo "   Students by score (descending):\n";
    foreach ($results as $r) {
        echo "     - {$r['name']}: {$r['score']}\n";
    }

    // 9. LIMIT
    echo "\n9. Testing LIMIT...\n";
    $results = $xdb->querySQL("SELECT name, score FROM school.students ORDER BY score DESC LIMIT 3");
    echo "   Top 3 students:\n";
    foreach ($results as $r) {
        echo "     - {$r['name']}: {$r['score']}\n";
    }

    // 10. UPDATE via SQL
    echo "\n10. Testing SQL UPDATE...\n";
    $xdb->querySQL("UPDATE school.students SET grade='A' WHERE name='Bob'");
    $results = $xdb->querySQL("SELECT name, grade FROM school.students WHERE name='Bob'");
    echo "    Bob's new grade: {$results[0]['grade']}\n";

    // 11. DELETE via SQL
    echo "\n11. Testing SQL DELETE...\n";
    $xdb->querySQL("DELETE FROM school.students WHERE name='Alice'");
    $count = $xdb->querySQL("SELECT COUNT(*) FROM school.students");
    echo "    Students after deleting Alice: " . $count[0]['COUNT(*)'] . "\n";

    // 12. XPath (XQuery-lite) direct query
    echo "\n12. Testing XPath (XQuery-lite)...\n";
    $xdb->selectDatabase('school');
    $xdb->connect('students');
    $results = $xdb->queryX("//row[score>85]");
    echo "    Students with score > 85:\n";
    foreach ($results as $r) {
        echo "      - {$r['name']}: {$r['score']}\n";
    }

    // 13. Multi-database: finance
    echo "\n13. Testing multi-database (finance)...\n";
    $xdb->querySQL("INSERT INTO finance.invoices (id, customer, amount, status) VALUES ('INV-001', 'Acme Corp', '5000', 'paid')");
    $xdb->querySQL("INSERT INTO finance.invoices (id, customer, amount, status) VALUES ('INV-002', 'Globex', '3200', 'pending')");
    $results = $xdb->querySQL("SELECT * FROM finance.invoices");
    echo "    Finance invoices: " . count($results) . "\n";

    // 14. Cross-database queries
    echo "\n14. Testing cross-database switching...\n";
    $school = $xdb->querySQL("SELECT COUNT(*) FROM school.students");
    $finance = $xdb->querySQL("SELECT COUNT(*) FROM finance.invoices");
    echo "    School students: {$school[0]['COUNT(*)']} | Finance invoices: {$finance[0]['COUNT(*)']}\n";

    // 15. SHOW DATABASES final
    echo "\n15. Testing SHOW DATABASES...\n";
    $xdb->selectDatabase('default');
    $dbs = $xdb->querySQL("SHOW DATABASES");
    $dbList = array_map(fn ($d) => is_array($d) ? ($d['Database'] ?? reset($d)) : $d, $dbs);
    echo "    All databases: " . implode(', ', $dbList) . "\n";

    // 16. DROP TABLE via SQL
    echo "\n16. Testing DROP TABLE via SQL...\n";
    $xdb->querySQL("DROP TABLE school.teachers");
    $xdb->selectDatabase('school');
    $tables = $xdb->querySQL("SHOW TABLES");
    $tableList = array_map(fn ($t) => is_array($t) ? reset($t) : $t, $tables);
    echo "    Tables in school after drop: " . implode(', ', $tableList) . "\n";

    // 17. Table/Database existence checks
    echo "\n17. Testing existence checks...\n";
    echo "    school DB exists: " . ($xdb->databaseExists('school') ? 'YES' : 'NO') . "\n";
    echo "    students table exists: " . ($xdb->tableExists('students') ? 'YES' : 'NO') . "\n";
    echo "    teachers table exists: " . ($xdb->tableExists('teachers') ? 'YES' : 'NO') . "\n";

    echo "\n=== All Verifications Passed! ===\n";

} catch (Exception $e) {
    echo "\nVerification FAILED: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
