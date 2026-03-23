
</main><!-- /.site-main -->

<footer class="site-footer">
    <div class="footer-inner">
        <p class="footer-copy">&copy;2026 &mdash; FMEL &mdash; Chantier LET</p>
        <p class="footer-disclaimer">
            Les informations publiées sur ce site le sont à titre informatif et n'ont pas de portée légale.
        </p>
    </div>
</footer>

<script>
// Mobile nav toggle
(function () {
    var btn = document.querySelector('.nav-toggle');
    var nav = document.getElementById('site-nav');
    if (!btn || !nav) return;
    btn.addEventListener('click', function () {
        var expanded = btn.getAttribute('aria-expanded') === 'true';
        btn.setAttribute('aria-expanded', String(!expanded));
        nav.classList.toggle('site-nav--open', !expanded);
    });
})();
</script>

</body>
</html>
