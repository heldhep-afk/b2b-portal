<?php
require BASE_PATH . '/views/layout/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-6 col-xl-5">
        <div class="card shadow-sm">
            <div class="card-body p-4 p-lg-5">
                <?php if (!$valid): ?>
                    <div class="auth-badge-sm mb-3"><i class="bi bi-link-45deg"></i></div>
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
                    <div class="auth-badge-sm mb-3"><i class="bi bi-shield-lock"></i></div>
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
                            <input type="password" id="password" name="password"
                                   class="form-control<?= isset($errors['password']) ? ' is-invalid' : '' ?>"
                                   autocomplete="new-password" autofocus required>
                            <?php if (isset($errors['password'])): ?>
                                <div class="invalid-feedback d-block"><?= e($errors['password']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-4">
                            <label class="form-label" for="password_confirm">Повторите пароль</label>
                            <input type="password" id="password_confirm" name="password_confirm"
                                   class="form-control<?= isset($errors['password_confirm']) ? ' is-invalid' : '' ?>"
                                   autocomplete="new-password" required>
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

<?php require BASE_PATH . '/views/layout/footer.php'; ?>
