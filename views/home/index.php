<?php
require BASE_PATH . '/views/layout/header.php';

/** Ссылка на каталог с текущими фильтрами и переопределёнными параметрами. */
$url = function (array $override = []) use ($baseQuery): string {
    $qs = array_filter(array_merge($baseQuery, $override), fn($v) => $v !== '' && $v !== null && $v !== 0);
    return '/' . ($qs ? '?' . http_build_query($qs) : '') . '#catalog';
};
$hasFilters = (bool)$baseQuery;
?>

<?php if ($dbNotice): ?>
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i><?= e($dbNotice) ?></div>
<?php endif; ?>

<section class="catalog-hero mb-4">
    <span class="catalog-hero-glow" aria-hidden="true"></span>
    <div class="row align-items-center g-4 position-relative">
        <div class="col-lg-7">
            <div class="hero-kicker"><i class="bi bi-buildings me-1"></i>Оптовые закупки между компаниями</div>
            <h1 class="hero-title mb-2">Каталог товаров и услуг</h1>
            <p class="hero-sub mb-3">Найдите поставщика, сравните условия и оформите закупку за пару минут — сделка, оплата и проверка качества в одном кабинете.</p>
            <form method="GET" action="/#catalog" class="hero-search hero-search-lg">
                <i class="bi bi-search" aria-hidden="true"></i>
                <input type="search" name="q" maxlength="100" value="<?= e($filters['q']) ?>"
                       placeholder="Товар, артикул или категория" aria-label="Поиск по каталогу">
                <button type="submit" class="btn btn-brand btn-sm">Найти</button>
            </form>
        </div>
        <div class="col-lg-5">
            <div class="hero-stats">
                <div class="hero-stat">
                    <span class="hero-stat-value"><?= number_format((int)($catalogStats['products'] ?? 0), 0, ',', ' ') ?></span>
                    <span class="hero-stat-label">товаров</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-value"><?= number_format((int)($catalogStats['companies'] ?? 0), 0, ',', ' ') ?></span>
                    <span class="hero-stat-label">компаний</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-value"><?= number_format((int)($catalogStats['categories'] ?? 0), 0, ',', ' ') ?></span>
                    <span class="hero-stat-label">категорий</span>
                </div>
            </div>
        </div>
    </div>
    <button class="btn btn-outline-brand d-lg-none mt-3" type="button" data-bs-toggle="collapse" data-bs-target="#filters">
        <i class="bi bi-funnel me-1"></i>Фильтры<?= $hasFilters ? ' •' : '' ?>
    </button>
</section>

<?php if (!isset($_SESSION['user_id'])): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3 py-3">
            <div>
                <div class="fw-semibold"><i class="bi bi-clipboard-check text-brand me-1"></i>Хотите проверять товары и услуги компаний?</div>
                <div class="small text-muted">Станьте проверяющим портала: отдельная заявка, без регистрации компании. Вознаграждение — за каждую подтверждённую проверку.</div>
            </div>
            <a href="/inspector/apply" class="btn btn-brand"><i class="bi bi-person-plus me-1"></i>Стать проверяющим</a>
        </div>
    </div>
<?php endif; ?>

<!-- Быстрые категории -->
<nav class="chip-row" aria-label="Категории">
    <a class="chip<?= ($filters['category'] === 0 && !$filters['rec']) ? ' active' : '' ?>" href="<?= e($url(['category' => '', 'rec' => '', 'page' => ''])) ?>">Все</a>
    <?php if ($recIds): ?>
        <a class="chip chip-rec<?= $filters['rec'] ? ' active' : '' ?>" href="<?= e($url(['rec' => '1', 'category' => '', 'page' => ''])) ?>">
            <i class="bi bi-stars me-1"></i>Рекомендованные<small><?= count($recIds) ?></small>
        </a>
    <?php endif; ?>
    <?php foreach ($categories as $cat): ?>
        <a class="chip<?= $filters['category'] === (int)$cat['id'] ? ' active' : '' ?>"
           href="<?= e($url(['category' => (int)$cat['id'], 'rec' => '', 'page' => ''])) ?>">
            <?= e($cat['name']) ?><small><?= (int)$cat['cnt'] ?></small>
        </a>
    <?php endforeach; ?>
</nav>

