/**
 * SPP Repeater Library
 * Handles dynamic adding and removing of fieldsets with proper indexing.
 */
document.addEventListener('DOMContentLoaded', () => {
    const repeaters = document.querySelectorAll('.spp-repeater');
    
    repeaters.forEach(repeater => {
        const list = repeater.querySelector('.spp-repeater-list');
        const template = repeater.querySelector('.spp-repeater-template');
        const addBtn = repeater.querySelector('.spp-repeater-add');
        const baseName = repeater.getAttribute('data-repeater-name');
        
        const addRow = () => {
            const index = list.children.length;
            const content = template.content.cloneNode(true);
            const row = content.querySelector('.spp-repeater-item');
            
            // Update names and IDs to include index
            row.querySelectorAll('[name]').forEach(input => {
                const originalName = input.getAttribute('name');
                input.setAttribute('name', `${baseName}[${index}][${originalName}]`);
                input.id = `${baseName}_${index}_${input.id}`;
            });
            
            row.querySelector('.spp-repeater-remove').addEventListener('click', () => {
                row.remove();
                reindexRows(list, baseName);
            });
            
            list.appendChild(row);
        };
        
        addBtn.addEventListener('click', addRow);
        
        // Initial row
        if (list.children.length === 0) addRow();
    });
});

function reindexRows(list, baseName) {
    Array.from(list.children).forEach((row, index) => {
        row.querySelectorAll('[name]').forEach(input => {
            const nameMatch = input.getAttribute('name').match(/\[(\d+)\]/);
            if (nameMatch) {
                const originalName = input.getAttribute('name').split('][')[1].replace(']', '');
                input.setAttribute('name', `${baseName}[${index}][${originalName}]`);
            }
        });
    });
}
