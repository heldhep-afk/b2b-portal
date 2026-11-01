<?php
require BASE_PATH . '/views/layout/header.php';

$st = inspectionStatusMeta($insp['status']);
$fm = inspectionFeeMeta($insp['fee_status']);
$vm = inspectionVerdictMeta($insp['verdict']);
$back = '/admin/inspections/view?id=' . (int)$insp['id'];
$hasReport = in_array($insp['status'], ['submitted', 'approved'], true) || $insp['report_summary'] !== null;

$actionMeta = [
    'confirm_fee' => ['Подтвердить доплату', 'bi-cash-coin',               'btn-outline-brand'],
    'assign'      => ['Назначить проверяющего', 'bi-person-check',         'btn-outline-brand'],
    'approve'     => ['Подтвердить отчёт', 'bi-check2-circle',             'btn-brand'],
    'return'      => ['Вернуть на доработку', 'bi-arrow-return-left',      'btn-outline-secondary'],
    'cancel'      => ['Отменить проверку', 'bi-x-circle',                  'btn-outline-danger'],
    'refund_fee'  => ['Вернуть доплату', 'bi-arrow-counterclockwise',      'btn-outline-dark'],
    'pay_reward'  => ['Выплатить проверяющему', 'bi-send-check',           'btn-success'],
];
$links = preg_split('/\s+/u', (string)$insp['report_links'], -1, PREG_SPLIT_NO_EMPTY) ?: [];
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/admin/inspections">Проверки</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= e($insp['deal_number']) ?></li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
    <div>
        <h2 class="mb-1">Проверка по сделке <?= e($insp['deal_number']) ?></h2>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <span class="badge text-bg-<?= e($st['badge']) ?>"><?= e($st['label']) ?></span>
            <span class="badge text-bg-<?= e($fm['badge']) ?>"><?= e($fm['label']) ?></span>
            <?php if ($insp['status'] === 'approved'): ?>
                <span class="badge text-bg-<?= $insp['reward_paid_at'] ? 'success' : 'warning' ?>">
                    <?= $insp['reward_paid_at'] ? 'Вознаграждение выплачено' : 'Вознаграждение к выплате' ?>
                </span>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($actions): ?>
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($actions as $act): [$label, $icon, $cls] = $actionMeta[$act]; ?>
                <button type="button" class="btn <?= e($cls) ?>" data-bs-toggle="modal" data-bs-target="#inspModal"
                        data-action="<?= e($act) ?>">
                    <i class="bi <?= e($icon) ?> me-1"></i><?= e($label) ?>
                </button>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php
$stepStatus = $insp['status']; $stepFee = $insp['fee_status']; $stepVisit = $insp['visit_status'];
require BASE_PATH . '/views/partials/inspection_steps.php';
?>

