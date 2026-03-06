import './bootstrap';

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
