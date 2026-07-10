<?php
require_once 'c:/projects/apache/school1/spp/sppinit.php';

// Prepare a mock component request payload
$componentClass = '\\App\\Samvaad\\comp\\live_demo';
$component = new $class();
$state = $component->dehydrate();
$state['id'] = 'test_live_123';
$stateJson = json_encode($state);
$checksum = \SPPMod\SPPView\LiveComponent::signState($state);

// Prepare the mock input data (which would be sent by spplive.js)
$postData = [
    'components' => [
        [
            'id' => 'test_live_123',
            'name' => $componentClass,
            'state' => $state,
            'checksum' => $checksum,
            'updates' => [],
            'calls' => [
                ['method' => 'increment', 'params' => []]
            ]
        ]
    ]
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://localhost/school1/samvaad?action=enterprise.live");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// Set the session cookie if we need it (the secret is stored in session)
// For testing without cookie, signState will just generate a new secret in THIS process's session, 
// which won't match the CURL request's session!
// Let's just output the PHP script that does internal dispatch.

