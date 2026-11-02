<?php
require BASE_PATH . '/views/layout/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-6 col-xl-5">
        <div class="card shadow-sm">
            <div class="card-body p-4 p-lg-5">
                <div class="auth-badge-sm mb-3"><i class="bi bi-key"></i></div>
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
