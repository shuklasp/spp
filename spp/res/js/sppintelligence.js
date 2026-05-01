/**
 * SPP Intelligence Library
 * Handles UX Telemetry, Hotkeys, and Smart Paste.
 */
document.addEventListener('DOMContentLoaded', () => {
    const forms = document.querySelectorAll('form[data-intelligence]');
    
    forms.forEach(form => {
        const telemetry = {
            fields: {},
            startedAt: Date.now()
        };
        
        // 1. Telemetry Tracking
        form.querySelectorAll('input, select, textarea').forEach(field => {
            const name = field.name;
            if (!name) return;
            
            field.addEventListener('focus', () => {
                if (!telemetry.fields[name]) telemetry.fields[name] = { focusCount: 0, totalTime: 0, corrections: 0 };
                telemetry.fields[name].lastFocus = Date.now();
                telemetry.fields[name].focusCount++;
            });
            
            field.addEventListener('blur', () => {
                if (telemetry.fields[name]?.lastFocus) {
                    telemetry.fields[name].totalTime += (Date.now() - telemetry.fields[name].lastFocus);
                }
            });
            
            // Track corrections if an error was present
            field.addEventListener('input', () => {
                const err = document.getElementById('err_' + (field.id || name));
                if (err && err.innerText) {
                    telemetry.fields[name].corrections = (telemetry.fields[name].corrections || 0) + 1;
                }
            });
        });
        
        // 2. Keyboard Shortcuts
        const hotkeys = JSON.parse(form.getAttribute('data-hotkeys') || '{}');
        window.addEventListener('keydown', (e) => {
            const key = (e.ctrlKey ? 'ctrl+' : '') + (e.altKey ? 'alt+' : '') + e.key.toLowerCase();
            if (hotkeys[key]) {
                e.preventDefault();
                const action = hotkeys[key];
                if (action === 'submit') form.submit();
                if (action === 'reset') form.reset();
                if (action === 'next') form.querySelector('.btn-next')?.click();
                if (action === 'prev') form.querySelector('.btn-prev')?.click();
            }
        });
        
        // Inject telemetry on submit
        form.addEventListener('submit', () => {
            telemetry.totalTime = Date.now() - telemetry.startedAt;
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = '__spp_telemetry';
            input.value = JSON.stringify(telemetry);
            form.appendChild(input);
        });

        // 3. Smart Paste (Enhanced with Address Splitting)
        form.addEventListener('paste', (e) => {
            const target = e.target;
            const text = e.clipboardData.getData('text').trim();
            
            // Name Splitting
            if (target.name === 'first_name' || target.name === 'name') {
                const parts = text.split(/\s+/);
                if (parts.length > 1) {
                    setTimeout(() => {
                        target.value = parts[0];
                        const lastInput = form.querySelector('[name="last_name"]');
                        if (lastInput) lastInput.value = parts.slice(1).join(' ');
                    }, 0);
                }
            }
            
            // Address Splitting (e.g., "123 Main St, Springfield, IL 62704")
            const addrRegex = /^(.+?),\s*(.+?),\s*([A-Z]{2})\s*(\d{5}(-\d{4})?)$/i;
            const match = text.match(addrRegex);
            if (match) {
                e.preventDefault();
                form.querySelector('[name="street"]')?.setAttribute('value', match[1]);
                form.querySelector('[name="city"]')?.setAttribute('value', match[2]);
                form.querySelector('[name="state"]')?.setAttribute('value', match[3]);
                form.querySelector('[name="zip"]')?.setAttribute('value', match[4]);
            }
        });

        // 4. Voice-to-Text (Native Web Speech API)
        const voiceInputs = form.querySelectorAll('[data-voice]');
        voiceInputs.forEach(input => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.innerHTML = '🎤';
            btn.style = 'background:none; border:none; cursor:pointer; margin-left:-30px; position:relative; z-index:10;';
            input.parentNode.insertBefore(btn, input.nextSibling);
            
            const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!Recognition) { btn.style.display = 'none'; return; }
            
            const recognizer = new Recognition();
            recognizer.onresult = (e) => {
                input.value = e.results[0][0].transcript;
                input.dispatchEvent(new Event('input'));
            };
            
            btn.addEventListener('click', () => {
                btn.style.color = 'red';
                recognizer.start();
                setTimeout(() => btn.style.color = 'inherit', 3000);
            });
        });
    });
});
