/**
 * SPP Modern Form Engine - Main Loader
 * Loads all component libraries needed for high-fidelity forms.
 */
(function() {
    const scripts = [
        'sppvalidations.js',
        'spprepeater.js',
        'sppautocomplete.js',
        'sppautosave.js',
        'sppcomputed.js',
        'sppfile.js',
        'sppwizard.js',
        'sppsignature.js',
        'spptags.js',
        'sppotp.js',
        'spprating.js',
        'spprange.js',
        'sppcropper.js',
        'spptreeselect.js',
        'sppduallist.js',
        'sppux.js',
        'spppassword.js',
        'sppintelligence.js',
        'sppoffline.js',
        'spportability.js',
        'sppaudit.js'
    ];

    // Auto-resolve base URL based on script location
    const currentScript = document.querySelector('script[src*="sppforms.js"]');
    const baseUrl = currentScript ? currentScript.src.replace('sppforms.js', '').split('?')[0] : '/spp/res/js/';

    scripts.forEach(script => {
        if (!document.querySelector(`script[src*="${script}"]`)) {
            const s = document.createElement('script');
            s.src = baseUrl + script + '?v=' + Date.now();
            s.async = false; // Maintain order if needed, though they are mostly independent
            document.head.appendChild(s);
        }
    });

    console.log('SPP Form Engine: All components loaded.');
})();
