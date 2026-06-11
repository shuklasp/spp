<?php
require_once __DIR__ . '/spp/sppinit.php';

echo "Testing Registry Enhancements...\n";

// 1. Test Dot-Notation
\SPP\Registry::register('core.db.host', 'localhost');
$val = \SPP\Registry::get('core=>db=>host');
if ($val === 'localhost') {
    echo "Dot-Notation to Legacy (=>): OK\n";
} else {
    echo "Dot-Notation to Legacy (=>): FAILED\n";
}

$val2 = \SPP\Registry::get('core.db.host');
if ($val2 === 'localhost') {
    echo "Dot-Notation fetch: OK\n";
} else {
    echo "Dot-Notation fetch: FAILED\n";
}

// 2. Test Immutable Locks
\SPP\Registry::lock('core.db');

$exceptionThrown = false;
try {
    \SPP\Registry::register('core.db.host', '10.0.0.1'); // This should fail!
} catch (\RuntimeException $e) {
    $exceptionThrown = true;
}

if ($exceptionThrown) {
    echo "Immutable Lock (Overwrite Child): OK\n";
} else {
    echo "Immutable Lock (Overwrite Child): FAILED\n";
}

$exceptionThrown2 = false;
try {
    \SPP\Registry::register('core=>db', ['host' => 'hacked']); // This should fail!
} catch (\RuntimeException $e) {
    $exceptionThrown2 = true;
}

if ($exceptionThrown2) {
    echo "Immutable Lock (Overwrite Parent): OK\n";
} else {
    echo "Immutable Lock (Overwrite Parent): FAILED\n";
}

// 3. Test unaffected reads
if (\SPP\Registry::get('core.db.host') === 'localhost') {
    echo "Read Locked Key: OK\n";
} else {
    echo "Read Locked Key: FAILED\n";
}

// 4. Test remove
$exceptionThrown3 = false;
try {
    \SPP\Registry::remove('core.db.host'); // This should fail!
} catch (\RuntimeException $e) {
    $exceptionThrown3 = true;
}
if ($exceptionThrown3) {
    echo "Remove Locked Key: OK\n";
} else {
    echo "Remove Locked Key: FAILED\n";
}

echo "All tests completed.\n";
