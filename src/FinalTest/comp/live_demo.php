<?php
namespace App\FinalTest\Comp;

use SPPMod\SPPView\LiveComponent;

class live_demo extends LiveComponent {
    public $count = 0;
    
    public function mount(array $params = []): void {
        $this->count = 0;
    }
    
    public function increment() {
        $this->count++;
    }
    
    public function decrement() {
        $this->count--;
    }
    
    public function render(): string {
        return 'partials/live_demo.html';
    }
}