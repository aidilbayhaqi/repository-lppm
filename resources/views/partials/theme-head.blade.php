<meta name="color-scheme" content="light dark">
<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
    (() => {
        let theme = 'system';
        try {
            theme = localStorage.getItem('repository-theme') || 'system';
        } catch {}
        if (!['light', 'dark', 'system'].includes(theme)) theme = 'system';
        document.documentElement.dataset.theme = theme === 'system' ? (matchMedia('(prefers-color-scheme: dark)')
            .matches ? 'dark' : 'light') : theme;
        document.documentElement.dataset.themePreference = theme;
    })();
</script>