<div class="row g-4">
    <div class="col-lg-7">
        <?php if ($insp['status'] === 'submitted'): ?>
            <div class="alert alert-info"><i class="bi bi-hourglass-split me-1"></i>
                Отчёт ждёт вашего решения: подтвердите его (при этом задаётся вознаграждение проверяющему) или верните на доработку.
            </div>
        <?php endif; ?>

        <div class="card shadow-sm mb-4">
            <div class="card-body p-4">
                <h5 class="mb-3">Отчёт проверяющего</h5>
                <?php if (!$hasReport || $insp['report_summary'] === null): ?>
                    <div class="text-muted">Отчёта пока нет<?= $insp['inspector_name'] ? ' — проверяющий ещё работает.' : '.' ?></div>
                <?php else: ?>
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span class="badge text-bg-<?= e($vm['badge']) ?> fs-6"><i class="bi <?= e($vm['icon']) ?> me-1"></i><?= e($vm['label']) ?></span>
                        <?php if ($insp['checked_on']): ?><span class="text-muted small">проверка от <?= e(formatDate($insp['checked_on'])) ?></span><?php endif; ?>
                    </div>
                    <div class="fw-semibold mb-1">Краткий вывод</div>
                    <p class="mb-3" style="white-space: pre-wrap"><?= e($insp['report_summary']) ?></p>
                    <?php if ($insp['report_details']): ?>
                        <div class="fw-semibold mb-1">Подробности</div>
                        <p class="mb-3" style="white-space: pre-wrap"><?= e($insp['report_details']) ?></p>
                    <?php endif; ?>
                    <?php if ($links): ?>
                        <div class="fw-semibold mb-1">Ссылки на материалы</div>
                        <ul class="mb-3">
                            <?php foreach ($links as $url): ?>
                                <li><a href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= e($url) ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <?php if ($insp['submitted_at']): ?>
                        <div class="small text-muted">
                            Отправлен <?= e(formatDateTime($insp['submitted_at'])) ?>
                            <?php if ((int)$insp['revision_count'] > 0): ?> · возвращался на доработку: <?= (int)$insp['revision_count'] ?><?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ($insp['admin_comment']): ?>
                    <div class="alert alert-light border small mt-3 mb-0">
                        <strong>Комментарий администратора:</strong> <?= e($insp['admin_comment']) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-body p-4">
                <h5 class="mb-3">Журнал</h5>
                <?php if (!$events): ?>
                    <div class="text-muted">Событий нет.</div>
                <?php else: ?>
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
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm mb-4">
            <div class="card-body p-4">
                <h5 class="mb-3">Заказ</h5>
                <dl class="row small mb-0">
                    <dt class="col-5 text-muted fw-normal">Товар / услуга</dt>
                    <dd class="col-7"><?= e($insp['product_name']) ?> · <?= number_format((int)$insp['quantity'], 0, ',', ' ') ?> <?= e($insp['unit']) ?></dd>
                    <dt class="col-5 text-muted fw-normal">Сумма сделки</dt>
                    <dd class="col-7"><?= e(formatPrice($insp['total_amount'])) ?></dd>
                    <dt class="col-5 text-muted fw-normal">Покупатель</dt>
                    <dd class="col-7"><?= e($insp['buyer_name']) ?><?= $insp['buyer_phone'] ? '<br>' . e($insp['buyer_phone']) : '' ?></dd>
                    <dt class="col-5 text-muted fw-normal">Продавец</dt>
                    <dd class="col-7"><?= e($insp['seller_name']) ?><?= $insp['seller_phone'] ? '<br>' . e($insp['seller_phone']) : '' ?></dd>
                    <dt class="col-5 text-muted fw-normal">Заказал</dt>
                    <dd class="col-7"><?= e($insp['requester_name'] ?: '—') ?></dd>
                    <dt class="col-5 text-muted fw-normal">Что проверить</dt>
                    <dd class="col-7"><?= $insp['buyer_note'] ? nl2br(e($insp['buyer_note'])) : '<span class="text-muted">не указано</span>' ?></dd>
                </dl>
            </div>
        </div>
        <?php $vm2 = visitStatusMeta($insp['visit_status'], $insp['visit_proposed_by']); ?>
        <div class="card shadow-sm mb-4">
            <div class="card-body p-4">
                <h5 class="mb-3">Время проверки и сопровождающий</h5>
                <span class="badge text-bg-<?= e($vm2['badge']) ?> mb-2"><?= e($vm2['label']) ?></span>
                <dl class="row small mb-0">
                    <dt class="col-5 text-muted fw-normal">Дата и время</dt>
                    <dd class="col-7"><?= $insp['visit_at'] ? e(formatDateTime($insp['visit_at'])) : '—' ?></dd>
                    <dt class="col-5 text-muted fw-normal">Сопровождающий</dt>
                    <dd class="col-7"><?= $insp['escort_name'] ? e($insp['escort_name']) . '<br>№ ' . e($insp['escort_number']) . ($insp['escort_phone'] ? '<br>' . e($insp['escort_phone']) : '') : '<span class="text-muted">не назначен</span>' ?></dd>
                    <?php if ($insp['visit_note']): ?>
                        <dt class="col-5 text-muted fw-normal">Комментарий</dt>
                        <dd class="col-7"><?= e($insp['visit_note']) ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
        <div class="card shadow-sm mb-4">
            <div class="card-body p-4">
                <h5 class="mb-3">Деньги и исполнитель</h5>
                <dl class="row small mb-0">
                    <dt class="col-6 text-muted fw-normal">Доплата компании</dt>
                    <dd class="col-6 text-end fw-semibold"><?= e(formatPrice($insp['fee'])) ?></dd>
                    <dt class="col-6 text-muted fw-normal">Вознаграждение проверяющему</dt>
                    <dd class="col-6 text-end fw-semibold"><?= e(formatPrice($insp['reward'])) ?></dd>
                    <dt class="col-6 text-muted fw-normal">Проверяющий</dt>
                    <dd class="col-6 text-end"><?= e($insp['inspector_name'] ?: 'не назначен') ?></dd>
                    <?php if ($insp['reviewer_name']): ?>
                        <dt class="col-6 text-muted fw-normal">Решение принял</dt>
                        <dd class="col-6 text-end"><?= e($insp['reviewer_name']) ?></dd>
                    <?php endif; ?>
                </dl>
                <div class="mt-3"><a href="/admin#deals" class="small">К списку сделок</a></div>
            </div>
        </div>
    </div>
</div>

