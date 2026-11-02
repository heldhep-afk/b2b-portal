<?php
require BASE_PATH . '/views/layout/header.php';

$val = fn(string $k): string => e($old[$k] ?? '');
$err = fn(string $k): string => isset($errors[$k]) ? '<div class="invalid-feedback d-block">' . e($errors[$k]) . '</div>' : '';
$cls = fn(string $k): string => isset($errors[$k]) ? ' is-invalid' : '';
$vm = inspectionVerdictMeta($task['verdict']);
$links = preg_split('/\s+/u', (string)$task['report_links'], -1, PREG_SPLIT_NO_EMPTY) ?: [];
$visitMeta = visitStatusMeta($task['visit_status'], $task['visit_proposed_by']);
$visitConfirmed = $task['visit_status'] === 'confirmed';
$sellerProposed = $task['visit_status'] === 'proposed' && $task['visit_proposed_by'] === 'seller';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/inspector">Кабинет проверяющего</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= e($task['deal_number']) ?></li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
    <div>
        <h2 class="mb-1">Задание по сделке <?= e($task['deal_number']) ?></h2>
        <span class="badge text-bg-<?= e($statusMeta['badge']) ?>"><?= e($statusMeta['label']) ?></span>
        <?php if ($task['status'] === 'approved'): ?>
            <span class="badge text-bg-<?= $task['reward_paid_at'] ? 'success' : 'warning' ?>">
                <?= $task['reward_paid_at'] ? 'Вознаграждение выплачено' : 'Вознаграждение ждёт выплаты' ?>
            </span>
        <?php endif; ?>
    </div>
    <div class="text-end">
        <div class="text-muted small">Вознаграждение</div>
        <div class="fs-4 fw-bold text-brand text-nowrap"><?= e(formatPrice($task['reward'])) ?></div>
    </div>
</div>

<?php
$stepStatus = $task['status']; $stepFee = $task['fee_status']; $stepVisit = $task['visit_status'];
require BASE_PATH . '/views/partials/inspection_steps.php';
if ($task['mine'] || $task['status'] === 'requested') {
    $next = inspectionNextStep('inspector', $task['status'], $task['fee_status'], $task['visit_status'], $task['visit_proposed_by'], $task['reward_paid_at'] !== null);
    echo '<div class="next-step' . ($next['action'] ? ' is-action' : '') . ' mb-3"><i class="bi ' . ($next['action'] ? 'bi-arrow-right-circle-fill' : 'bi-info-circle') . '"></i><span>' . e($next['text']) . '</span></div>';
}
?>

<?php if ($task['status'] === 'assigned' && (int)$task['revision_count'] > 0 && $task['admin_comment']): ?>
    <div class="alert alert-danger"><i class="bi bi-arrow-return-left me-1"></i>
        <strong>Администратор вернул отчёт на доработку:</strong> <?= e($task['admin_comment']) ?>
    </div>
<?php elseif ($task['status'] === 'submitted'): ?>
    <div class="alert alert-info"><i class="bi bi-hourglass-split me-1"></i>Отчёт отправлен и ждёт подтверждения администратора.</div>
