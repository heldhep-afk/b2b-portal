<?php
require BASE_PATH . '/views/layout/header.php';

$tabs = [
    'mine' => ['Мои задания', count($mine)],
    'pool' => ['Свободные', count($pool)],
    'done' => ['История', count($done)],
];
$list = ['mine' => $mine, 'pool' => $pool, 'done' => $done][$tab];
?>

<?php if ($dbNotice): ?>
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i><?= e($dbNotice) ?></div>
<?php endif; ?>

<div class="mb-4">
    <h2 class="mb-1"><i class="bi bi-clipboard-check text-brand me-2"></i>Кабинет проверяющего</h2>
    <div class="text-muted small"><?= e($me['full_name'] ?: $me['username']) ?> · проверка товаров и услуг по заказам компаний</div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm h-100 stat-card stat-warning"><div class="card-body">
            <div class="text-muted small">В работе</div>
            <div class="fs-3 fw-bold"><?= (int)$money['in_work'] ?></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm h-100 stat-card stat-success"><div class="card-body">
            <div class="text-muted small">Подтверждено проверок</div>
            <div class="fs-3 fw-bold"><?= (int)$money['approved'] ?></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm h-100 stat-card stat-info"><div class="card-body">
            <div class="text-muted small">К выплате</div>
            <div class="fs-5 fw-bold text-nowrap"><?= e(formatPrice($money['owed'])) ?></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm h-100 stat-card stat-secondary"><div class="card-body">
            <div class="text-muted small">Уже выплачено</div>
            <div class="fs-5 fw-bold text-nowrap"><?= e(formatPrice($money['earned'])) ?></div>
        </div></div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body p-4">
        <ul class="nav nav-pills mb-3 gap-1">
            <?php foreach ($tabs as $key => [$label, $count]): ?>
                <li class="nav-item">
                    <a class="nav-link py-1 px-3<?= $tab === $key ? ' active' : '' ?>" href="/inspector?tab=<?= e($key) ?>">
                        <?= e($label) ?> <span class="badge text-bg-light"><?= (int)$count ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if (!$list): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                <?= $tab === 'pool' ? 'Свободных заданий нет — загляните позже.' : ($tab === 'mine' ? 'У вас нет заданий в работе. Возьмите задание во вкладке «Свободные».' : 'Завершённых проверок пока нет.') ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>Сделка</th>
                        <th>Что проверить</th>
                        <th>Продавец</th>
                        <th class="text-end">Вознаграждение</th>
                        <th>Статус</th>
                        <th class="text-end"></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($list as $t):
                        $st = inspectionStatusMeta($t['status']);
                        $vm = inspectionVerdictMeta($t['verdict']);
                        $next = inspectionNextStep('inspector', $t['status'], $t['fee_status'], $t['visit_status'], $t['visit_proposed_by'], $t['reward_paid_at'] !== null);
                        $needsMe = $tab === 'mine' && $next['action'];
                    ?>
                        <tr class="<?= $needsMe ? 'row-action' : '' ?>">
                            <td class="text-nowrap fw-semibold">
                                <?= e($t['deal_number']) ?>
                                <div class="small text-muted fw-normal"><?= e(formatDate($t['created_at'])) ?></div>
                            </td>
                            <td>
                                <div><?= e($t['product_name']) ?></div>
                                <div class="small text-muted"><?= number_format((int)$t['quantity'], 0, ',', ' ') ?> <?= e($t['unit']) ?><?= $t['category_name'] ? ' · ' . e($t['category_name']) : '' ?></div>
                            </td>
                            <td>
                                <?= e($t['seller_name']) ?>
                                <div class="small text-muted"><?= e($t['seller_address']) ?></div>
                            </td>
                            <td class="text-end text-nowrap fw-semibold"><?= e(formatPrice($t['reward'])) ?></td>
                            <td>
                                <span class="badge text-bg-<?= e($st['badge']) ?>"><?= e($st['label']) ?></span>
                                <div class="next-hint<?= $next['action'] ? ' is-action' : '' ?>"><?= e($next['text']) ?></div>
                                <?php if ($t['visit_at'] && $t['status'] === 'assigned'): ?>
                                    <div class="small mt-1"><i class="bi bi-calendar-event me-1"></i><?= e(formatDateTime($t['visit_at'])) ?></div>
                                <?php endif; ?>
                                <?php if ($t['status'] === 'assigned' && (int)$t['revision_count'] > 0 && $t['admin_comment']): ?>
                                    <div class="small text-danger mt-1"><i class="bi bi-arrow-return-left me-1"></i>возвращён на доработку</div>
                                <?php endif; ?>
                                <?php if ($t['status'] === 'approved'): ?>
                                    <div class="small mt-1"><i class="bi <?= e($vm['icon']) ?> me-1"></i><?= e($vm['label']) ?></div>
                                    <div class="small text-muted"><?= $t['reward_paid_at'] ? 'выплачено ' . e(formatDate($t['reward_paid_at'])) : 'ждёт выплаты' ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <?php if ($tab === 'pool'): ?>
                                    <a href="/inspector/task?id=<?= (int)$t['id'] ?>" class="btn btn-sm btn-outline-brand">Подробнее</a>
                                    <?php if ($t['fee_status'] === 'paid'): ?>
                                        <form method="POST" action="/inspector/take" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="inspection_id" value="<?= (int)$t['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-brand"><i class="bi bi-hand-index-thumb me-1"></i>Взять</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="badge text-bg-light border">ждёт оплаты заказчиком</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <a href="/inspector/task?id=<?= (int)$t['id'] ?>"
                                       class="btn btn-sm <?= $t['status'] === 'assigned' ? 'btn-brand' : 'btn-outline-brand' ?>">
                                        <?= $t['status'] === 'assigned' ? ($needsMe ? 'Продолжить' : 'Открыть') : 'Открыть' ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require BASE_PATH . '/views/layout/footer.php'; ?>
