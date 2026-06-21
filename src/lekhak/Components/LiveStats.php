<?php
namespace App\Lekhak\Components;

use SPPMod\SPPView\LiveComponent;

class LiveStats extends LiveComponent
{
    public int $counter = 0;
    public string $lastUpdate = 'Never';

    public function mount(array $params = []): void
    {
        parent::mount($params);
        $this->lastUpdate = date('H:i:s');
    }

    public function increment()
    {
        $this->counter++;
        $this->lastUpdate = date('H:i:s');
        
        // Broadcast a generic event to test Javascript reception
        $this->emit('statsUpdated', [
            'count' => $this->counter,
            'time' => $this->lastUpdate
        ]);
    }

    public function refresh()
    {
        $this->lastUpdate = date('H:i:s');
        $this->emit('statsRefreshed', [
            'time' => $this->lastUpdate
        ]);
    }

    public function render(): string
    {
        return 'src/lekhak/resources/views/components/live_stats.blade.php';
    }

    public static function embed(): string
    {
        $comp = new self();
        $comp->mount();
        $state = $comp->dehydrate();
        $checksum = self::signState($state);
        
        $res = self::handleRequest(self::class, $state, $checksum, null);
        
        // Wrap the initial HTML in the required wire attributes
        $html = trim($res['html']);
        // Inject attributes into the root element (assuming it's a div)
        $attributes = sprintf(' wire:id="%s" wire:component="%s" wire:state=\'%s\' wire:checksum="%s" ',
            htmlspecialchars($comp->id),
            htmlspecialchars(self::class),
            htmlspecialchars(json_encode($state, JSON_HEX_APOS | JSON_HEX_QUOT)),
            htmlspecialchars($checksum)
        );
        
        $html = preg_replace('/<div/', '<div' . $attributes, $html, 1);
        return $html;
    }
}
