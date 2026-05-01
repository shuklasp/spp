/**
 * SPP Dual Range Slider Library
 */
document.addEventListener('DOMContentLoaded', () => {
    const rangeContainers = document.querySelectorAll('.spp-range-container');
    
    rangeContainers.forEach(container => {
        const minInput = container.querySelector('.range-min');
        const maxInput = container.querySelector('.range-max');
        const display = container.querySelector('.range-display');
        const hidden = container.querySelector('input[type="hidden"]');
        
        const update = () => {
            if (parseInt(minInput.value) > parseInt(maxInput.value)) {
                minInput.value = maxInput.value;
            }
            display.innerText = `${minInput.value} - ${maxInput.value}`;
            hidden.value = `${minInput.value}-${maxInput.value}`;
        };
        
        minInput.addEventListener('input', update);
        maxInput.addEventListener('input', update);
        
        update();
    });
});
