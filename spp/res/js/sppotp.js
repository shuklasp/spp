/**
 * SPP OTP Input Library
 */
document.addEventListener('DOMContentLoaded', () => {
    const otpContainers = document.querySelectorAll('.spp-otp-container');
    
    otpContainers.forEach(container => {
        const inputs = container.querySelectorAll('.otp-digit');
        const hidden = container.querySelector('input[type="hidden"]');
        
        inputs.forEach((input, idx) => {
            input.addEventListener('input', (e) => {
                if (input.value.length > 0 && idx < inputs.length - 1) {
                    inputs[idx + 1].focus();
                }
                update();
            });
            
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !input.value && idx > 0) {
                    inputs[idx - 1].focus();
                }
            });
            
            input.addEventListener('paste', (e) => {
                const data = e.clipboardData.getData('text').split('');
                inputs.forEach((inp, i) => {
                    if (data[i]) inp.value = data[i];
                });
                update();
                e.preventDefault();
            });
        });
        
        const update = () => {
            let code = '';
            inputs.forEach(inp => code += inp.value);
            hidden.value = code;
        };
    });
});
