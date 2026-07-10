<?php
namespace App\test_api_app\Comp;

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
        return 'src/test_api_app/comp/partials/live_demo.html';
    }
}