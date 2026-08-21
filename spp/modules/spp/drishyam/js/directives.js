/**
 * SPP-UX HTML-First Declarative Directives Engine
 * 
 * Extracted from the monolithic sppux.js (v11 lines 1534-2055).
 * Provides 18 zero-JS HTML attribute directives for building
 * interactive pages without writing any JavaScript.
 * 
 * Lazy-loaded by sppux.js only when directive attributes are detected
 * on the page, saving parse time for pages that don't use them.
 * 
 * @module directives
 * @version 13.0.0
 */

/**
 * Initialize all HTML-First directives.
 * Called by sppux.js after lazy-loading this module.
 */
export function initHtmlDirectives() {
    console.log("⚡ SPPUX HTML-First Directives Engine Initialized");

    // 1. Zero-JS Declarative Actions: data-spp-post / data-spp-action
    document.addEventListener('submit', async (e) => {
        const targetForm = e.target && e.target.closest ? e.target.closest('[data-spp-post], [data-spp-action]') : null;
        if (!targetForm) return;

        e.preventDefault();
        const action = targetForm.getAttribute('data-spp-post') || targetForm.getAttribute('data-spp-action');
        const targetSelector = targetForm.getAttribute('data-spp-target');

        window.SPPUX.Busy.start();
        try {
            const formData = new FormData(targetForm);
            if (!formData.has('action')) formData.append('action', action);

            const res = await window.SPPUX.apiPost(formData);
            if (res && res.html && targetSelector) {
                const dest = document.querySelector(targetSelector);
                if (dest) {
                    const transition = targetForm.getAttribute('data-spp-transition');
                    if (transition) dest.classList.add(`spp-transition-${transition}`);
                    dest.innerHTML = res.html;
                    if (transition) setTimeout(() => dest.classList.remove(`spp-transition-${transition}`), 100);
                }
            }

            if (res && res.message) {
                const notifyAttr = targetForm.getAttribute('data-spp-notify') || res.message;
                if (window.SPPUX.Notify) window.SPPUX.Notify.show(notifyAttr, res.status || 'info');
                else alert(notifyAttr);
            }
        } catch (err) {
            console.error("[SPPUX Directives] Action processing failed:", err);
        } finally {
            window.SPPUX.Busy.stop();
        }
    });

    // 2. HTML-Native Two-Way Signal Binding (data-spp-bind <-> data-spp-text)
    const sharedSignals = new Map();
    document.addEventListener('input', (e) => {
        const bindKey = e.target.getAttribute('data-spp-bind');
        if (!bindKey) return;
        const val = e.target.value;
        sharedSignals.set(bindKey, val);
        document.querySelectorAll(`[data-spp-text="${bindKey}"]`).forEach(node => {
            node.textContent = val;
        });
    });

    // 3. Live DOM Search Filtering (data-spp-search)
    document.addEventListener('input', (e) => {
        const targetGridSelector = e.target.getAttribute('data-spp-search');
        if (!targetGridSelector) return;
        const destGrid = document.querySelector(targetGridSelector);
        if (destGrid) {
            const query = (e.target.value || '').toLowerCase();
            destGrid.querySelectorAll('[data-search-name]').forEach(item => {
                const name = (item.getAttribute('data-search-name') || '').toLowerCase();
                item.style.display = name.includes(query) ? '' : 'none';
            });
        }
    });

    // 4. Custom Component Tag Observers (<spp-component>)
    const observeCustomTags = () => {
        document.querySelectorAll('spp-component:not([data-initialized])').forEach(comp => {
            comp.setAttribute('data-initialized', 'true');
            const name = comp.getAttribute('name');
            const island = comp.getAttribute('data-island') || 'visible';
            comp.setAttribute('data-spp-island', island);
        });
    };
    observeCustomTags();
    const observer = new MutationObserver(() => observeCustomTags());
    observer.observe(document.body, { childList: true, subtree: true });

    // 5. Scroll Animations (data-spp-animate)
    const animateObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('spp-animated');
                animateObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });

    const observeAnimations = () => {
        document.querySelectorAll('[data-spp-animate]:not(.spp-animated-tracked)').forEach(el => {
            el.classList.add('spp-animated-tracked');
            animateObserver.observe(el);
        });
    };
    observeAnimations();
    const animMutObserver = new MutationObserver(() => observeAnimations());
    animMutObserver.observe(document.body, { childList: true, subtree: true });

    // 6. Global Hotkeys (data-spp-hotkey)
    document.addEventListener('keydown', (e) => {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
        const keys = [];
        if (e.ctrlKey) keys.push('ctrl');
        if (e.altKey) keys.push('alt');
        if (e.shiftKey) keys.push('shift');
        if (e.metaKey) keys.push('meta');
        keys.push(e.key.toLowerCase());
        const combo = keys.join('+');

        const target = document.querySelector(`[data-spp-hotkey="${combo}"]`);
        if (target) {
            e.preventDefault();
            target.click();
        }
    });

    // 7. Infinite Scroll (data-spp-infinite-scroll)
    const infiniteObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const el = entry.target;
                const eventName = el.getAttribute('data-spp-infinite-scroll');
                if (eventName && window.SPPUX && SPPUX.events) {
                    SPPUX.events.emit(eventName, el);
                }
            }
        });
    }, { rootMargin: '200px' });

    const observeInfinite = () => {
        document.querySelectorAll('[data-spp-infinite-scroll]:not(.spp-infinite-tracked)').forEach(el => {
            el.classList.add('spp-infinite-tracked');
            infiniteObserver.observe(el);
        });
    };
    observeInfinite();
    const infMutObserver = new MutationObserver(() => observeInfinite());
    infMutObserver.observe(document.body, { childList: true, subtree: true });

    // 8. Copy to Clipboard (data-spp-copy)
    document.addEventListener('click', async (e) => {
        const copyTarget = e.target && e.target.closest ? e.target.closest('[data-spp-copy]') : null;
        if (copyTarget) {
            const selectorId = copyTarget.getAttribute('data-spp-copy');
            let text = '';
            if (selectorId && document.getElementById(selectorId)) {
                const el = document.getElementById(selectorId);
                text = el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' ? el.value : el.innerText;
            } else {
                text = copyTarget.innerText;
            }
            if (text) {
                try {
                    await navigator.clipboard.writeText(text);
                    const original = copyTarget.innerHTML;
                    copyTarget.innerHTML = '<span>✓ Copied!</span>';
                    setTimeout(() => copyTarget.innerHTML = original, 2000);
                } catch(err) { console.warn('Copy failed', err); }
            }
        }
    });

    // 9. Ripple Effect (data-spp-ripple)
    document.addEventListener('pointerdown', (e) => {
        const rippleEl = e.target && e.target.closest ? e.target.closest('[data-spp-ripple]') : null;
        if (rippleEl) {
            const rect = rippleEl.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            const circle = document.createElement('span');
            circle.style.position = 'absolute';
            circle.style.background = 'rgba(255,255,255,0.3)';
            circle.style.borderRadius = '50%';
            circle.style.pointerEvents = 'none';
            circle.style.transform = 'translate(-50%, -50%) scale(0)';
            circle.style.animation = 'sppux-ripple-anim 0.6s linear';
            circle.style.left = x + 'px';
            circle.style.top = y + 'px';
            const size = Math.max(rect.width, rect.height);
            circle.style.width = circle.style.height = size + 'px';
            rippleEl.style.position = rippleEl.style.position === 'static' ? 'relative' : rippleEl.style.position;
            rippleEl.style.overflow = 'hidden';
            rippleEl.appendChild(circle);
            setTimeout(() => circle.remove(), 600);
        }
    });

    // 10. Input Masking (data-spp-mask)
    document.addEventListener('input', (e) => {
        const mask = e.target.getAttribute('data-spp-mask');
        if (mask) {
            let val = e.target.value.replace(/\D/g, '');
            if (mask === 'phone') {
                const match = val.match(/^(\d{0,3})(\d{0,3})(\d{0,4})$/);
                if (match) val = !match[2] ? match[1] : '(' + match[1] + ') ' + match[2] + (match[3] ? '-' + match[3] : '');
            } else if (mask === 'date') {
                const match = val.match(/^(\d{0,2})(\d{0,2})(\d{0,4})$/);
                if (match) val = !match[2] ? match[1] : match[1] + '/' + match[2] + (match[3] ? '/' + match[3] : '');
            }
            e.target.value = val;
        }
    });

    // 11. Parallax (data-spp-parallax)
    window.addEventListener('scroll', () => {
        document.querySelectorAll('[data-spp-parallax]').forEach(el => {
            const speed = parseFloat(el.getAttribute('data-spp-parallax') || '0.5');
            const yPos = -(window.scrollY * speed);
            el.style.transform = `translateY(${yPos}px)`;
        });
    });

    // 12. Form Validation (data-spp-validate)
    const validateField = (el) => {
        const rules = el.getAttribute('data-spp-validate').split('|');
        let error = null;
        const val = el.value.trim();
        for (const r of rules) {
            if (r === 'required' && !val) { error = 'This field is required'; break; }
            if (r === 'email' && val && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) { error = 'Invalid email address'; break; }
            if (r.startsWith('min:')) {
                const min = parseInt(r.split(':')[1], 10);
                if (val.length < min) { error = `Minimum ${min} characters required`; break; }
            }
        }

        let errEl = el.nextElementSibling;
        if (errEl && errEl.classList.contains('spp-err-msg')) errEl.remove();

        if (error) {
            el.classList.add('spp-invalid');
            el.insertAdjacentHTML('afterend', `<div class="spp-err-msg" style="color:var(--sppux-danger);font-size:0.8rem;margin-top:4px;">${error}</div>`);
            return false;
        } else {
            el.classList.remove('spp-invalid');
            return true;
        }
    };

    document.addEventListener('blur', (e) => {
        if (e.target && e.target.hasAttribute && e.target.hasAttribute('data-spp-validate')) validateField(e.target);
    }, true);

    document.addEventListener('input', (e) => {
        if (e.target && e.target.hasAttribute && e.target.hasAttribute('data-spp-validate') && e.target.classList.contains('spp-invalid')) {
            validateField(e.target);
        }
    });

    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (form && form.hasAttribute && form.hasAttribute('data-spp-form')) {
            let valid = true;
            form.querySelectorAll('[data-spp-validate]').forEach(el => {
                if (!validateField(el)) valid = false;
            });
            if (!valid) e.preventDefault();
        }
    });

    // 13. Magnetic Elements (data-spp-magnetic)
    document.addEventListener('mousemove', (e) => {
        document.querySelectorAll('[data-spp-magnetic]').forEach(el => {
            const rect = el.getBoundingClientRect();
            const strength = parseFloat(el.getAttribute('data-spp-magnetic') || '0.2');
            const centerX = rect.left + rect.width / 2;
            const centerY = rect.top + rect.height / 2;
            const distanceX = e.clientX - centerX;
            const distanceY = e.clientY - centerY;

            if (Math.abs(distanceX) < 100 && Math.abs(distanceY) < 100) {
                el.style.transform = `translate(${distanceX * strength}px, ${distanceY * strength}px)`;
                el.style.transition = 'none';
            } else {
                el.style.transform = 'translate(0px, 0px)';
                el.style.transition = 'transform 0.3s ease';
            }
        });
    });

    // 14. Typewriter Effect (data-spp-typewriter)
    const typeWriterObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting && !entry.target.classList.contains('spp-typed')) {
                const el = entry.target;
                el.classList.add('spp-typed');
                const text = el.getAttribute('data-spp-typewriter') || el.innerText;
                el.innerText = '';
                let i = 0;
                const speed = 30;
                const type = () => {
                    if (i < text.length) {
                        el.innerHTML += text.charAt(i);
                        i++;
                        setTimeout(type, speed);
                    }
                };
                type();
            }
        });
    });
    const observeTypewriters = () => {
        document.querySelectorAll('[data-spp-typewriter]:not(.spp-typewriter-tracked)').forEach(el => {
            el.classList.add('spp-typewriter-tracked');
            typeWriterObserver.observe(el);
        });
    };
    observeTypewriters();
    const typeMutObserver = new MutationObserver(() => observeTypewriters());
    typeMutObserver.observe(document.body, { childList: true, subtree: true });

    // 15. Pull to Refresh (data-spp-pull-refresh)
    let pullStartY = 0;
    let isPulling = false;
    let pullSpinner = null;
    document.addEventListener('touchstart', (e) => {
        if (window.scrollY === 0) {
            pullStartY = e.touches[0].clientY;
            isPulling = true;
        }
    }, {passive: true});
    document.addEventListener('touchmove', (e) => {
        if (!isPulling) return;
        const y = e.touches[0].clientY;
        const dy = y - pullStartY;
        if (dy > 0 && window.scrollY === 0) {
            if (!pullSpinner) {
                pullSpinner = document.createElement('div');
                pullSpinner.innerHTML = '↻';
                pullSpinner.style.cssText = 'position:fixed;top:-40px;left:50%;transform:translateX(-50%);width:30px;height:30px;background:var(--sppux-panel);border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 10px rgba(0,0,0,0.2);z-index:9999;transition:0.2s;font-weight:bold;color:var(--sppux-primary);';
                document.body.appendChild(pullSpinner);
            }
            pullSpinner.style.top = Math.min(dy / 2 - 40, 20) + 'px';
            pullSpinner.style.transform = `translateX(-50%) rotate(${dy * 2}deg)`;
        }
    }, {passive: true});
    document.addEventListener('touchend', (e) => {
        if (!isPulling) return;
        isPulling = false;
        if (pullSpinner) {
            if (parseInt(pullSpinner.style.top) >= 20) {
                const target = document.querySelector('[data-spp-pull-refresh]');
                if (target) {
                    pullSpinner.innerHTML = '...';
                    const evt = target.getAttribute('data-spp-pull-refresh');
                    if (evt && window.SPPUX && SPPUX.events) SPPUX.events.emit(evt, target);
                    else location.reload();
                } else {
                    location.reload();
                }
                setTimeout(() => { if (pullSpinner) pullSpinner.remove(); pullSpinner = null; }, 1000);
            } else {
                pullSpinner.remove();
                pullSpinner = null;
            }
        }
    });

    // 16. Canvas Particle Network (data-spp-particles)
    const initParticles = (canvas) => {
        const ctx = canvas.getContext('2d');
        let width = canvas.width = canvas.parentElement.clientWidth || 800;
        let height = canvas.height = canvas.parentElement.clientHeight || 600;
        const color = canvas.getAttribute('data-spp-particles') || 'rgba(255, 255, 255, 0.5)';
        const particles = [];
        for(let i=0; i<50; i++) particles.push({ x: Math.random()*width, y: Math.random()*height, vx: (Math.random()-0.5)*1, vy: (Math.random()-0.5)*1, radius: Math.random()*2+1 });

        const draw = () => {
            ctx.clearRect(0, 0, width, height);
            for(let i=0; i<particles.length; i++) {
                const p = particles[i];
                p.x += p.vx; p.y += p.vy;
                if(p.x < 0 || p.x > width) p.vx *= -1;
                if(p.y < 0 || p.y > height) p.vy *= -1;
                ctx.beginPath(); ctx.arc(p.x, p.y, p.radius, 0, Math.PI*2); ctx.fillStyle = color; ctx.fill();
                for(let j=i+1; j<particles.length; j++) {
                    const p2 = particles[j];
                    const dist = Math.hypot(p.x-p2.x, p.y-p2.y);
                    if(dist < 100) {
                        ctx.beginPath(); ctx.moveTo(p.x, p.y); ctx.lineTo(p2.x, p2.y);
                        ctx.strokeStyle = color.replace(/[\d\.]+\)$/, (1 - dist/100) + ')'); ctx.stroke();
                    }
                }
            }
            requestAnimationFrame(draw);
        };
        draw();
        window.addEventListener('resize', () => { width = canvas.width = canvas.parentElement.clientWidth; height = canvas.height = canvas.parentElement.clientHeight; });
    };

    const particleObserver = new MutationObserver(() => {
        document.querySelectorAll('canvas[data-spp-particles]:not(.spp-particles-init)').forEach(el => {
            el.classList.add('spp-particles-init');
            initParticles(el);
        });
    });
    particleObserver.observe(document.body, { childList: true, subtree: true });
    document.querySelectorAll('canvas[data-spp-particles]').forEach(el => { el.classList.add('spp-particles-init'); initParticles(el); });

    // 17. 3D Tilt Effect (data-spp-tilt)
    document.addEventListener('mousemove', (e) => {
        document.querySelectorAll('[data-spp-tilt]').forEach(el => {
            const rect = el.getBoundingClientRect();
            if(e.clientX >= rect.left && e.clientX <= rect.right && e.clientY >= rect.top && e.clientY <= rect.bottom) {
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;
                const rotateX = ((y - centerY) / centerY) * -15;
                const rotateY = ((x - centerX) / centerX) * 15;
                el.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale3d(1.05, 1.05, 1.05)`;
                el.style.transition = 'transform 0.1s ease-out';
            } else {
                el.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) scale3d(1, 1, 1)';
                el.style.transition = 'transform 0.5s ease-out';
            }
        });
    });
    if (window.DeviceOrientationEvent) {
        window.addEventListener('deviceorientation', (e) => {
            document.querySelectorAll('[data-spp-tilt]').forEach(el => {
                const rotateX = Math.max(-15, Math.min(15, e.beta - 45));
                const rotateY = Math.max(-15, Math.min(15, e.gamma));
                el.style.transform = `perspective(1000px) rotateX(${-rotateX}deg) rotateY(${rotateY}deg)`;
            });
        });
    }

    // 18. Voice Input Dictation (data-spp-voice-input)
    document.addEventListener('click', (e) => {
        const btn = e.target && e.target.closest ? e.target.closest('[data-spp-voice-input]') : null;
        if (btn) {
            const targetId = btn.getAttribute('data-spp-voice-input');
            const targetEl = document.getElementById(targetId);
            if (!targetEl) return;

            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!SpeechRecognition) {
                if (window.SPPUX && SPPUX.notify) SPPUX.notify('Speech Recognition API not supported in this browser.', 'warning');
                return;
            }

            const recognition = new SpeechRecognition();
            recognition.continuous = false;
            recognition.interimResults = true;

            const originalHtml = btn.innerHTML;
            btn.innerHTML = '🎙️ (Listening...)';
            btn.style.color = 'var(--sppux-danger)';

            recognition.onresult = (event) => {
                let text = '';
                for (let i = 0; i < event.results.length; i++) {
                    text += event.results[i][0].transcript;
                }
                targetEl.value = text;
                if(window.SPPUX && window.SPPUX.events) SPPUX.events.emit('input', targetEl);
            };

            recognition.onerror = (event) => { console.warn('Speech error', event.error); };
            recognition.onend = () => {
                btn.innerHTML = originalHtml;
                btn.style.color = '';
            };

            recognition.start();
        }
    });
}
