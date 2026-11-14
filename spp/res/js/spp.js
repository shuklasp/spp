window.spp = window.spp || {};

spp.navigate=function(url){
	window.location.href=url;
}

spp.callService=function(url,data,callback){
    $.ajax({
        type: "POST",
        url: url,
        data: data,
        dataType: "json",
        success: callback
    });
}

spp.alert=function(message){
    alert(message);
}

spp.confirm=function(message){
    return confirm(message);
}

spp.prompt=function(message){
    return prompt(message);
}

spp.updateSection=function(section){
    $('#section').val(section);
};

// =========================================================================
// SPP Universal Client Diagnostic & Error Boundary for All SPP Applications
// =========================================================================
(function() {
    if (typeof window === 'undefined' || window.__spp_global_error_listener_installed) return;
    window.__spp_global_error_listener_installed = true;

    const isDevEnvironment = () => {
        return window.SPP_DEBUG === true || 
               location.hostname === 'localhost' || 
               location.hostname === '127.0.0.1' ||
               location.search.includes('debug=1') ||
               location.pathname.includes('/sppdev/') ||
               location.pathname.includes('/spp/admin/');
    };

    const showFloatingDiagnosticToast = (title, message, error) => {
        if (!isDevEnvironment()) return;
        
        let container = document.getElementById('spp-dev-error-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'spp-dev-error-toast-container';
            container.style.cssText = 'position:fixed; bottom:20px; right:20px; z-index:999999; max-width:480px; display:flex; flex-direction:column; gap:10px; font-family:Inter,system-ui,sans-serif; pointer-events:none;';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.style.cssText = 'pointer-events:auto; background:#0d1117; border:1px solid #f85149; border-left:4px solid #f85149; border-radius:8px; padding:12px 16px; box-shadow:0 8px 24px rgba(0,0,0,0.6); color:#f0f6fc; font-size:0.8rem;';
        
        const isSyntax = error instanceof SyntaxError || (message && (message.includes('Unexpected token') || message.includes('SyntaxError')));
        const typeBadge = isSyntax ? 'Syntax Error' : 'Uncaught Exception';

        toast.innerHTML = `
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:8px;">
                <div style="font-weight:700; color:#f85149; display:flex; align-items:center; gap:6px;">
                    <span>🚨</span> ${title}
                    <span style="background:rgba(248,81,73,0.15); font-size:0.65rem; padding:1px 6px; border-radius:10px; text-transform:uppercase;">${typeBadge}</span>
                </div>
                <button type="button" style="background:none; border:none; color:#8b949e; cursor:pointer; font-size:1rem; padding:0;" onclick="this.parentElement.parentElement.remove();">✕</button>
            </div>
            <div style="margin-top:6px; color:#c9d1d9; font-family:monospace; font-size:0.75rem; word-break:break-word; max-height:80px; overflow-y:auto; line-height:1.4;">${message}</div>
            <div style="margin-top:8px; display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" style="background:#21262d; border:1px solid #30363d; color:#58a6ff; font-size:0.7rem; padding:2px 8px; border-radius:4px; cursor:pointer;" onclick="console.error('${title}:', ${JSON.stringify(message)}); alert('Check browser console (F12) for detailed stack trace.');">
                    🔍 View in Console
                </button>
            </div>
        `;

        container.appendChild(toast);
        setTimeout(() => { if (toast.parentNode) toast.remove(); }, 12000);
    };

    window.addEventListener('error', function(event) {
        if (!event || !event.message) return;
        showFloatingDiagnosticToast('Frontend Script Error', `${event.message} at ${event.filename || 'unknown'}:${event.lineno || 0}`, event.error);
    });

    window.addEventListener('unhandledrejection', function(event) {
        if (!event || !event.reason) return;
        const msg = event.reason.message || String(event.reason);
        showFloatingDiagnosticToast('Unhandled Promise Rejection', msg, event.reason);
    });
})();