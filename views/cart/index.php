<?php
require BASE_PATH . '/views/layout/header.php';
$itemCount = 0;
foreach ($groups as $g) {
    $itemCount += count($g['items']);
}
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/">Каталог</a></li>
        <li class="breadcrumb-item active" aria-current="page">Заявка</li>
    </ol>
</nav>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4">
    <div>
        <h2 class="mb-1">Заявка</h2>
        <div class="text-muted">
            <?php if ($groups): ?>
                Позиций: <?= $itemCount ?> · Продавцов: <?= count($groups) ?>
            <?php else: ?>
                Пока пусто
            <?php endif; ?>
        </div>
    </div>
    <a href="/" class="btn btn-outline-brand btn-sm">
        <i class="bi bi-grid me-1"></i>В каталог
    </a>
</div>

<?php if (!$groups): ?>

    <div class="card shadow-sm">
        <div class="card-body text-center py-5">
            <div class="display-5 text-brand mb-3"><i class="bi bi-cart3"></i></div>
            <h4 class="mb-2">В заявке нет товаров</h4>
            <p class="text-muted mb-4">Добавляйте товары из каталога — они соберутся здесь, и вы оформите одну закупку у нескольких продавцов.</p>
            <a href="/" class="btn btn-brand"><i class="bi bi-grid me-1"></i>Перейти в каталог</a>
        </div>
    </div>

<?php else: ?>

    <form method="POST" action="/cart/update" id="cartForm">
        <?= csrfField() ?>
        <!-- кнопка по умолчанию: Enter в поле количества сохраняет заявку, а не удаляет первую позицию -->
        <button type="submit" class="d-none" tabindex="-1" aria-hidden="true"></button>

        <?php foreach ($groups as $g): ?>
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-shop me-1 text-brand"></i><?= e($g['company_name']) ?></span>
                    <span class="small text-muted"><?= count($g['items']) ?> поз.</span>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Товар</th>
                                <th scope="col" class="text-end">Цена</th>
                                <th scope="col" style="width: 9.5rem;">Количество</th>
                                <th scope="col" class="text-end">Сумма</th>
                                <th scope="col"><span class="visually-hidden">Действия</span></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($g['items'] as $it): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= e($it['name']) ?></div>
                                    <div class="small text-muted">
                                        <?php if ($it['sku']): ?>арт. <?= e($it['sku']) ?><?php endif; ?>
                                        <?php if ($it['category_name']): ?> · <?= e($it['category_name']) ?><?php endif; ?>
                                        <?php if ((int)$it['in_stock'] === 0): ?>
                                            · <span class="text-warning-emphasis">нет в наличии</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-end text-nowrap">
                                    <?= e(formatPrice($it['price'])) ?>
                                    <div class="small text-muted">за <?= e($it['unit']) ?></div>
                                </td>
                                <td>
                                    <label class="visually-hidden" for="qty<?= (int)$it['id'] ?>">Количество, <?= e($it['unit']) ?></label>
                                    <input type="number" class="form-control form-control-sm"
                                           id="qty<?= (int)$it['id'] ?>"
                                           name="qty[<?= (int)$it['id'] ?>]"
                                           value="<?= (int)$it['qty'] ?>"
                                           min="<?= max(1, (int)$it['min_order']) ?>" max="<?= Cart::MAX_QTY ?>" step="1"
                                           inputmode="numeric">
                                    <div class="form-text">мин. <?= number_format(max(1, (int)$it['min_order']), 0, ',', ' ') ?> <?= e($it['unit']) ?></div>
                                </td>
                                <td class="text-end text-nowrap fw-semibold"><?= e(formatPrice($it['line_total'])) ?></td>
                                <td class="text-end">
                                    <button type="submit" name="remove" value="<?= (int)$it['id'] ?>"
                                            class="btn btn-sm btn-outline-secondary"
                                            formnovalidate
                                            title="Удалить позицию"
                                            aria-label="Удалить «<?= e($it['name']) ?>» из заявки">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (count($groups) > 1): ?>
                    <div class="card-footer bg-transparent d-flex justify-content-between align-items-center">
                        <span class="text-muted small">Итого у продавца</span>
                        <span class="fw-bold text-brand"><?= e(formatPrice($g['total'])) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <div class="card shadow-sm">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted">Сумма заявки</span>
                    <span class="fs-4 fw-bold text-brand text-nowrap"><?= e(formatPrice($total)) ?></span>
                </div>
                <div class="small text-muted mb-4">Без учёта доставки. Цены и наличие проверяются при оформлении.</div>

                <div class="d-flex flex-wrap gap-2">
                    <?php if ($canOrder): ?>
                        <button type="submit" formaction="/cart/update" name="then" value="checkout"
                                class="btn btn-brand btn-lg flex-grow-1">
                            <i class="bi bi-check2-circle me-1"></i>Оформить заявку
                        </button>
                    <?php elseif (!$isAuth): ?>
                        <a href="/login" class="btn btn-brand btn-lg flex-grow-1">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Войти и оформить
                        </a>
                    <?php else: ?>
                        <button type="button" class="btn btn-secondary btn-lg flex-grow-1" disabled>
                            Нет права на оформление закупок
                        </button>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-outline-brand" formaction="/cart/update">
                        <i class="bi bi-arrow-repeat me-1"></i>Обновить
                    </button>
                    <button type="submit" class="btn btn-outline-secondary" formaction="/cart/clear" formnovalidate>
                        <i class="bi bi-x-circle me-1"></i>Очистить
                    </button>
                </div>

                <?php if ($canOrder && count($groups) > 1): ?>
                    <p class="text-muted small mt-3 mb-0">
                        <i class="bi bi-info-circle me-1"></i>Товары от разных продавцов оформляются отдельными сделками —
                        по одной на каждого продавца.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </form>

<?php endif; ?>

<?php require BASE_PATH . '/views/layout/footer.php'; ?>
