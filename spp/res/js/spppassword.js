/**
 * SPP Password Strength Library
 */
document.addEventListener('DOMContentLoaded', () => {
    const passwordFields = document.querySelectorAll('input[type="password"][data-strength]');
    
    passwordFields.forEach(field => {
        const meter = document.createElement('div');
        meter.className = 'spp-strength-meter';
        meter.style = 'height: 4px; background: #eee; margin-top: 5px; border-radius: 2px; overflow: hidden;';
        
        const bar = document.createElement('div');
        bar.style = 'height: 100%; width: 0%; transition: width 0.3s, background 0.3s;';
        meter.appendChild(bar);
        field.parentNode.appendChild(meter);
        
        field.addEventListener('input', () => {
            const val = field.value;
            let score = 0;
            if (val.length > 8) score++;
            if (/[A-Z]/.test(val)) score++;
            if (/[0-9]/.test(val)) score++;
            if (/[^A-Za-z0-9]/.test(val)) score++;
            
            const colors = ['#e74c3c', '#e67e22', '#f1c40f', '#2ecc71'];
            const widths = ['25%', '50%', '75%', '100%'];
            
            bar.style.width = val.length > 0 ? widths[score - 1] || '10%' : '0%';
            bar.style.background = colors[score - 1] || '#ccc';
        });
    });
});
