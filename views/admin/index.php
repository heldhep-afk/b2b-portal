<?php
require BASE_PATH . '/views/layout/header.php';

$actionMeta = [
    'confirm_payment' => ['Подтвердить оплату',   'bi-cash-coin',           'btn-outline-brand'],
    'confirm_deal'    => ['Подтвердить сделку',   'bi-check2-circle',       'btn-brand'],
    'payout'          => ['Выплатить продавцу',   'bi-send-check',          'btn-success'],
    'refund'          => ['Вернуть покупателю',   'bi-arrow-counterclockwise', 'btn-outline-dark'],
    'cancel'          => ['Отменить',             'bi-x-circle',            'btn-outline-danger'],
];
$viewTabs = [
    'all'     => ['Все', null],
    'payment' => ['Ждут оплаты', $stats['payment']],
    'confirm' => ['На подтверждение', $stats['confirm']],
    'payout'  => ['К выплате', $stats['payout']],
    'refund'  => ['К возврату', $stats['refund']],
];
$tabUrl = fn(string $v): string => '/admin' . ($v !== 'all' ? '?view=' . $v : '') . '#deals';
?>

<?php if ($dbNotice): ?>
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i><?= e($dbNotice) ?></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
    <div>
        <h2 class="mb-1"><i class="bi bi-shield-lock text-brand me-2"></i>Админ-панель</h2>
        <div class="text-muted small">
            <?= e($admin['full_name'] ?: $admin['username']) ?> · все платежи по сделкам проходят через администраторов платформы
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="/admin/inspections<?= !empty($stats['insp_review']) ? '?view=review' : '' ?>" class="btn btn-outline-brand">
            <i class="bi bi-patch-check me-1"></i>Проверки
            <?php if (!empty($stats['insp_review'])): ?><span class="badge text-bg-warning"><?= (int)$stats['insp_review'] ?></span><?php endif; ?>
        </a>
        <a href="/admin/staff" class="btn btn-outline-brand"><i class="bi bi-person-badge me-1"></i>Персонал</a>
        <a href="/admin/stats" class="btn btn-outline-brand"><i class="bi bi-graph-up me-1"></i>Статистика продаж</a>
    </div>
</div>

<?php if (!empty($stats['insp_review']) || !empty($stats['insp_todo'])): ?>
    <div class="alert alert-warning d-flex align-items-center justify-content-between flex-wrap gap-2 py-2">
        <span>
            <i class="bi bi-patch-check me-1"></i>Проверки качества:
            отчётов на подтверждение — <strong><?= (int)($stats['insp_review'] ?? 0) ?></strong>,
            требуют действий администратора (доплаты, выплаты, возвраты) — <strong><?= (int)($stats['insp_todo'] ?? 0) ?></strong>
        </span>
        <a href="/admin/inspections" class="alert-link">Открыть</a>
    </div>
<?php endif; ?>

<!-- Сводка -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg">
        <a href="<?= e($tabUrl('payment')) ?>" class="stat-link">
            <div class="card shadow-sm h-100 stat-card stat-secondary"><div class="card-body">
                <div class="text-muted small">Ждут оплаты</div>
                <div class="fs-3 fw-bold"><?= (int)$stats['payment'] ?></div>
            </div></div>
        </a>
    </div>
    <div class="col-6 col-lg">
        <a href="<?= e($tabUrl('confirm')) ?>" class="stat-link">
            <div class="card shadow-sm h-100 stat-card stat-warning"><div class="card-body">
                <div class="text-muted small">На подтверждение</div>
                <div class="fs-3 fw-bold"><?= (int)$stats['confirm'] ?></div>
            </div></div>
        </a>
    </div>
    <div class="col-6 col-lg">
        <a href="<?= e($tabUrl('payout')) ?>" class="stat-link">
            <div class="card shadow-sm h-100 stat-card stat-success"><div class="card-body">
                <div class="text-muted small">К выплате продавцам</div>
                <div class="fs-3 fw-bold"><?= (int)$stats['payout'] ?></div>
            </div></div>
        </a>
    </div>
    <div class="col-6 col-lg">
        <a href="<?= e($tabUrl('refund')) ?>" class="stat-link">
            <div class="card shadow-sm h-100 stat-card stat-danger"><div class="card-body">
                <div class="text-muted small">К возврату</div>
                <div class="fs-3 fw-bold"><?= (int)$stats['refund'] ?></div>
            </div></div>
        </a>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card shadow-sm h-100 stat-card stat-info"><div class="card-body">
            <div class="d-flex justify-content-between flex-wrap gap-2">
                <div>
                    <div class="text-muted small">Средства у платформы</div>
                    <div class="fs-5 fw-bold text-nowrap"><?= e(formatPrice($stats['held'])) ?></div>
                </div>
                <div class="text-end">
                    <div class="text-muted small">Выплачено продавцам</div>
                    <div class="fs-5 fw-bold text-nowrap"><?= e(formatPrice($stats['paid_out'])) ?></div>
                </div>
            </div>
        </div></div>
    </div>