<div class="row g-4">
    <!-- Фильтры -->
    <aside class="col-lg-3">
        <div class="collapse d-lg-block" id="filters">
            <form method="GET" action="/#catalog" id="filterForm" class="card shadow-sm">
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="q">Поиск</label>
                        <input type="search" id="q" name="q" class="form-control" maxlength="100"
                               placeholder="Название, артикул…" value="<?= e($filters['q']) ?>">
                    </div>

                    <div class="mb-3">
                        <div class="form-label fw-semibold">Категория</div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="category" id="cat0" value=""
                                   <?= $filters['category'] === 0 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="cat0">Все категории</label>
                        </div>
                        <?php foreach ($categories as $cat): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="category"
                                       id="cat<?= (int)$cat['id'] ?>" value="<?= (int)$cat['id'] ?>"
                                       <?= $filters['category'] === (int)$cat['id'] ? 'checked' : '' ?>>
                                <label class="form-check-label d-flex justify-content-between gap-2" for="cat<?= (int)$cat['id'] ?>">
                                    <span><?= e($cat['name']) ?></span>
                                    <span class="text-muted small"><?= (int)$cat['cnt'] ?></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="seller">Поставщик</label>
                        <select name="seller" id="seller" class="form-select">
                            <option value="">Все поставщики</option>
                            <?php foreach ($sellers as $s): ?>
                                <option value="<?= (int)$s['id'] ?>" <?= $filters['seller'] === (int)$s['id'] ? 'selected' : '' ?>>
                                    <?= e($s['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <div class="form-label fw-semibold">Цена, ₽</div>
                        <div class="input-group">
                            <input type="text" name="price_min" class="form-control" inputmode="decimal"
                                   placeholder="от" aria-label="Минимальная цена, ₽" value="<?= e($filters['price_min']) ?>">
                            <input type="text" name="price_max" class="form-control" inputmode="decimal"
                                   placeholder="до" aria-label="Максимальная цена, ₽" value="<?= e($filters['price_max']) ?>">
                        </div>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="in_stock" id="in_stock" value="1"
                               <?= $filters['in_stock'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="in_stock">Только в наличии</label>
                    </div>

                    <?php if ($recIds): ?>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="rec" id="rec" value="1" <?= $filters['rec'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="rec">Только рекомендованные</label>
                        </div>
                    <?php endif; ?>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-brand">Применить</button>
                        <?php if ($hasFilters): ?>
                            <a href="/#catalog" class="btn btn-outline-secondary">Сбросить</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </aside>

    <!-- Товары -->
    <section class="col-lg-9" id="catalog">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div class="text-muted">Найдено: <strong class="text-body"><?= (int)$total ?></strong></div>
            <div class="d-flex align-items-center gap-2">
                <label for="sort" class="small text-muted mb-0">Сортировка</label>
                <select name="sort" id="sort" form="filterForm" class="form-select form-select-sm w-auto"
                        onchange="this.form.submit()">
                    <?php foreach ($sorts as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $filters['sort'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <?php if (!$items): ?>
            <div class="card shadow-sm empty-state">
                <div class="card-body text-center py-5">
                    <span class="empty-icon"><i class="bi bi-search"></i></span>
                    <h5 class="mt-3 mb-1">Ничего не найдено</h5>
                    <p class="text-muted mb-3">Попробуйте изменить запрос, выбрать другую категорию или сбросить фильтры.</p>
                    <a href="/#catalog" class="btn btn-outline-brand btn-sm">
                        <i class="bi bi-arrow-counterclockwise me-1"></i><?= $hasFilters ? 'Сбросить фильтры' : 'Обновить каталог' ?>
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
                <?php foreach ($items as $p): ?>
                    <?php
                    $own = $isAuth && (int)$p['company_id'] === $currentCompanyId;
                    $inStock = (int)$p['in_stock'] > 0;
                    $recReasons = $recMap[(int)$p['id']] ?? null;
                    $wasOrdered = isset($orderedBefore[(int)$p['id']]);
                    ?>
                    <div class="col">
                        <div class="card product-card shadow-sm h-100">
                            <div class="product-card-head">
                                <span class="product-thumb" aria-hidden="true"><i class="bi bi-box-seam"></i></span>
                                <div class="d-flex flex-column align-items-end gap-1">
                                    <?php if ($recReasons): ?>
                                        <span class="rec-badge" title="<?= e($recReasons[0]['text'] ?? '') ?>"><i class="bi bi-stars me-1"></i>Рекомендовано</span>
                                    <?php endif; ?>
                                    <?php if ($inStock): ?>
                                        <span class="badge text-bg-success text-nowrap">В наличии</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary text-nowrap">Нет в наличии</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body d-flex flex-column pt-2">
                                <h5 class="product-title mb-1"><?= e($p['name']) ?></h5>
                                <div class="product-meta small text-muted mb-2">
                                    <span><i class="bi bi-building me-1"></i><?= e($p['company_name']) ?></span>
                                    <?php if ($p['category_name']): ?><span class="product-meta-sep">·</span><span><?= e($p['category_name']) ?></span><?php endif; ?>
                                    <?php if ($p['sku']): ?><span class="product-meta-sep">·</span><span>арт. <?= e($p['sku']) ?></span><?php endif; ?>
                                </div>
                                <?php if ($recReasons): ?>
                                    <div class="small text-brand mb-2"><i class="bi bi-stars me-1"></i><?= e($recReasons[0]['text']) ?></div>
                                <?php endif; ?>
                                <?php if ($p['description']): ?>
                                    <p class="product-desc small text-muted mb-0"><?= e(mb_strimwidth($p['description'], 0, 110, '…')) ?></p>
                                <?php endif; ?>

                                <div class="mt-auto pt-3">
                                    <div class="product-price-row d-flex justify-content-between align-items-end mb-3">
                                        <div>
                                            <div class="product-price fs-5 fw-bold text-brand text-nowrap"><?= e(formatPrice($p['price'])) ?></div>
                                            <div class="small text-muted">за <?= e($p['unit']) ?></div>
                                        </div>
                                        <div class="small text-muted text-end">
                                            Мин. заказ<br>
                                            <strong class="text-body"><?= number_format((int)$p['min_order'], 0, ',', ' ') ?> <?= e($p['unit']) ?></strong>
                                        </div>
                                    </div>

                                    <?php if ($own): ?>
                                        <button type="button" class="btn btn-outline-secondary w-100" disabled>Ваш товар</button>
                                    <?php elseif (!$inStock && $isAuth && $wasOrdered): ?>
                                        <a href="/account#deals-done" class="btn btn-outline-brand w-100">
                                            <i class="bi bi-clock-history me-1"></i>Заказать из истории
                                        </a>
                                    <?php elseif (!$inStock): ?>
                                        <button type="button" class="btn btn-outline-secondary w-100" disabled>Нет в наличии</button>
                                    <?php elseif ($isAuth && !canCompany('create_deals')): ?>
                                        <button type="button" class="btn btn-outline-secondary w-100" disabled>Нет права на закупки</button>
                                    <?php elseif ($isAuth): ?>
                                        <a href="/checkout?product_id=<?= (int)$p['id'] ?>" class="btn btn-brand w-100">
                                            <i class="bi bi-cart-plus me-1"></i>Купить
                                        </a>
                                    <?php else: ?>
                                        <a href="/checkout?product_id=<?= (int)$p['id'] ?>" class="btn btn-outline-brand w-100">
                                            Войти и купить
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($pages > 1): ?>
                <?php $from = max(1, $page - 2); $to = min($pages, $page + 2); ?>
                <nav class="mt-4" aria-label="Страницы каталога">
                    <ul class="pagination justify-content-center flex-wrap mb-0">
                        <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>">
                            <?php if ($page <= 1): ?>
                                <span class="page-link" aria-disabled="true">&laquo;</span>
                            <?php else: ?>
                                <a class="page-link" href="<?= e($url(['page' => $page - 1 > 1 ? $page - 1 : ''])) ?>" aria-label="Предыдущая страница">&laquo;</a>
                            <?php endif; ?>
                        </li>
                        <?php if ($from > 1): ?>
                            <li class="page-item"><a class="page-link" href="<?= e($url()) ?>" aria-label="Страница 1">1</a></li>
                            <?php if ($from > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                        <?php endif; ?>
                        <?php for ($i = $from; $i <= $to; $i++): ?>
                            <li class="page-item<?= $i === $page ? ' active' : '' ?>">
                                <a class="page-link" href="<?= e($url(['page' => $i > 1 ? $i : ''])) ?>" aria-label="Страница <?= $i ?>"<?= $i === $page ? ' aria-current="page"' : '' ?>><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <?php if ($to < $pages): ?>
                            <?php if ($to < $pages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                            <li class="page-item"><a class="page-link" href="<?= e($url(['page' => $pages])) ?>" aria-label="Страница <?= (int)$pages ?>"><?= (int)$pages ?></a></li>
                        <?php endif; ?>
                        <li class="page-item<?= $page >= $pages ? ' disabled' : '' ?>">
                            <?php if ($page >= $pages): ?>
                                <span class="page-link" aria-disabled="true">&raquo;</span>
                            <?php else: ?>
                                <a class="page-link" href="<?= e($url(['page' => $page + 1])) ?>" aria-label="Следующая страница">&raquo;</a>
                            <?php endif; ?>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>

<?php require BASE_PATH . '/views/layout/footer.php'; ?>
