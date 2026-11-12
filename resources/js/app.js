import './bootstrap';
import './prescription-entry-training';
import './opd-spotlight';

document.addEventListener('livewire:init', () => {
    // 1. The Directive
    Livewire.directive('mary-confirm', ({ el, directive, cleanup }) => {
        let message = directive.expression;

        let onClick = (e) => {
            if (el.dataset.isConfirming) return;

            e.preventDefault();
            e.stopImmediatePropagation();

            // Trigger the Alpine store
            Alpine.store('maryConfirm').ask(message, () => {
                el.dataset.isConfirming = true;
                el.click();
                delete el.dataset.isConfirming;
            });
        };

        el.addEventListener('click', onClick, { capture: true });

        cleanup(() => {
            el.removeEventListener('click', onClick, { capture: true });
        });
    });
});

// 2. The Alpine Store (Must be outside livewire:init)
document.addEventListener('alpine:init', () => {
    Alpine.store('maryConfirm', {
        open: false,
        title: 'Are you sure?',
        description: '',
        action: null,

        ask(description, action) {
            this.title = 'Confirm Action';
            this.description = description;
            this.action = action;
            this.open = true;
        },

        confirm() {
            if (this.action) this.action();
            this.close();
        },

        close() {
            this.open = false;
            this.action = null;
        }
    });
});

// One appearance preference for login, pharmacy, portal, dispensing and queues.
document.addEventListener('alpine:init', () => {
    Alpine.data('pharmacyAppearance', () => ({
        dark: document.documentElement.dataset.theme === 'dark',
        init() {
            this.onThemeChanged = (event) => { this.dark = event.detail === 'dark'; };
            this.onStorage = (event) => {
                if (event.key === 'mary-theme') {
                    this.applyTheme(event.newValue?.replaceAll('"', '') === 'dark', false);
                }
            };
            window.addEventListener('theme-changed', this.onThemeChanged);
            window.addEventListener('storage', this.onStorage);
        },
        destroy() {
            window.removeEventListener('theme-changed', this.onThemeChanged);
            window.removeEventListener('storage', this.onStorage);
        },
        applyTheme(dark, persist = true) {
            this.dark = dark;
            const theme = dark ? 'dark' : 'light';
            document.documentElement.dataset.theme = theme;
            document.documentElement.classList.toggle('dark', dark);
            if (persist) {
                try {
                    localStorage.setItem('mary-theme', JSON.stringify(theme));
                    localStorage.setItem('mary-class', JSON.stringify(theme));
                    localStorage.setItem('pdims.login.theme', theme);
                } catch { /* Appearance remains usable without browser storage. */ }
            }
            window.dispatchEvent(new CustomEvent('theme-changed', { detail: theme }));
        },
        toggleTheme() { this.applyTheme(!this.dark); },
    }));
});
