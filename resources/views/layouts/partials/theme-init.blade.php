{{-- Restore appearance before styles paint. Print views intentionally stay light. --}}
<script>
    (() => {
        function restoreTheme() {
            let theme = 'light';
            try {
                const saved = localStorage.getItem('mary-theme') || localStorage.getItem('pdims.login.theme');
                theme = saved ? saved.replaceAll('"', '') : (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            } catch {}
            theme = theme === 'dark' ? 'dark' : 'light';
            document.documentElement.dataset.theme = theme;
            document.documentElement.classList.toggle('dark', theme === 'dark');
            window.dispatchEvent(new CustomEvent('theme-changed', { detail: theme }));
        }

        restoreTheme();

        // Livewire replaces the root attributes without rerunning this shared head script.
        document.addEventListener('livewire:navigated', () => {
            if (document.body.classList.contains('pdims-theme')) restoreTheme();
        });
    })();
</script>
