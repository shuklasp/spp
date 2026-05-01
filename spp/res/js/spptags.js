/**
 * SPP Tag Input Library
 */
document.addEventListener('DOMContentLoaded', () => {
    const tagInputs = document.querySelectorAll('.spp-tag-input-container');
    
    tagInputs.forEach(container => {
        const input = container.querySelector('input[type="text"]');
        const hidden = container.querySelector('input[type="hidden"]');
        const list = container.querySelector('.spp-tag-list');
        let tags = hidden.value ? hidden.value.split(',') : [];
        
        const renderTags = () => {
            list.innerHTML = '';
            tags.forEach((tag, idx) => {
                const pill = document.createElement('span');
                pill.className = 'spp-tag-pill';
                pill.style = 'display: inline-flex; align-items: center; background: #e1e4e8; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem; margin: 2px;';
                pill.innerHTML = `${tag} <span class="tag-remove" style="margin-left: 5px; cursor: pointer; opacity: 0.5;">&times;</span>`;
                pill.querySelector('.tag-remove').addEventListener('click', () => {
                    tags.splice(idx, 1);
                    update();
                });
                list.appendChild(pill);
            });
        };
        
        const update = () => {
            hidden.value = tags.join(',');
            renderTags();
        };
        
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ',') {
                e.preventDefault();
                const val = input.value.trim().replace(',', '');
                if (val && !tags.includes(val)) {
                    tags.push(val);
                    input.value = '';
                    update();
                }
            } else if (e.key === 'Backspace' && !input.value) {
                tags.pop();
                update();
            }
        });
        
        renderTags();
    });
});
