/**
 * SPP Morph Engine - Wraps idiomorph for SPP LiveComponent integration
 */
(function() {
    'use strict';
    
    // Ensure SPPUX is available
    if (!window.SPPUX) {
        window.SPPUX = {};
    }

    const originalMorph = window.SPPUX.morph;

    // Override SPPUX.morph to use idiomorph
    window.SPPUX.morph = function(el, newHtml, options = {}) {
        if (!window.Idiomorph) {
            console.warn('[SPPUX] Idiomorph not loaded, falling back to innerHTML swap');
            if (typeof originalMorph === 'function') {
                return originalMorph(el, newHtml);
            }
            // Simple innerHTML fallback if original not found
            const temp = document.createElement('div');
            temp.innerHTML = newHtml;
            const newNode = temp.firstElementChild;
            if (newNode) el.innerHTML = newNode.innerHTML;
            return;
        }

        // Fire pre-morph event for extensions
        el.dispatchEvent(new CustomEvent('spplive:morphing', { bubbles: true, detail: { el, newHtml } }));

        window.Idiomorph.morph(el, newHtml, {
            callbacks: {
                beforeNodeMorphed(oldNode, newNode) {
                    // Check for wire:ignore
                    if (oldNode.nodeType === Node.ELEMENT_NODE && oldNode.hasAttribute('wire:ignore')) {
                        return false; // Skip
                    }

                    // User provided callbacks
                    if (options.callbacks && typeof options.callbacks.beforeNodeMorphed === 'function') {
                        return options.callbacks.beforeNodeMorphed(oldNode, newNode);
                    }
                }
            }
        });

        // Fire post-morph event
        el.dispatchEvent(new CustomEvent('spplive:morphed', { bubbles: true, detail: { el } }));
    };
})();
