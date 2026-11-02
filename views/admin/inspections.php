<?php
require BASE_PATH . '/views/layout/header.php';

$tabs = [
    'all'    => 'Все',
    'review' => 'Отчёты на подтверждение',
    'fee'    => 'Ждут доплаты',
    'assign' => 'Без проверяющего',
    'work'   => 'Проверяются',
    'reward' => 'К выплате проверяющим',
    'refund' => 'К возврату доплаты',
];
$tabUrl = fn(string $v): string => '/admin/inspections' . ($v !== 'all' ? '?view=' . $v : '');
?>

<?php if ($dbNotice): ?>
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i><?= e($dbNotice) ?></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
    <div>
        <h2 class="mb-1"><i class="bi bi-patch-check text-brand me-2"></i>Проверки качества</h2>
        <div class="text-muted small">
            Компании доплачивают за проверку, проверяющий выполняет её и присылает отчёт, администратор подтверждает отчёт.
        </div>
    </div>
    <a href="/admin/staff" class="btn btn-outline-brand"><i class="bi bi-person-badge me-1"></i>Персонал</a>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body p-4">
        <ul class="nav nav-pills mb-3 gap-1">
            <?php foreach ($tabs as $key => $label): ?>
                <li class="nav-item">
                    <a class="nav-link py-1 px-3<?= $filters['view'] === $key ? ' active' : '' ?>" href="<?= e($tabUrl($key)) ?>">
                        <?= e($label) ?> <span class="badge text-bg-light"><?= (int)$counts[$key] ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <form method="GET" action="/admin/inspections" class="row g-2 mb-3">
            <input type="hidden" name="view" value="<?= e($filters['view']) ?>">
            <div class="col-md-9">
                <input type="search" name="q" class="form-control" maxlength="100"
                       placeholder="№ сделки, компания, товар, проверяющий" value="<?= e($filters['q']) ?>">
            </div>
            <div class="col-md-3 d-grid"><button type="submit" class="btn btn-brand">Найти</button></div>
        </form>

        <?php if (!$list): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>Проверок по выбранным условиям нет.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>Сделка</th>
                        <th>Покупатель → Продавец</th>
                        <th>Товар / услуга</th>
                        <th>Проверяющий</th>
                        <th class="text-end">Доплата</th>
                        <th>Статус</th>
                        <th class="text-end"></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($list as $i):
                        $st = inspectionStatusMeta($i['status']);
                        $fm = inspectionFeeMeta($i['fee_status']);
                        $vm = inspectionVerdictMeta($i['verdict']);
                    ?>
                        <tr>
                            <td class="text-nowrap fw-semibold">
                                <?= e($i['deal_number']) ?>
                                <div class="small text-muted fw-normal"><?= e(formatDate($i['created_at'])) ?></div>
                            </td>
                            <td>
                                <div><?= e($i['buyer_name']) ?></div>
                                <div class="small text-muted"><i class="bi bi-arrow-right-short"></i><?= e($i['seller_name']) ?></div>
                            </td>
                            <td><?= e(dealItemsTitle((string)$i['product_name'], (int)($i['items_count'] ?? 1))) ?></td>
                            <td>
                                <?php if ($i['inspector_name']): ?>
                                    <?= e($i['inspector_name']) ?>
                                <?php else: ?>
                                    <span class="text-muted">не назначен</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <div class="fw-semibold"><?= e(formatPrice($i['fee'])) ?></div>
                                <span class="badge text-bg-<?= e($fm['badge']) ?>"><?= e($fm['label']) ?></span>
                            </td>
                            <td>
                                <span class="badge text-bg-<?= e($st['badge']) ?>"><?= e($st['label']) ?></span>
                                <?php if ($i['verdict'] && in_array($i['status'], ['submitted', 'approved'], true)): ?>
                                    <div class="small mt-1"><i class="bi <?= e($vm['icon']) ?> me-1"></i><?= e($vm['label']) ?></div>
                                <?php endif; ?>
                                <?php if ($i['status'] === 'approved'): ?>
                                    <div class="small text-muted mt-1">
                                        <?= $i['reward_paid_at'] ? 'вознаграждение выплачено' : 'вознаграждение ' . e(formatPrice($i['reward'])) . ' — к выплате' ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="/admin/inspections/view?id=<?= (int)$i['id'] ?>"
                                   class="btn btn-sm <?= $i['status'] === 'submitted' ? 'btn-brand' : 'btn-outline-brand' ?>">
                                    <?= $i['status'] === 'submitted' ? 'Проверить отчёт' : 'Открыть' ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (count($list) >= 200): ?>
                <div class="small text-muted mt-2">Показаны последние 200 проверок — уточните поиск.</div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require BASE_PATH . '/views/layout/footer.php'; ?>
