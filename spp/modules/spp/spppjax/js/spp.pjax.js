/**
 * SPP PJAX Engine
 * A lightweight, dependency-free Single Page Application (SPA) navigator.
 */

window.spp = window.spp || {};

window.spp.pjax = {
    options: {
        containers: ['body', 'title'],
        interceptAll: false
    },
    
    init: function(options) {
        this.options = Object.assign(this.options, options || {});
        
        // Listen to all click events on the document
        document.addEventListener('click', function(e) {
            let link = e.target.closest('a');
            if (!link) return;
            
            let url = link.getAttribute('href');
            
            // Ignore links without href, javascript links, hash links, or new tab links
            if (!url || url.indexOf('javascript:') === 0 || url.indexOf('#') === 0 || link.getAttribute('target') === '_blank') return;
            
            // Ignore external links
            if (link.hostname && link.hostname !== window.location.hostname) return;
            
            // Ignore links marked with data-pjax="false" or class "no-pjax"
            if (link.getAttribute('data-pjax') === 'false' || link.classList.contains('no-pjax')) return;
            
            // If interceptAll is false, only intercept if explicitly marked data-pjax="true"
            if (!spp.pjax.options.interceptAll && link.getAttribute('data-pjax') !== 'true') return;
            
            e.preventDefault();
            spp.pjax.load(link.href);
        });

        // Listen for form submissions
        document.addEventListener('submit', function(e) {
            let form = e.target;
            
            // Don't intercept multipart forms (file uploads)
            if (form.getAttribute('enctype') === 'multipart/form-data') return;
            
            // Ignore if explicitly disabled
            if (form.getAttribute('data-pjax') === 'false' || form.classList.contains('no-pjax')) return;

            // If interceptAll is false, only intercept if explicitly marked data-pjax="true"
            if (!spp.pjax.options.interceptAll && form.getAttribute('data-pjax') !== 'true') return;

            e.preventDefault();
            
            let method = (form.getAttribute('method') || 'GET').toUpperCase();
            let action = form.getAttribute('action') || window.location.href;
            let formData = new FormData(form);
            
            // If the submit button has a name and value, include it
            if (e.submitter && e.submitter.name) {
                formData.append(e.submitter.name, e.submitter.value);
            }

            spp.pjax.submit(action, method, formData);
        });

        // Handle browser back/forward buttons
        window.addEventListener('popstate', function(e) {
            if (e.state && e.state.pjax) {
                spp.pjax.load(window.location.href, true);
            }
        });
        
        // Push initial state
        if (!window.history.state || !window.history.state.pjax) {
            window.history.replaceState({pjax: true}, document.title, window.location.href);
        }
    },

    load: function(url, isPopState = false) {
        document.body.style.cursor = 'wait';
        
        fetch(url, {
            headers: {
                'X-PJAX': 'true',
                'Accept': 'text/html'
            }
        })
        .then(response => {
            if (response.redirected) {
                url = response.url;
            }
            if (!response.ok) throw new Error("Network response was not ok");
            return response.text();
        })
        .then(html => {
            document.body.style.cursor = '';
            spp.pjax.processHTML(html, url, isPopState);
        })
        .catch(err => {
            console.error('PJAX Error:', err);
            document.body.style.cursor = '';
            window.location.href = url; // Fallback
        });
    },

    submit: function(url, method, formData) {
        document.body.style.cursor = 'wait';
        console.log('PJAX Form Submit Intercepted:', method, url);
        
        let fetchOptions = {
            method: method,
            headers: {
                'X-PJAX': 'true',
                'Accept': 'text/html'
            }
        };

        if (method === 'GET') {
            let params = new URLSearchParams(formData).toString();
            url = url + (url.indexOf('?') === -1 ? '?' : '&') + params;
        } else {
            // Encode as application/x-www-form-urlencoded for better PHP $_POST compatibility
            fetchOptions.body = new URLSearchParams(formData).toString();
            fetchOptions.headers['Content-Type'] = 'application/x-www-form-urlencoded';
        }

        fetch(url, fetchOptions)
        .then(response => {
            if (response.redirected) {
                url = response.url;
            }
            if (!response.ok) throw new Error("Network response was not ok");
            return response.text();
        })
        .then(html => {
            document.body.style.cursor = '';
            spp.pjax.processHTML(html, url, false);
        })
        .catch(err => {
            console.error('PJAX Submit Error:', err);
            document.body.style.cursor = '';
            window.location.reload(); 
        });
    },

    processHTML: function(html, url, isPopState) {
        let parser = new DOMParser();
        let doc = parser.parseFromString(html, 'text/html');
        
        let successfulSwaps = 0;

        // Process configured containers
        this.options.containers.forEach(selector => {
            if (selector === 'title') {
                if (doc.title) {
                    document.title = doc.title;
                    successfulSwaps++;
                }
                return;
            }

            let newContainer = doc.querySelector(selector);
            let currentContainer = document.querySelector(selector);
            
            if (newContainer && currentContainer) {
                currentContainer.innerHTML = newContainer.innerHTML;
                successfulSwaps++;
            }
        });

        // Update history
        if (!isPopState) {
            window.history.pushState({pjax: true}, document.title, url);
        }

        // Re-evaluate scripts in replaced containers
        this.options.containers.forEach(selector => {
            if (selector === 'title') return;
            let currentContainer = document.querySelector(selector);
            if (currentContainer) {
                let scripts = currentContainer.querySelectorAll('script');
                scripts.forEach(oldScript => {
                    let newScript = document.createElement('script');
                    Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                    newScript.appendChild(document.createTextNode(oldScript.innerHTML));
                    oldScript.parentNode.replaceChild(newScript, oldScript);
                });
            }
        });

        // Dispatch a custom event for other scripts to know DOM changed
        document.dispatchEvent(new Event('pjax:complete'));
    }
};

// Auto-initialize if configured globally
if (window.sppPjaxConfig) {
    spp.pjax.init(window.sppPjaxConfig);
}
