<?php
require BASE_PATH . '/views/layout/header.php';

$roleBadge = ['owner' => 'text-bg-brand', 'manager' => 'text-bg-info', 'employee' => 'text-bg-secondary'];
$myId = (int)($_SESSION['user_id'] ?? 0);
$staff = array_values(array_filter($members, fn($m) => $m['company_role'] !== 'owner'));
?>

<?php if ($dbNotice): ?>
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i><?= e($dbNotice) ?></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
    <div>
        <h2 class="mb-1"><i class="bi bi-people text-brand me-2"></i>Команда компании</h2>
        <div class="text-muted"><?= e($companyName) ?> · сотрудников: <?= (int)$activeCount ?></div>
    </div>
    <a href="/account" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>В кабинет</a>
</div>

<ul class="nav nav-pills mb-4" id="teamTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" type="button" role="tab" data-bs-toggle="pill" data-bs-target="#tab-members">
            <i class="bi bi-people me-1"></i>Сотрудники <span class="badge text-bg-light"><?= count($members) ?></span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" type="button" role="tab" data-bs-toggle="pill" data-bs-target="#tab-requests">
            <i class="bi bi-person-plus me-1"></i>Заявки<?php if ($canReview && $requests): ?> <span class="badge text-bg-light"><?= count($requests) ?></span><?php endif; ?>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" type="button" role="tab" data-bs-toggle="pill" data-bs-target="#tab-permissions">
            <i class="bi bi-sliders me-1"></i>Права
        </button>
    </li>
</ul>

