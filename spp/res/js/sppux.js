/**
 * SPP UX Enhancements Library
 * Handles auto-expanding textareas and character/word counters.
 */
document.addEventListener('DOMContentLoaded', () => {
    // 1. Auto-Expanding Textareas
    const autoExpanders = document.querySelectorAll('textarea.spp-auto-expand');
    autoExpanders.forEach(ta => {
        ta.style.overflowY = 'hidden';
        ta.addEventListener('input', () => {
            ta.style.height = 'auto';
            ta.style.height = (ta.scrollHeight) + 'px';
        });
        // Trigger initial
        ta.dispatchEvent(new Event('input'));
    });

    // 2. Character & Word Counters
    const counters = document.querySelectorAll('[data-counter]');
    counters.forEach(field => {
        const type = field.getAttribute('data-counter'); // 'char' or 'word'
        const limit = field.getAttribute('maxlength') || field.getAttribute('data-max');
        const display = document.createElement('div');
        display.className = 'spp-counter-display';
        display.style = 'font-size: 0.7rem; color: #888; text-align: right; margin-top: 2px;';
        field.parentNode.appendChild(display);
        
        const update = () => {
            const val = field.value || '';
            const count = (type === 'word') ? val.trim().split(/\s+/).filter(w => w).length : val.length;
            display.innerText = count + (limit ? ' / ' + limit : '') + ' ' + type + 's';
            if (limit && count > limit) display.style.color = 'red';
            else display.style.color = '#888';
        };
        
        field.addEventListener('input', update);
        update();
    });
});
