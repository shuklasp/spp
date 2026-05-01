/**
 * SPP Tree Select Library
 */
document.addEventListener('DOMContentLoaded', () => {
    const trees = document.querySelectorAll('.spp-tree-select');
    
    trees.forEach(tree => {
        const list = tree.querySelector('.tree-list');
        const hidden = tree.querySelector('input[type="hidden"]');
        const toggle = tree.querySelector('.tree-toggle');
        
        toggle.addEventListener('click', () => {
            list.style.display = (list.style.display === 'none') ? 'block' : 'none';
        });
        
        tree.querySelectorAll('.tree-node').forEach(node => {
            node.addEventListener('click', (e) => {
                e.stopPropagation();
                hidden.value = node.getAttribute('data-value');
                toggle.innerText = node.innerText;
                list.style.display = 'none';
                hidden.dispatchEvent(new Event('change'));
            });
        });
    });
});
