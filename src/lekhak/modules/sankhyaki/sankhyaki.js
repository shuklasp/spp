/**
 * Sankhyaki JS Tracker
 * Tracks precise time-on-page and sends a beacon before the user leaves.
 */
(function() {
    let startTime = Date.now();
    let sessionId = '';
    
    // Attempt to get session ID from cookie if available, or rely on server detecting it
    const cookies = document.cookie.split(';');
    for (let c of cookies) {
        if (c.trim().startsWith('PHPSESSID=')) {
            sessionId = c.split('=')[1].trim();
        }
    }

    function sendPing() {
        const timeOnPage = Math.floor((Date.now() - startTime) / 1000);
        
        // Don't ping if less than 1 second
        if (timeOnPage < 1) return;

        const data = JSON.stringify({
            session_id: sessionId,
            url: window.location.pathname,
            time_on_page: timeOnPage
        });

        // Use sendBeacon which is reliable during page unload
        if (navigator.sendBeacon) {
            navigator.sendBeacon('/api/sankhyaki/ping', data);
        } else {
            // Fallback
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '/api/sankhyaki/ping', false); // sync fallback
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.send(data);
        }
    }

    // Ping when visibility changes (e.g. switching tabs or minimizing)
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'hidden') {
            sendPing();
        }
    });

    // Ping right before leaving
    window.addEventListener('beforeunload', function() {
        sendPing();
    });
})();