</div>

<!-- Сделки -->
<div id="deals" class="card shadow-sm mb-4">
    <div class="card-body p-4">
        <h4 class="mb-3">Сделки <span class="badge text-bg-secondary"><?= (int)$stats['total'] ?></span></h4>

        <ul class="nav nav-pills mb-3 gap-1">
            <?php foreach ($viewTabs as $key => [$label, $count]): ?>
                <li class="nav-item">
                    <a class="nav-link py-1 px-3<?= $filters['view'] === $key ? ' active' : '' ?>" href="<?= e($tabUrl($key)) ?>">
                        <?= e($label) ?><?php if ($count !== null): ?> <span class="badge text-bg-light"><?= (int)$count ?></span><?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <form method="GET" action="/admin#deals" class="row g-2 mb-3">
            <input type="hidden" name="view" value="<?= e($filters['view']) ?>">
            <div class="col-md-4">
                <input type="search" name="q" class="form-control" maxlength="100" placeholder="№ сделки, компания, товар"
                       value="<?= e($filters['q']) ?>">
            </div>
            <div class="col-6 col-md-3">
                <select name="status" class="form-select">
                    <option value="all">Любой статус</option>
                    <?php foreach (['in_progress', 'completed', 'rejected', 'cancelled'] as $st): ?>
                        <option value="<?= e($st) ?>" <?= $filters['status'] === $st ? 'selected' : '' ?>><?= e(dealStatusMeta($st)['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <select name="pay" class="form-select">
                    <option value="all">Любая оплата</option>
                    <?php foreach (['unpaid', 'paid', 'paid_out', 'refunded'] as $ps): ?>
                        <option value="<?= e($ps) ?>" <?= $filters['pay'] === $ps ? 'selected' : '' ?>><?= e(paymentStatusMeta($ps)['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-brand">Найти</button>
            </div>
        </form>

        <?php if (!$deals): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>Сделок по выбранным условиям нет.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>№</th>
                        <th>Дата</th>
                        <th>Покупатель → Продавец</th>
                        <th>Товар</th>
                        <th class="text-end">Сумма</th>
                        <th>Статус</th>
                        <th>Оплата</th>
                        <th class="text-end">Действия</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($deals as $d): ?>
                        <tr>
                            <td class="text-nowrap fw-semibold"><?= e($d['deal_number']) ?></td>
                            <td class="text-nowrap small"><?= e(formatDate($d['created_at'])) ?></td>
                            <td>
                                <div><?= e($d['buyer_name']) ?></div>
                                <div class="small text-muted"><i class="bi bi-arrow-right-short"></i><?= e($d['seller_name']) ?></div>
                            </td>
                            <td>
                                <?= e(dealItemsTitle((string)$d['product_name'], (int)($d['items_count'] ?? 1))) ?>
                                <div class="small text-muted">
                                    <?php if ((int)($d['items_count'] ?? 1) > 1): ?>
                                        <?= (int)$d['items_count'] ?> поз.
                                    <?php else: ?>
                                        <?= number_format((int)$d['quantity'], 0, ',', ' ') ?> <?= e($d['unit']) ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-end text-nowrap fw-semibold"><?= e(formatPrice($d['total_amount'])) ?></td>
                            <td>
                                <span class="badge text-bg-<?= e($d['status_badge']) ?>"><?= e($d['status_label']) ?></span>
                                <?php if ($d['status'] === 'in_progress'): ?>
                                    <div class="small mt-1 <?= $d['approved'] ? 'text-success' : 'text-muted' ?>">
                                        <?= $d['approved'] ? '<i class="bi bi-check2"></i> одобрена покупателем' : 'ждёт одобрения покупателя' ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($d['inspection_id'])): $im = inspectionStatusMeta($d['inspection_status']); ?>
                                    <div class="small mt-1">
                                        <a href="/admin/inspections/view?id=<?= (int)$d['inspection_id'] ?>" class="text-decoration-none">
                                            <i class="bi bi-patch-check me-1"></i>Проверка:
                                            <span class="badge text-bg-<?= e($im['badge']) ?>"><?= e($im['label']) ?></span>
                                        </a>
                                    </div>
                                    <?php if ($d['status'] === 'in_progress' && $d['payment_status'] === 'paid' && $d['approved'] && inspectionBlocksDeal($d['inspection_status'])): ?>
                                        <div class="small text-muted">подтверждение сделки — после проверки</div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge text-bg-<?= e($d['pay_badge']) ?>"><?= e($d['pay_label']) ?></span>
                                <?php if ($d['paid_at']): ?><div class="small text-muted">получено <?= e(formatDate($d['paid_at'])) ?></div><?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if (!$d['actions']): ?>
                                    <span class="text-muted">—</span>
                                <?php else: ?>
                                    <div class="d-flex flex-wrap gap-1 justify-content-end">
                                        <?php foreach ($d['actions'] as $act): [$label, $icon, $cls] = $actionMeta[$act]; ?>
                                            <button type="button" class="btn btn-sm <?= e($cls) ?>"
                                                    data-bs-toggle="modal" data-bs-target="#adminModal"
                                                    data-action="<?= e($act) ?>" data-deal-id="<?= (int)$d['id'] ?>"
                                                    data-deal-number="<?= e($d['deal_number']) ?>"
                                                    data-amount="<?= e(formatPrice($d['total_amount'])) ?>">
                                                <i class="bi <?= e($icon) ?> me-1"></i><?= e($label) ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (count($deals) >= 200): ?>
                <div class="small text-muted mt-2">Показаны последние 200 сделок — уточните фильтры.</div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Журнал -->
<div id="journal" class="card shadow-sm mb-4">
    <div class="card-body p-4">
        <h4 class="mb-3">Журнал действий администраторов</h4>
        <?php if (!$events): ?>
            <div class="text-muted">Действий пока нет.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                    <tr><th>Время</th><th>Сделка</th><th>Действие</th><th>Администратор</th><th>Комментарий</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($events as $ev): ?>
                        <tr>
                            <td class="text-nowrap small"><?= e(formatDateTime($ev['created_at'])) ?></td>
                            <td class="text-nowrap fw-semibold"><?= e($ev['deal_number']) ?></td>
                            <td><?= e(adminActionLabel($ev['action'])) ?></td>
                            <td><?= e($ev['admin_name'] ?: '—') ?></td>
                            <td class="small text-muted"><?= e($ev['comment'] ?: '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Подтверждение действия -->
<div class="modal fade" id="adminModal" tabindex="-1" aria-labelledby="adminModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="/admin/deals/action" class="modal-content">
            <?= csrfField() ?>
            <input type="hidden" name="deal_id" id="adminDealId" value="">
            <input type="hidden" name="action" id="adminAction" value="">
            <input type="hidden" name="back" value="<?= e($backUrl) ?>">
            <div class="modal-header">
                <h5 class="modal-title" id="adminModalTitle">Действие по сделке</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <p id="adminModalText" class="mb-3"></p>
                <label class="form-label" for="adminComment" id="adminCommentLabel">Комментарий</label>
                <textarea name="comment" id="adminComment" class="form-control" rows="3" maxlength="500"></textarea>
                <div class="form-text">Сохраняется в журнале действий.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="submit" class="btn btn-brand" id="adminConfirm">Подтвердить</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var texts = {
        confirm_payment: ['Подтвердить оплату', 'Подтвердите, что оплата по сделке {n} на сумму {a} поступила на счёт платформы.', 'Подтвердить', 'btn-brand', false],
        confirm_deal:    ['Подтвердить сделку', 'Сделка {n} будет завершена: оплата получена, покупатель одобрил сделку. После этого можно выплатить средства продавцу.', 'Подтвердить сделку', 'btn-brand', false],
        payout:          ['Выплатить продавцу', 'Отметьте, что средства ({a}) по сделке {n} переведены продавцу.', 'Выплата произведена', 'btn-success', false],
        refund:          ['Вернуть оплату покупателю', 'Отметьте, что оплата ({a}) по сделке {n} возвращена покупателю.', 'Возврат произведён', 'btn-dark', true],
        cancel:          ['Отменить сделку', 'Сделка {n} получит статус «Отменён». Если оплата уже получена, потом потребуется возврат покупателю.', 'Отменить сделку', 'btn-danger', true]
    };
    var modal = document.getElementById('adminModal');
    modal.addEventListener('show.bs.modal', function (ev) {
        var btn = ev.relatedTarget;
        if (!btn) return;
        var t = texts[btn.dataset.action];
        if (!t) return;
        document.getElementById('adminDealId').value = btn.dataset.dealId;
        document.getElementById('adminAction').value = btn.dataset.action;
        document.getElementById('adminModalTitle').textContent = t[0] + ' · ' + btn.dataset.dealNumber;
        document.getElementById('adminModalText').textContent = t[1].replace('{n}', btn.dataset.dealNumber).replace('{a}', btn.dataset.amount);
        var ok = document.getElementById('adminConfirm');
        ok.textContent = t[2];
        ok.className = 'btn ' + t[3];
        var c = document.getElementById('adminComment');
        c.value = '';
        c.required = t[4];
        document.getElementById('adminCommentLabel').textContent = t[4] ? 'Причина (обязательно)' : 'Комментарий (необязательно)';
    });
})();
</script>

<?php require BASE_PATH . '/views/layout/footer.php'; ?>
