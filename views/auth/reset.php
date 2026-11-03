<?php
require BASE_PATH . '/views/layout/header.php';
?>

<div class="row justify-content-center">
    <div class="col-xl-9">
        <div class="auth-shell row g-0">

            <aside class="col-lg-5 auth-aside d-none d-lg-flex flex-column">
                <div class="mb-auto">
                    <div class="auth-badge"><i class="bi <?= $valid ? 'bi-shield-lock' : 'bi-link-45deg' ?>"></i></div>
                    <h3 class="mt-4 mb-2"><?= $valid ? 'Новый пароль' : 'Ссылка не сработала' ?></h3>
                    <p class="opacity-75 mb-0">
                        <?= $valid
                            ? 'Остался один шаг: придумайте пароль и войдите с ним.'
                            : 'Ссылки действуют один час и срабатывают один раз. Запросите новую — это быстро.' ?>
                    </p>

                    <?php if ($valid): ?>
                        <div class="perk"><i class="bi bi-123"></i><div><strong>Не короче 8 символов</strong><br><span class="opacity-75 small">Лучше добавить цифры и знаки</span></div></div>
                        <div class="perk"><i class="bi bi-shield-check"></i><div><strong>Сразу сохранится</strong><br><span class="opacity-75 small">Старый пароль перестанет работать</span></div></div>
                        <div class="perk"><i class="bi bi-box-arrow-in-right"></i><div><strong>Останется войти</strong><br><span class="opacity-75 small">С новым паролем на странице входа</span></div></div>
                    <?php else: ?>
                        <div class="perk"><i class="bi bi-clock-history"></i><div><strong>Срок — один час</strong><br><span class="opacity-75 small">После этого нужна новая ссылка</span></div></div>
                        <div class="perk"><i class="bi bi-envelope-check"></i><div><strong>Письмо на email</strong><br><span class="opacity-75 small">Тот, что указан при регистрации</span></div></div>
                    <?php endif; ?>
                </div>
                <div class="pt-4">
                    <span class="opacity-75 small d-block mb-2">Нужна помощь?</span>
                    <a href="/forgot" class="btn btn-outline-light btn-sm px-3">Запросить ссылку</a>
                </div>
            </aside>

            <div class="col-lg-7 auth-main">
                <?php if (!$valid): ?>
                    <div class="auth-badge-sm d-lg-none"><i class="bi bi-link-45deg"></i></div>
                    <h3 class="mb-2">Ссылка недействительна</h3>
                    <p class="text-muted mb-4">
                        Ссылка для восстановления пароля устарела, уже использована или скопирована не полностью.
                        Запросите новую — она действует один час.
                    </p>
                    <div class="d-grid gap-2">
                        <a href="/forgot" class="btn btn-brand"><i class="bi bi-arrow-repeat me-1"></i>Запросить новую ссылку</a>
                        <a href="/login" class="btn btn-outline-brand">Вернуться к входу</a>
                    </div>
                <?php else: ?>
                    <div class="auth-badge-sm d-lg-none"><i class="bi bi-shield-lock"></i></div>
                    <h3 class="mb-1">Новый пароль</h3>
                    <p class="text-muted mb-4">Придумайте пароль не короче 8 символов. После сохранения войдите с ним.</p>

                    <?php if ($errorMsg): ?>
                        <div class="alert alert-danger d-flex gap-2 align-items-start">
                            <i class="bi bi-exclamation-triangle mt-1"></i><span><?= e($errorMsg) ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="/reset" novalidate>
                        <?= csrfField() ?>
                        <input type="hidden" name="token" value="<?= e($token) ?>">

                        <div class="mb-3">
                            <label class="form-label" for="password">Новый пароль</label>
                            <div class="pass-wrap">
                                <input type="password" id="password" name="password"
                                       class="form-control<?= isset($errors['password']) ? ' is-invalid' : '' ?>"
                                       autocomplete="new-password" autofocus required>
                                <button type="button" class="pass-toggle" data-pass-target="password"
                                        aria-label="Показать пароль"><i class="bi bi-eye"></i></button>
                            </div>
                            <?php if (isset($errors['password'])): ?>
                                <div class="invalid-feedback d-block"><?= e($errors['password']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-4">
                            <label class="form-label" for="password_confirm">Повторите пароль</label>
                            <div class="pass-wrap">
                                <input type="password" id="password_confirm" name="password_confirm"
                                       class="form-control<?= isset($errors['password_confirm']) ? ' is-invalid' : '' ?>"
                                       autocomplete="new-password" required>
                                <button type="button" class="pass-toggle" data-pass-target="password_confirm"
                                        aria-label="Показать пароль"><i class="bi bi-eye"></i></button>
                            </div>
                            <?php if (isset($errors['password_confirm'])): ?>
                                <div class="invalid-feedback d-block"><?= e($errors['password_confirm']) ?></div>
                            <?php endif; ?>
                        </div>

                        <button type="submit" class="btn btn-brand w-100 py-2">
                            <i class="bi bi-check2 me-1"></i>Сохранить пароль
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    [].forEach.call(document.querySelectorAll('.pass-toggle[data-pass-target]'), function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.getAttribute('data-pass-target'));
            if (!input) return;
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.setAttribute('aria-label', show ? 'Скрыть пароль' : 'Показать пароль');
            var icon = btn.querySelector('i');
            if (icon) icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
    });
})();
</script>

<?php require BASE_PATH . '/views/layout/footer.php'; ?>
