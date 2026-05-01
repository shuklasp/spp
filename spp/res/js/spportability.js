/**
 * SPP Form Portability Library
 * Handles local Export/Import of form data as JSON.
 */
document.addEventListener('DOMContentLoaded', () => {
    const portables = document.querySelectorAll('.spp-portability-container');
    
    portables.forEach(container => {
        const form = container.closest('form');
        const exportBtn = container.querySelector('.btn-export-json');
        const importBtn = container.querySelector('.btn-import-json');
        const fileInput = container.querySelector('.import-file-input');
        
        exportBtn.addEventListener('click', () => {
            const formData = new FormData(form);
            const data = {};
            formData.forEach((v, k) => data[k] = v);
            
            const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `form_data_${form.id || 'draft'}.json`;
            a.click();
            URL.revokeObjectURL(url);
        });
        
        importBtn.addEventListener('click', () => fileInput.click());
        
        fileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (!file) return;
            
            const reader = new FileReader();
            reader.onload = (event) => {
                const data = JSON.parse(event.target.result);
                Object.keys(data).forEach(name => {
                    const input = form.querySelector(`[name="${name}"]`);
                    if (input) {
                        if (input.type === 'checkbox' || input.type === 'radio') input.checked = data[name];
                        else input.value = data[name];
                        input.dispatchEvent(new Event('change'));
                        input.dispatchEvent(new Event('input'));
                    }
                });
                alert('Form data imported successfully!');
            };
            reader.readAsDataURL(file);
        });
    });
});
