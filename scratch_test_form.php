<?php
require_once 'spp.php';
\SPP\App::init('school1');

// Test Fluent Form API
$form = \SPPMod\SPPView\Form::make('login_form')
    ->setAction('/api/login')
    ->addText('username', 'Email Address')
        ->setRequired()
        ->addRule('email')
    ->addPassword('password', 'Secure Password')
        ->setRequired()
        ->setMinLength(8)
    ->addSubmit('login', 'Login')
    ->build();

echo "Form ID: " . $form->getAttribute('id') . "\n";
echo "Elements built: " . count($form->getElements()) . "\n";

// Render and check for HTML5 native validation attributes
echo $form->render();
