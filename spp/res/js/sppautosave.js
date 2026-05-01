/**
 * SPP Form Auto-Save Library
 * Persists form data to localStorage as the user types.
 */
document.addEventListener('DOMContentLoaded', () => {
    const forms = document.querySelectorAll('form[data-autosave]');
    
    forms.forEach(form => {
        const formId = form.id || form.name;
        const storageKey = `spp_draft_${formId}`;
        
        // Restore draft if exists
        const draft = localStorage.getItem(storageKey);
        if (draft) {
            const data = JSON.parse(draft);
            Object.keys(data).forEach(name => {
                const input = form.querySelector(`[name="${name}"]`);
                if (input) {
                    if (input.type === 'checkbox' || input.type === 'radio') {
                        input.checked = data[name];
                    } else {
                        input.value = data[name];
                    }
                }
            });
            console.log(`SPP: Restored draft for ${formId}`);
        }
        
        // Save on input
        form.addEventListener('input', () => {
            const formData = new FormData(form);
            const data = {};
            formData.forEach((value, key) => {
                data[key] = value;
            });
            localStorage.setItem(storageKey, JSON.stringify(data));
        });
        
        // Clear on submit
        form.addEventListener('submit', () => {
            localStorage.removeItem(storageKey);
        });
    });
});
