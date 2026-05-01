/**
 * SPP Image Cropper Library
 */
document.addEventListener('DOMContentLoaded', () => {
    const croppers = document.querySelectorAll('.spp-cropper-container');
    
    croppers.forEach(container => {
        const fileInput = container.querySelector('input[type="file"]');
        const hidden = container.querySelector('input[type="hidden"]');
        const preview = container.querySelector('.crop-preview');
        
        fileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (!file) return;
            
            const reader = new FileReader();
            reader.onload = (event) => {
                const img = new Image();
                img.src = event.target.result;
                img.onload = () => {
                    // Simple auto-crop to square for demo
                    const canvas = document.createElement('canvas');
                    const size = Math.min(img.width, img.height);
                    canvas.width = size;
                    canvas.height = size;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, (img.width - size) / 2, (img.height - size) / 2, size, size, 0, 0, size, size);
                    
                    const croppedData = canvas.toDataURL('image/jpeg');
                    preview.src = croppedData;
                    preview.style.display = 'block';
                    hidden.value = croppedData;
                };
            };
            reader.readAsDataURL(file);
        });
    });
});
