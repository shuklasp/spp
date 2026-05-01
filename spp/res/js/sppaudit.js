/**
 * SPP Accessibility Auditor (Dev Mode Only)
 */
document.addEventListener('DOMContentLoaded', () => {
    if (!window.location.search.includes('spp_audit')) return;
    
    console.log('SPP: Starting Accessibility Audit...');
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        const issues = [];
        
        // 1. Check for Labels
        form.querySelectorAll('input, select, textarea').forEach(input => {
            if (input.type === 'hidden' || input.type === 'submit') return;
            const label = form.querySelector(`label[for="${input.id}"]`);
            if (!label && !input.getAttribute('aria-label')) {
                issues.push(`Missing Label: Field "${input.name}" has no linked label or aria-label.`);
                input.style.border = '2px solid red';
            }
        });
        
        // 2. Check for Placeholder-only labels
        form.querySelectorAll('[placeholder]').forEach(input => {
            if (!form.querySelector(`label[for="${input.id}"]`)) {
                issues.push(`A11y Warning: Field "${input.name}" uses placeholder as label. This is poor UX/A11y.`);
                input.style.border = '2px solid orange';
            }
        });
        
        if (issues.length > 0) {
            const div = document.createElement('div');
            div.style = 'background: #fff5f5; border: 1px solid red; padding: 15px; margin-bottom: 20px; border-radius: 4px; color: #c53030; font-family: monospace; font-size: 0.8rem;';
            div.innerHTML = '<strong>SPP Accessibility Audit Issues Found:</strong><ul><li>' + issues.join('</li><li>') + '</li></ul>';
            form.parentNode.insertBefore(div, form);
        }
    });
});
