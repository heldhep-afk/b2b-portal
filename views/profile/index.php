<?php
require BASE_PATH . '/views/layout/header.php';
$canEdit = canCompany('edit_company');
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h2 class="mb-0">Профиль компании</h2>
    <a href="/company" class="btn btn-outline-brand">
        <i class="bi bi-building me-1"></i>Страница компании
    </a>
</div>

<div class="card shadow-sm">
    <div class="card-body p-4">
        <div class="row mb-4">
            <div class="col-md-6"><strong>Email:</strong> <?= e($user['email']) ?></div>
            <div class="col-md-3"><strong>ИНН:</strong> <?= e($user['inn'] ?: '—') ?></div>
            <div class="col-md-3"><strong>Роль в компании:</strong> <?= e(companyRoleLabel($user['company_role'])) ?></div>
        </div>

        <form method="POST" action="/profile/edit">
            <?= csrfField() ?>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="company_name">Название компании</label>
                    <input type="text" id="company_name" name="company_name"<?= $canEdit ? '' : ' readonly' ?> class="form-control" maxlength="150"
                           value="<?= e($user['company_name']) ?>"<?= $canEdit ? ' required' : '' ?>>
                    <?php if (!$canEdit): ?><div class="form-text">У вас нет права менять данные компании. Обратитесь к директору.</div><?php endif; ?>
                </div>
                <div class="col-12">
                    <label class="form-label" for="legal_address">Юридический адрес</label>
                    <input type="text" id="legal_address" name="legal_address"<?= $canEdit ? '' : ' readonly' ?> class="form-control" maxlength="255"
                           value="<?= e($user['legal_address']) ?>"<?= $canEdit ? ' required' : '' ?>>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="contact_person">ФИО</label>
                    <input type="text" id="contact_person" name="contact_person" class="form-control" maxlength="150"
                           value="<?= e($user['contact_person']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="company_phone">Номер для связи</label>
                    <input type="tel" id="company_phone" name="company_phone"<?= $canEdit ? '' : ' readonly' ?> class="form-control" maxlength="30"
                           value="<?= e($user['company_phone']) ?>"<?= $canEdit ? ' required' : '' ?>>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="position">Должность</label>
                    <input type="text" id="position" name="position" class="form-control" maxlength="100" value="<?= e($user['position']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="phone">Личный телефон</label>
                    <input type="tel" id="phone" name="phone" class="form-control" maxlength="30" value="<?= e($user['phone']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="username">Логин</label>
                    <input type="text" id="username" name="username" class="form-control" maxlength="50"
                           pattern="[A-Za-z0-9_.\-]+" title="Латинские буквы, цифры и символы _ . -"
                           value="<?= e($user['username']) ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label" for="description">О компании</label>
                    <textarea id="description" name="description"<?= $canEdit ? '' : ' readonly' ?> class="form-control" rows="4" maxlength="2000"><?= e($user['description']) ?></textarea>
                </div>
            </div>
            <button type="submit" class="btn btn-brand mt-4">Сохранить изменения</button>
        </form>
    </div>
</div>

<?php require BASE_PATH . '/views/layout/footer.php'; ?>
