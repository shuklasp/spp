/**
 * SPP Form Dependency Engine
 * Handles showing/hiding fields based on data-depends-on attributes.
 */
(function() {
    const init = () => {
        const dependentFields = document.querySelectorAll('[data-depends-on]');
        dependentFields.forEach(field => {
            const wrapper = field.closest('.spp-form-group') || field.closest('.input-group') || field.closest('.form-element-wrapper') || field;
            const configStr = field.getAttribute('data-depends-on');
            if (!configStr) return;

            try {
                const config = JSON.parse(configStr);
                const targets = Object.keys(config);

                const check = () => {
                    let visible = true;
                    targets.forEach(targetName => {
                        const targetField = document.querySelector(`[name="${targetName}"], [data-key="${targetName}"]`);
                        if (!targetField) return;

                        const currentVal = targetField.value;
                        const requiredVals = Array.isArray(config[targetName]) ? config[targetName] : [config[targetName]];
                        
                        if (!requiredVals.includes(currentVal)) {
                            visible = false;
                        }
                    });

                    wrapper.classList.toggle('spp-hidden', !visible);
                    wrapper.style.display = visible ? '' : 'none';

                    // Toggle disabled/required to prevent browser validation blocks on hidden fields
                    const inputs = (wrapper === field) ? [field] : wrapper.querySelectorAll('input, select, textarea');
                    inputs.forEach(inp => {
                        if (!visible) {
                            if (inp.hasAttribute('required')) {
                                inp.setAttribute('data-was-required', 'true');
                                inp.removeAttribute('required');
                            }
                            inp.disabled = true;
                        } else {
                            if (inp.getAttribute('data-was-required') === 'true') {
                                inp.setAttribute('required', 'required');
                                inp.removeAttribute('data-was-required');
                            }
                            inp.disabled = false;
                        }
                    });
                };

                targets.forEach(targetName => {
                    const targetField = document.querySelector(`[name="${targetName}"], [data-key="${targetName}"]`);
                    if (targetField) {
                        targetField.addEventListener('change', check);
                        targetField.addEventListener('input', check);
                    }
                });

                // Initial check
                check();
            } catch (e) {
                console.error('Failed to parse dependency config:', configStr, e);
            }
        });
    };

    // Export to global for manual re-triggering
    window.SPPDependencies = {
        init: init
    };

    // Auto-init on load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
