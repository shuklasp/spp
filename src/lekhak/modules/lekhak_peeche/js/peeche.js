(function() {
    if (!window.lekhakPeecheConfig || !window.lekhakPeecheConfig.enabled) {
        return;
    }

    const config = window.lekhakPeecheConfig;

    // Check if we should only intercept external visitors
    if (config.interceptExternalOnly) {
        const referrer = document.referrer;
        // If there's no referrer, or if the referrer is from the same host, don't intercept
        if (!referrer) {
            return; // Direct traffic
        }
        
        try {
            const referrerUrl = new URL(referrer);
            if (referrerUrl.host === config.host) {
                return; // Internal traffic
            }
        } catch (e) {
            return; // Invalid referrer URL
        }
    }

    // Initialize the back button hijack
    function initPeeche() {
        // Push a state into the history, so that when the user clicks back, 
        // they land on this state instead of actually going back
        
        // Push the target state
        history.pushState({ peeche: true }, document.title, window.location.href);
        // Push the current state again so we are on top
        history.pushState({ current: true }, document.title, window.location.href);

        window.addEventListener("popstate", function(e) {
            // If we popped into the peeche state, redirect to target
            if (e.state && e.state.peeche) {
                window.location.href = config.targetUrl;
            }
        }, false);
    }

    // Run when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPeeche);
    } else {
        initPeeche();
    }
})();
