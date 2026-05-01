/**
 * SPP Star Rating Library
 */
document.addEventListener('DOMContentLoaded', () => {
    const ratings = document.querySelectorAll('.spp-rating-container');
    
    ratings.forEach(container => {
        const stars = container.querySelectorAll('.star-icon');
        const hidden = container.querySelector('input[type="hidden"]');
        
        stars.forEach((star, idx) => {
            star.addEventListener('mouseover', () => highlight(stars, idx));
            star.addEventListener('mouseout', () => highlight(stars, hidden.value - 1));
            star.addEventListener('click', () => {
                hidden.value = idx + 1;
                highlight(stars, idx);
                hidden.dispatchEvent(new Event('change'));
            });
        });
        
        const highlight = (elements, idx) => {
            elements.forEach((el, i) => {
                el.style.color = (i <= idx) ? '#f39c12' : '#ccc';
            });
        };
        
        // Initial state
        if (hidden.value) highlight(stars, hidden.value - 1);
    });
});
