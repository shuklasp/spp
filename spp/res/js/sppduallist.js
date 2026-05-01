/**
 * SPP Dual Listbox Library
 */
document.addEventListener('DOMContentLoaded', () => {
    const duallists = document.querySelectorAll('.spp-dual-list');
    
    duallists.forEach(dl => {
        const available = dl.querySelector('.list-available');
        const selected = dl.querySelector('.list-selected');
        const hidden = dl.querySelector('input[type="hidden"]');
        const addBtn = dl.querySelector('.btn-add');
        const removeBtn = dl.querySelector('.btn-remove');
        
        const update = () => {
            const values = Array.from(selected.options).map(o => o.value);
            hidden.value = values.join(',');
            hidden.dispatchEvent(new Event('change'));
        };
        
        addBtn.addEventListener('click', () => {
            Array.from(available.selectedOptions).forEach(o => {
                selected.appendChild(o);
            });
            update();
        });
        
        removeBtn.addEventListener('click', () => {
            Array.from(selected.selectedOptions).forEach(o => {
                available.appendChild(o);
            });
            update();
        });
    });
});
