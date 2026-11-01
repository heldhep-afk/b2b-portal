<?php
require BASE_PATH . '/views/layout/header.php';

$inv = fn(string $k): string => (isset($errors[$k]) || $errorMsg) ? ' is-invalid' : '';
$fe  = fn(string $k): string => '<div class="field-error" data-error-for="' . e($k) . '"' . (isset($errors[$k]) ? '' : ' hidden')
    . '><i class="bi bi-exclamation-circle"></i><span>' . e($errors[$k] ?? '') . '</span></div>';
?>

<div class="row justify-content-center">
    <div class="col-xl-9">
        <div class="auth-shell row g-0">

            <aside class="col-lg-5 auth-aside d-none d-lg-flex flex-column">
                <div class="mb-auto">
                    <div class="auth-badge"><i class="bi bi-buildings"></i></div>
                    <h3 class="mt-4 mb-2">С возвращением!</h3>
                    <p class="opacity-75 mb-0">Войдите, чтобы управлять товарами и сделками вашей компании.</p>

                    <div class="perk"><i class="bi bi-grid"></i><div><strong>Каталог</strong><br><span class="opacity-75 small">Оптовые предложения поставщиков</span></div></div>
                    <div class="perk"><i class="bi bi-file-earmark-text"></i><div><strong>Сделки</strong><br><span class="opacity-75 small">В работе, завершённые, отклонённые</span></div></div>
                    <div class="perk"><i class="bi bi-patch-check"></i><div><strong>Одобрение покупателем</strong><br><span class="opacity-75 small">Подтверждайте сделки в один клик</span></div></div>
                </div>
                <div class="pt-4">
                    <span class="opacity-75 small d-block mb-2">Компания ещё не зарегистрирована?</span>
                    <a href="/register" class="btn btn-outline-light btn-sm px-3">Регистрация</a>
                    <a href="/#catalog" class="btn btn-link btn-sm text-white-50 ms-1">Каталог без входа</a>
                </div>
            </aside>

            <div class="col-lg-7 auth-main">
                <div class="auth-badge-sm d-lg-none"><i class="bi bi-buildings"></i></div>
                <h3 class="mb-1">Вход для компаний</h3>
                <p class="text-muted mb-4">Используйте email, указанный при регистрации.</p>

                <?php if ($errorMsg): ?>
                    <div class="alert alert-danger d-flex gap-2 align-items-start">
                        <i class="bi bi-exclamation-triangle mt-1"></i><span><?= e($errorMsg) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="/login" id="loginForm" novalidate>
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-control<?= $inv('email') ?>"
                               value="<?= e($oldEmail) ?>" placeholder="name@company.ru" autocomplete="email" autofocus>
                        <?= $fe('email') ?>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="password">Пароль</label>
                        <div class="pass-wrap">
                            <input type="password" id="password" name="password" class="form-control<?= $inv('password') ?>"
                                   autocomplete="current-password">
                            <button type="button" class="pass-toggle" id="passToggle" aria-label="Показать пароль"><i class="bi bi-eye"></i></button>
                        </div>
                        <div class="form-text text-warning-emphasis" id="capsHint" hidden>
                            <i class="bi bi-capslock me-1"></i>Включён Caps Lock
                        </div>
                        <?= $fe('password') ?>
                    </div>
                    <button type="submit" class="btn btn-brand w-100 py-2" id="loginBtn">
                        <span class="spinner-border spinner-border-sm me-2" id="loginSpinner" role="status" hidden></span>
                        <span id="loginBtnText">Войти</span>
                    </button>
                </form>

                <div class="auth-divider"><span>или</span></div>

                <div class="d-grid gap-2">
                    <a href="/register" class="btn btn-outline-brand">
                        <i class="bi bi-building-add me-1"></i>Зарегистрировать компанию
                    </a>
                    <a href="/inspector/apply" class="btn btn-outline-secondary">
                        <i class="bi bi-clipboard-check me-1"></i>Стать проверяющим портала
                    </a>
                    <a href="/join" class="btn btn-outline-secondary">
                        <i class="bi bi-person-plus me-1"></i>Я сотрудник: присоединиться по коду
                    </a>
                    <a href="/#catalog" class="btn btn-light border">
                        <i class="bi bi-grid me-1"></i>Посмотреть каталог без регистрации
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('loginForm');
    var pass = document.getElementById('password');
    var rules = {
        email: function (v) {
            v = v.trim();
            if (!v) return 'Введите email.';
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v)) return 'Некорректный email, например name@company.ru.';
            return '';
        },
        password: function (v) { return v ? '' : 'Введите пароль.'; }
    };
    function showError(input, msg) {
        var box = form.querySelector('[data-error-for="' + input.name + '"]');
        input.classList.toggle('is-invalid', !!msg);
        if (box) {
            box.hidden = !msg;
            if (msg) box.querySelector('span').textContent = msg;
        }
    }
    function check(input) {
        if (!rules[input.name]) return '';
        var msg = rules[input.name](input.value);
        showError(input, msg);
        return msg;
    }

    form.addEventListener('submit', function (ev) {
        var first = null;
        [].forEach.call(form.querySelectorAll('input[name]'), function (inp) {
            if (check(inp) && !first) first = inp;
        });
        if (first) {
            ev.preventDefault();
            first.focus();
            return;
        }
        var btn = document.getElementById('loginBtn');
        document.getElementById('loginSpinner').hidden = false;
        document.getElementById('loginBtnText').textContent = 'Входим…';
        btn.disabled = true;
    });
    form.addEventListener('focusout', function (ev) {
        var t = ev.target;
        if (rules[t.name] && (t.value !== '' || t.classList.contains('is-invalid'))) check(t);
    });
    form.addEventListener('input', function (ev) {
        if (ev.target.classList.contains('is-invalid')) check(ev.target);
    });

    function caps(ev) {
        document.getElementById('capsHint').hidden = !(ev.getModifierState && ev.getModifierState('CapsLock'));
    }
    pass.addEventListener('keyup', caps);
    pass.addEventListener('keydown', caps);
    pass.addEventListener('blur', function () { document.getElementById('capsHint').hidden = true; });

    document.getElementById('passToggle').addEventListener('click', function () {
        var show = pass.type === 'password';
        pass.type = show ? 'text' : 'password';
        this.querySelector('i').className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
        this.setAttribute('aria-label', show ? 'Скрыть пароль' : 'Показать пароль');
    });
})();
</script>

<?php require BASE_PATH . '/views/layout/footer.php'; ?>
