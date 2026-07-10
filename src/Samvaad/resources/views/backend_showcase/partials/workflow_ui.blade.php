<div style="text-align: center; margin-bottom: 2rem;">
    <div style="font-size: 0.9rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px;">Current State</div>
    <div style="font-size: 2rem; font-weight: 700; color: var(--text-main); margin: 0.5rem 0;">{{ $currentState ?? 'draft' }}</div>
</div>

<div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
    @foreach($availableTransitions ?? ['submit', 'cancel'] as $transition)
        <button class="btn btn-primary" 
                hx-post="<?= \SPP\App::url('backend-showcase/workflow/transition', 'samvaad') ?>" 
                hx-vals='{"transition": "{{ $transition }}"}'
                hx-target="#showcase-container">
            Apply: {{ ucfirst($transition) }}
        </button>
    @endforeach
    
    <button class="btn btn-outline" 
            hx-post="<?= \SPP\App::url('backend-showcase/workflow/reset', 'samvaad') ?>" 
            hx-target="#showcase-container" style="margin-top: 1rem; width: 100%;">
        Reset Workflow
    </button>
</div>
