<?php
require BASE_PATH . '/views/layout/header.php';
$name = $company['company_name'] ?: $company['username'];
$row = function (string $label, ?string $value): void {
    echo '<dt class="col-sm-4 text-muted fw-normal">' . e($label) . '</dt>';
    echo '<dd class="col-sm-8 text-break">' . (($value !== null && $value !== '') ? e($value) : '<span class="text-muted">—</span>') . '</dd>';
};
?>

<!-- Карточка компании -->
<div class="card shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
            <div>
                <h2 class="mb-1 text-break"><?= e($name) ?></h2>
                <?php if ($company['inn']): ?>
                    <span class="badge text-bg-brand">ИНН <?= e($company['inn']) ?></span>
                <?php endif; ?>
            </div>
            <a href="/profile" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-pencil me-1"></i>Редактировать данные
            </a>
        </div>

        <?php if ($company['description']): ?>
            <p class="lead fs-6"><?= nl2br(e($company['description'])) ?></p>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-6">
                <dl class="row mb-0">
                    <?php $row('Юр. адрес', $company['legal_address']); ?>
                </dl>
            </div>
            <div class="col-lg-6">
                <dl class="row mb-0">
                    <?php $row('Контактное лицо', $company['contact_person']); ?>
                    <?php $row('Номер для связи', $company['phone']); ?>
                    <?php $row('Email', $company['email']); ?>
                </dl>
            </div>
        </div>
    </div>
</div>

<!-- Товары -->
<div id="products" class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h4 class="mb-0">Товары компании <span class="badge text-bg-secondary"><?= count($products) ?></span></h4>
    <?php if (canCompany('manage_products')): ?>
        <button class="btn btn-brand" type="button" data-bs-toggle="collapse" data-bs-target="#addProduct">
            <i class="bi bi-plus-lg me-1"></i>Добавить товар
        </button>
    <?php endif; ?>
</div>

<?php if (!canCompany('manage_products')): ?>
    <div class="alert alert-light border small mb-3">
        <i class="bi bi-info-circle me-1"></i>У вас нет права добавлять и удалять товары. Обратитесь к директору компании.
    </div>
<?php endif; ?>

<?php if (canCompany('manage_products')): ?>
<div class="collapse mb-4" id="addProduct">
    <div class="card card-body shadow-sm">
        <?php if (!$myCategories): ?>
            <div class="alert alert-warning mb-0">
                Сначала выберите категории товаров в
                <a href="/account#categories" class="alert-link">личном кабинете</a> — после этого можно будет добавлять товары.
            </div>
        <?php else: ?>
        <form method="POST" action="/company/products/add">
            <?= csrfField() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Название <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" maxlength="255" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Артикул</label>
                    <input type="text" name="sku" class="form-control" maxlength="64">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Цена, ₽ <span class="text-danger">*</span></label>
                    <input type="text" name="price" class="form-control" inputmode="decimal" placeholder="0,00" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Категория <span class="text-danger">*</span></label>
                    <select name="category_id" class="form-select" required>
                        <option value="">Выберите…</option>
                        <?php foreach ($myCategories as $mc): ?>
                            <option value="<?= (int)$mc['id'] ?>"><?= e($mc['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Единица</label>
                    <input type="text" name="unit" class="form-control" maxlength="20" value="шт.">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Мин. заказ</label>
                    <input type="number" name="min_order" class="form-control" min="1" value="1">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="in_stock" id="in_stock" checked>
                        <label class="form-check-label" for="in_stock">В наличии</label>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Описание</label>
                    <textarea name="description" class="form-control" rows="2" maxlength="2000"></textarea>
                </div>
            </div>
            <button type="submit" class="btn btn-brand mt-3">Сохранить товар</button>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if (!$products): ?>
    <div class="card shadow-sm">
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-box-seam fs-1 d-block mb-2"></i>
            Компания пока не добавила ни одного товара.
        </div>
    </div>
<?php else: ?>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>Товар</th>
                    <th>Категория</th>
                    <th>Артикул</th>
                    <th class="text-end">Цена</th>
                    <th>Мин. заказ</th>
                    <th>Наличие</th>
                    <th><span class="visually-hidden">Действия</span></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($products as $p): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= e($p['name']) ?></div>
                            <?php if ($p['description']): ?>
                                <div class="small text-muted"><?= e(mb_strimwidth($p['description'], 0, 120, '…')) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= e($p['category_name'] ?: '—') ?></td>
                        <td><?= e($p['sku'] ?: '—') ?></td>
                        <td class="text-end text-nowrap">
                            <?= e(formatPrice($p['price'])) ?> <span class="text-muted small">/ <?= e($p['unit']) ?></span>
                        </td>
                        <td><?= (int)$p['min_order'] ?> <?= e($p['unit']) ?></td>
                        <td>
                            <?php if ($p['in_stock']): ?>
                                <span class="badge text-bg-success">В наличии</span>
                            <?php else: ?>
                                <span class="badge text-bg-secondary">Нет в наличии</span>
                            <?php endif; ?>
                        </td>
                        <?php if (canCompany('manage_products')): ?>
                        <td class="text-end">
                            <form method="POST" action="/company/products/delete" class="js-confirm-delete"
                                  data-confirm-name="<?= e($p['name']) ?>">
                                <?= csrfField() ?>
                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Удалить" aria-label="Удалить товар «<?= e($p['name']) ?>»">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                        <?php else: ?>
                        <td></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<script>
document.querySelectorAll('.js-confirm-delete').forEach(function (form) {
    form.addEventListener('submit', function (ev) {
        if (!window.confirm('Удалить товар «' + (form.dataset.confirmName || '') + '»?')) {
            ev.preventDefault();
        }
    });
});

window.addEventListener('load', function () {
    function openFromHash() {
        if (location.hash !== '#addProduct') return;
        var el = document.getElementById('addProduct');
        if (!el || typeof bootstrap === 'undefined') return;
        bootstrap.Collapse.getOrCreateInstance(el, { toggle: false }).show();
        el.scrollIntoView({ block: 'start' });
    }
    window.addEventListener('hashchange', openFromHash);
    openFromHash();
});
</script>

<?php require BASE_PATH . '/views/layout/footer.php'; ?>
