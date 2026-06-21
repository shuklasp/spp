<div class="card bg-white shadow-sm rounded p-4 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="card-title mb-0">
            <i class="bi bi-activity text-primary me-2"></i> Live Server Stats
        </h5>
        <span class="badge bg-success">Live</span>
    </div>
    
    <div class="row text-center mb-3">
        <div class="col-6">
            <h2 class="display-4 text-primary">{{ $counter }}</h2>
            <p class="text-muted small text-uppercase">Ping Count</p>
        </div>
        <div class="col-6 border-start">
            <h4 class="text-secondary mt-3">{{ $lastUpdate }}</h4>
            <p class="text-muted small text-uppercase">Last Ping</p>
        </div>
    </div>
    
    <div class="d-grid gap-2 d-md-flex justify-content-md-center">
        <button class="btn btn-outline-primary btn-sm" wire:click="increment">
            <i class="bi bi-arrow-up-circle"></i> Send Ping
        </button>
        <button class="btn btn-outline-secondary btn-sm" wire:click="refresh">
            <i class="bi bi-arrow-clockwise"></i> Refresh Time
        </button>
    </div>
</div>

<script>
    // Listen for the custom event broadcasted from PHP
    window.addEventListener('statsUpdated', function(e) {
        console.log('Intercepted statsUpdated event:', e.detail);
        // We could trigger toastr or any UI notification here
    });
</script>
