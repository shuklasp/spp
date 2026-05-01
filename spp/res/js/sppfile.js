/**
 * SPP Advanced File Library
 * Handles drag-and-drop, previews, and progress for file inputs.
 */
document.addEventListener('DOMContentLoaded', () => {
    const fileInputs = document.querySelectorAll('input[type="file"].spp-file-enhanced');
    
    fileInputs.forEach(input => {
        const wrapper = input.closest('.spp-input-wrapper');
        const preview = document.createElement('div');
        preview.className = 'spp-file-preview';
        preview.style.display = 'flex';
        preview.style.gap = '10px';
        preview.style.marginTop = '10px';
        preview.style.flexWrap = 'wrap';
        wrapper.appendChild(preview);
        
        input.addEventListener('change', () => {
            preview.innerHTML = '';
            Array.from(input.files).forEach(file => {
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.style.width = '80px';
                        img.style.height = '80px';
                        img.style.objectFit = 'cover';
                        img.style.borderRadius = '4px';
                        img.style.border = '1px solid #ddd';
                        preview.appendChild(img);
                    };
                    reader.readAsDataURL(file);
                } else {
                    const icon = document.createElement('div');
                    icon.innerText = '📄 ' + file.name;
                    icon.style.fontSize = '0.75rem';
                    icon.style.padding = '5px';
                    icon.style.background = '#f5f5f5';
                    icon.style.border = '1px solid #ddd';
                    preview.appendChild(icon);
                }
            });
        });
        
        // Add drag & drop support
        const dropZone = wrapper;
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        dropZone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            input.files = dt.files;
            input.dispatchEvent(new Event('change'));
        });
    });
});
