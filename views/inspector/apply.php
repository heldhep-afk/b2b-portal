<?php
require BASE_PATH . '/views/layout/header.php';

$val = fn(string $k): string => e($old[$k] ?? '');
$err = fn(string $k): string => isset($errors[$k]) ? '<div class="invalid-feedback d-block">' . e($errors[$k]) . '</div>' : '';
$cls = fn(string $k): string => isset($errors[$k]) ? ' is-invalid' : '';
?>

<div class="row justify-content-center">
    <div class="col-lg-7 col-xl-6">
        <div class="card shadow-sm">
            <div class="card-body p-4 p-md-5">
                <h2 class="mb-1"><i class="bi bi-clipboard-check text-brand me-2"></i>Стать проверяющим</h2>
                <p class="text-muted">
                    Проверяющие портала проверяют товары и услуги по заказам компаний и получают вознаграждение за каждую подтверждённую проверку.
                    Это отдельная заявка — регистрировать компанию не нужно. После одобрения администратором вы войдёте по email и паролю из этой формы.
                </p>

                <?php if (!empty($errors['form'])): ?>
                    <div class="alert alert-danger"><?= e($errors['form']) ?></div>
                <?php endif; ?>

                <form method="POST" action="/inspector/apply" novalidate>
                    <?= csrfField() ?>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="full_name">ФИО <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" id="full_name" class="form-control<?= $cls('full_name') ?>" maxlength="150"
                                   value="<?= $val('full_name') ?>" required>
                            <?= $err('full_name') ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="email" class="form-control<?= $cls('email') ?>" maxlength="100"
                                   value="<?= $val('email') ?>" required>
                            <?= $err('email') ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="phone">Телефон</label>
                            <input type="text" name="phone" id="phone" class="form-control<?= $cls('phone') ?>" maxlength="30" value="<?= $val('phone') ?>">
                            <?= $err('phone') ?>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="about">Опыт и сферы проверки</label>
                            <textarea name="about" id="about" class="form-control<?= $cls('about') ?>" rows="3" maxlength="1000"
                                      placeholder="Например: контроль качества металлопроката, приёмка оборудования, аудит услуг"><?= $val('about') ?></textarea>
                            <?= $err('about') ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="password">Пароль <span class="text-danger">*</span></label>
                            <input type="password" name="password" id="password" class="form-control<?= $cls('password') ?>" autocomplete="new-password" required>
                            <div class="form-text">Не короче 8 символов.</div>
                            <?= $err('password') ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="password_confirm">Повтор пароля <span class="text-danger">*</span></label>
                            <input type="password" name="password_confirm" id="password_confirm" class="form-control<?= $cls('password_confirm') ?>" autocomplete="new-password" required>
                            <?= $err('password_confirm') ?>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-brand w-100 py-2 mt-4"><i class="bi bi-send me-1"></i>Отправить заявку</button>
                </form>
                <div class="text-center small mt-3">
                    <a href="/login">Уже есть аккаунт? Войти</a> · <a href="/register">Регистрация компании</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require BASE_PATH . '/views/layout/footer.php'; ?>
