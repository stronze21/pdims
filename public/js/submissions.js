/* Stable keys for ordinary forms and same-origin JSON mutations. Livewire uses signed snapshots. */
(() => {
    if (window.__submissionProtection) return;
    window.__submissionProtection = true;
    const uuid = () => crypto.randomUUID ? crypto.randomUUID() : Array.from(crypto.getRandomValues(new Uint8Array(24)), b => b.toString(16).padStart(2, '0')).join('');
    const writes = method => !['GET', 'HEAD', 'OPTIONS'].includes(method.toUpperCase());
    const sameOrigin = url => new URL(url, location.href).origin === location.origin;
    const pending = new Map();
    const rotateFormKey = key => {
        document.querySelectorAll('input[name="_submission_key"]').forEach(input => {
            if (input.value === key) input.value = uuid();
        });
    };
    const bodyIdentity = body => {
        if (body instanceof FormData) return JSON.stringify(Array.from(body.entries(), ([name, value]) => [name,
            typeof value === 'string' ? value : [value.name, value.size, value.type, value.lastModified]]));
        if (body instanceof URLSearchParams) return body.toString();
        return typeof body === 'string' ? body : JSON.stringify(body || {});
    };
    const prepare = () => document.querySelectorAll('form').forEach(form => {
        if (!writes(form.method || 'GET') || !sameOrigin(form.action || location.href)) return;
        if (!form.querySelector('[name="_submission_key"]')) {
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = '_submission_key'; input.value = uuid();
            form.appendChild(input);
        }
    });
    prepare();
    new MutationObserver(prepare).observe(document.documentElement, { childList: true, subtree: true });
    document.addEventListener('submit', event => {
        const form = event.target;
        if (Array.from(form.attributes).some(a => a.name.startsWith('wire:submit'))) return;
        if (!writes(form.method || 'GET') || !sameOrigin(form.action || location.href)) return;
        if (form.dataset.submitting) { event.preventDefault(); return; }
        form.dataset.submitting = 'true';
        // Wait until the browser has serialized the submitter's name/value.
        setTimeout(() => form.querySelectorAll('button:not([type]), button[type="submit"], input[type="submit"]').forEach(button => {
            if (!button.disabled) { button.dataset.submissionDisabled = 'true'; button.disabled = true; }
        }), 0);
    }, true);
    window.addEventListener('pageshow', () => document.querySelectorAll('form[data-submitting]').forEach(form => {
        delete form.dataset.submitting;
        form.querySelectorAll('[data-submission-disabled]').forEach(button => {
            button.disabled = false; delete button.dataset.submissionDisabled;
        });
    }));

    const originalFetch = window.fetch.bind(window);
    window.fetch = async (input, options = {}) => {
        const url = input instanceof Request ? input.url : String(input);
        const method = (options.method || (input instanceof Request ? input.method : 'GET')).toUpperCase();
        if (!writes(method) || !sameOrigin(url) || new URL(url, location.href).pathname.includes('/livewire/')) return originalFetch(input, options);
        const headers = new Headers(options.headers || (input instanceof Request ? input.headers : {}));
        // Coalesce identical in-flight string-body requests. Callers must retain the key on
        // network failures; the map deliberately retains uncertain requests until refresh.
        const body = options.body === undefined && input instanceof Request ? await input.clone().text() : options.body;
        const signature = method + ':' + new URL(url, location.href).href + ':' + bodyIdentity(body);
        const formKey = body instanceof FormData ? body.get('_submission_key') : null;
        const key = headers.get('Idempotency-Key') || formKey || pending.get(signature) || uuid();
        headers.set('Idempotency-Key', key);
        pending.set(signature, key);
        const response = await originalFetch(input, { ...options, headers });
        if (response.status < 500 && response.status !== 409) {
            pending.delete(signature);
            if (formKey) rotateFormKey(formKey);
        }
        return response;
    };
    const installAxios = () => {
        if (!window.axios || window.axios.__submissionProtection) return;
        window.axios.__submissionProtection = true;
        window.axios.interceptors.request.use(config => {
            if (writes(config.method || 'GET') && sameOrigin(config.url || location.href)) {
                const signature = (config.method || '').toUpperCase() + ':' + config.url + ':' + bodyIdentity(config.data);
                config.headers = config.headers || {};
                const formKey = config.data instanceof FormData ? config.data.get('_submission_key') : null;
                config.headers['Idempotency-Key'] = config.headers['Idempotency-Key'] || formKey || pending.get(signature) || uuid();
                pending.set(signature, config.headers['Idempotency-Key']);
                config.submissionSignature = signature;
            }
            return config;
        });
        window.axios.interceptors.response.use(response => {
            pending.delete(response.config.submissionSignature);
            if (response.config.data instanceof FormData) rotateFormKey(response.config.data.get('_submission_key'));
            return response;
        }, error => {
            if (error.response && error.response.status < 500 && error.response.status !== 409) pending.delete(error.config.submissionSignature);
            return Promise.reject(error);
        });
    };
    installAxios();
    document.addEventListener('DOMContentLoaded', installAxios);

    // Queue polling only reads state and must not lock interactive controls.
    const passiveMethods = new Set(['$refresh', '$set', 'refreshQueues']);
    let activeActions = 0;
    const disabled = new Set();
    const lockActions = () => {
        activeActions++;
        document.querySelectorAll('button, input[type="submit"]').forEach(button => {
            const action = Array.from(button.attributes).some(a => a.name.startsWith('wire:click'))
                || button.type === 'submit';
            if (action && !button.disabled) { disabled.add(button); button.disabled = true; }
        });
        let released = false;
        return () => {
            if (released) return;
            released = true;
            if (--activeActions > 0) return;
            disabled.forEach(button => button.disabled = false);
            disabled.clear();
        };
    };
    const installLivewire3 = () => {
        if (!window.Livewire || window.Livewire.__submissionHooks3) return;
        window.Livewire.__submissionHooks3 = true;
        window.Livewire.hook('request', ({ payload, respond, fail }) => {
            const data = typeof payload === 'string' ? JSON.parse(payload) : payload;
            if (!(data.components || []).some(component => (component.calls || []).some(call => !passiveMethods.has(call.method)))) return;
            const release = lockActions();
            // Restore before morphing so server-rendered disabled states remain authoritative.
            respond(release);
            fail(release);
        });
    };
    const installLivewire2 = () => {
        if (!window.Livewire || window.Livewire.__submissionHooks2) return;
        window.Livewire.__submissionHooks2 = true;
        window.Livewire.hook('message.sent', message => {
            if (message.updateQueue.some(update => update.type === 'callMethod' && !passiveMethods.has(update.payload.method))) {
                message.releaseSubmission = lockActions();
            }
        });
        const release = message => message.releaseSubmission && message.releaseSubmission();
        window.Livewire.hook('message.received', release);
        window.Livewire.hook('message.failed', release);
    };
    document.addEventListener('livewire:init', installLivewire3);
    document.addEventListener('livewire:load', installLivewire2);
    // Legacy layouts can start Livewire before this deferred script executes.
    if (window.Livewire && typeof window.Livewire.dispatch === 'function') installLivewire3();
    else if (window.Livewire && typeof window.Livewire.emit === 'function') installLivewire2();
})();
