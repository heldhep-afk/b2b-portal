<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

// ---- Ошибки посетителям не показываем: только в лог сервера ----
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// ---- Сессия: HttpOnly + SameSite, строгий режим, защита от фиксации ----
$isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
    || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', '86400');
    session_start();
}

// ---- Заголовки безопасности ----
if (!headers_sent()) {
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()');
    if ($isHttps) {
        // Имеет смысл только на доверенном origin (HTTPS), иначе браузер ругается в консоли.
        header('Cross-Origin-Opener-Policy: same-origin');
    }
    header(
        "Content-Security-Policy: default-src 'self'; "
        . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; "
        . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; "
        . "img-src 'self' data: https:; "
        . "font-src 'self' data: https://cdn.jsdelivr.net; "
        . "connect-src 'self'; "
        . "frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'"
    );
}

require_once BASE_PATH . '/config/db.php';
require_once BASE_PATH . '/app/Core/helpers.php';
require_once BASE_PATH . '/app/Core/Security.php';
require_once BASE_PATH . '/app/Core/Router.php';

// ---- Тайм-аут бездействия и периодическая смена идентификатора сессии ----
const SESSION_IDLE_LIMIT = 4 * 3600;   // 4 часа без активности
const SESSION_ID_ROTATE  = 1800;       // менять ID сессии каждые 30 минут

if (isset($_SESSION['user_id'])) {
    $now = time();
    $last = (int)($_SESSION['_last_activity'] ?? $now);

    if ($now - $last > SESSION_IDLE_LIMIT) {
        $_SESSION = [];
        $_SESSION['flash_error'] = 'Сессия истекла из-за неактивности. Войдите снова.';
    } else {
        $_SESSION['_last_activity'] = $now;
        $regen = (int)($_SESSION['_id_rotated'] ?? 0);
        if ($regen === 0) {
            $_SESSION['_id_rotated'] = $now;
        } elseif ($now - $regen > SESSION_ID_ROTATE) {
            session_regenerate_id(true);
            $_SESSION['_id_rotated'] = $now;
        }
    }
}

// Актуальные данные пользователя при каждом запросе: компания, роль, активность.
// Отключённого владельцем сотрудника сразу выводим из системы.
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare('SELECT company_id, role, company_role, permissions, join_status, is_active FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        $current = $stmt->fetch();

        if (!$current || (int)$current['is_active'] !== 1) {
            $_SESSION = [];
            $_SESSION['flash_error'] = 'Доступ к аккаунту закрыт. Обратитесь к владельцу компании.';
        } else {
            $_SESSION['company_id'] = (int)$current['company_id'];
            $_SESSION['role'] = $current['role'];
            $_SESSION['company_role'] = $current['company_role'];
            $_SESSION['permissions'] = $current['permissions'];   // NULL — права роли
            $_SESSION['join_status'] = $current['join_status'];   // approved / pending / rejected
        }
    } catch (PDOException $e) {
        error_log('Session refresh failed: ' . $e->getMessage());
    }
}

// Сотрудник, чья заявка ещё не одобрена, видит только страницу ожидания.
if (($_SESSION['join_status'] ?? 'approved') !== 'approved') {
    $reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if (!in_array($reqPath, ['/pending', '/pending/code', '/logout'], true)) {
        header('Location: /pending');
        exit;
    }
}

// Аккаунты других компаний того же человека — для переключателя в шапке.
$GLOBALS['linkedAccounts'] = [];
if (isset($_SESSION['user_id']) && ($_SESSION['join_status'] ?? 'approved') === 'approved') {
    try {
        $stmt = $pdo->prepare(
            "SELECT u.id, u.company_role, c.name AS company_name
             FROM user_links l
             JOIN users u ON u.id = l.linked_user_id
             JOIN companies c ON c.id = u.company_id
             WHERE l.user_id = ? AND u.is_active = 1 AND u.join_status = 'approved' AND u.role NOT IN ('admin', 'inspector')
             ORDER BY c.name"
        );
        $stmt->execute([$_SESSION['user_id']]);
        $GLOBALS['linkedAccounts'] = $stmt->fetchAll();
    } catch (PDOException $e) {
        // таблицы user_links ещё нет — переключатель просто не показывается
    }
}

