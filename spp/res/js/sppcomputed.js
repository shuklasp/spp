/**
 * SPP Computed Fields Library
 * Handles real-time calculations in forms based on data-computed attributes.
 */
document.addEventListener('DOMContentLoaded', () => {
    const computedFields = document.querySelectorAll('[data-computed]');
    
    computedFields.forEach(field => {
        const formula = field.getAttribute('data-computed');
        const dependencies = extractDependencies(formula);
        
        dependencies.forEach(depId => {
            const depField = document.getElementById(depId);
            if (depField) {
                depField.addEventListener('input', () => updateComputedField(field, formula));
            }
        });
        
        // Initial calculation
        updateComputedField(field, formula);
    });
});

function extractDependencies(formula) {
    // Matches {field_id} in the formula
    const matches = formula.match(/\{([^}]+)\}/g);
    return matches ? matches.map(m => m.slice(1, -1)) : [];
}

function updateComputedField(field, formula) {
    let expression = formula;
    const dependencies = extractDependencies(formula);
    
    dependencies.forEach(depId => {
        const depField = document.getElementById(depId);
        const val = depField ? (parseFloat(depField.value) || 0) : 0;
        expression = expression.replace(`{${depId}}`, val);
    });
    
    try {
        // Safe evaluation of the numeric expression
        const result = eval(expression.replace(/[^-()\d/*+.]/g, ''));
        field.value = result.toFixed(2);
        // Trigger change event for other listeners
        field.dispatchEvent(new Event('change'));
    } catch (e) {
        console.error('SPP Computed Error:', e);
    }
}
