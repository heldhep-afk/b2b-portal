<?php
require BASE_PATH . '/views/layout/header.php';

$fullName = $user['full_name'] ?: $user['username'];
$tabs = [
    'all'      => 'Все',
    'progress' => 'В работе',
    'done'     => 'Завершённые',
    'rejected' => 'Отклонённые',
    'cancelled' => 'Отменённые',
];
?>

<?php if ($dbNotice): ?>
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i><?= e($dbNotice) ?></div>
<?php endif; ?>

<?php
$nameWords = preg_split('/\s+/u', trim($fullName), -1, PREG_SPLIT_NO_EMPTY) ?: [$fullName];
$firstName = $nameWords[1] ?? $nameWords[0];
?>
<div class="page-head mb-3">
    <h2 class="mb-1">Здравствуйте, <?= e($firstName) ?></h2>
    <div class="text-muted text-break"><?= e($user['company_name']) ?> · <?= e(companyRoleLabel($user['company_role'])) ?></div>
</div>

<!-- Навигация по разделам -->
<nav class="section-nav mb-4" aria-label="Разделы кабинета">
    <a href="#profile" class="btn btn-sm btn-outline-brand"><i class="bi bi-person-badge me-1"></i>Профиль</a>
    <a href="#stats" class="btn btn-sm btn-outline-brand"><i class="bi bi-bar-chart me-1"></i>Сводка</a>
    <a href="#categories" class="btn btn-sm btn-outline-brand"><i class="bi bi-tags me-1"></i>Категории <span class="badge text-bg-light border"><?= (int)$selectedCount ?></span></a>
    <a href="#deals" class="btn btn-sm btn-outline-brand"><i class="bi bi-file-earmark-text me-1"></i>Сделки <span class="badge text-bg-light border"><?= (int)$stats['all'] ?></span></a>
    <?php if ($stats['awaiting'] > 0): ?>
        <a href="#deals-awaiting" class="btn btn-sm btn-warning"><i class="bi bi-hourglass-split me-1"></i>Ждут решения <span class="badge text-bg-light"><?= (int)$stats['awaiting'] ?></span></a>
    <?php endif; ?>
    <span class="section-nav-sep"></span>
    <a href="/company#products" class="btn btn-sm btn-outline-secondary"><i class="bi bi-box-seam me-1"></i>Мои товары</a>
    <a href="/team" class="btn btn-sm btn-outline-secondary"><i class="bi bi-people me-1"></i>Команда</a>
    <a href="/#catalog" class="btn btn-sm btn-outline-secondary"><i class="bi bi-grid me-1"></i>Каталог</a>
</nav>