<!-- Подтверждение действия -->
<div class="modal fade" id="inspModal" tabindex="-1" aria-labelledby="inspModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="/admin/inspections/action" class="modal-content">
            <?= csrfField() ?>
            <input type="hidden" name="inspection_id" value="<?= (int)$insp['id'] ?>">
            <input type="hidden" name="action" id="inspAction" value="">
            <input type="hidden" name="back" value="<?= e($back) ?>">
            <div class="modal-header">
                <h5 class="modal-title" id="inspModalTitle">Действие по проверке</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <p id="inspModalText" class="mb-3"></p>

                <div id="assignWrap" class="mb-3 d-none">
                    <label class="form-label" for="inspector_id">Проверяющий</label>
                    <select name="inspector_id" id="inspector_id" class="form-select">
                        <option value="">Выберите…</option>
                        <?php foreach ($inspectors as $in): ?>
                            <option value="<?= (int)$in['id'] ?>" <?= (int)$insp['inspector_id'] === (int)$in['id'] ? 'selected' : '' ?>><?= e($in['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!$inspectors): ?><div class="form-text text-danger">Нет действующих проверяющих — добавьте сотрудника на странице «Персонал».</div><?php endif; ?>
                </div>

                <div id="rewardWrap" class="mb-3 d-none">
                    <label class="form-label" for="reward">Вознаграждение проверяющему, ₽</label>
                    <input type="text" inputmode="decimal" name="reward" id="reward" class="form-control"
                           value="<?= e(number_format((float)$insp['reward'], 2, '.', '')) ?>">
                    <div class="form-text">Не больше доплаты компании (<?= e(formatPrice($insp['fee'])) ?>).</div>
                </div>

                <label class="form-label" for="inspComment" id="inspCommentLabel">Комментарий</label>
                <textarea name="comment" id="inspComment" class="form-control" rows="3" maxlength="500"></textarea>
                <div class="form-text">Сохраняется в журнале проверки.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="submit" class="btn btn-brand" id="inspConfirm">Подтвердить</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    // [заголовок, текст, кнопка, класс кнопки, комментарий обязателен, показывать выбор проверяющего, показывать вознаграждение]
    var texts = {
        confirm_fee: ['Подтвердить доплату', 'Подтвердите, что доплата за проверку <?= e(formatPrice($insp['fee'])) ?> поступила на счёт платформы. После этого задание станет доступно проверяющим.', 'Подтвердить', 'btn-brand', false, false, false],
        assign:      ['Назначить проверяющего', 'Выберите сотрудника, который выполнит проверку. Без назначения задание может взять любой проверяющий.', 'Назначить', 'btn-brand', false, true, false],
        approve:     ['Подтвердить отчёт', 'Отчёт принимается, проверяющему начисляется вознаграждение. Покупатель увидит результат проверки, а сделку можно будет подтвердить.', 'Подтвердить отчёт', 'btn-brand', false, false, true],
        'return':    ['Вернуть на доработку', 'Проверяющий получит задание обратно вместе с вашим комментарием и сможет исправить отчёт.', 'Вернуть', 'btn-secondary', true, false, false],
        cancel:      ['Отменить проверку', 'Проверка будет отменена. Если доплата уже получена, потом потребуется вернуть её компании.', 'Отменить проверку', 'btn-danger', true, false, false],
        refund_fee:  ['Вернуть доплату', 'Отметьте, что доплата <?= e(formatPrice($insp['fee'])) ?> возвращена компании-покупателю.', 'Возврат произведён', 'btn-dark', true, false, false],
        pay_reward:  ['Выплатить проверяющему', 'Отметьте, что вознаграждение <?= e(formatPrice($insp['reward'])) ?> выплачено проверяющему.', 'Выплата произведена', 'btn-success', false, false, false]
    };
    var modal = document.getElementById('inspModal');
    modal.addEventListener('show.bs.modal', function (ev) {
        var b = ev.relatedTarget;
        if (!b) return;
        var t = texts[b.dataset.action];
        if (!t) return;
        document.getElementById('inspAction').value = b.dataset.action;
        document.getElementById('inspModalTitle').textContent = t[0];
        document.getElementById('inspModalText').textContent = t[1];
        var ok = document.getElementById('inspConfirm');
        ok.textContent = t[2];
        ok.className = 'btn ' + t[3];
        var c = document.getElementById('inspComment');
        c.value = '';
        c.required = t[4];
        document.getElementById('inspCommentLabel').textContent = t[4] ? 'Причина (обязательно)' : 'Комментарий (необязательно)';
        document.getElementById('assignWrap').classList.toggle('d-none', !t[5]);
        document.getElementById('rewardWrap').classList.toggle('d-none', !t[6]);
        document.getElementById('inspector_id').required = t[5];
    });
})();
</script>

<?php require BASE_PATH . '/views/layout/footer.php'; ?>
