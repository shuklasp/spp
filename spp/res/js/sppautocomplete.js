/**
 * SPP Autocomplete Library
 * Turns standard selects into searchable AJAX-powered inputs.
 */
document.addEventListener('DOMContentLoaded', () => {
    const autocompletes = document.querySelectorAll('.spp-autocomplete');
    
    autocompletes.forEach(select => {
        const sourceUrl = select.getAttribute('data-source');
        if (!sourceUrl) return;
        
        const wrapper = document.createElement('div');
        wrapper.className = 'spp-autocomplete-wrapper';
        wrapper.style.position = 'relative';
        select.parentNode.insertBefore(wrapper, select);
        wrapper.appendChild(select);
        
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'form-control spp-autocomplete-input';
        input.placeholder = 'Type to search...';
        wrapper.insertBefore(input, select);
        select.style.display = 'none';
        
        const results = document.createElement('div');
        results.className = 'spp-autocomplete-results';
        results.style.position = 'absolute';
        results.style.width = '100%';
        results.style.background = '#fff';
        results.style.border = '1px solid #ddd';
        results.style.zIndex = '1000';
        results.style.display = 'none';
        wrapper.appendChild(results);
        
        input.addEventListener('input', debounce(() => {
            const query = input.value;
            if (query.length < 2) {
                results.style.display = 'none';
                return;
            }
            
            fetch(`${sourceUrl}?q=${encodeURIComponent(query)}`)
                .then(r => r.json())
                .then(data => {
                    results.innerHTML = '';
                    results.style.display = 'block';
                    data.forEach(item => {
                        const div = document.createElement('div');
                        div.className = 'spp-autocomplete-item';
                        div.style.padding = '8px 12px';
                        div.style.cursor = 'pointer';
                        div.innerText = item.label;
                        div.addEventListener('click', () => {
                            select.innerHTML = `<option value="${item.value}" selected>${item.label}</option>`;
                            input.value = item.label;
                            results.style.display = 'none';
                            select.dispatchEvent(new Event('change'));
                        });
                        results.appendChild(div);
                    });
                });
        }, 300));
    });
});

function debounce(func, wait) {
    let timeout;
    return function() {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, arguments), wait);
    };
}
