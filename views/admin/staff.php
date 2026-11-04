<?php
require BASE_PATH . '/views/layout/header.php';

$val = fn(string $k): string => e($old[$k] ?? '');
$err = fn(string $k): string => isset($errors[$k]) ? '<div class="invalid-feedback d-block">' . e($errors[$k]) . '</div>' : '';
$cls = fn(string $k): string => isset($errors[$k]) ? ' is-invalid' : '';
$activeCount = count(array_filter($staff, fn($u) => (int)$u['is_active'] === 1));
?>

<?php if ($dbNotice): ?>
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i><?= e($dbNotice) ?></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
    <div>
        <h2 class="mb-1"><i class="bi bi-person-badge text-brand me-2"></i>Персонал</h2>
        <div class="text-muted small">
            Проверяющие выполняют проверку товаров и услуг по заказам компаний. Действующих: <?= (int)$activeCount ?>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="/admin/inspections" class="btn btn-outline-brand"><i class="bi bi-patch-check me-1"></i>Проверки</a>
        <a href="#add" class="btn btn-brand"><i class="bi bi-person-plus me-1"></i>Добавить сотрудника</a>
    </div>
</div>

<?php if (!empty($applications)): ?>
<div class="card shadow-sm mb-4 border-warning">
    <div class="card-body p-4">
        <h4 class="mb-1">Заявки на работу проверяющим <span class="badge text-bg-warning"><?= count($applications) ?></span></h4>
        <p class="text-muted small mb-3">Люди подали заявку с главной страницы. При принятии создаётся аккаунт с паролем, который указал заявитель.</p>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th>Кандидат</th><th>Контакты</th><th>О себе</th><th>Подана</th><th class="text-end">Решение</th></tr></thead>
                <tbody>
                <?php foreach ($applications as $a): ?>
                    <tr>
                        <td class="fw-semibold text-break"><?= e($a['full_name']) ?></td>
                        <td class="small text-break"><?= e($a['email']) ?><?= $a['phone'] ? '<br><span class="text-muted">' . e($a['phone']) . '</span>' : '' ?></td>
                        <td class="small" style="max-width: 320px; white-space: pre-wrap"><?= e($a['about'] ?: '—') ?></td>
                        <td class="small text-nowrap"><?= e(formatDateTime($a['created_at'])) ?></td>
                        <td class="text-end">
                            <div class="d-flex flex-wrap gap-1 justify-content-end align-items-center">
                            <?php foreach (['approve' => ['Принять', 'btn-success'], 'reject' => ['Отклонить', 'btn-outline-danger']] as $dec => [$lbl, $btn]): ?>
                                <form method="POST" action="/admin/staff/application" class="d-inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="application_id" value="<?= (int)$a['id'] ?>">
                                    <input type="hidden" name="decision" value="<?= e($dec) ?>">
                                    <button type="submit" class="btn btn-sm <?= e($btn) ?>"><?= e($lbl) ?></button>
                                </form>
                            <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-body p-4">
        <h4 class="mb-3">Проверяющие <span class="badge text-bg-secondary"><?= count($staff) ?></span></h4>

        <?php if (!$staff): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-people fs-1 d-block mb-2"></i>Персонала пока нет — добавьте первого проверяющего ниже.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>Сотрудник</th>
                        <th>Контакты</th>
                        <th class="text-end">В работе</th>
                        <th class="text-end">Подтверждено</th>
                        <th class="text-end">К выплате</th>
                        <th class="text-end">Выплачено</th>
                        <th>Статус</th>
                        <th class="text-end">Действия</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($staff as $u): ?>
                        <tr class="<?= (int)$u['is_active'] === 1 ? '' : 'text-muted' ?>">
                            <td>
                                <div class="fw-semibold text-break"><?= e($u['full_name']) ?></div>
                                <div class="small text-muted"><?= e($u['position'] ?: 'Проверяющий') ?> · <?= e($u['username']) ?></div>
                            </td>
                            <td class="small">
                                <div class="text-break"><?= e($u['email']) ?></div>
                                <?php if ($u['phone']): ?><div class="text-muted"><?= e($u['phone']) ?></div><?php endif; ?>
                            </td>
                            <td class="text-end"><?= (int)$u['in_work'] ?></td>
                            <td class="text-end"><?= (int)$u['approved_cnt'] ?></td>
                            <td class="text-end text-nowrap<?= (float)$u['owed'] > 0 ? ' fw-semibold' : '' ?>"><?= e(formatPrice($u['owed'])) ?></td>
                            <td class="text-end text-nowrap"><?= e(formatPrice($u['paid'])) ?></td>
                            <td>
                                <?php if ((int)$u['is_active'] === 1): ?>
                                    <span class="badge text-bg-success">Работает</span>
                                <?php else: ?>
                                    <span class="badge text-bg-secondary">Отключён</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="d-flex flex-wrap gap-1 justify-content-end align-items-center">
                                <button type="button" class="btn btn-sm btn-outline-brand"
                                        data-bs-toggle="modal" data-bs-target="#pwdModal"
                                        data-user-id="<?= (int)$u['id'] ?>" data-user-name="<?= e($u['full_name']) ?>">
                                    <i class="bi bi-key me-1"></i>Пароль
                                </button>
                                <form method="POST" action="/admin/staff/toggle" class="d-inline"
                                      onsubmit="return confirm('<?= (int)$u['is_active'] === 1 ? 'Отключить сотрудника? Его задания «Проверяется» вернутся в свободные.' : 'Вернуть сотруднику доступ?' ?>');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                    <?php if ((int)$u['is_active'] === 1): ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-person-x me-1"></i>Отключить</button>
                                    <?php else: ?>
                                        <button type="submit" class="btn btn-sm btn-outline-success"><i class="bi bi-person-check me-1"></i>Вернуть</button>
                                    <?php endif; ?>
                                </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Добавление сотрудника -->
