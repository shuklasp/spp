<?php

require_once __DIR__ . '/../spp/core/class.autoloader.php';
require_once __DIR__ . '/../spp/core/class.app.php';
\SPP\App::init();

class CounterComponent extends \SPPMod\SPPView\LiveComponent
{
    public int $count = 0;

    public function increment()
    {
        $this->count++;
    }

    public function decrement()
    {
        $this->count--;
    }

    public function render(): string
    {
        $state = $this->dehydrate();
        $checksum = static::signState($state);
        $stateJson = htmlspecialchars(json_encode($state), ENT_QUOTES, 'UTF-8');
        return <<<HTML
<div wire:id="{$this->id}" wire:state="{$stateJson}" wire:checksum="{$checksum}" wire:component="CounterComponent">
    <h2>Live Counter: {$this->count}</h2>
    <button wire:click="decrement">-</button>
    <button wire:click="increment">+</button>
</div>
HTML;
    }
}

// 1. Initial Render
$comp = new CounterComponent('counter_1');
echo "=== INITIAL HTML ===\n";
echo $comp->render() . "\n\n";

// 2. Simulate AJAX Live Update to increment
$state = $comp->dehydrate();
$checksum = \SPPMod\SPPView\LiveComponent::signState($state);

$_POST = [
    'component' => 'CounterComponent',
    'state' => $state,
    'checksum' => $checksum,
    'method' => 'increment',
    'params' => []
];

echo "=== SIMULATING INCREMENT ===\n";
// Suppress exit so it returns
try {
    $response = \SPPMod\SPPView\LiveComponent::handleRequest($_POST['component'], $_POST['state'], $_POST['checksum'], $_POST['method'], $_POST['params']);
    echo json_encode($response, JSON_PRETTY_PRINT) . "\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

