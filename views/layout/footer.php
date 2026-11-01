</main>

<footer class="footer-apple mt-auto">
    <div class="container">
        <div class="row g-4 footer-cols">
            <div class="col-md-5">
                <div class="fw-semibold mb-1"><i class="bi bi-buildings text-brand me-1"></i>B2B Портал</div>
                <div class="small text-muted">Платформа для оптовых поставок между компаниями: каталог, заявки, оплата через администратора и проверка качества силами персонала портала.</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="footer-title">Разделы</div>
                <ul class="list-unstyled small mb-0">
                    <li><a href="/">Каталог</a></li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li><a href="<?= e(isStaffRole($_SESSION['role'] ?? '') ? staffHome($_SESSION['role'] ?? '') : '/account') ?>">Личный кабинет</a></li>
                        <li><a href="/profile">Профиль</a></li>
                    <?php else: ?>
                        <li><a href="/login">Вход</a></li>
                        <li><a href="/register">Регистрация компании</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="col-6 col-md-4">
                <div class="footer-title">Сотрудничество</div>
                <ul class="list-unstyled small mb-0">
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <li><a href="/inspector/apply">Стать проверяющим</a></li>
                        <li><a href="/join">Присоединиться к компании по коду</a></li>
                    <?php else: ?>
                        <li class="text-muted">Проверка качества — в разделе «Сделки»</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <div class="footer-bottom small text-muted d-flex justify-content-between flex-wrap gap-2">
            <span>&copy; <?= date('Y') ?> B2B Портал</span>
            <a href="#top" class="text-decoration-none text-brand"><i class="bi bi-arrow-up-circle me-1"></i>Наверх</a>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* переключатель темы: авто → светлая → тёмная */
(function () {
    var order = ['auto', 'light', 'dark'];
    var icons = { auto: 'bi-circle-half', light: 'bi-sun', dark: 'bi-moon-stars' };
    var names = { auto: 'авто', light: 'светлая', dark: 'тёмная' };
    var mq = window.matchMedia('(prefers-color-scheme: dark)');
    function pref() { try { return localStorage.getItem('theme') || 'auto'; } catch (e) { return 'auto'; } }
    function apply() {
        var p = pref();
        var dark = p === 'dark' || (p === 'auto' && mq.matches);
        document.documentElement.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
        var meta = document.querySelector('meta[name="theme-color"]');
        if (meta) meta.setAttribute('content', dark ? '#141011' : '#f5f5f7');
        [].forEach.call(document.querySelectorAll('.theme-toggle'), function (b) {
            b.title = 'Тема: ' + names[p];
            var i = b.querySelector('i');
            if (i) i.className = 'bi ' + icons[p];
        });
    }
    [].forEach.call(document.querySelectorAll('.theme-toggle'), function (b) {
        b.addEventListener('click', function () {
            var next = order[(order.indexOf(pref()) + 1) % order.length];
            try { localStorage.setItem('theme', next); } catch (e) {}
            apply();
        });
    });
    if (mq.addEventListener) mq.addEventListener('change', apply);
    apply();
})();
</script>
</body>
</html>