$router = new Router();

$router->add('GET', '/', 'HomeController', 'index');
$router->add('GET', '/catalog', 'HomeController', 'index');
$router->add('GET', '/checkout', 'CheckoutController', 'show');
$router->add('POST', '/checkout', 'CheckoutController', 'store');
$router->add('GET', '/team', 'TeamController', 'index');
$router->add('POST', '/team/requests/decide', 'TeamController', 'decide');
$router->add('POST', '/team/code/regenerate', 'TeamController', 'regenerateCode');
$router->add('POST', '/team/code/toggle', 'TeamController', 'toggleCode');
$router->add('POST', '/team/member/role', 'TeamController', 'changeRole');
$router->add('POST', '/team/permissions', 'TeamController', 'savePermissions');
$router->add('POST', '/team/member/toggle', 'TeamController', 'toggle');
$router->add('GET', '/join', 'JoinController', 'join');
$router->add('POST', '/join', 'JoinController', 'join');
$router->add('GET', '/pending', 'JoinController', 'pending');
$router->add('POST', '/pending/code', 'JoinController', 'changeCode');
$router->add('GET', '/login', 'AuthController', 'login');
$router->add('POST', '/login', 'AuthController', 'login');
$router->add('GET', '/register', 'AuthController', 'register');
$router->add('POST', '/register', 'AuthController', 'register');
$router->add('GET', '/profile', 'ProfileController', 'index');
$router->add('POST', '/profile/edit', 'ProfileController', 'edit');
$router->add('GET', '/logout', 'ProfileController', 'logout');

$router->add('GET', '/account/link', 'LinkController', 'form');
$router->add('POST', '/account/link', 'LinkController', 'link');
$router->add('POST', '/account/switch', 'LinkController', 'switchTo');
$router->add('POST', '/account/unlink', 'LinkController', 'unlink');
$router->add('GET', '/account', 'AccountController', 'index');
$router->add('POST', '/account/categories', 'AccountController', 'saveCategories');

$router->add('POST', '/deals/decision', 'DealController', 'decide');

$router->add('GET', '/admin', 'AdminController', 'index');
$router->add('GET', '/admin/stats', 'AdminController', 'stats');
$router->add('POST', '/admin/deals/action', 'AdminController', 'act');

// Персонал платформы и проверки качества
$router->add('GET', '/admin/staff', 'StaffController', 'index');
$router->add('POST', '/admin/staff/add', 'StaffController', 'add');
$router->add('POST', '/admin/staff/toggle', 'StaffController', 'toggle');
$router->add('POST', '/admin/staff/password', 'StaffController', 'password');
$router->add('GET', '/admin/inspections', 'StaffController', 'inspections');
$router->add('GET', '/admin/inspections/view', 'StaffController', 'inspection');
$router->add('POST', '/admin/inspections/action', 'StaffController', 'act');
$router->add('GET', '/inspector/apply', 'InspectorApplyController', 'apply');
$router->add('POST', '/inspector/apply', 'InspectorApplyController', 'apply');
$router->add('POST', '/admin/staff/application', 'StaffController', 'application');
$router->add('GET', '/inspector', 'InspectorController', 'index');
$router->add('GET', '/inspector/task', 'InspectorController', 'task');
$router->add('POST', '/inspector/take', 'InspectorController', 'take');
$router->add('POST', '/inspector/report', 'InspectorController', 'report');
$router->add('POST', '/inspector/visit', 'InspectorController', 'visit');
$router->add('POST', '/inspections/visit', 'InspectionController', 'visit');
$router->add('POST', '/inspections/order', 'InspectionController', 'order');
$router->add('POST', '/inspections/cancel', 'InspectionController', 'cancel');

$router->add('GET', '/company', 'CompanyController', 'index');
$router->add('POST', '/company/products/add', 'CompanyController', 'addProduct');
$router->add('POST', '/company/products/delete', 'CompanyController', 'deleteProduct');

$router->dispatch($_SERVER['REQUEST_URI'] ?? '/', $_SERVER['REQUEST_METHOD'] ?? 'GET');
