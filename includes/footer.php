<div class="footer">
    <p>&copy; <?= date('Y') ?> TokoKu. Semua hak dilindungi.</p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {

    const navToggle = document.getElementById('navToggle');
    const navbarNav = document.getElementById('navbarNav');
    const navOverlay = document.getElementById('navOverlay');

    if (!navToggle) return;

    navToggle.addEventListener('click', function() {
        navToggle.classList.toggle('is-active');
        navbarNav.classList.toggle('is-open');
        navOverlay.classList.toggle('is-visible');

        if (navbarNav.classList.contains('is-open')) {
            document.body.classList.add('no-scroll');
        } else {
            document.body.classList.remove('no-scroll');
        }
    });

    navOverlay.addEventListener('click', function() {
        navToggle.classList.remove('is-active');
        navbarNav.classList.remove('is-open');
        navOverlay.classList.remove('is-visible');
        document.body.classList.remove('no-scroll');
    });

    navbarNav.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', () => {
            navToggle.classList.remove('is-active');
            navbarNav.classList.remove('is-open');
            navOverlay.classList.remove('is-visible');
            document.body.classList.remove('no-scroll');
        });
    });

});
</script>

</body>
</html>
