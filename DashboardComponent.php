<?php

namespace App\LiveComponents;

use SPPMod\SPPView\LiveComponent;
use SPPMod\SPPView\Attributes\Lazy;
use SPP\Attributes\Validate;
use SPP\Attributes\Session;
use SPPMod\SPPView\Attributes\Title;
use SPP\Attributes\On;

#[Title('Dashboard - Live Showcase')]
#[Lazy(action: 'loadData')]
class DashboardComponent extends LiveComponent
{
    public string $stats = 'Loading...';

    public function loadData()
    {
        // Simulate heavy DB query
        sleep(2);
        $this->stats = '1,234 Active Users';
    }

    public function refreshStats()
    {
        $this->stats = number_format(rand(1000, 5000)) . ' Active Users (Updated at ' . date('H:i:s') . ')';
    }

    public function render(): string
    {
        return <<<HTML
        <div class="p-6 bg-white rounded shadow">
            <h2 class="text-xl font-bold mb-4">Real-Time Analytics</h2>
            <div class="text-3xl font-mono text-blue-600 mb-4" wire:poll.2s.visible="refreshStats">
                {\$this->stats}
            </div>
            <p class="text-sm text-gray-500">This component was lazy loaded, and is polling only when visible.</p>
        </div>
        HTML;
    }
}
