<?php require BASE_PATH . '/views/layout/header.php'; ?>

<?php if ($dbNotice): ?>
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i><?= e($dbNotice) ?></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
    <div>
        <h2 class="mb-1"><i class="bi bi-buildings text-brand me-2"></i>Мои компании</h2>
        <div class="text-muted">Работайте в нескольких компаниях под одним входом и переключайтесь между ними в меню справа вверху.</div>
    </div>
    <a href="/account" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>В кабинет</a>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h4 class="mb-3">Привязанные компании</h4>

                <div class="perm-item mb-2">
                    <div>
                        <div class="fw-semibold text-break"><i class="bi bi-check2 text-brand me-1"></i><?= e($_SESSION['company_name'] ?? '') ?></div>
                        <div class="small text-muted">Текущая компания · <?= e(companyRoleLabel($_SESSION['company_role'] ?? 'employee')) ?></div>
                    </div>
                </div>

                <?php if (!$linked): ?>
                    <div class="text-muted small mt-3">Других компаний пока нет. Привяжите аккаунт справа.</div>
                <?php endif; ?>

                <?php foreach ($linked as $l): ?>
                    <div class="perm-item mb-2">
                        <div>
                            <div class="fw-semibold text-break"><?= e($l['company_name']) ?></div>
                            <div class="small text-muted text-break"><?= e(companyRoleLabel($l['company_role'])) ?> · <?= e($l['email']) ?></div>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <?php if ((int)$l['is_active'] === 1 && $l['join_status'] === 'approved'): ?>
                                <form method="POST" action="/account/switch" class="m-0">
                                    <?= csrfField() ?>
                                    <button type="submit" name="user_id" value="<?= (int)$l['id'] ?>" class="btn btn-sm btn-brand">
                                        <i class="bi bi-arrow-left-right me-1"></i>Перейти
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="badge text-bg-secondary">Недоступна</span>
                            <?php endif; ?>
                            <form method="POST" action="/account/unlink" class="m-0" onsubmit="return confirm('Отвязать компанию? Аккаунт останется, но переключаться на него отсюда будет нельзя.');">
                                <?= csrfField() ?>
                                <button type="submit" name="user_id" value="<?= (int)$l['id'] ?>" class="btn btn-sm btn-outline-danger">Отвязать</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h4 class="mb-1">Привязать компанию</h4>
                <p class="text-muted small mb-3">
                    Введите email и пароль вашего аккаунта в другой компании. Привязка действует в обе стороны —
                    из той компании можно будет вернуться сюда.
                </p>
                <form method="POST" action="/account/link">
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label" for="link_email">Email аккаунта</label>
                        <input type="email" id="link_email" name="email" class="form-control" maxlength="100" required autocomplete="off">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="link_pass">Пароль аккаунта</label>
                        <input type="password" id="link_pass" name="password" class="form-control" required autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn btn-brand w-100"><i class="bi bi-link-45deg me-1"></i>Привязать</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require BASE_PATH . '/views/layout/footer.php'; ?>
