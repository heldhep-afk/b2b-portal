<?php
require BASE_PATH . '/views/layout/header.php';

$v   = fn(string $k): string => e($old[$k] ?? '');
$inv = fn(string $k): string => isset($errors[$k]) ? ' is-invalid' : '';
$fe  = fn(string $k): string => '<div class="field-error" data-error-for="' . e($k) . '"' . (isset($errors[$k]) ? '' : ' hidden')
    . '><i class="bi bi-exclamation-circle"></i><span>' . e($errors[$k] ?? '') . '</span></div>';
?>

<div class="row justify-content-center">
    <div class="col-xl-10">
        <div class="auth-shell row g-0">

            <aside class="col-lg-4 auth-aside d-none d-lg-flex flex-column">
                <div class="mb-auto">
                    <div class="auth-badge"><i class="bi bi-person-plus"></i></div>
                    <h3 class="mt-4 mb-2">Вы сотрудник?</h3>
                    <p class="opacity-75 mb-0">Присоединитесь к компании по коду — его даёт директор или менеджер.</p>

                    <div class="perk"><i class="bi bi-key"></i><div><strong>Введите код</strong><br><span class="opacity-75 small">8 символов, например ABCD-EF23</span></div></div>
                    <div class="perk"><i class="bi bi-hourglass-split"></i><div><strong>Дождитесь решения</strong><br><span class="opacity-75 small">Заявку рассмотрит директор или менеджер</span></div></div>
                    <div class="perk"><i class="bi bi-shield-check"></i><div><strong>Получите роль</strong><br><span class="opacity-75 small">Роль и права выдаёт руководитель</span></div></div>
                </div>
                <div class="pt-4">
                    <span class="opacity-75 small d-block mb-2">Уже зарегистрированы?</span>
                    <a href="/login" class="btn btn-outline-light btn-sm px-3">Войти</a>
                </div>
            </aside>

            <div class="col-lg-8 auth-main">
                <div class="nav nav-pills mb-4">
                    <a class="nav-link" href="/register">Регистрация компании</a>
                    <a class="nav-link active" href="/join">Я сотрудник — по коду</a>
                </div>

                <h3 class="mb-1">Присоединиться к компании</h3>
                <p class="text-muted mb-4">Укажите код компании и свои данные. Заявка уйдёт руководителю, роль он выдаст при одобрении.</p>

                <?php if ($errorMsg): ?>
                    <div class="alert alert-danger"><i class="bi bi-x-octagon me-1"></i><?= e($errorMsg) ?></div>
                <?php elseif ($errors): ?>
                    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-1"></i>Проверьте поля формы — они подсвечены красным.</div>
                <?php endif; ?>

                <form method="POST" action="/join" id="joinForm" novalidate>
                    <?= csrfField() ?>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="code">Код компании <span class="text-danger">*</span></label>
                            <input type="text" id="code" name="code" class="form-control form-control-lg code-input<?= $inv('code') ?>"
                                   maxlength="9" placeholder="ABCD-EF23" value="<?= $v('code') ?>"
                                   autocomplete="off" autocapitalize="characters" spellcheck="false" autofocus>
                            <div class="form-text">Код выдаёт директор или менеджер компании</div>
                            <?= $fe('code') ?>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="contact_person">ФИО <span class="text-danger">*</span></label>
                            <input type="text" id="contact_person" name="contact_person" class="form-control<?= $inv('contact_person') ?>"
                                   maxlength="150" placeholder="Иванов Иван Иванович" value="<?= $v('contact_person') ?>" autocomplete="name">
                            <?= $fe('contact_person') ?>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="phone">Телефон <span class="text-danger">*</span></label>
                            <input type="tel" id="phone" name="phone" class="form-control<?= $inv('phone') ?>"
                                   maxlength="30" placeholder="+7 900 000-00-00" value="<?= $v('phone') ?>" autocomplete="tel">
                            <?= $fe('phone') ?>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="email">Email <span class="text-danger">*</span></label>
                            <input type="email" id="email" name="email" class="form-control<?= $inv('email') ?>"
                                   maxlength="100" placeholder="name@company.ru" value="<?= $v('email') ?>" autocomplete="email">
                            <div class="form-text">Будет использоваться для входа</div>
                            <?= $fe('email') ?>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="username">Логин <span class="text-danger">*</span></label>
                            <input type="text" id="username" name="username" class="form-control<?= $inv('username') ?>"
                                   maxlength="50" placeholder="ivanov_company" value="<?= $v('username') ?>" autocomplete="username">
                            <div class="form-text">Латинские буквы, цифры, символы _ . -</div>
                            <?= $fe('username') ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="password">Пароль <span class="text-danger">*</span></label>
                            <div class="pass-wrap">
                                <input type="password" id="password" name="password" class="form-control<?= $inv('password') ?>" autocomplete="new-password">
                                <button type="button" class="pass-toggle" data-target="password" aria-label="Показать пароль"><i class="bi bi-eye"></i></button>
                            </div>
                            <div class="form-text">Минимум 8 символов, буквы и цифры</div>
                            <?= $fe('password') ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="password_confirm">Повторите пароль <span class="text-danger">*</span></label>
                            <div class="pass-wrap">
                                <input type="password" id="password_confirm" name="password_confirm" class="form-control<?= $inv('password_confirm') ?>" autocomplete="new-password">
                                <button type="button" class="pass-toggle" data-target="password_confirm" aria-label="Показать пароль"><i class="bi bi-eye"></i></button>
                            </div>
                            <?= $fe('password_confirm') ?>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-brand btn-lg w-100 mt-4">
                        <i class="bi bi-send me-1"></i>Отправить заявку
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('joinForm');
    var codeInput = document.getElementById('code');

    // код: заглавные буквы и цифры, дефис после четвёртого символа
    function formatCode(v) {
        v = v.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 8);
        return v.length > 4 ? v.slice(0, 4) + '-' + v.slice(4) : v;
    }
    codeInput.addEventListener('input', function () { codeInput.value = formatCode(codeInput.value); });
    codeInput.value = formatCode(codeInput.value);

    function phoneRule(v) {
        v = v.trim();
        if (!v) return 'Укажите номер телефона.';
        var d = v.replace(/\D/g, '').length;
        if (d < 10 || d > 15 || v.length > 30 || /[^\d\s()+\-]/.test(v)) return 'Введите корректный номер, например +7 900 000-00-00.';
        return '';
    }
    var rules = {
        code: function (v) {
            var c = v.toUpperCase().replace(/[^A-Z0-9]/g, '');
            if (!c) return 'Введите код компании.';
            if (c.length !== 8) return 'Код состоит из 8 символов, например ABCD-EF23.';
            return '';
        },
        contact_person: function (v) {
            v = v.trim();
            if (!v) return 'Укажите ваше ФИО.';
            if (v.split(/\s+/).length < 2) return 'Укажите фамилию и имя.';
            if (v.length > 150) return 'ФИО не должно быть длиннее 150 символов.';
            return '';
        },
        phone: phoneRule,
        email: function (v) {
            v = v.trim();
            if (!v) return 'Укажите email.';
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v)) return 'Некорректный email, например name@company.ru.';
            return '';
        },
        username: function (v) {
            v = v.trim();
            if (!v) return 'Придумайте логин.';
            if (v.length < 3) return 'Логин — минимум 3 символа.';
            if (v.length > 50) return 'Логин не должен быть длиннее 50 символов.';
            if (!/^[A-Za-z0-9_.\-]+$/.test(v)) return 'Только латинские буквы, цифры и символы _ . -';
            return '';
        },
        password: function (v) {
            if (!v) return 'Введите пароль.';
            if (v.length < 8) return 'Пароль — минимум 8 символов.';
            if (v.length > 72) return 'Пароль не должен быть длиннее 72 символов.';
            if (!/[A-Za-z]/.test(v) || !/\d/.test(v)) return 'Нужна хотя бы одна буква и одна цифра.';
            return '';
        },
        password_confirm: function (v) {
            if (!v) return 'Повторите пароль.';
            if (v !== form.elements['password'].value) return 'Пароли не совпадают.';
            return '';
        }
    };
    function showError(input, msg) {
        var box = form.querySelector('[data-error-for="' + input.name + '"]');
        input.classList.toggle('is-invalid', !!msg);
        if (box) { box.hidden = !msg; if (msg) box.querySelector('span').textContent = msg; }
    }
    function check(input) {
        var msg = rules[input.name] ? rules[input.name](input.value) : '';
        showError(input, msg);
        return msg;
    }
    form.addEventListener('submit', function (ev) {
        var first = null;
        [].forEach.call(form.querySelectorAll('input[name]'), function (inp) {
            if (check(inp) && !first) first = inp;
        });
        if (first) { ev.preventDefault(); first.focus(); }
    });
    form.addEventListener('focusout', function (ev) {
        var t = ev.target;
        if (rules[t.name] && (t.value !== '' || t.classList.contains('is-invalid'))) check(t);
    });
    form.addEventListener('input', function (ev) {
        var t = ev.target;
        if (t.classList.contains('is-invalid')) check(t);
        if (t.name === 'password' && form.elements['password_confirm'].value !== '') check(form.elements['password_confirm']);
    });
    [].forEach.call(document.querySelectorAll('.pass-toggle'), function (btn) {
        btn.addEventListener('click', function () {
            var inp = document.getElementById(btn.dataset.target);
            var show = inp.type === 'password';
            inp.type = show ? 'text' : 'password';
            btn.querySelector('i').className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
            btn.setAttribute('aria-label', show ? 'Скрыть пароль' : 'Показать пароль');
        });
    });
})();
</script>

<?php require BASE_PATH . '/views/layout/footer.php'; ?>
