<?php
require BASE_PATH . '/views/layout/header.php';
?>

<div class="row justify-content-center">
    <div class="col-xl-9">
        <div class="auth-shell row g-0">

            <aside class="col-lg-5 auth-aside d-none d-lg-flex flex-column">
                <div class="mb-auto">
                    <div class="auth-badge"><i class="bi bi-key"></i></div>
                    <h3 class="mt-4 mb-2">Восстановление доступа</h3>
                    <p class="opacity-75 mb-0">Пришлём ссылку для установки нового пароля на email, указанный при регистрации.</p>

                    <div class="perk"><i class="bi bi-envelope-check"></i><div><strong>Письмо со ссылкой</strong><br><span class="opacity-75 small">Ничего вводить заново не нужно</span></div></div>
                    <div class="perk"><i class="bi bi-clock-history"></i><div><strong>Действует один час</strong><br><span class="opacity-75 small">Потом ссылка перестанет работать</span></div></div>
                    <div class="perk"><i class="bi bi-shield-lock"></i><div><strong>Одноразовая</strong><br><span class="opacity-75 small">Срабатывает только один раз</span></div></div>
                </div>
                <div class="pt-4">
                    <span class="opacity-75 small d-block mb-2">Вспомнили пароль?</span>
                    <a href="/login" class="btn btn-outline-light btn-sm px-3">Войти</a>
                </div>
            </aside>

            <div class="col-lg-7 auth-main">
                <div class="auth-badge-sm d-lg-none"><i class="bi bi-key"></i></div>
                <h3 class="mb-1">Восстановление пароля</h3>
                <p class="text-muted mb-4">Укажите email, под которым вы входите. Мы отправим ссылку для установки нового пароля.</p>

                <?php if ($sent): ?>
                    <div class="alert alert-success d-flex gap-2 align-items-start">
                        <i class="bi bi-envelope-check mt-1"></i>
                        <span>
                            Если такой email зарегистрирован, письмо со ссылкой уже отправлено.
                            Ссылка действует один час. Проверьте папку «Спам», если письма нет через пару минут.
                        </span>
                    </div>
                <?php endif; ?>

                <?php if ($errorMsg): ?>
                    <div class="alert alert-danger d-flex gap-2 align-items-start">
                        <i class="bi bi-exclamation-triangle mt-1"></i><span><?= e($errorMsg) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!$sent): ?>
                    <form method="POST" action="/forgot" novalidate>
                        <?= csrfField() ?>
                        <div class="mb-4">
                            <label class="form-label" for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-control"
                                   value="<?= e($oldEmail) ?>" placeholder="name@company.ru"
                                   autocomplete="email" autofocus required>
                        </div>
                        <button type="submit" class="btn btn-brand w-100 py-2">
                            <i class="bi bi-envelope me-1"></i>Отправить ссылку
                        </button>
                    </form>
                <?php endif; ?>

                <div class="auth-divider"><span>или</span></div>

                <div class="d-grid gap-2">
                    <a href="/login" class="btn btn-outline-brand">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Вернуться к входу
                    </a>
                    <a href="/join" class="btn btn-outline-secondary">
                        <i class="bi bi-person-plus me-1"></i>Присоединиться по коду компании
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require BASE_PATH . '/views/layout/footer.php'; ?>
