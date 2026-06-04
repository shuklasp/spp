(function() {
    if (window.__spp_hmr_loaded) return;
    window.__spp_hmr_loaded = true;

    console.log('[SPP HMR] Connecting to Server-Sent Events...');
    
    // Connect to the SSE server (assumed to be running on port + 1 of the current port)
    const currentPort = parseInt(window.location.port || '80');
    const hmrPort = currentPort === 80 ? 8001 : currentPort + 1;
    const sseUrl = `http://${window.location.hostname}:${hmrPort}/hmr.php`;

    function connect() {
        const evtSource = new EventSource(sseUrl);

        evtSource.onmessage = function(e) {
            // keep-alive ping
        };

        evtSource.addEventListener("reload", function(e) {
            console.log('[SPP HMR] File change detected! Reloading...');
            window.location.reload();
        });

        evtSource.onerror = function(e) {
            console.error('[SPP HMR] Connection lost. Reconnecting in 3s...');
            evtSource.close();
            setTimeout(connect, 3000);
        };
    }

    connect();
})();
