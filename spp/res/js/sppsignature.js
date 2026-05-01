/**
 * SPP Signature Pad Library
 */
document.addEventListener('DOMContentLoaded', () => {
    const signatures = document.querySelectorAll('.spp-signature-pad');
    
    signatures.forEach(pad => {
        const canvas = pad.querySelector('canvas');
        const input = pad.querySelector('input[type="hidden"]');
        const clearBtn = pad.querySelector('.btn-clear-sig');
        const ctx = canvas.getContext('2d');
        let drawing = false;
        
        ctx.strokeStyle = '#222';
        ctx.lineWidth = 2;
        ctx.lineJoin = 'round';
        ctx.lineCap = 'round';
        
        const startDrawing = (e) => {
            drawing = true;
            ctx.beginPath();
            ctx.moveTo(getX(e), getY(e));
        };
        
        const draw = (e) => {
            if (!drawing) return;
            ctx.lineTo(getX(e), getY(e));
            ctx.stroke();
            input.value = canvas.toDataURL(); // Update hidden input with base64
        };
        
        const stopDrawing = () => drawing = false;
        
        const getX = (e) => (e.offsetX || e.touches[0].clientX - canvas.getBoundingClientRect().left);
        const getY = (e) => (e.offsetY || e.touches[0].clientY - canvas.getBoundingClientRect().top);
        
        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mousemove', draw);
        window.addEventListener('mouseup', stopDrawing);
        
        canvas.addEventListener('touchstart', (e) => { e.preventDefault(); startDrawing(e); });
        canvas.addEventListener('touchmove', (e) => { e.preventDefault(); draw(e); });
        canvas.addEventListener('touchend', stopDrawing);
        
        clearBtn.addEventListener('click', () => {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            input.value = '';
        });
    });
});
