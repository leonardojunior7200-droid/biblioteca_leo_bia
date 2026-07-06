</main>
<footer>
    <p>&copy; <?php echo date('Y'); ?> <?php echo h(SITE_NAME); ?>.</p>
</footer>
<script>
(function() {
    const toggle = document.getElementById('theme-toggle');
    const backgroundToggle = document.getElementById('background-toggle');
    if (!toggle) return;

    const root = document.documentElement;
    const body = document.body;
    const savedTheme = localStorage.getItem('theme');
    const savedBackground = localStorage.getItem('background');
    const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    const isDark = savedTheme === 'dark' || (!savedTheme && prefersDark);
    const showBackground = savedBackground !== 'hidden';

    root.classList.toggle('theme-dark', isDark);
    body.classList.toggle('theme-dark', isDark);
    body.classList.toggle('no-image', !showBackground);

    function updateButton() {
        const active = root.classList.contains('theme-dark');
        toggle.textContent = active ? 'Tema claro' : 'Tema escuro';
    }

    function updateBackgroundButton() {
        if (!backgroundToggle) return;
        backgroundToggle.textContent = body.classList.contains('no-image') ? 'Mostrar fundo' : 'Ocultar fundo';
    }

    updateButton();
    updateBackgroundButton();

    toggle.addEventListener('click', function() {
        const active = root.classList.toggle('theme-dark');
        body.classList.toggle('theme-dark', active);
        localStorage.setItem('theme', active ? 'dark' : 'light');
        updateButton();
    });

    if (backgroundToggle) {
        backgroundToggle.addEventListener('click', function() {
            const hidden = body.classList.toggle('no-image');
            localStorage.setItem('background', hidden ? 'hidden' : 'visible');
            updateBackgroundButton();
        });
    }
})();
</script>
</body>
</html>
