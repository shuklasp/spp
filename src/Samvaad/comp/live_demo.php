<?php
namespace App\Samvaad\comp;

use SPPMod\SPPView\LiveComponent;

class live_demo extends LiveComponent {
    public int $counter = 0;
    public string $message = 'Hello from SPPLive!';

    public function increment() {
        $this->counter++;
        $this->message = "Counter incremented to {$this->counter}";
    }

    public function render(): string {
        return 'src/Samvaad/comp/partials/live_demo.html';
    }
}
