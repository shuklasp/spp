/**
 * SPP-UX Signal Stress Test
 */

class StressTestComponent extends BaseComponent {
    onInit() {
        this.count = SPPUX.signal(0);
        this.double = SPPUX.computed(() => this.count.value * 2);
        
        // Rapid update interval (10ms)
        this.timer = setInterval(() => {
            this.count.value++;
        }, 10);
    }

    onDestroy() {
        clearInterval(this.timer);
    }

    render() {
        return html`
            <div class="stress-test">
                <h3>Signal Stress Test</h3>
                <div class="stat">Count: <b>${this.count.value}</b></div>
                <div class="stat">Double: <b>${this.double.value}</b></div>
                <button @click=${() => this.count.value = 0}>Reset</button>
            </div>
        `;
    }
}

console.log("Stress test component defined. Use 'new StressTestComponent(admin, container)' to run.");
