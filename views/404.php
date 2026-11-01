<?php
$pageTitle = '404';
require BASE_PATH . '/views/layout/header.php';
?>
<div class="text-center py-5">
    <div class="empty-icon mx-auto mb-3"><i class="bi bi-compass"></i></div>
    <h1 class="display-4 fw-bold text-brand mb-2">404</h1>
    <p class="lead text-muted mb-4">Страница не найдена или была перемещена.</p>
    <div class="d-flex justify-content-center gap-2 flex-wrap">
        <a href="/" class="btn btn-brand"><i class="bi bi-house me-1"></i>На главную</a>
        <a href="/#catalog" class="btn btn-outline-brand"><i class="bi bi-grid me-1"></i>В каталог</a>
    </div>
</div>
<?php require BASE_PATH . '/views/layout/footer.php'; ?>