<div id="add" class="card shadow-sm mb-4">
    <div class="card-body p-4">
        <h4 class="mb-1">Добавить проверяющего</h4>
        <p class="text-muted small mb-3">
            Администратор создаёт аккаунт и передаёт сотруднику email и пароль. Вход — на обычной странице входа,
            после входа сотрудник попадает в кабинет проверяющего.
        </p>
        <form method="POST" action="/admin/staff/add" novalidate>
            <?= csrfField() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="full_name">ФИО <span class="text-danger">*</span></label>
                    <input type="text" name="full_name" id="full_name" class="form-control<?= $cls('full_name') ?>" maxlength="150"
                           value="<?= $val('full_name') ?>" required>
                    <?= $err('full_name') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="position">Должность</label>
                    <input type="text" name="position" id="position" class="form-control<?= $cls('position') ?>" maxlength="100"
                           placeholder="Проверяющий качества" value="<?= $val('position') ?>">
                    <?= $err('position') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="email">Email (логин для входа) <span class="text-danger">*</span></label>
                    <input type="email" name="email" id="email" class="form-control<?= $cls('email') ?>" maxlength="100"
                           value="<?= $val('email') ?>" required>
                    <?= $err('email') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="phone">Телефон</label>
                    <input type="text" name="phone" id="phone" class="form-control<?= $cls('phone') ?>" maxlength="30" value="<?= $val('phone') ?>">
                    <?= $err('phone') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="username">Имя пользователя <span class="text-danger">*</span></label>
                    <input type="text" name="username" id="username" class="form-control<?= $cls('username') ?>" maxlength="50"
                           value="<?= $val('username') ?>" autocomplete="off" required>
                    <?= $err('username') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="password">Пароль</label>
                    <input type="text" name="password" id="password" class="form-control<?= $cls('password') ?>" autocomplete="off"
                           placeholder="Оставьте пустым — создастся автоматически">
                    <?= $err('password') ?>
                </div>
            </div>
            <button type="submit" class="btn btn-brand mt-4"><i class="bi bi-person-plus me-1"></i>Добавить сотрудника</button>
        </form>
    </div>
</div>

<!-- Смена пароля -->
<div class="modal fade" id="pwdModal" tabindex="-1" aria-labelledby="pwdTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="/admin/staff/password" class="modal-content">
            <?= csrfField() ?>
            <input type="hidden" name="user_id" id="pwdUserId" value="">
            <div class="modal-header">
                <h5 class="modal-title" id="pwdTitle">Новый пароль</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <label class="form-label" for="pwdValue">Пароль (не короче 8 символов)</label>
                <input type="text" name="password" id="pwdValue" class="form-control" autocomplete="off"
                       placeholder="Оставьте пустым — создастся автоматически">
                <div class="form-text">Пароль будет показан один раз — сразу после сохранения.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="submit" class="btn btn-brand">Сменить пароль</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var m = document.getElementById('pwdModal');
    m.addEventListener('show.bs.modal', function (ev) {
        var b = ev.relatedTarget;
        if (!b) return;
        document.getElementById('pwdUserId').value = b.dataset.userId;
        document.getElementById('pwdTitle').textContent = 'Новый пароль · ' + b.dataset.userName;
        document.getElementById('pwdValue').value = '';
    });
})();
</script>

<?php require BASE_PATH . '/views/layout/footer.php'; ?>
