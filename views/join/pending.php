<?php require BASE_PATH . '/views/layout/header.php'; $rejected = $me['join_status'] === 'rejected'; ?>

<div class="row justify-content-center">
    <div class="col-lg-7 col-xl-6">
        <div class="card shadow-sm">
            <div class="card-body p-4 p-lg-5 text-center">
                <?php if ($rejected): ?>
                    <i class="bi bi-x-circle display-4 text-danger"></i>
                    <h3 class="mt-3 mb-2">Заявка отклонена</h3>
                    <p class="text-muted mb-4">
                        Руководитель компании «<?= e($me['company_name']) ?>» отклонил заявку.
                        Если вы ошиблись компанией, отправьте заявку по другому коду.
                    </p>
                <?php else: ?>
                    <i class="bi bi-hourglass-split display-4 text-brand"></i>
                    <h3 class="mt-3 mb-2">Заявка на рассмотрении</h3>
                    <p class="text-muted mb-1">
                        Вы подали заявку в компанию «<?= e($me['company_name']) ?>»
                        <?php if ($me['join_requested_at']): ?>(<?= e(formatDateTime($me['join_requested_at'])) ?>)<?php endif; ?>.
                    </p>
                    <p class="text-muted mb-4">
                        Директор или менеджер примет её и выдаст вам роль. Зайдите сюда позже — как только заявку одобрят,
                        откроется ваш кабинет.
                    </p>
                <?php endif; ?>

                <div class="d-flex justify-content-center gap-2 mb-4">
                    <a href="/pending" class="btn btn-outline-brand"><i class="bi bi-arrow-clockwise me-1"></i>Проверить статус</a>
                    <a href="/logout" class="btn btn-outline-secondary">Выйти</a>
                </div>

                <hr class="my-4">
                <form method="POST" action="/pending/code" class="text-start">
                    <?= csrfField() ?>
                    <label class="form-label" for="code"><?= $rejected ? 'Код другой компании' : 'Ошиблись компанией?' ?></label>
                    <div class="input-group">
                        <input type="text" id="code" name="code" class="form-control code-input" maxlength="9" placeholder="ABCD-EF23"
                               autocomplete="off" autocapitalize="characters" spellcheck="false">
                        <button type="submit" class="btn btn-brand">Отправить заявку</button>
                    </div>
                    <div class="form-text">Введите код другой компании — заявка будет отправлена туда.</div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var input = document.getElementById('code');
    input.addEventListener('input', function () {
        var v = input.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 8);
        input.value = v.length > 4 ? v.slice(0, 4) + '-' + v.slice(4) : v;
    });
})();
</script>

<?php require BASE_PATH . '/views/layout/footer.php'; ?>