<!-- Пользователь и компания -->
<div id="profile" class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-body p-4">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="rounded-circle bg-brand text-white d-flex align-items-center justify-content-center fw-bold flex-shrink-0"
                         style="width:64px;height:64px;font-size:1.4rem;"><?= e(initials($fullName)) ?></div>
                    <div class="min-w-0">
                        <div class="text-muted small">Вы вошли как</div>
                        <h4 class="mb-0 text-break"><?= e($fullName) ?></h4>
                        <div class="mt-1">
                            <?php if ($user['position']): ?>
                                <span class="badge text-bg-brand"><?= e($user['position']) ?></span>
                            <?php else: ?>
                                <a href="/profile" class="badge text-bg-light border text-decoration-none">Должность не указана — добавить</a>
                            <?php endif; ?>
                            <span class="badge text-bg-secondary"><?= e(companyRoleLabel($user['company_role'])) ?></span>
                        </div>
                    </div>
                </div>
                <dl class="row mb-0 small">
                    <dt class="col-sm-4 text-muted fw-normal">Логин</dt>
                    <dd class="col-sm-8"><?= e($user['username']) ?></dd>
                    <dt class="col-sm-4 text-muted fw-normal">Email</dt>
                    <dd class="col-sm-8 text-break"><?= e($user['email']) ?></dd>
                    <dt class="col-sm-4 text-muted fw-normal">Телефон</dt>
                    <dd class="col-sm-8"><?= e($user['phone'] ?: '—') ?></dd>
                    <dt class="col-sm-4 text-muted fw-normal">В системе с</dt>
                    <dd class="col-sm-8 mb-0"><?= e(formatDate($user['created_at'])) ?></dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3 flex-wrap">
                    <div>
                        <div class="text-muted small">Компания</div>
                        <h4 class="mb-1 text-break"><i class="bi bi-building me-1 text-brand"></i><?= e($user['company_name']) ?></h4>
                        <?php if ($user['inn']): ?>
                            <span class="badge text-bg-light border">ИНН <?= e($user['inn']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex gap-2 flex-wrap justify-content-end">
                        <a href="/company" class="btn btn-outline-brand btn-sm text-nowrap"><i class="bi bi-box-seam me-1"></i>Товары</a>
                        <a href="/team" class="btn btn-outline-brand btn-sm text-nowrap"><i class="bi bi-people me-1"></i>Команда</a>
                    </div>
                </div>
                <dl class="row mb-0 small">
                    <dt class="col-sm-4 text-muted fw-normal">Адрес</dt>
                    <dd class="col-sm-8 text-break"><?= e($user['company_address'] ?: '—') ?></dd>
                    <dt class="col-sm-4 text-muted fw-normal">Телефон</dt>
                    <dd class="col-sm-8"><?= e($user['company_phone'] ?: '—') ?></dd>
                    <dt class="col-sm-4 text-muted fw-normal">Категорий</dt>
                    <dd class="col-sm-8"><?= (int)$selectedCount ?> из <?= count($categories) ?></dd>
                    <dt class="col-sm-4 text-muted fw-normal">Сотрудников</dt>
                    <dd class="col-sm-8 mb-0"><a href="/team" class="text-decoration-none"><?= (int)$memberCount ?></a></dd>
                </dl>
                <div class="mt-3">
                    <a href="/profile" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-pencil me-1"></i>Редактировать профиль
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Сводка по сделкам -->
<div id="stats" class="row g-3 mb-4">
    <div class="col-6 col-lg">
        <a href="#deals-progress" class="stat-link">
            <div class="card shadow-sm h-100 stat-card stat-warning">
                <div class="card-body">
                    <div class="text-muted small">В работе</div>
                    <div class="fs-3 fw-bold"><?= (int)$stats['progress'] ?></div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-lg">
        <a href="#deals-done" class="stat-link">
            <div class="card shadow-sm h-100 stat-card stat-success">
                <div class="card-body">
                    <div class="text-muted small">Завершённые</div>
                    <div class="fs-3 fw-bold"><?= (int)$stats['done'] ?></div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-lg">
        <a href="#deals-rejected" class="stat-link">
            <div class="card shadow-sm h-100 stat-card stat-danger">
                <div class="card-body">
                    <div class="text-muted small">Отклонённые</div>
                    <div class="fs-3 fw-bold"><?= (int)$stats['rejected'] ?></div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-lg">
        <a href="#deals-cancelled" class="stat-link">
            <div class="card shadow-sm h-100 stat-card stat-dark">
                <div class="card-body">
                    <div class="text-muted small">Отменённые</div>
                    <div class="fs-3 fw-bold"><?= (int)$stats['cancelled'] ?></div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-12 col-lg">
        <a href="#deals-done" class="stat-link">
            <div class="card shadow-sm h-100 stat-card stat-secondary">
                <div class="card-body">
                    <div class="text-muted small">Сумма завершённых</div>
                    <div class="fs-5 fw-bold text-nowrap"><?= e(formatPrice($stats['done_sum'])) ?></div>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Категории товаров -->
<div id="categories" class="card shadow-sm mb-4">
    <div class="card-body p-4">
        <h4 class="mb-1">Категории, в которых вы предоставляете товары</h4>
        <p class="text-muted small mb-3">
            Отметьте категории — только из них можно будет добавлять товары на странице компании.
            Уже добавленные товары при снятии категории не удаляются.
        </p>

        <?php if (!$categories): ?>
            <div class="text-muted">Список категорий пуст.</div>
        <?php else: ?>
            <form method="POST" action="/account/categories">
                <?= csrfField() ?>
                <div class="row g-2">
                    <?php foreach ($categories as $cat): ?>
                        <div class="col-md-6 col-xl-4">
                            <input type="checkbox" class="btn-check" name="categories[]"
                                   id="cat<?= (int)$cat['id'] ?>" value="<?= (int)$cat['id'] ?>"
                                   <?= (int)$cat['selected'] ? 'checked' : '' ?>
                                   <?= canCompany('manage_categories') ? '' : 'disabled' ?>>
                            <label class="btn btn-outline-brand w-100 text-start h-100 d-flex justify-content-between align-items-start gap-2"
                                   for="cat<?= (int)$cat['id'] ?>">
                                <span>
                                    <span class="fw-semibold d-block"><?= e($cat['name']) ?></span>
                                    <?php if ($cat['description']): ?>
                                        <span class="small d-block opacity-75"><?= e(mb_strimwidth($cat['description'], 0, 80, '…')) ?></span>
                                    <?php endif; ?>
                                </span>
                                <?php if ((int)$cat['products_cnt'] > 0): ?>
                                    <span class="badge text-bg-light border text-nowrap"><?= (int)$cat['products_cnt'] ?> тов.</span>
                                <?php endif; ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (canCompany('manage_categories')): ?>
                <button type="submit" class="btn btn-brand mt-3">
                    <i class="bi bi-check2 me-1"></i>Сохранить категории
                </button>
                <?php else: ?>
                <div class="small text-muted mt-3"><i class="bi bi-info-circle me-1"></i>У вас нет права выбирать категории. Обратитесь к директору компании.</div>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Сделки -->
<div id="deals" class="card shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <h4 class="mb-0">Сделки <span class="badge text-bg-secondary"><?= (int)$stats['all'] ?></span></h4>

            <div class="btn-group btn-group-sm" role="group" aria-label="Роль в сделке" id="roleFilter">
                <button type="button" class="btn btn-outline-brand active" data-role="all">Все</button>
                <button type="button" class="btn btn-outline-brand" data-role="buyer">
                    Я покупатель <span class="badge text-bg-light"><?= (int)$stats['buyer'] ?></span>
                </button>
                <button type="button" class="btn btn-outline-brand" data-role="seller">
                    Я продавец <span class="badge text-bg-light"><?= (int)$stats['seller'] ?></span>
                </button>
            </div>
        </div>

        <?php if ($stats['awaiting'] > 0): ?>
            <div class="alert alert-warning d-flex align-items-center justify-content-between flex-wrap gap-2 py-2">
                <span><i class="bi bi-hourglass-split me-1"></i>Ожидают вашего решения: <strong><?= (int)$stats['awaiting'] ?></strong></span>
                <a href="#deals-awaiting" class="alert-link">Показать</a>
            </div>
        <?php endif; ?>

        <ul class="nav nav-pills mb-3 gap-1" id="statusFilter">
            <?php foreach ($tabs as $key => $label): ?>
                <li class="nav-item">
                    <button type="button" class="nav-link py-1 px-3<?= $key === 'all' ? ' active' : '' ?>" data-group="<?= e($key) ?>">
                        <?= e($label) ?> <span class="badge text-bg-light"><?= (int)$stats[$key] ?></span>
                    </button>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if (!$deals): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-file-earmark-text fs-1 d-block mb-2"></i>
                У компании пока нет сделок.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>№</th>
                        <th>Дата</th>
                        <th>Ваша роль</th>
                        <th>Контрагент</th>
                        <th>Товар</th>
                        <th class="text-end">Кол-во</th>
                        <th class="text-end">Сумма</th>
                        <th>Статус</th>
                        <th>Оплата</th>
                        <th class="text-end">Действия</th>
                    </tr>
                    </thead>
                    <tbody id="dealsBody">
                    <?php foreach ($deals as $d): ?>
                        <tr data-role="<?= e($d['my_role']) ?>" data-group="<?= e($d['group']) ?>">
                            <td class="text-nowrap fw-semibold"><?= e($d['deal_number']) ?></td>
                            <td class="text-nowrap">
                                <?= e(formatDate($d['created_at'])) ?>
                                <?php if ($d['closed_at']): ?>
                                    <div class="small text-muted">закрыта <?= e(formatDate($d['closed_at'])) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($d['my_role'] === 'buyer'): ?>
                                    <span class="badge text-bg-primary"><i class="bi bi-cart me-1"></i>Покупатель</span>
                                <?php else: ?>
                                    <span class="badge text-bg-brand"><i class="bi bi-shop me-1"></i>Продавец</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= e($d['counterparty']) ?>
                                <?php if ($d['creator_name']): ?>
                                    <div class="small text-muted"><i class="bi bi-person me-1"></i>оформил: <?= e($d['creator_name']) ?><?= $d['creator_position'] ? ' · ' . e($d['creator_position']) : '' ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><?= e(dealItemsTitle((string)$d['product_name'], (int)($d['items_count'] ?? 1))) ?></div>
                                <?php if ($d['category_name']): ?>
                                    <div class="small text-muted"><?= e($d['category_name']) ?></div>
                                <?php endif; ?>
                                <?php if (count($d['items'] ?? []) > 1): ?>
                                    <details class="mt-1">
                                        <summary class="small text-brand">Состав заявки</summary>
                                        <ul class="small text-muted mb-0 ps-3 mt-1">
                                            <?php foreach ($d['items'] as $it): ?>
                                                <li>
                                                    <?= e($it['name']) ?> —
                                                    <?= number_format((int)$it['quantity'], 0, ',', ' ') ?> <?= e($it['unit']) ?>
                                                    × <?= e(formatPrice($it['unit_price'])) ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </details>
                                <?php endif; ?>
                                <?php if ($d['insp_meta']): ?>
                                    <div class="small mt-1">
                                        <i class="bi bi-patch-check me-1 text-brand"></i>Проверка:
                                        <span class="badge text-bg-<?= e($d['insp_meta']['badge']) ?>"><?= e($d['insp_meta']['label']) ?></span>
                                        <?php if ($d['insp_approved'] && $d['insp_verdict']): ?>
                                            <span class="text-muted">· <?= e(inspectionVerdictMeta($d['insp_verdict'])['label']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php $nx = inspectionNextStep($d['my_role'], $d['insp_status'], $d['insp_fee_status'] ?? 'unpaid', $d['insp_visit_status'] ?? 'none', $d['insp_visit_by'] ?? null); ?>
                                    <div class="next-hint<?= $nx['action'] ? ' is-action' : '' ?>"><?= $nx['action'] ? '<i class="bi bi-arrow-right-circle-fill me-1"></i>' : '' ?><?= e($nx['text']) ?></div>
                                    <?php if ($d['insp_status'] === 'assigned' && !empty($d['insp_visit_at'])): ?>
                                        <div class="small mt-1">
                                            <i class="bi bi-calendar-event me-1"></i><?= e(formatDateTime($d['insp_visit_at'])) ?>
                                            <span class="badge text-bg-<?= e($d['visit_meta']['badge']) ?>"><?= ($d['insp_visit_status'] ?? '') === 'confirmed' ? 'согласовано' : 'на согласовании' ?></span>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <?php if ((int)($d['items_count'] ?? 1) > 1): ?>
                                    <span class="badge text-bg-light border"><?= (int)$d['items_count'] ?> поз.</span>
                                <?php else: ?>
                                    <?= number_format((int)$d['quantity'], 0, ',', ' ') ?> <?= e($d['unit']) ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <div class="fw-semibold"><?= e(formatPrice($d['total_amount'])) ?></div>
                                <?php if ($d['payment_terms']): ?>
                                    <div class="small text-muted"><?= e($d['payment_terms']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge text-bg-<?= e($d['status_badge']) ?>"><?= e($d['status_label']) ?></span>
                                <?php if ($d['status'] === 'in_progress' && $d['approved']): ?>
                                    <div class="small text-muted mt-1"><i class="bi bi-check2 text-success"></i> одобрена покупателем</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge text-bg-<?= e($d['pay_badge']) ?>"><?= e($d['pay_label']) ?></span>
                            </td>
                            <td class="text-end">
                                <div class="d-flex flex-wrap gap-1 justify-content-end align-items-center">
                                <?php if ($d['can_order_insp']): ?>
                                    <button type="button" class="btn btn-sm btn-outline-brand"
                                            data-bs-toggle="modal" data-bs-target="#inspOrderModal"
                                            data-deal-id="<?= (int)$d['id'] ?>" data-deal-number="<?= e($d['deal_number']) ?>"
                                            data-fee="<?= e(formatPrice($d['insp_fee_preview'])) ?>">
                                        <i class="bi bi-patch-check me-1"></i>Заказать проверку
                                    </button>
                                <?php endif; ?>
                                <?php if ($d['can_pay_insp']): ?>
                                    <button type="button" class="btn btn-sm btn-outline-brand"
                                            data-bs-toggle="modal" data-bs-target="#payModal"
                                            data-purpose="Оплата проверки качества по сделке <?= e($d['deal_number']) ?>"
                                            data-amount="<?= e(formatPrice($d['insp_fee'])) ?>">
                                        <i class="bi bi-credit-card me-1"></i>Оплатить проверку
                                    </button>
                                <?php endif; ?>
                                <?php if ($d['can_visit_insp']): ?>
                                    <button type="button" class="btn btn-sm <?= ($d['insp_visit_status'] ?? '') === 'proposed' && ($d['insp_visit_by'] ?? '') === 'inspector' ? 'btn-brand' : 'btn-outline-brand' ?>"
                                            data-bs-toggle="modal" data-bs-target="#visitModal"
                                            data-id="<?= (int)$d['insp_id'] ?>" data-deal-number="<?= e($d['deal_number']) ?>"
                                            data-visit-status="<?= e($d['insp_visit_status'] ?? 'none') ?>"
                                            data-visit-by="<?= e($d['insp_visit_by'] ?? '') ?>"
                                            data-visit-at="<?= !empty($d['insp_visit_at']) ? e(formatDateTime($d['insp_visit_at'])) : '' ?>"
                                            data-visit-note="<?= e($d['insp_visit_note'] ?? '') ?>"
                                            data-escort-name="<?= e($d['insp_escort_name'] ?? '') ?>"
                                            data-escort-number="<?= e($d['insp_escort_number'] ?? '') ?>"
                                            data-escort-phone="<?= e($d['insp_escort_phone'] ?? '') ?>">
                                        <i class="bi bi-calendar-check me-1"></i>Согласовать проверку
                                    </button>
                                <?php endif; ?>
                                <?php if ($d['insp_id']): ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                            data-bs-toggle="modal" data-bs-target="#inspInfoModal"
                                            data-id="<?= (int)$d['insp_id'] ?>"
                                            data-deal-number="<?= e($d['deal_number']) ?>"
                                            data-status="<?= e($d['insp_meta']['label']) ?>"
                                            data-buyer="<?= $d['my_role'] === 'buyer' ? '1' : '' ?>"
                                            data-fee="<?= $d['my_role'] === 'buyer' ? e(formatPrice($d['insp_fee'])) : '' ?>"
                                            data-fee-status="<?= $d['my_role'] === 'buyer' ? e(inspectionFeeMeta($d['insp_fee_status'])['label']) : '' ?>"
                                            data-note="<?= $d['my_role'] === 'buyer' ? e($d['insp_note']) : '' ?>"
                                            data-inspector="<?= $d['insp_approved'] ? e($d['insp_inspector']) : '' ?>"
                                            data-approved="<?= $d['insp_approved'] ? '1' : '' ?>"
                                            data-verdict="<?= $d['insp_approved'] ? e(inspectionVerdictMeta($d['insp_verdict'])['label']) : '' ?>"
                                            data-checked="<?= $d['insp_approved'] ? e(formatDate($d['insp_checked_on'])) : '' ?>"
                                            data-summary="<?= $d['insp_approved'] ? e($d['insp_summary']) : '' ?>"
                                            data-details="<?= $d['insp_approved'] ? e($d['insp_details']) : '' ?>"
                                            data-links="<?= $d['insp_approved'] ? e($d['insp_links']) : '' ?>"
                                            data-visit="<?= !empty($d['insp_visit_at']) ? e(formatDateTime($d['insp_visit_at'])) . ' — ' . ($d['insp_visit_status'] === 'confirmed' ? 'согласовано' : 'на согласовании') : '' ?>"
                                            data-escort="<?= !empty($d['insp_escort_name']) ? e($d['insp_escort_name'] . ', № ' . $d['insp_escort_number'] . (!empty($d['insp_escort_phone']) ? ', ' . $d['insp_escort_phone'] : '')) : '' ?>"
                                            data-steps="<?= e(implode('', array_map(fn($f) => $f ? '1' : '0', inspectionStepFlags($d['insp_status'], $d['insp_fee_status'] ?? 'unpaid', $d['insp_visit_status'] ?? 'none')))) ?>"
                                            data-cancelled="<?= $d['insp_status'] === 'cancelled' ? '1' : '' ?>"
                                            data-next="<?= e($nx['text']) ?>"
                                            data-can-cancel="<?= $d['can_cancel_insp'] ? '1' : '' ?>">
                                        <i class="bi bi-clipboard-data me-1"></i>Проверка
                                    </button>
                                <?php endif; ?>
                                <?php if ($d['can_pay']): ?>
                                    <button type="button" class="btn btn-sm btn-outline-brand"
                                            data-bs-toggle="modal" data-bs-target="#payModal"
                                            data-deal-number="<?= e($d['deal_number']) ?>"
                                            data-amount="<?= e(formatPrice($d['total_amount'])) ?>">
                                        <i class="bi bi-credit-card me-1"></i>Как оплатить
                                    </button>
                                <?php endif; ?>
                                <?php if ($d['can_repeat']): ?>
                                    <form method="POST" action="/cart/repeat" class="d-inline">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="deal_id" value="<?= (int)$d['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-brand"
                                                title="Добавить все позиции этой сделки в новую заявку">
                                            <i class="bi bi-arrow-repeat me-1"></i>Повторить
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($d['can_decide']): ?>
                                    <button type="button" class="btn btn-sm btn-brand"
                                            data-bs-toggle="modal" data-bs-target="#decisionModal"
                                            data-decision="approve" data-deal-id="<?= (int)$d['id'] ?>"
                                            data-deal-number="<?= e($d['deal_number']) ?>">
                                        <i class="bi bi-check2 me-1"></i>Одобрить
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                            data-bs-toggle="modal" data-bs-target="#decisionModal"
                                            data-decision="reject" data-deal-id="<?= (int)$d['id'] ?>"
                                            data-deal-number="<?= e($d['deal_number']) ?>">
                                        <i class="bi bi-x-lg me-1"></i>Отклонить
                                    </button>
                                <?php elseif ($d['status'] === 'in_progress'): ?>
                                    <?php if ($d['approved']): ?>
                                        <span class="small text-muted">Ждёт подтверждения администратора</span>
                                    <?php elseif ($d['my_role'] === 'seller'): ?>
                                        <span class="small text-muted">Ждёт решения покупателя</span>
                                    <?php elseif ($d['my_role'] === 'buyer'): ?>
                                        <span class="small text-muted">Решение принимает директор или уполномоченный сотрудник</span>
                                    <?php endif; ?>
                                <?php elseif (!$d['can_pay'] && !$d['can_repeat'] && !$d['insp_id'] && !$d['can_order_insp']): ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr id="dealsEmpty" class="d-none">
                        <td colspan="10" class="text-center text-muted py-4">Нет сделок по выбранным фильтрам.</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Подтверждение решения покупателя -->
<div class="modal fade" id="decisionModal" tabindex="-1" aria-labelledby="decisionTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="/deals/decision" class="modal-content">
            <?= csrfField() ?>
            <input type="hidden" name="deal_id" id="decisionDealId" value="">
            <input type="hidden" name="decision" id="decisionValue" value="">
            <div class="modal-header">
                <h5 class="modal-title" id="decisionTitle">Решение по сделке</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body" id="decisionText"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="submit" class="btn btn-brand" id="decisionConfirm">Подтвердить</button>
            </div>
        </form>
    </div>
</div>

<!-- Продавец: согласование времени проверки и сопровождающий -->
<div class="modal fade" id="visitModal" tabindex="-1" aria-labelledby="visitTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="/inspections/visit" class="modal-content" id="visitForm">
            <?= csrfField() ?>
            <input type="hidden" name="inspection_id" id="visitInspId" value="">
            <input type="hidden" name="mode" id="visitMode" value="confirm">
            <div class="modal-header">
                <h5 class="modal-title" id="visitTitle">Согласование проверки</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-light border small" id="visitState"></div>

                <div class="fw-semibold mb-2">Сопровождающий сотрудник</div>
                <div class="row g-2 mb-3">
                    <div class="col-12">
                        <label class="form-label small mb-1" for="escortName">ФИО <span class="text-danger">*</span></label>
                        <input type="text" name="escort_name" id="escortName" class="form-control" maxlength="150" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small mb-1" for="escortNumber">Номер сотрудника <span class="text-danger">*</span></label>
                        <input type="text" name="escort_number" id="escortNumber" class="form-control" maxlength="50" required placeholder="табельный номер / пропуск">
                    </div>
                    <div class="col-6">
                        <label class="form-label small mb-1" for="escortPhone">Телефон</label>
                        <input type="text" name="escort_phone" id="escortPhone" class="form-control" maxlength="30">
                    </div>
                </div>

                <div id="visitProposeWrap" class="d-none mb-2">
                    <label class="form-label small mb-1" for="visitAt">Ваше время проверки <span class="text-danger">*</span></label>
                    <input type="datetime-local" name="visit_at" id="visitAt" class="form-control"
                           min="<?= e(date('Y-m-d\TH:i')) ?>" max="<?= e(date('Y-m-d\TH:i', strtotime('+60 days'))) ?>">
                </div>
                <label class="form-label small mb-1" for="visitNote">Комментарий проверяющему</label>
                <input type="text" name="visit_note" id="visitNote" class="form-control" maxlength="300" placeholder="Например: вход через проходную №2, нужен паспорт">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary me-auto" data-bs-dismiss="modal">Закрыть</button>
                <button type="submit" class="btn btn-outline-brand" id="visitProposeBtn">Предложить другое время</button>
                <button type="submit" class="btn btn-success" id="visitConfirmBtn">Подтвердить время</button>
            </div>
        </form>
    </div>
</div>

<!-- Заказ проверки качества по сделке -->
<div class="modal fade" id="inspOrderModal" tabindex="-1" aria-labelledby="inspOrderTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="/inspections/order" class="modal-content">
            <?= csrfField() ?>
            <input type="hidden" name="deal_id" id="inspOrderDealId" value="">
            <div class="modal-header">
                <h5 class="modal-title" id="inspOrderTitle">Заказать проверку</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">
                    Проверяющий портала проверит товар или услугу по сделке и пришлёт отчёт, который подтвердит администратор.
                    Стоимость проверки оплачивается отдельно, сверх суммы сделки: <strong id="inspOrderFee"></strong>.
                    Пока отчёт не подтверждён, сделку завершить нельзя.
                </p>
                <label class="form-label" for="inspOrderNote">Что именно проверить</label>
                <textarea name="note" id="inspOrderNote" class="form-control" rows="3" maxlength="500"
                          placeholder="Комплектность, соответствие описанию, качество исполнения, сроки…"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="submit" class="btn btn-brand">Заказать проверку</button>
            </div>
        </form>
    </div>
</div>

<!-- Проверка качества: статус и результат -->
<div class="modal fade" id="inspInfoModal" tabindex="-1" aria-labelledby="inspInfoTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="inspInfoTitle">Проверка качества</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <ol class="insp-steps" id="inspInfoSteps"></ol>
                <div class="next-step mb-3"><i class="bi bi-info-circle"></i><span id="inspInfoNext"></span></div>
                <dl class="row small mb-3">
                    <dt class="col-5 text-muted fw-normal">Статус</dt>
                    <dd class="col-7" id="inspInfoStatus"></dd>
                    <dt class="col-5 text-muted fw-normal visit-row">Время проверки</dt>
                    <dd class="col-7 visit-row" id="inspInfoVisit"></dd>
                    <dt class="col-5 text-muted fw-normal visit-row">Сопровождает</dt>
                    <dd class="col-7 visit-row" id="inspInfoEscort"></dd>
                    <dt class="col-5 text-muted fw-normal buyer-only">Доплата</dt>
                    <dd class="col-7 buyer-only" id="inspInfoFee"></dd>
                    <dt class="col-5 text-muted fw-normal buyer-only">Что проверить</dt>
                    <dd class="col-7 buyer-only" id="inspInfoNote" style="white-space: pre-wrap"></dd>
                </dl>
                <div id="inspInfoReport" class="d-none">
                    <hr>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="fw-semibold" id="inspInfoVerdict"></span>
                        <span class="text-muted small" id="inspInfoMeta"></span>
                    </div>
                    <p class="mb-2" id="inspInfoSummary" style="white-space: pre-wrap"></p>
                    <p class="mb-2" id="inspInfoDetails" style="white-space: pre-wrap"></p>
                    <ul class="mb-0 small" id="inspInfoLinks"></ul>
                </div>
                <div id="inspInfoPending" class="small text-muted">Результат проверки появится здесь после того, как отчёт подтвердит администратор.</div>
            </div>
            <div class="modal-footer">
                <form method="POST" action="/inspections/cancel" class="me-auto d-none" id="inspCancelForm"
                      onsubmit="return confirm('Отменить проверку? Если доплата уже получена, администратор вернёт её.');">
                    <?= csrfField() ?>
                    <input type="hidden" name="inspection_id" id="inspCancelId" value="">
                    <button type="submit" class="btn btn-outline-danger">Отменить проверку</button>
                </form>
                <button type="button" class="btn btn-brand" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>

<!-- Оплата через администратора платформы -->
<div class="modal fade" id="payModal" tabindex="-1" aria-labelledby="payTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="payTitle">Оплата сделки</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Платежи проходят через администратора портала. Переведите сумму на счёт платформы:</p>
                <dl class="row small mb-3">
                    <?php foreach ($requisites as $label => $value): ?>
                        <dt class="col-5 text-muted fw-normal"><?= e($label) ?></dt>
                        <dd class="col-7"><?= e($value) ?></dd>
                    <?php endforeach; ?>
                    <dt class="col-5 text-muted fw-normal">Сумма</dt>
                    <dd class="col-7 fw-semibold" id="payAmount"></dd>
                    <dt class="col-5 text-muted fw-normal">Назначение</dt>
                    <dd class="col-7" id="payPurpose"></dd>
                </dl>
                <div class="alert alert-light border small mb-0">
                    После поступления денег администратор подтвердит оплату — статус сделки изменится на «Оплачено».
                    Продавцу средства переводятся только после подтверждения сделки.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-brand" data-bs-dismiss="modal">Понятно</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var orderModal = document.getElementById('inspOrderModal');
    if (orderModal) {
        orderModal.addEventListener('show.bs.modal', function (ev) {
            var b = ev.relatedTarget;
            if (!b) return;
            document.getElementById('inspOrderDealId').value = b.dataset.dealId;
            document.getElementById('inspOrderTitle').textContent = 'Заказать проверку · ' + b.dataset.dealNumber;
            document.getElementById('inspOrderFee').textContent = b.dataset.fee;
            document.getElementById('inspOrderNote').value = '';
        });
    }
    var visitModal = document.getElementById('visitModal');
    if (visitModal) {
        var form = document.getElementById('visitForm');
        var wrap = document.getElementById('visitProposeWrap');
        var at = document.getElementById('visitAt');
        var mode = document.getElementById('visitMode');
        var confirmBtn = document.getElementById('visitConfirmBtn');
        var proposeBtn = document.getElementById('visitProposeBtn');
        visitModal.addEventListener('show.bs.modal', function (ev) {
            var b = ev.relatedTarget;
            if (!b) return;
            var d = b.dataset;
            document.getElementById('visitInspId').value = d.id;
            document.getElementById('visitTitle').textContent = 'Согласование проверки · ' + d.dealNumber;
            document.getElementById('escortName').value = d.escortName || '';
            document.getElementById('escortNumber').value = d.escortNumber || '';
            document.getElementById('escortPhone').value = d.escortPhone || '';
            document.getElementById('visitNote').value = '';
            at.value = '';
            var canConfirm = d.visitStatus === 'proposed' && d.visitBy === 'inspector';
            var state = 'Проверяющий пока не предложил время. Вы можете предложить своё.';
            if (canConfirm) state = 'Проверяющий предлагает: ' + d.visitAt + '. Подтвердите время или предложите другое.';
            else if (d.visitStatus === 'proposed') state = 'Вы предложили: ' + d.visitAt + '. Ждём ответа проверяющего.';
            else if (d.visitStatus === 'confirmed') state = 'Время согласовано: ' + d.visitAt + '. Можно предложить другое.';
            document.getElementById('visitState').textContent = state;
            confirmBtn.classList.toggle('d-none', !canConfirm);
            wrap.classList.toggle('d-none', canConfirm);
            at.required = !canConfirm;
            mode.value = canConfirm ? 'confirm' : 'propose';
            proposeBtn.textContent = canConfirm ? 'Предложить другое время' : 'Предложить время';
        });
        // «Предложить другое время» при наличии предложения проверяющего: раскрываем поле выбора времени
        proposeBtn.addEventListener('click', function (e) {
            if (mode.value === 'confirm') {
                e.preventDefault();
                mode.value = 'propose';
                wrap.classList.remove('d-none');
                at.required = true;
                confirmBtn.classList.add('d-none');
                proposeBtn.textContent = 'Отправить новое время';
            }
        });
    }
    var infoModal = document.getElementById('inspInfoModal');
    if (infoModal) {
        infoModal.addEventListener('show.bs.modal', function (ev) {
            var b = ev.relatedTarget;
            if (!b) return;
            var d = b.dataset;
            var $ = function (id) { return document.getElementById(id); };
            $('inspInfoTitle').textContent = 'Проверка качества · ' + d.dealNumber;
            $('inspInfoStatus').textContent = d.status;
            $('inspInfoNext').textContent = d.next || '';
            var labels = <?= json_encode(inspectionStepLabels(), JSON_UNESCAPED_UNICODE) ?>;
            var bits = (d.steps || '').split('');
            var cur = bits.indexOf('0');
            var ol = $('inspInfoSteps');
            ol.className = 'insp-steps' + (d.cancelled ? ' is-cancelled' : '');
            ol.textContent = '';
            labels.forEach(function (label, n) {
                var st = d.cancelled ? 'off' : (bits[n] === '1' ? 'done' : (n === cur ? 'now' : 'todo'));
                var li = document.createElement('li');
                li.className = 'insp-step is-' + st;
                var dot = document.createElement('span');
                dot.className = 'insp-dot';
                if (st === 'done') { var ic = document.createElement('i'); ic.className = 'bi bi-check-lg'; dot.appendChild(ic); }
                else { dot.textContent = n + 1; }
                var lb = document.createElement('span');
                lb.className = 'insp-label';
                lb.textContent = label;
                li.appendChild(dot); li.appendChild(lb);
                ol.appendChild(li);
            });
            [].forEach.call(infoModal.querySelectorAll('.buyer-only'), function (el) { el.classList.toggle('d-none', !d.buyer); });
            $('inspInfoFee').textContent = d.fee + (d.feeStatus ? ' · ' + d.feeStatus.toLowerCase() : '');
            $('inspInfoNote').textContent = d.note || '—';
            $('inspInfoVisit').textContent = d.visit || '—';
            $('inspInfoEscort').textContent = d.escort || '—';
            var approved = !!d.approved;
            $('inspInfoReport').classList.toggle('d-none', !approved);
            $('inspInfoPending').classList.toggle('d-none', approved);
            if (approved) {
                $('inspInfoVerdict').textContent = d.verdict;
                $('inspInfoMeta').textContent = 'проверка от ' + d.checked + (d.inspector ? ' · ' + d.inspector : '');
                $('inspInfoSummary').textContent = d.summary;
                $('inspInfoDetails').textContent = d.details;
                var ul = $('inspInfoLinks');
                ul.textContent = '';
                (d.links || '').split(/\s+/).forEach(function (u) {
                    if (!/^https?:\/\//i.test(u)) return;
                    var li = document.createElement('li');
                    var a = document.createElement('a');
                    a.href = u; a.textContent = u; a.target = '_blank'; a.rel = 'noopener noreferrer nofollow';
                    li.appendChild(a); ul.appendChild(li);
                });
            }
            $('inspCancelForm').classList.toggle('d-none', !d.canCancel);
            $('inspCancelId').value = d.id;
        });
    }
    var payModal = document.getElementById('payModal');
    if (payModal) {
        payModal.addEventListener('show.bs.modal', function (ev) {
            var btn = ev.relatedTarget;
            if (!btn) return;
            document.getElementById('payAmount').textContent = btn.dataset.amount;
            document.getElementById('payPurpose').textContent = btn.dataset.purpose || ('Оплата по сделке ' + btn.dataset.dealNumber);
        });
    }
    var modal = document.getElementById('decisionModal');
    if (modal) {
        modal.addEventListener('show.bs.modal', function (ev) {
            var btn = ev.relatedTarget;
            if (!btn) return;
            var approve = btn.dataset.decision === 'approve';
            var number = btn.dataset.dealNumber;
            document.getElementById('decisionDealId').value = btn.dataset.dealId;
            document.getElementById('decisionValue').value = btn.dataset.decision;
            document.getElementById('decisionTitle').textContent = approve ? 'Одобрить сделку ' + number : 'Отклонить сделку ' + number;
            document.getElementById('decisionText').textContent = approve
                ? 'Вы одобряете сделку. Завершит её администратор после проверки оплаты. Отменить одобрение нельзя.'
                : 'Сделка будет закрыта со статусом «Отклонён». Изменить решение после подтверждения нельзя.';
            var ok = document.getElementById('decisionConfirm');
            ok.textContent = approve ? 'Одобрить' : 'Отклонить';
            ok.className = 'btn ' + (approve ? 'btn-brand' : 'btn-danger');
        });
    }
})();
</script>

<script>
(function () {
    var body = document.getElementById('dealsBody');
    if (!body) return;
    var role = 'all', group = 'all';
    var rows = body.querySelectorAll('tr[data-role]');
    var empty = document.getElementById('dealsEmpty');

    function apply() {
        var shown = 0;
        rows.forEach(function (tr) {
            var ok = (role === 'all' || tr.dataset.role === role) &&
                     (group === 'all' || tr.dataset.group === group);
            tr.classList.toggle('d-none', !ok);
            if (ok) shown++;
        });
        empty.classList.toggle('d-none', shown !== 0);
    }

    function bind(containerId, attr, setter) {
        var box = document.getElementById(containerId);
        box.addEventListener('click', function (ev) {
            var btn = ev.target.closest('button[data-' + attr + ']');
            if (!btn) return;
            box.querySelectorAll('button').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            setter(btn.dataset[attr]);
            apply();
        });
    }

    bind('roleFilter', 'role', function (v) { role = v; });
    bind('statusFilter', 'group', function (v) { group = v; });

    // Якоря вида #deals-progress / #deals-done / #deals-rejected / #deals-awaiting / #deals-all
    function applyHash() {
        var m = /^#deals(?:-(all|progress|done|rejected|cancelled|awaiting))?$/.exec(location.hash);
        if (!m) return;
        var key = m[1];
        if (key) {
            var r = key === 'awaiting' ? 'buyer' : 'all';
            var g = (key === 'awaiting') ? 'progress' : key;
            document.querySelector('#roleFilter [data-role="' + r + '"]').click();
            document.querySelector('#statusFilter [data-group="' + g + '"]').click();
        }
        var section = document.getElementById('deals');
        if (section) section.scrollIntoView({ block: 'start' });
    }
    [].forEach.call(document.querySelectorAll('a[href^="#deals"]'), function (a) {
        a.addEventListener('click', function (ev) {
            ev.preventDefault();
            history.pushState(null, '', a.getAttribute('href'));
            applyHash();
        });
    });
    window.addEventListener('hashchange', applyHash);
    applyHash();
})();
</script>

<script>
// Подсветка текущего раздела в навигации
(function () {
    var links = [].slice.call(document.querySelectorAll('.section-nav a[href^="#"]'));
    if (!links.length || !('IntersectionObserver' in window)) return;
    var ids = ['profile', 'stats', 'categories', 'deals'];
    function setActive(id) {
        links.forEach(function (a) { a.classList.toggle('active', a.getAttribute('href') === '#' + id); });
    }
    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) { if (en.isIntersecting) setActive(en.target.id); });
    }, { rootMargin: '-15% 0px -65% 0px' });
    ids.forEach(function (id) {
        var el = document.getElementById(id);
        if (el) io.observe(el);
    });
})();
</script>

<?php require BASE_PATH . '/views/layout/footer.php'; ?>
