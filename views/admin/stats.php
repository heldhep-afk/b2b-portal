<?php
require BASE_PATH . '/views/layout/header.php';

$viewLabels = ['year' => 'Год', 'month' => 'Месяц', 'day' => 'День'];
$deltaIcon = $delta === null ? '' : ($delta >= 0 ? 'bi-arrow-up-right' : 'bi-arrow-down-right');
$deltaClass = $delta === null ? 'text-muted' : ($delta >= 0 ? 'text-success' : 'text-danger');
?>

<?php if ($dbNotice): ?>
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i><?= e($dbNotice) ?></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
    <div>
        <h2 class="mb-1"><i class="bi bi-graph-up text-brand me-2"></i>Статистика продаж</h2>
        <div class="text-muted small">Продажей считается завершённая сделка — по дате её завершения.</div>
    </div>
    <a href="/admin" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>К сделкам</a>
</div>

<div class="row g-4">
    <div class="col-lg-8" id="report">

        <!-- Период -->
        <div class="card shadow-sm mb-3">
            <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div class="btn-group btn-group-sm" role="group" aria-label="Период">
                    <?php foreach ($viewLabels as $key => $label): ?>
                        <a href="<?= e($viewUrls[$key]) ?>" class="btn btn-outline-brand<?= $view === $key ? ' active' : '' ?>"><?= e($label) ?></a>
                    <?php endforeach; ?>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="<?= e($prevUrl) ?>" class="btn btn-sm btn-outline-secondary" aria-label="Назад"><i class="bi bi-chevron-left"></i></a>
                    <div class="fw-semibold text-center period-title"><?= e($periodTitle) ?></div>
                    <?php if ($nextDisabled): ?>
                        <span class="btn btn-sm btn-outline-secondary disabled" aria-disabled="true"><i class="bi bi-chevron-right"></i></span>
                    <?php else: ?>
                        <a href="<?= e($nextUrl) ?>" class="btn btn-sm btn-outline-secondary" aria-label="Вперёд"><i class="bi bi-chevron-right"></i></a>
                    <?php endif; ?>
                    <a href="<?= e($todayUrl) ?>" class="btn btn-sm btn-outline-brand">Сегодня</a>
                </div>
            </div>
        </div>

        <!-- Сводка -->
        <div class="row g-3 mb-3">
            <div class="col-6 col-md-3">
                <div class="card shadow-sm h-100 stat-card stat-success"><div class="card-body">
                    <div class="text-muted small">Продажи</div>
                    <div class="fs-5 fw-bold"><?= e(formatPrice($totals['sum'])) ?></div>
                </div></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card shadow-sm h-100 stat-card stat-warning"><div class="card-body">
                    <div class="text-muted small">Сделок</div>
                    <div class="fs-5 fw-bold"><?= (int)$totals['cnt'] ?></div>
                </div></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card shadow-sm h-100 stat-card stat-info"><div class="card-body">
                    <div class="text-muted small">Средний чек</div>
                    <div class="fs-5 fw-bold"><?= e(formatPrice($avg)) ?></div>
                </div></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card shadow-sm h-100 stat-card stat-secondary"><div class="card-body">
                    <div class="text-muted small">Динамика</div>
                    <div class="fs-5 fw-bold <?= e($deltaClass) ?>">
                        <?php if ($delta === null): ?>—<?php else: ?>
                            <i class="bi <?= e($deltaIcon) ?>"></i> <?= ($delta > 0 ? '+' : '') . number_format($delta, 1, ',', ' ') ?>%
                        <?php endif; ?>
                    </div>
                    <div class="small text-muted"><?= e($prevLabel) ?></div>
                </div></div>
            </div>
        </div>

        <!-- График или список за день -->
        <div class="card shadow-sm mb-4">
            <div class="card-body p-4">
                <?php if ($view === 'day'): ?>
                    <h5 class="mb-3">Продажи за день <span class="badge text-bg-secondary"><?= count($dayDeals) ?></span></h5>
                    <?php if (!$dayDeals): ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>В этот день завершённых сделок не было.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                <tr>
                                    <th>Время</th><th>№</th><th>Покупатель → Продавец</th><th>Товар</th><th class="text-end">Сумма</th><th>Оплата</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($dayDeals as $d): $pm = paymentStatusMeta($d['payment_status']); ?>
                                    <tr>
                                        <td class="text-nowrap"><?= e(date('H:i', strtotime($d['closed_at']))) ?></td>
                                        <td class="text-nowrap fw-semibold"><?= e($d['deal_number']) ?></td>
                                        <td>
                                            <div><?= e($d['buyer_name']) ?></div>
                                            <div class="small text-muted"><i class="bi bi-arrow-right-short"></i><?= e($d['seller_name']) ?></div>
                                        </td>
                                        <td>
                                            <?= e($d['product_name']) ?>
                                            <div class="small text-muted"><?= number_format((int)$d['quantity'], 0, ',', ' ') ?> <?= e($d['unit']) ?></div>
                                        </td>
                                        <td class="text-end text-nowrap fw-semibold"><?= e(formatPrice($d['total_amount'])) ?></td>
                                        <td><span class="badge text-bg-<?= e($pm['badge']) ?>"><?= e($pm['label']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                <tr class="table-light">
                                    <td colspan="4" class="fw-semibold">Итого</td>
                                    <td class="text-end text-nowrap fw-bold"><?= e(formatPrice($totals['sum'])) ?></td>
                                    <td></td>
                                </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0"><?= $view === 'year' ? 'Продажи по месяцам' : 'Продажи по дням' ?></h5>
                        <span class="small text-muted">Нажмите на столбец, чтобы перейти <?= $view === 'year' ? 'к месяцу' : 'к дню' ?></span>
                    </div>
                    <?php if ($totals['cnt'] === 0): ?>
                        <div class="text-muted small mb-2">За этот период продаж не было.</div>
                    <?php endif; ?>
                    <div class="bar-chart<?= $view === 'month' ? ' bar-chart-dense' : '' ?>">
                        <?php foreach ($chart as $c): ?>
                            <?php $h = $chartMax > 0 ? round($c['sum'] / $chartMax * 100, 1) : 0; ?>
                            <a class="bar-col" href="<?= e($c['url']) ?>"
                               title="<?= e($c['title'] . ': ' . formatPrice($c['sum']) . ', сделок: ' . $c['cnt']) ?>">
                                <span class="bar-value"><?= ($view === 'year' && $c['sum'] > 0) ? e(formatCompact($c['sum'])) : '' ?></span>
                                <span class="bar-wrap"><span class="bar<?= $c['sum'] <= 0 ? ' bar-empty' : '' ?>" style="height: <?= $h ?>%"></span></span>
                                <span class="bar-label"><?= e($c['label']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Календарь -->
    <div class="col-lg-4">
        <div class="card shadow-sm sticky-lg" id="calendar">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <a href="<?= e($calPrevUrl) ?>" class="btn btn-sm btn-outline-secondary" aria-label="Предыдущий месяц"><i class="bi bi-chevron-left"></i></a>
                    <div class="fw-semibold"><?= e($calTitle) ?></div>
                    <a href="<?= e($calNextUrl) ?>" class="btn btn-sm btn-outline-secondary" aria-label="Следующий месяц"><i class="bi bi-chevron-right"></i></a>
                </div>

                <div class="cal-grid cal-head mb-1">
                    <?php foreach (['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'] as $wd): ?><span><?= e($wd) ?></span><?php endforeach; ?>
                </div>
                <div class="cal-grid">
                    <?php foreach ($cells as $c): ?>
                        <?php if ($c === null): ?>
                            <span class="cal-cell cal-empty"></span>
                        <?php else: ?>
                            <?php
                            $cls = 'cal-cell cal-l' . $c['level'] . ($c['today'] ? ' cal-today' : '') . ($c['selected'] ? ' cal-selected' : '');
                            $title = $c['day'] . ($c['sum'] > 0 ? ': ' . formatPrice($c['sum']) . ', сделок: ' . $c['cnt'] : ': продаж нет');
                            ?>
                            <?php if ($c['future']): ?>
                                <span class="<?= e($cls) ?> cal-future"><span class="cal-day"><?= (int)$c['day'] ?></span></span>
                            <?php else: ?>
                                <a href="<?= e($c['url']) ?>" class="<?= e($cls) ?>" title="<?= e($title) ?>">
                                    <span class="cal-day"><?= (int)$c['day'] ?></span>
                                    <?php if ($c['sum'] > 0): ?><span class="cal-sum"><?= e(formatCompact($c['sum'])) ?></span><?php endif; ?>
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <div class="small text-muted mt-3">
                    Нажмите на день, чтобы посмотреть продажи за него. Чем темнее ячейка, тем выше сумма продаж.
                </div>
            </div>
        </div>
    </div>
</div>

<?php require BASE_PATH . '/views/layout/footer.php'; ?>
