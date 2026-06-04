<?php
require_once dirname(__DIR__, 2) . '/core/class.registry.php';

use SPP\Registry;

echo "Running RegistryTest...\n";

// Test 1: Set and Get
Registry::register('test_key', 'test_value');
if (Registry::get('test_key') !== 'test_value') {
    throw new \Exception("RegistryTest: get() returned incorrect value.");
}

// Test 2: Strict Types
Registry::register('test_int', '42');
if (Registry::getInt('test_int') !== 42) {
    throw new \Exception("RegistryTest: getInt() failed.");
}

// Test 3: Garbage Collection
Registry::register('temp_key', 'temp');
Registry::remove('temp_key');
if (Registry::isRegistered('temp_key')) {
    throw new \Exception("RegistryTest: remove() failed.");
}

echo "RegistryTest Passed.\n";
