<div style="padding:1rem; border:1px solid var(--sppux-border, #333); border-radius:8px; background:var(--sppux-surface, rgba(0,0,0,0.05)); text-align:center;">
    <h4 style="font-size:1.2rem; color:var(--sppux-primary, #6366f1); margin-bottom:1rem;">
        View Transitions &amp; Live Components
    </h4>
    <script>window.SPPLiveUseSSE = false;</script>
    <p style="margin-bottom:1.5rem; opacity:0.8;">
        <?php echo htmlspecialchars($message); ?>
    </p>
    <div style="font-size:2rem; font-weight:bold; margin-bottom:1.5rem;">
        <?php echo $counter; ?>
    </div>
    <button wire:click="increment.optimistic" wire:optimistic.class="sppux-loading opacity-50 cursor-not-allowed" wire:loading.attr="disabled" class="sppux-btn sppux-btn-primary" style="margin: 0 auto; transition: all 0.2s;">
        Increment (wire:click)
    </button>
</div>
