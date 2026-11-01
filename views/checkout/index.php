<?php
require BASE_PATH . '/views/layout/header.php';

$val = fn(string $k): string => e($old[$k] ?? '');
$qty = max(1, (int)($old['quantity'] ?? $product['min_order']));
$price = (float)$product['price'];
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/">Каталог</a></li>
        <li class="breadcrumb-item active" aria-current="page">Оформление покупки</li>
    </ol>
</nav>

<h2 class="mb-4">Оформление покупки</h2>

<?php if ($errorMsg): ?>
    <div class="alert alert-danger"><?= e($errorMsg) ?></div>
<?php endif; ?>

<?php if ($outOfStock): ?>
    <div class="alert alert-warning"><i class="bi bi-clock-history me-1"></i>Товара сейчас нет в наличии. Вы заказывали его раньше, поэтому повторный заказ доступен из истории — продавец подтвердит сроки поставки.</div>
<?php endif; ?>

<div class="row g-4">
    <!-- Форма -->
    <div class="col-lg-7">
        <form method="POST" action="/checkout" class="card shadow-sm" id="checkoutForm">
            <div class="card-body p-4">
                <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                <?= csrfField() ?>

                <h5 class="mb-3">Параметры заказа</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label" for="quantity">Количество, <?= e($product['unit']) ?> <span class="text-danger">*</span></label>
                        <input type="number" name="quantity" id="quantity" class="form-control"
                               min="<?= (int)$product['min_order'] ?>" max="100000000" step="1"
                               value="<?= $qty ?>" required>
                        <div class="form-text">Минимальный заказ: <?= number_format((int)$product['min_order'], 0, ',', ' ') ?> <?= e($product['unit']) ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="payment_terms">Условия оплаты <span class="text-danger">*</span></label>
                        <select name="payment_terms" id="payment_terms" class="form-select" required>
                            <option value="">Выберите…</option>
                            <?php foreach ($paymentTerms as $t): ?>
                                <option value="<?= e($t) ?>" <?= ($old['payment_terms'] ?? '') === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="delivery_address">Адрес доставки <span class="text-danger">*</span></label>
                        <input type="text" name="delivery_address" id="delivery_address" class="form-control"
                               maxlength="255" value="<?= $val('delivery_address') ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="comment">Комментарий для продавца</label>
                        <textarea name="comment" id="comment" class="form-control" rows="3" maxlength="500"
                                  placeholder="Желаемые сроки, требования к упаковке и т. п."><?= $val('comment') ?></textarea>
                    </div>
                </div>

                <h5 class="mb-3">Проверка качества</h5>
                <div class="rounded border p-3 mb-4" id="inspectionBox">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" role="switch" name="inspection" value="1" id="inspection"
                               <?= !empty($old['inspection']) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="inspection">
                            Заказать проверку проверяющим портала
                            <span class="text-brand text-nowrap ms-1" id="inspectionFeeLabel"></span>
                        </label>
                    </div>
                    <div class="small text-muted mt-1">
                        Сотрудник платформы проверит товар или услугу и пришлёт отчёт, который подтвердит администратор.
                        Стоимость проверки оплачивается отдельно, сверх суммы сделки. Пока отчёт не подтверждён, сделку завершить нельзя.
                    </div>
                    <div id="inspectionNoteWrap" class="mt-3 d-none">
                        <label class="form-label" for="inspection_note">Что именно проверить</label>
                        <textarea name="inspection_note" id="inspection_note" class="form-control" rows="2" maxlength="500"
                                  placeholder="Комплектность, соответствие описанию, качество исполнения, сроки…"><?= $val('inspection_note') ?></textarea>
                    </div>
                </div>

                <h5 class="mb-3">Покупатель</h5>
                <div class="rounded bg-light border p-3 mb-4 small">
                    <div class="fw-semibold"><i class="bi bi-building me-1 text-brand"></i><?= e($buyer['company_name'] ?? '') ?></div>
                    <div class="text-muted">
                        <?= e($buyer['full_name'] ?: ($buyer['username'] ?? '')) ?>
                        <?php if (!empty($buyer['position'])): ?>· <?= e($buyer['position']) ?><?php endif; ?>
                    </div>
                </div>

                <div class="alert alert-light border small d-flex gap-2">
                    <i class="bi bi-shield-check text-brand mt-1"></i>
                    <span>Оплата проходит через администратора портала: после оформления в личном кабинете появятся реквизиты.
                    Деньги передаются продавцу только после подтверждения сделки.</span>
                </div>

                <button type="submit" class="btn btn-brand btn-lg w-100">
                    <i class="bi bi-check2-circle me-1"></i>Оформить заявку
                </button>
                <p class="text-muted small mt-3 mb-0">
                    Сделка получит статус «В работе». Одобрить или отклонить её можно будет в
                    <a href="/account#deals">личном кабинете</a>.
                </p>
            </div>
        </form>
    </div>

    <!-- Сводка по товару -->
    <div class="col-lg-5">
        <div class="card shadow-sm sticky-lg">
            <div class="card-body p-4">
                <?php if ($product['category_name']): ?>
                    <span class="badge text-bg-light border mb-2"><?= e($product['category_name']) ?></span>
                <?php endif; ?>
                <h5 class="mb-1"><?= e($product['name']) ?></h5>
                <div class="small text-muted mb-3">
                    <i class="bi bi-shop me-1"></i><?= e($product['company_name']) ?>
                    <?php if ($product['sku']): ?> · арт. <?= e($product['sku']) ?><?php endif; ?>
                </div>
                <?php if ($product['description']): ?>
                    <p class="small text-muted"><?= nl2br(e($product['description'])) ?></p>
                <?php endif; ?>

                <dl class="row small mb-3">
                    <dt class="col-6 text-muted fw-normal">Цена за <?= e($product['unit']) ?></dt>
                    <dd class="col-6 text-end fw-semibold"><?= e(formatPrice($product['price'])) ?></dd>
                    <dt class="col-6 text-muted fw-normal">Наличие</dt>
                    <dd class="col-6 text-end">
                        <?php if ((int)$product['in_stock'] > 0): ?>
                            <span class="badge text-bg-success">В наличии</span>
                        <?php else: ?>
                            <span class="badge text-bg-secondary">Нет в наличии</span>
                        <?php endif; ?>
                    </dd>
                    <?php if ($product['company_phone']): ?>
                        <dt class="col-6 text-muted fw-normal">Телефон продавца</dt>
                        <dd class="col-6 text-end"><?= e($product['company_phone']) ?></dd>
                    <?php endif; ?>
                </dl>

                <div class="border-top pt-3 d-flex justify-content-between align-items-center">
                    <span class="text-muted">Итого по сделке</span>
                    <span class="fs-4 fw-bold text-brand text-nowrap" id="orderTotal"><?= e(formatPrice($price * $qty)) ?></span>
                </div>
                <div class="small text-muted text-end">без учёта доставки</div>
                <div id="inspectionSummary" class="d-none border-top mt-3 pt-3">
                    <div class="d-flex justify-content-between small">
                        <span class="text-muted"><i class="bi bi-patch-check me-1"></i>Проверка качества (доплата)</span>
                        <span class="fw-semibold text-nowrap" id="inspectionFee"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var price = <?= json_encode($price) ?>;
    var pr = <?= json_encode(['rate' => $pricing['rate'], 'min' => $pricing['min'], 'max' => $pricing['max'], 'step' => $pricing['step']]) ?>;
    var input = document.getElementById('quantity');
    var out = document.getElementById('orderTotal');
    var chk = document.getElementById('inspection');
    var feeLabel = document.getElementById('inspectionFeeLabel');
    var feeOut = document.getElementById('inspectionFee');
    var summary = document.getElementById('inspectionSummary');
    var noteWrap = document.getElementById('inspectionNoteWrap');
    var fmt = new Intl.NumberFormat('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    // та же формула, что inspectionFeeFor() в app/Core/helpers.php
    function feeFor(total) {
        var f = Math.max(pr.min, Math.min(pr.max, total * pr.rate));
        return Math.round(f / pr.step) * pr.step;
    }
    function update() {
        var q = parseInt(input.value, 10);
        var total = isNaN(q) || q < 0 ? 0 : price * q;
        out.textContent = fmt.format(total) + ' ₽';
        var fee = fmt.format(feeFor(total)) + ' ₽';
        feeLabel.textContent = '+ ' + fee;
        feeOut.textContent = fee;
        summary.classList.toggle('d-none', !chk.checked);
        noteWrap.classList.toggle('d-none', !chk.checked);
    }
    input.addEventListener('input', update);
    chk.addEventListener('change', update);
    update();
})();
</script>

<?php require BASE_PATH . '/views/layout/footer.php'; ?>