<div class="tab-content">

    <!-- ===== Сотрудники ===== -->
    <div class="tab-pane fade show active" id="tab-members" role="tabpanel">
        <div class="card shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Сотрудник</th>
                            <th>Должность</th>
                            <th>Роль</th>
                            <th>Права</th>
                            <th>Контакты</th>
                            <th class="text-end">Сделок оформил</th>
                            <th>Статус</th>
                            <?php if ($isOwner): ?><th class="text-end">Действия</th><?php endif; ?>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($members as $m): ?>
                            <?php $isMe = (int)$m['id'] === $myId; $isMemberOwner = $m['company_role'] === 'owner'; ?>
                            <tr class="<?= (int)$m['is_active'] === 1 ? '' : 'text-muted' ?>">
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="member-avatar"><?= e(initials($m['full_name'] ?: $m['username'])) ?></span>
                                        <div>
                                            <div class="fw-semibold"><?= e($m['full_name'] ?: $m['username']) ?><?= $isMe ? ' <span class="small text-muted">(вы)</span>' : '' ?></div>
                                            <div class="small text-muted"><?= e($m['username']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?= e($m['position'] ?: '—') ?></td>
                                <td>
                                    <?php if ($isOwner && !$isMemberOwner && !$isMe && (int)$m['is_active'] === 1): ?>
                                        <form method="POST" action="/team/member/role">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="user_id" value="<?= (int)$m['id'] ?>">
                                            <select name="company_role" class="form-select form-select-sm w-auto" onchange="this.form.submit()" aria-label="Роль">
                                                <option value="manager" <?= $m['company_role'] === 'manager' ? 'selected' : '' ?>>Менеджер</option>
                                                <option value="employee" <?= $m['company_role'] === 'employee' ? 'selected' : '' ?>>Сотрудник</option>
                                            </select>
                                        </form>
                                    <?php else: ?>
                                        <span class="badge <?= e($roleBadge[$m['company_role']] ?? 'text-bg-secondary') ?>"><?= e(companyRoleLabel($m['company_role'])) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="small">
                                    <?php if ($isMemberOwner): ?>
                                        <span class="text-muted">Все права</span>
                                    <?php else: ?>
                                        <?= $m['custom'] ? '<span class="badge text-bg-info">Индивидуальные</span>' : '<span class="text-muted">По роли</span>' ?>
                                        <?php if ($isOwner): ?>
                                            <div><a href="#permissions" data-open-tab="tab-permissions">Настроить</a></div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="small">
                                    <div class="text-break"><?= e($m['email']) ?></div>
                                    <div class="text-muted"><?= e($m['phone'] ?: '—') ?></div>
                                </td>
                                <td class="text-end"><?= (int)$m['deals_cnt'] ?></td>
                                <td>
                                    <?php if ((int)$m['is_active'] === 1): ?>
                                        <span class="badge text-bg-success">Активен</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary">Отключён</span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($isOwner): ?>
                                    <td class="text-end text-nowrap">
                                        <?php if (!$isMemberOwner && !$isMe): ?>
                                            <form method="POST" action="/team/member/toggle" class="d-inline"
                                                  onsubmit="return confirm('<?= (int)$m['is_active'] === 1 ? 'Отключить сотрудника? Он больше не сможет войти.' : 'Вернуть сотруднику доступ?' ?>');">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="user_id" value="<?= (int)$m['id'] ?>">
                                                <?php if ((int)$m['is_active'] === 1): ?>
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-person-dash me-1"></i>Отключить</button>
                                                <?php else: ?>
                                                    <button type="submit" class="btn btn-sm btn-outline-brand"><i class="bi bi-person-check me-1"></i>Вернуть</button>
                                                <?php endif; ?>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== Права ===== -->
    <div class="tab-pane fade" id="tab-permissions" role="tabpanel">
        <?php if ($isOwner): ?>
            <form method="POST" action="/team/permissions" id="permForm" class="card shadow-sm mb-4">
                <?= csrfField() ?>
                <div class="card-body p-4 p-lg-5">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                        <div>
                            <h3 class="mb-1">Права сотрудников</h3>
                            <div class="text-muted">
                                Включайте и выключайте действия для каждого сотрудника. Изменения действуют сразу после сохранения.
                                Директору доступно всё.
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <span class="small text-warning-emphasis" id="permDirty" hidden><i class="bi bi-circle-fill me-1" style="font-size:.5rem"></i>Есть несохранённые изменения</span>
                            <button type="submit" class="btn btn-brand btn-lg"><i class="bi bi-check2 me-1"></i>Сохранить права</button>
                        </div>
                    </div>

                    <?php if (!$staff): ?>
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-person-plus fs-1 d-block mb-2"></i>
                            В компании пока нет сотрудников. Они присоединяются по коду компании — смотрите вкладку «Заявки».
                        </div>
                    <?php else: ?>
                        <div class="perm-matrix-wrap">
                            <table class="perm-matrix">
                                <thead>
                                <tr>
                                    <th class="perm-corner">Действие</th>
                                    <?php foreach ($staff as $m): ?>
                                        <th class="perm-col" data-defaults="<?= e(implode(',', $roleDefaults[$m['company_role']] ?? [])) ?>">
                                            <input type="hidden" name="perm_users[]" value="<?= (int)$m['id'] ?>">
                                            <span class="member-avatar mx-auto mb-2"><?= e(initials($m['full_name'] ?: $m['username'])) ?></span>
                                            <div class="fw-semibold text-truncate"><?= e($m['full_name'] ?: $m['username']) ?></div>
                                            <div class="small text-muted text-truncate"><?= e($m['position'] ?: '—') ?></div>
                                            <div class="mt-1">
                                                <span class="badge <?= e($roleBadge[$m['company_role']] ?? 'text-bg-secondary') ?>"><?= e(companyRoleLabel($m['company_role'])) ?></span>
                                                <?php if ((int)$m['is_active'] !== 1): ?><span class="badge text-bg-secondary">Отключён</span><?php endif; ?>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-tinted perm-reset mt-2">Права роли</button>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($permissionCatalog as $pkey => [$plabel, $pdesc]): ?>
                                    <tr data-perm="<?= e($pkey) ?>">
                                        <th class="perm-name" scope="row">
                                            <div class="fw-semibold"><?= e($plabel) ?></div>
                                            <div class="small text-muted"><?= e($pdesc) ?></div>
                                            <button type="button" class="perm-all">Включить всем</button>
                                        </th>
                                        <?php foreach ($staff as $m): ?>
                                            <td class="text-center">
                                                <div class="form-check form-switch d-inline-block m-0">
                                                    <input class="form-check-input perm-switch" type="checkbox" role="switch"
                                                           name="perm[<?= (int)$m['id'] ?>][]" value="<?= e($pkey) ?>"
                                                           aria-label="<?= e($plabel . ' — ' . ($m['full_name'] ?: $m['username'])) ?>"
                                                           <?= in_array($pkey, $m['perms'], true) ? 'checked' : '' ?>>
                                                </div>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="small text-muted mt-4">
                            «Права роли» возвращает набор, заданный ролью. Менять роли и права и управлять кодом компании может только директор — это не передаётся.
                        </div>
                    <?php endif; ?>
                </div>
            </form>
        <?php else: ?>
            <div class="card shadow-sm mb-4">
                <div class="card-body p-4 p-lg-5">
                    <h3 class="mb-1">Ваши права</h3>
                    <div class="text-muted mb-4">Права настраивает директор компании.</div>
                    <div class="row g-3">
                        <?php foreach ($permissionCatalog as $pkey => [$plabel, $pdesc]): $on = canCompany($pkey); ?>
                            <div class="col-md-6">
                                <div class="perm-item h-100">
                                    <div>
                                        <div class="fw-semibold"><?= e($plabel) ?></div>
                                        <div class="small text-muted"><?= e($pdesc) ?></div>
                                    </div>
                                    <?= $on ? '<i class="bi bi-check-circle-fill text-success fs-4"></i>' : '<i class="bi bi-dash-circle text-muted fs-4"></i>' ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ===== Заявки и код компании ===== -->
    <div class="tab-pane fade" id="tab-requests" role="tabpanel">
        <?php if (!$canReview): ?>
            <div class="card shadow-sm mb-4"><div class="card-body p-4">
                <h4 class="mb-1">Заявки на вступление</h4>
                <p class="text-muted mb-0">Заявки новых сотрудников принимает директор или сотрудник с таким правом.</p>
            </div></div>
        <?php else: ?>
            <div class="card shadow-sm mb-4">
                <div class="card-body p-4 p-lg-5">
                    <div class="row align-items-center g-4">
                        <div class="col-lg-6">
                            <h4 class="mb-1">Код компании</h4>
                            <p class="text-muted mb-2">
                                Сотрудник вводит этот код при регистрации на странице «Я сотрудник» — и его заявка появляется ниже.
                                Роль выдаёте вы при одобрении заявки.
                            </p>
                            <div class="small text-muted">Свободных мест: <?= (int)$slotsLeft ?></div>
                        </div>
                        <div class="col-lg-6">
                            <?php if (!empty($company['join_code'])): ?>
                                <div class="join-code-box">
                                    <div class="join-code" id="joinCode"><?= e(formatJoinCode($company['join_code'])) ?></div>
                                    <button type="button" class="btn btn-brand" id="copyCode"><i class="bi bi-clipboard me-1"></i>Копировать</button>
                                </div>
                            <?php else: ?>
                                <div class="text-muted">Код ещё не создан.</div>
                            <?php endif; ?>
                            <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                                <?php if ((int)$company['join_enabled'] === 1): ?>
                                    <span class="badge text-bg-success">Приём заявок включён</span>
                                <?php else: ?>
                                    <span class="badge text-bg-secondary">Приём заявок выключен</span>
                                <?php endif; ?>
                                <?php if ($isOwner): ?>
                                    <form method="POST" action="/team/code/toggle" class="d-inline">
                                        <?= csrfField() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                                            <?= (int)$company['join_enabled'] === 1 ? 'Выключить приём' : 'Включить приём' ?>
                                        </button>
                                    </form>
                                    <form method="POST" action="/team/code/regenerate" class="d-inline"
                                          onsubmit="return confirm('Создать новый код? Старый перестанет работать, уже поданные заявки останутся.');">
                                        <?= csrfField() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-brand"><i class="bi bi-arrow-repeat me-1"></i><?= !empty($company['join_code']) ? 'Сменить код' : 'Создать код' ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-body p-4">
                    <h4 class="mb-3">Заявки на вступление <span class="badge text-bg-secondary"><?= count($requests) ?></span></h4>
                    <?php if (!$requests): ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>Новых заявок нет.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead class="table-light">
                                <tr><th>Заявитель</th><th>Контакты</th><th>Подана</th><th>Роль при принятии</th><th class="text-end">Решение</th></tr>
                                </thead>
                                <tbody>
                                <?php foreach ($requests as $r): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="member-avatar"><?= e(initials($r['full_name'] ?: $r['username'])) ?></span>
                                                <div>
                                                    <div class="fw-semibold"><?= e($r['full_name']) ?></div>
                                                    <div class="small text-muted"><?= e($r['position'] ?: '—') ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="small">
                                            <div class="text-break"><?= e($r['email']) ?></div>
                                            <div class="text-muted"><?= e($r['phone'] ?: '—') ?></div>
                                        </td>
                                        <td class="small text-nowrap"><?= e(formatDateTime($r['join_requested_at'])) ?></td>
                                        <td colspan="2" class="text-end">
                                            <form method="POST" action="/team/requests/decide" class="d-flex flex-wrap gap-2 justify-content-end align-items-center">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="user_id" value="<?= (int)$r['id'] ?>">
                                                <input type="text" name="position" class="form-control form-control-sm w-auto" style="width: 12rem !important"
                                                       maxlength="100" placeholder="Должность (необязательно)" aria-label="Должность">
                                                <select name="company_role" class="form-select form-select-sm w-auto" aria-label="Роль">
                                                    <?php if (in_array('manager', $assignable, true)): ?><option value="manager">Менеджер</option><?php endif; ?>
                                                    <option value="employee" selected>Сотрудник</option>
                                                </select>
                                                <button type="submit" name="decision" value="approve" class="btn btn-sm btn-brand"><i class="bi bi-check2 me-1"></i>Принять</button>
                                                <button type="submit" name="decision" value="reject" class="btn btn-sm btn-outline-danger"
                                                        onclick="return confirm('Отклонить заявку?');"><i class="bi bi-x-lg me-1"></i>Отклонить</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if (!canCompany('manage_team')): ?>
                            <div class="small text-muted mt-3">Роль «Менеджер» назначает директор. Вы можете принимать заявки с ролью «Сотрудник».</div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// вкладки: открываем нужную по якорю и запоминаем выбор в адресе
window.addEventListener('load', function () {
    var map = { members: 'tab-members', requests: 'tab-requests', permissions: 'tab-permissions' };
    function show(id) {
        var btn = document.querySelector('#teamTabs [data-bs-target="#' + id + '"]');
        if (btn && typeof bootstrap !== 'undefined') bootstrap.Tab.getOrCreateInstance(btn).show();
    }
    var h = location.hash.replace('#', '');
    if (map[h]) show(map[h]);
    [].forEach.call(document.querySelectorAll('#teamTabs [data-bs-toggle="pill"]'), function (btn) {
        btn.addEventListener('shown.bs.tab', function () {
            var key = Object.keys(map).filter(function (k) { return '#' + map[k] === btn.dataset.bsTarget; })[0];
            if (key) history.replaceState(null, '', '#' + key);
        });
    });
    [].forEach.call(document.querySelectorAll('[data-open-tab]'), function (a) {
        a.addEventListener('click', function (ev) { ev.preventDefault(); show(a.dataset.openTab); });
    });
});

// панель прав: «права роли», «включить всем», индикатор несохранённых изменений
(function () {
    var form = document.getElementById('permForm');
    if (!form) return;
    var switches = [].slice.call(form.querySelectorAll('.perm-switch'));
    var dirty = document.getElementById('permDirty');
    function snapshot() { return switches.map(function (s) { return s.checked ? '1' : '0'; }).join(''); }
    var initial = snapshot();
    function refresh() { dirty.hidden = snapshot() === initial; }

    form.addEventListener('change', function (ev) { if (ev.target.classList.contains('perm-switch')) refresh(); });

    [].forEach.call(form.querySelectorAll('.perm-reset'), function (btn) {
        btn.addEventListener('click', function () {
            var th = btn.closest('th');
            var idx = [].indexOf.call(th.parentNode.children, th);
            var defaults = (th.dataset.defaults || '').split(',');
            [].forEach.call(form.querySelectorAll('tbody tr'), function (tr) {
                var cb = tr.children[idx].querySelector('.perm-switch');
                cb.checked = defaults.indexOf(tr.dataset.perm) !== -1;
            });
            refresh();
        });
    });
    [].forEach.call(form.querySelectorAll('.perm-all'), function (btn) {
        btn.addEventListener('click', function () {
            var cbs = [].slice.call(btn.closest('tr').querySelectorAll('.perm-switch'));
            var allOn = cbs.every(function (c) { return c.checked; });
            cbs.forEach(function (c) { c.checked = !allOn; });
            btn.textContent = allOn ? 'Включить всем' : 'Выключить всем';
            refresh();
        });
    });
})();

(function () {
    var btn = document.getElementById('copyCode');
    var code = document.getElementById('joinCode');
    if (!btn || !code) return;
    btn.addEventListener('click', function () {
        function done() { btn.innerHTML = '<i class="bi bi-check2 me-1"></i>Скопировано'; }
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(code.textContent.trim()).then(done);
        } else {
            var r = document.createRange(); r.selectNodeContents(code);
            var sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(r);
            try { document.execCommand('copy'); done(); } catch (e) {}
        }
    });
})();
</script>

<?php require BASE_PATH . '/views/layout/footer.php'; ?>
