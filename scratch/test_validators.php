<?php

require_once 'spp/sppinit.php';

use SPPMod\SPPView\ViewTag;
use SPPMod\SPPView\SPPRequiredValidator;
use SPPMod\SPPView\SPPEmailValidator;
use SPPMod\SPPView\SPPRegexValidator;
use SPPMod\SPPView\SPPCallbackValidator;

echo "SPP Validator Modernization Test\n";
echo "================================\n\n";

// Mock element
$elem = new ViewTag('input', 'test_field');
$elem->setAttribute('id', 'test_field');

// 1. Test Silent Required Validator
echo "Test 1: Silent Required Validator\n";
$req = new SPPRequiredValidator($elem, 'err', 'Required!');
$req->setSilent(true);

$res = $req->validateAll(['test_field' => '']);
echo "Result (empty): " . ($res ? "PASS" : "FAIL (Expected)") . "\n";
print_r($req->getLastResult()->getErrors());

$res = $req->validateAll(['test_field' => 'something']);
echo "Result (filled): " . ($res ? "PASS" : "FAIL") . "\n";

// 2. Test Email Validator
echo "\nTest 2: Email Validator\n";
$email = new SPPEmailValidator($elem);
$email->setSilent(true);

$res = $email->validateAll(['test_field' => 'invalid-email']);
echo "Result (invalid): " . ($res ? "PASS" : "FAIL (Expected)") . "\n";

$res = $email->validateAll(['test_field' => 'test@example.com']);
echo "Result (valid): " . ($res ? "PASS" : "FAIL") . "\n";

// 3. Test Callback Validator
echo "\nTest 3: Callback Validator\n";
$callback = new SPPCallbackValidator($elem, function($val) {
    return strpos($val, 'secret') !== false;
}, 'err', 'Must contain secret!');
$callback->setSilent(true);

$res = $callback->validateAll(['test_field' => 'hello']);
echo "Result (invalid): " . ($res ? "PASS" : "FAIL (Expected)") . "\n";

$res = $callback->validateAll(['test_field' => 'my secret key']);
echo "Result (valid): " . ($res ? "PASS" : "FAIL") . "\n";

echo "\nTests Completed.\n";
