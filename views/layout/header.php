<?php

$pageTitle = $pageTitle ?? 'B2B Портал';
$isAuth    = isset($_SESSION['user_id']);
$isAdmin   = $isAuth && ($_SESSION['role'] ?? '') === 'admin';
$isInspector = $isAuth && ($_SESSION['role'] ?? '') === 'inspector';
$isPending = $isAuth && ($_SESSION['join_status'] ?? 'approved') !== 'approved';
$path      = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';
$active    = fn(string $href): string => str_starts_with($path, $href) ? ' active' : '';
$current   = fn(bool $on): string => $on ? ' aria-current="page"' : '';
$catalogOn = $path === '/' || str_starts_with($path, '/checkout') || str_starts_with($path, '/cart');

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?= e($pageTitle) ?> — B2B Портал</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script>
        /* тема применяется до отрисовки, чтобы страница не мигала */
        (function () {
            try {
                var t = localStorage.getItem('theme') || 'auto';
                var dark = t === 'dark' || (t === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
            } catch (e) {}
        })();
    </script>
    <link href="/css/theme.css?v=15" rel="stylesheet">
    <!-- build 2026-10-31-a -->
    <style>
        /* критичные исправления дублируют theme.css: действуют даже при закешированных стилях */
        .auth-aside, .auth-aside h1, .auth-aside h2, .auth-aside h3, .auth-aside h4, .auth-aside h5, .auth-aside h6,
        .auth-aside p, .auth-aside strong, .auth-aside .small, .auth-aside .perk { color: #fff !important; }
        .stat-card .card-body { padding-right: 2rem !important; }
    </style>
    <meta name="theme-color" content="#f5f5f7">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
</head>
<body id="top" class="bg-light d-flex flex-column min-vh-100">

<a class="skip-link" href="#main-content">Перейти к содержимому</a>

<nav class="navbar navbar-expand-lg navbar-apple sticky-top">
    <div class="container">
        <a class="navbar-brand" href="/">
            <i class="bi bi-buildings me-1"></i>B2B Портал
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Меню">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <?php if ($isAuth): ?>
                <?php if (!$isPending): ?>
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link<?= $catalogOn ? ' active' : '' ?>" href="/"<?= $current($catalogOn) ?>>
                            <i class="bi bi-grid me-1"></i>Каталог
                        </a>
                    </li>
                    <?php if ($isAdmin): ?>
                    <li class="nav-item">
                        <a class="nav-link<?= $path === '/admin' ? ' active' : '' ?>" href="/admin">
                            <i class="bi bi-shield-lock me-1"></i>Админ-панель
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?= $active('/admin/stats') ?>" href="/admin/stats">
                            <i class="bi bi-graph-up me-1"></i>Статистика
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?= $active('/admin/inspections') ?>" href="/admin/inspections">
                            <i class="bi bi-patch-check me-1"></i>Проверки
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?= $active('/admin/staff') ?>" href="/admin/staff">
                            <i class="bi bi-person-badge me-1"></i>Персонал
                        </a>
                    </li>
                    <?php elseif ($isInspector): ?>
                    <li class="nav-item">
                        <a class="nav-link<?= $active('/inspector') ?>" href="/inspector">
                            <i class="bi bi-clipboard-check me-1"></i>Кабинет проверяющего
                        </a>
                    </li>
                    <?php else: ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle<?= $active('/account') ?>" href="/account" role="button"
                           data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-speedometer2 me-1"></i>Личный кабинет
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="/account#profile"><i class="bi bi-person-badge me-2"></i>Профиль и компания</a></li>
                            <li><a class="dropdown-item" href="/account#categories"><i class="bi bi-tags me-2"></i>Категории товаров</a></li>
                            <li><a class="dropdown-item" href="/account#deals"><i class="bi bi-file-earmark-text me-2"></i>Мои сделки</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/account#deals-awaiting"><i class="bi bi-hourglass-split me-2"></i>Ожидают решения</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle<?= $active('/company') . $active('/team') ?>" href="/company" role="button"
                           data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-building me-1"></i>Моя компания
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="/company"><i class="bi bi-info-circle me-2"></i>Данные компании</a></li>
                            <li><a class="dropdown-item" href="/company#products"><i class="bi bi-box-seam me-2"></i>Мои товары</a></li>
                            <li><a class="dropdown-item" href="/company#addProduct"><i class="bi bi-plus-circle me-2"></i>Добавить товар</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/team"><i class="bi bi-people me-2"></i>Сотрудники</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link<?= $active('/profile') ?>" href="/profile"<?= $current(str_starts_with($path, '/profile')) ?>>
                            <i class="bi bi-person-gear me-1"></i>Профиль
                        </a>
                    </li>
                </ul>
                <?php else: ?>
                <ul class="navbar-nav me-auto mb-2 mb-lg-0"></ul>
                <?php endif; ?>
                <div class="nav-actions d-flex align-items-center gap-2 gap-lg-3 flex-wrap">
                    <?php if (!$isAdmin && !$isInspector && !$isPending): ?>
                        <?php $cartCount = class_exists('Cart') ? Cart::count() : 0; ?>
                        <a href="/cart" class="btn btn-tinted btn-sm position-relative cart-link"
                           aria-label="Заявка<?= $cartCount > 0 ? ': позиций ' . $cartCount : '' ?>" title="Заявка">
                            <i class="bi bi-cart3"></i>
                            <?php if ($cartCount > 0): ?>
                                <span class="badge rounded-pill text-bg-danger position-absolute top-0 start-100 translate-middle"
                                      style="font-size:.65rem"><?= $cartCount ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>
                    <button type="button" class="btn btn-tinted btn-sm theme-toggle" id="themeToggle" aria-label="Тема оформления" title="Тема: авто">
                        <i class="bi bi-circle-half"></i>
                    </button>
                    <?php if ($isAdmin || $isInspector || $isPending): ?>
                        <span class="small text-muted">
                            <i class="bi bi-person-circle me-1"></i><?= e($isInspector ? 'Проверяющий' : ($_SESSION['company_name'] ?? 'Компания')) ?>
                        </span>
                    <?php else: ?>
                        <?php $linkedAccounts = $GLOBALS['linkedAccounts'] ?? []; ?>
                        <div class="dropdown">
                            <button type="button" class="btn btn-tinted btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-building me-1"></i><?= e($_SESSION['company_name'] ?? 'Компания') ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><span class="dropdown-item-text small text-muted">Вы работаете от имени</span></li>
                                <li><span class="dropdown-item fw-semibold"><i class="bi bi-check2 me-2 text-brand"></i><?= e($_SESSION['company_name'] ?? '') ?></span></li>
                                <?php foreach ($linkedAccounts as $la): ?>
                                    <li>
                                        <form method="POST" action="/account/switch" class="m-0">
                                            <?= csrfField() ?>
                                            <button type="submit" name="user_id" value="<?= (int)$la['id'] ?>" class="dropdown-item">
                                                <i class="bi bi-arrow-left-right me-2"></i><?= e($la['company_name']) ?>
                                                <small class="text-muted ms-1">· <?= e(companyRoleLabel($la['company_role'])) ?></small>
                                            </button>
                                        </form>
                                    </li>
                                <?php endforeach; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="/account/link"><i class="bi bi-buildings me-2"></i>Мои компании</a></li>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <a href="/logout" class="btn btn-tinted btn-sm">
                        <i class="bi bi-box-arrow-right me-1"></i>Выйти
                    </a>
                </div>
            <?php else: ?>
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link<?= $catalogOn ? ' active' : '' ?>" href="/"<?= $current($catalogOn) ?>>
                            <i class="bi bi-grid me-1"></i>Каталог
                        </a>
                    </li>
                </ul>
                <div class="nav-actions d-flex align-items-center flex-wrap gap-2 pb-2 pb-lg-0">
                    <?php $cartCount = class_exists('Cart') ? Cart::count() : 0; ?>
                    <a href="/cart" class="btn btn-tinted btn-sm position-relative cart-link"
                       aria-label="Заявка<?= $cartCount > 0 ? ': позиций ' . $cartCount : '' ?>" title="Заявка">
                        <i class="bi bi-cart3"></i>
                        <?php if ($cartCount > 0): ?>
                            <span class="badge rounded-pill text-bg-danger position-absolute top-0 start-100 translate-middle"
                                  style="font-size:.65rem"><?= $cartCount ?></span>
                        <?php endif; ?>
                    </a>
                    <button type="button" class="btn btn-tinted btn-sm theme-toggle" id="themeToggle" aria-label="Тема оформления" title="Тема: авто">
                        <i class="bi bi-circle-half"></i>
                    </button>
                    <a class="btn btn-tinted btn-sm" href="/login">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Войти
                    </a>
                    <a class="btn btn-outline-brand btn-sm" href="/inspector/apply" title="Стать проверяющим">
                        <i class="bi bi-clipboard-check me-1"></i><span class="d-none d-md-inline">Стать проверяющим</span><span class="d-md-none">Проверяющим</span>
                    </a>
                    <a class="btn btn-brand btn-sm" href="/register">
                        <i class="bi bi-person-plus me-1"></i>Регистрация
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</nav>

<main id="main-content" class="container py-4 flex-grow-1" tabindex="-1">
    <?php if ($flashSuccess): ?>
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-start gap-2" role="alert">
            <i class="bi bi-check-circle-fill mt-1" aria-hidden="true"></i>
            <div class="flex-grow-1"><?= e($flashSuccess) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"></button>
        </div>
    <?php endif; ?>
    <?php if ($flashError): ?>
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-start gap-2" role="alert">
            <i class="bi bi-exclamation-triangle-fill mt-1" aria-hidden="true"></i>
            <div class="flex-grow-1"><?= e($flashError) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"></button>
        </div>
    <?php endif; ?>
