/**
 * SPP Multi-Step Wizard Library
 */
document.addEventListener('DOMContentLoaded', () => {
    const wizards = document.querySelectorAll('.spp-wizard');
    
    wizards.forEach(wizard => {
        const steps = wizard.querySelectorAll('.spp-wizard-step');
        let currentStep = 0;
        
        const showStep = (idx) => {
            steps.forEach((s, i) => {
                s.style.display = (i === idx) ? 'block' : 'none';
            });
            updateNav(wizard, idx, steps.length);
        };
        
        wizard.querySelector('.btn-next')?.addEventListener('click', () => {
            if (validateStep(steps[currentStep])) {
                currentStep++;
                showStep(currentStep);
            }
        });
        
        wizard.querySelector('.btn-prev')?.addEventListener('click', () => {
            currentStep--;
            showStep(currentStep);
        });
        
        showStep(currentStep);
    });
});

function validateStep(step) {
    // Basic check before allowing next step
    const inputs = step.querySelectorAll('input, select, textarea');
    let valid = true;
    inputs.forEach(input => {
        if (input.hasAttribute('required') && !input.value) {
            valid = false;
            // Trigger error UI
            const errId = 'err_' + input.id;
            const errElem = document.getElementById(errId);
            if (errElem) errElem.innerText = 'This field is required before proceeding.';
        }
    });
    return valid;
}

function updateNav(wizard, idx, total) {
    const nextBtn = wizard.querySelector('.btn-next');
    const prevBtn = wizard.querySelector('.btn-prev');
    const submitBtn = wizard.querySelector('input[type="submit"], button[type="submit"]');
    
    if (prevBtn) prevBtn.style.display = (idx === 0) ? 'none' : 'inline-block';
    if (nextBtn) nextBtn.style.display = (idx === total - 1) ? 'none' : 'inline-block';
    if (submitBtn) submitBtn.style.display = (idx === total - 1) ? 'inline-block' : 'none';
}
