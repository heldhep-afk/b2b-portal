<?php
require BASE_PATH . '/views/layout/header.php';

$val = fn(string $k): string => e($old[$k] ?? '');
$dealsCount = count($groups);
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/">Каталог</a></li>
        <li class="breadcrumb-item"><a href="/cart">Заявка</a></li>
        <li class="breadcrumb-item active" aria-current="page">Оформление</li>
    </ol>
</nav>

<h2 class="mb-4">Оформление закупки</h2>

<?php if ($errorMsg): ?>
    <div class="alert alert-danger"><?= e($errorMsg) ?></div>
<?php endif; ?>

<?php if ($dealsCount > 1): ?>
    <div class="alert alert-light border d-flex gap-2">
        <i class="bi bi-diagram-3 text-brand mt-1"></i>
        <span>В заявке товары <?= $dealsCount ?> продавцов. Мы оформим <?= $dealsCount ?> отдельные сделки — по одной с каждым продавцом.</span>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Форма -->
    <div class="col-lg-7">
        <form method="POST" action="/checkout" class="card shadow-sm" id="checkoutForm">
            <div class="card-body p-4">
                <?= csrfField() ?>

                <h5 class="mb-3">Параметры закупки</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label" for="payment_terms">Условия оплаты <span class="text-danger">*</span></label>
                        <select name="payment_terms" id="payment_terms" class="form-select" required>
                            <option value="">Выберите…</option>
                            <?php foreach ($paymentTerms as $t): ?>
                                <option value="<?= e($t) ?>" <?= ($old['payment_terms'] ?? '') === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="delivery_address">Адрес доставки <span class="text-danger">*</span></label>
                        <input type="text" name="delivery_address" id="delivery_address" class="form-control"
                               maxlength="255" value="<?= $val('delivery_address') ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="comment">Комментарий для продавцов</label>
                        <textarea name="comment" id="comment" class="form-control" rows="3" maxlength="500"
                                  placeholder="Желаемые сроки, требования к упаковке и т. п."><?= $val('comment') ?></textarea>
                        <div class="form-text">Комментарий увидят все продавцы из заявки.</div>
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
                        Сотрудник платформы проверит товары и пришлёт отчёт, который подтвердит администратор.
                        Стоимость проверки оплачивается отдельно, сверх суммы сделки: считается по каждой сделке
                        и складывается. Пока отчёт не подтверждён, сделку завершить нельзя.
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
                    <i class="bi bi-check2-circle me-1"></i>Оформить <?= $dealsCount > 1 ? $dealsCount . ' заявки' : 'заявку' ?>
                </button>
                <p class="text-muted small mt-3 mb-0">
                    Сделка получит статус «В работе». Одобрить или отклонить её можно будет в
                    <a href="/account#deals">личном кабинете</a>.
                </p>
            </div>
        </form>
    </div>

    <!-- Сводка по заявке -->
    <div class="col-lg-5">
        <div class="card shadow-sm sticky-lg">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Состав заявки</h5>
                    <a href="/cart" class="btn btn-sm btn-outline-brand">
                        <i class="bi bi-pencil me-1"></i>Изменить
                    </a>
                </div>

                <?php foreach ($groups as $g): ?>
                    <div class="mb-3">
                        <div class="small fw-semibold text-brand mb-2">
                            <i class="bi bi-shop me-1"></i><?= e($g['company_name']) ?>
                        </div>
                        <ul class="list-unstyled small mb-1">
                            <?php foreach ($g['items'] as $it): ?>
                                <li class="d-flex justify-content-between gap-2 py-1 border-bottom">
                                    <span class="flex-grow-1">
                                        <?= e($it['name']) ?>
                                        <span class="text-muted text-nowrap">
                                            · <?= number_format((int)$it['qty'], 0, ',', ' ') ?> <?= e($it['unit']) ?>
                                            × <?= e(formatPrice($it['price'])) ?>
                                        </span>
                                    </span>
                                    <span class="fw-semibold text-nowrap"><?= e(formatPrice($it['line_total'])) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="d-flex justify-content-between small">
                            <span class="text-muted">Итого у продавца</span>
                            <span class="fw-semibold text-nowrap"><?= e(formatPrice($g['total'])) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="border-top pt-3 d-flex justify-content-between align-items-center">
                    <span class="text-muted">Итого по заявке</span>
                    <span class="fs-4 fw-bold text-brand text-nowrap" id="orderTotal"><?= e(formatPrice($total)) ?></span>
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
    var groupTotals = <?= json_encode(array_values($groupTotals)) ?>;
    var pr = <?= json_encode(['rate' => $pricing['rate'], 'min' => $pricing['min'], 'max' => $pricing['max'], 'step' => $pricing['step']]) ?>;
    var chk = document.getElementById('inspection');
    var feeLabel = document.getElementById('inspectionFeeLabel');
    var feeOut = document.getElementById('inspectionFee');
    var summary = document.getElementById('inspectionSummary');
    var noteWrap = document.getElementById('inspectionNoteWrap');
    var fmt = new Intl.NumberFormat('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    // та же формула, что inspectionFeeFor() в app/Core/helpers.php, но по каждой сделке
    function feeFor(total) {
        var f = Math.max(pr.min, Math.min(pr.max, total * pr.rate));
        return Math.round(f / pr.step) * pr.step;
    }

    function update() {
        var fee = 0;
        for (var i = 0; i < groupTotals.length; i++) {
            fee += feeFor(groupTotals[i]);
        }
        var text = fmt.format(fee) + ' ₽';
        feeLabel.textContent = '+ ' + text;
        feeOut.textContent = text;
        summary.classList.toggle('d-none', !chk.checked);
        noteWrap.classList.toggle('d-none', !chk.checked);
    }

    chk.addEventListener('change', update);
    update();
})();
</script>

<?php require BASE_PATH . '/views/layout/footer.php'; ?>
