(function() {
    // Generate a unique session ID for the page load
    const sessionId = Math.random().toString(36).substring(2, 15);
    const url = window.location.pathname + window.location.search;

    function getApiUrl() {
        // Dynamically find the API endpoint based on the script src or base URL
        // A simple heuristic is that if the app is mounted under /school1/lekhak, we use that.
        // For robustness, we can extract it from the path to this JS file.
        const scripts = document.getElementsByTagName('script');
        for (let i = 0; i < scripts.length; i++) {
            if (scripts[i].src.includes('sankhyaki/assets/js/tracker.js')) {
                const parts = scripts[i].src.split('/modules/sankhyaki/assets/js/tracker.js');
                if (parts.length > 1) {
                    return new URL(parts[0] + '/api/sankhyaki/ping', window.location.origin).href;
                }
            }
        }
        return '/api/sankhyaki/ping'; // fallback
    }

    const apiUrl = getApiUrl();

    function pingTimeOnPage() {
        // Use sendBeacon if available, otherwise fetch
        const data = JSON.stringify({ session_id: sessionId, url: url });
        if (navigator.sendBeacon) {
            navigator.sendBeacon(apiUrl, data);
        } else {
            fetch(apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: data,
                keepalive: true
            }).catch(e => {});
        }
    }

    // Ping every 10 seconds to accumulate time on page
    setInterval(pingTimeOnPage, 10000);

    // Ping immediately on unload
    window.addEventListener('beforeunload', pingTimeOnPage);
})();