<?php elseif ($task['status'] === 'approved' && $task['admin_comment']): ?>
    <div class="alert alert-success"><i class="bi bi-check2-circle me-1"></i><strong>Комментарий администратора:</strong> <?= e($task['admin_comment']) ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-7">
        <?php if ($canReport): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-body p-4">
                    <h5 class="mb-2"><i class="bi bi-calendar-event text-brand me-1"></i>Согласование времени проверки</h5>
                    <div class="mb-3">
                        <span class="badge text-bg-<?= e($visitMeta['badge']) ?>"><?= e($visitMeta['label']) ?></span>
                        <?php if ($task['visit_at']): ?>
                            <span class="fs-5 fw-semibold ms-2"><?= e(formatDateTime($task['visit_at'])) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($task['visit_note']): ?>
                        <div class="small text-muted mb-2">Комментарий: <?= e($task['visit_note']) ?></div>
                    <?php endif; ?>
                    <?php if ($task['escort_name']): ?>
                        <div class="rounded bg-light border p-3 small mb-3">
                            <div class="fw-semibold mb-1"><i class="bi bi-person-badge me-1"></i>Вас сопровождает сотрудник продавца</div>
                            <?= e($task['escort_name']) ?> · номер сотрудника <strong><?= e($task['escort_number']) ?></strong>
                            <?= $task['escort_phone'] ? ' · ' . e($task['escort_phone']) : '' ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($sellerProposed): ?>
                        <form method="POST" action="/inspector/visit" class="mb-3">
                            <?= csrfField() ?>
                            <input type="hidden" name="inspection_id" value="<?= (int)$task['id'] ?>">
                            <input type="hidden" name="mode" value="confirm">
                            <button type="submit" class="btn btn-success"><i class="bi bi-check2-circle me-1"></i>Согласиться с временем продавца</button>
                        </form>
                    <?php endif; ?>

                    <form method="POST" action="/inspector/visit">
                        <?= csrfField() ?>
                        <input type="hidden" name="inspection_id" value="<?= (int)$task['id'] ?>">
                        <input type="hidden" name="mode" value="propose">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-5">
                                <label class="form-label small mb-1" for="visit_at"><?= $visitConfirmed || $sellerProposed ? 'Предложить другое время' : 'Предложить время проверки' ?></label>
                                <input type="datetime-local" name="visit_at" id="visit_at" class="form-control" required
                                       min="<?= e(date('Y-m-d\TH:i')) ?>" max="<?= e(date('Y-m-d\TH:i', strtotime('+60 days'))) ?>">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small mb-1" for="visit_note">Комментарий</label>
                                <input type="text" name="visit_note" id="visit_note" class="form-control" maxlength="300" placeholder="Например: нужен доступ на склад">
                            </div>
                            <div class="col-md-2 d-grid"><button type="submit" class="btn btn-outline-brand">Отправить</button></div>
                        </div>
                    </form>
                    <?php if (!$visitConfirmed): ?>
                        <p class="text-muted small mt-3 mb-0">Отчёт можно отправить после того, как продавец подтвердит время и назовёт сопровождающего сотрудника.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php elseif ($task['mine'] && $task['visit_at']): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-body p-4 small">
                    <h5 class="mb-2"><i class="bi bi-calendar-event text-brand me-1"></i>Проверка на месте</h5>
                    <?= e(formatDateTime($task['visit_at'])) ?>
                    <?php if ($task['escort_name']): ?> · сопровождал: <?= e($task['escort_name']) ?> (№ <?= e($task['escort_number']) ?>)<?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$task['mine']): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-body p-4">
                    <h5 class="mb-2">Свободное задание</h5>
                    <?php if ($task['fee_status'] === 'paid'): ?>
                        <p class="text-muted">Доплата за проверку получена. Возьмите задание, чтобы выполнить проверку и отправить отчёт.</p>
                        <form method="POST" action="/inspector/take">
                            <?= csrfField() ?>
                            <input type="hidden" name="inspection_id" value="<?= (int)$task['id'] ?>">
                            <button type="submit" class="btn btn-brand"><i class="bi bi-hand-index-thumb me-1"></i>Взять задание</button>
                        </form>
                    <?php else: ?>
                        <p class="text-muted mb-0">Заказчик ещё не оплатил проверку. Взять задание можно после того, как администратор подтвердит оплату.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php elseif ($canReport && $visitConfirmed): ?>
            <form method="POST" action="/inspector/report" class="card shadow-sm mb-4" novalidate>
                <div class="card-body p-4">
                    <?= csrfField() ?>
                    <input type="hidden" name="inspection_id" value="<?= (int)$task['id'] ?>">
                    <h5 class="mb-3">Отчёт о проверке</h5>

                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label" for="verdict">Итог проверки <span class="text-danger">*</span></label>
                            <select name="verdict" id="verdict" class="form-select<?= $cls('verdict') ?>" required>
                                <option value="">Выберите…</option>
                                <?php foreach (['ok', 'remarks', 'fail'] as $v): ?>
                                    <option value="<?= e($v) ?>" <?= ($old['verdict'] ?? '') === $v ? 'selected' : '' ?>><?= e(inspectionVerdictMeta($v)['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= $err('verdict') ?>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="checked_on">Дата проверки <span class="text-danger">*</span></label>
                            <input type="date" name="checked_on" id="checked_on" class="form-control<?= $cls('checked_on') ?>"
                                   max="<?= e(date('Y-m-d')) ?>" value="<?= $val('checked_on') ?>" required>
                            <?= $err('checked_on') ?>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="report_summary">Краткий вывод <span class="text-danger">*</span></label>
                            <textarea name="report_summary" id="report_summary" class="form-control<?= $cls('report_summary') ?>" rows="3"
                                      maxlength="1000" required
                                      placeholder="Соответствует ли товар или услуга заказу, что обнаружено"><?= $val('report_summary') ?></textarea>
                            <?= $err('report_summary') ?>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="report_details">Подробности</label>
                            <textarea name="report_details" id="report_details" class="form-control<?= $cls('report_details') ?>" rows="5"
                                      maxlength="5000"
                                      placeholder="Что именно проверено, какие замечания, как это подтверждается"><?= $val('report_details') ?></textarea>
                            <?= $err('report_details') ?>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="report_links">Ссылки на фото и документы</label>
                            <textarea name="report_links" id="report_links" class="form-control<?= $cls('report_links') ?>" rows="2"
                                      maxlength="1000" placeholder="https://… (по одной ссылке на строке)"><?= $val('report_links') ?></textarea>
                            <?= $err('report_links') ?>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-brand mt-4"><i class="bi bi-send-check me-1"></i>Отправить отчёт администратору</button>
                    <p class="text-muted small mt-3 mb-0">После подтверждения отчёта администратором вам начисляется вознаграждение. Результат увидит компания-покупатель.</p>
                </div>
            </form>
        <?php elseif ($task['report_summary'] !== null): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-body p-4">
                    <h5 class="mb-3">Отправленный отчёт</h5>
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span class="badge text-bg-<?= e($vm['badge']) ?> fs-6"><i class="bi <?= e($vm['icon']) ?> me-1"></i><?= e($vm['label']) ?></span>
                        <?php if ($task['checked_on']): ?><span class="text-muted small">проверка от <?= e(formatDate($task['checked_on'])) ?></span><?php endif; ?>
                    </div>
                    <p style="white-space: pre-wrap"><?= e($task['report_summary']) ?></p>
                    <?php if ($task['report_details']): ?><p style="white-space: pre-wrap"><?= e($task['report_details']) ?></p><?php endif; ?>
                    <?php if ($links): ?>
                        <ul class="mb-0">
                            <?php foreach ($links as $url): ?>
                                <li><a href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= e($url) ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($events): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-body p-4">
                    <h5 class="mb-3">История</h5>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light"><tr><th>Время</th><th>Действие</th><th>Кто</th><th>Комментарий</th></tr></thead>
                            <tbody>
                            <?php foreach ($events as $ev): ?>
                                <tr>
                                    <td class="text-nowrap small"><?= e(formatDateTime($ev['created_at'])) ?></td>
                                    <td><?= e(inspectionEventLabel($ev['action'])) ?></td>
                                    <td><?= e($ev['full_name'] ?: '—') ?></td>
                                    <td class="small text-muted"><?= e($ev['comment'] ?: '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm mb-4">
            <div class="card-body p-4">
                <h5 class="mb-3">Что проверить</h5>
                <div class="rounded bg-light border p-3 small mb-3">
                    <?= $task['buyer_note'] ? nl2br(e($task['buyer_note'])) : '<span class="text-muted">Особых пожеланий нет — проверьте соответствие товара или услуги заказу.</span>' ?>
                </div>
                <h6><?= e(dealItemsTitle((string)$task['product_name'], (int)($task['items_count'] ?? 1))) ?></h6>
                <div class="small text-muted mb-2">
                    <?php if ((int)($task['items_count'] ?? 1) > 1): ?>
                        <?= (int)$task['items_count'] ?> поз.
                    <?php else: ?>
                        <?= number_format((int)$task['quantity'], 0, ',', ' ') ?> <?= e($task['unit']) ?>
                    <?php endif; ?>
                    <?php if ($task['sku']): ?> · арт. <?= e($task['sku']) ?><?php endif; ?>
                    <?php if ($task['category_name']): ?> · <?= e($task['category_name']) ?><?php endif; ?>
                </div>
                <?php if ($task['product_description']): ?>
                    <p class="small text-muted"><?= nl2br(e($task['product_description'])) ?></p>
                <?php endif; ?>
                <dl class="row small mb-0">
                    <dt class="col-5 text-muted fw-normal">Продавец</dt>
                    <dd class="col-7"><?= e($task['seller_name']) ?><?= $task['seller_phone'] ? '<br>' . e($task['seller_phone']) : '' ?><br><span class="text-muted"><?= e($task['seller_address']) ?></span></dd>
                    <dt class="col-5 text-muted fw-normal">Покупатель</dt>
                    <dd class="col-7"><?= e($task['buyer_name']) ?><?= $task['buyer_phone'] ? '<br>' . e($task['buyer_phone']) : '' ?></dd>
                    <?php if ($task['delivery_address']): ?>
                        <dt class="col-5 text-muted fw-normal">Адрес доставки</dt>
                        <dd class="col-7"><?= e($task['delivery_address']) ?></dd>
                    <?php endif; ?>
                    <?php if ($task['deal_comment']): ?>
                        <dt class="col-5 text-muted fw-normal">Комментарий к заказу</dt>
                        <dd class="col-7"><?= e($task['deal_comment']) ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
    </div>
</div>

<?php require BASE_PATH . '/views/layout/footer.php'; ?>
