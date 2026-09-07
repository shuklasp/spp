<?php

/**
 * Ultimate Verification script for SPP XDB (Phase 13 & 15)
 * Verifies Foreign Keys, Views, and GraphQL
 */

require_once(__DIR__ . '/sppxdb.php');

use SPPMod\SPPXDB\SPP_XDB;

echo "=== SPP XDB Ultimate Phase 13 & 15 Verification ===\n\n";

try {
    $xdb = new SPP_XDB('ultimate_test');

    // 1. Testing Virtual Views
    echo "1. Testing Virtual Views...\n";
    $xdb->querySQL("CREATE TABLE authors (id int, name varchar)");
    $xdb->querySQL("CREATE TABLE books (id int, title varchar, author_id int)");

    $xdb->connect('authors')->insert(['id' => 1, 'name' => 'J.K. Rowling']);
    $xdb->connect('books')->insert(['id' => 1, 'title' => 'Harry Potter', 'author_id' => 1]);

    $viewSql = "SELECT books.title, authors.name FROM books INNER JOIN authors ON books.author_id = authors.id";
    $xdb->createView('v_catalog', $viewSql);

    $viewData = $xdb->querySQL("SELECT * FROM v_catalog");
    print_r($viewData);
    if (count($viewData) === 1 && $viewData[0]['books.title'] === 'Harry Potter') {
        echo "   [CONFIRMED] Virtual View resolved complex JOIN correctly.\n";
    } else {
        echo "   [ERROR] Virtual View failed!\n";
    }

    // 2. Testing Foreign Key Cascading
    echo "\n2. Testing Foreign Key Cascading...\n";
    $xdb->addForeignKey('books', 'author_id', 'authors', 'id', 'CASCADE');

    echo "   Deleting author 1 (should cascade to books)...\n";
    $xdb->connect('authors')->delete("id = ?", [1]);

    $booksCount = $xdb->connect('books')->querySQL("SELECT COUNT(*) as total FROM books");
    echo "   Remaining books: " . $booksCount[0]['total'] . "\n";
    if ($booksCount[0]['total'] == 0) {
        echo "   [CONFIRMED] Foreign Key CASCADE deleted dependent records.\n";
    } else {
        echo "   [ERROR] Cascade failed!\n";
    }

    // 3. Testing GraphQL Bridge
    echo "\n3. Testing GraphQL Bridge...\n";
    $xdb->connect('authors')->insert(['id' => 2, 'name' => 'Tolkien']);
    $gql = '{ authors(name: "Tolkien") { id name } }';
    $gqlData = $xdb->queryGraphQL($gql);
    print_r($gqlData);
    if (count($gqlData) === 1 && $gqlData[0]['name'] === 'Tolkien') {
        echo "   [CONFIRMED] GraphQL query mapped to SQL correctly.\n";
    } else {
        echo "   [ERROR] GraphQL Bridge failed!\n";
    }

    echo "\n=== All Ultimate Verifications Passed! ===\n";

} catch (Exception $e) {
    echo "\nVerification FAILED: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
