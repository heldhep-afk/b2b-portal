<?php
declare(strict_types=1);

/**
 * Подключение к MySQL.
 *
 * Это шаблон: скопируйте файл в config/db.php и укажите свои параметры.
 *   copy config/db.example.php config/db.php        (Windows)
 *   cp   config/db.example.php config/db.php        (Linux/macOS)
 *
 * config/db.php в репозиторий не попадает (.gitignore) — в нём реальные
 * логин и пароль от базы.
 *
 * Параметры можно задать и переменными окружения (удобно для хостинга):
 *   DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, APP_DEBUG=1
 * Переменные окружения имеют приоритет над значениями по умолчанию.
 *
 * Файл создаёт глобальный $pdo, который используют контроллеры
 * и маршрутизатор (см. public_html/index.php).
 */

$dbHost = getenv('DB_HOST');
$dbHost = $dbHost === false || $dbHost === '' ? 'localhost' : $dbHost;

$dbPort = getenv('DB_PORT');
$dbPort = $dbPort === false || $dbPort === '' ? '3306' : $dbPort;

$dbName = getenv('DB_NAME');
$dbName = $dbName === false || $dbName === '' ? 'b2b_portal' : $dbName;

$dbUser = getenv('DB_USER');
$dbUser = $dbUser === false || $dbUser === '' ? 'root' : $dbUser;

$dbPass = getenv('DB_PASS');
$dbPass = $dbPass === false ? '' : $dbPass;

$dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
    ]);
} catch (Throwable $e) {
    error_log('B2B Portal: database connection failed: ' . $e->getMessage());

    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');

    // Подробности (хост, имя базы, подсказки) показываем только при отладке:
    // посетителю они не нужны и раскрывают внутреннюю конфигурацию.
    $debug = getenv('APP_DEBUG') === '1';
    $host = $debug ? htmlspecialchars((string)$dbHost, ENT_QUOTES, 'UTF-8') : '';
    $name = $debug ? htmlspecialchars((string)$dbName, ENT_QUOTES, 'UTF-8') : '';

    $details = '';
    if ($debug) {
        $driverHint = in_array('mysql', PDO::getAvailableDrivers(), true)
            ? ''
            : '<li>Включите расширение <code>pdo_mysql</code> в <code>php.ini</code> '
              . '(раскомментируйте строку <code>extension=pdo_mysql</code>).</li>';

        $details = '<p>Сервер не смог подключиться к MySQL <code>' . $host . '</code>, база <code>' . $name . '</code>.</p>'
            . '<ol><li>Убедитесь, что MySQL запущен.</li>'
            . $driverHint
            . '<li>Создайте базу данных и импортируйте SQL-схему.</li>'
            . '<li>Проверьте параметры подключения в <code>config/db.php</code> '
            . 'или переменные окружения <code>DB_HOST</code>, <code>DB_NAME</code>, <code>DB_USER</code>, <code>DB_PASS</code>.</li></ol>';
    } else {
        $details = '<p>Сервис временно недоступен. Мы уже знаем о проблеме — попробуйте обновить страницу через пару минут.</p>';
    }

    echo '<!doctype html><html lang="ru"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Сервис недоступен</title>'
        . '<style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;'
        . 'background:#f5f5f7;color:#1d1d1f;margin:0;min-height:100vh;display:grid;place-items:center;padding:2rem}'
        . '.card{background:#fff;border-radius:20px;padding:2rem;max-width:640px;width:100%;'
        . 'box-shadow:0 18px 56px rgba(0,0,0,.12)}h1{font-size:1.5rem;margin:0 0 .75rem}'
        . 'p{line-height:1.55;color:#6e6e73;margin:.5rem 0}code{background:#f5f5f7;border-radius:6px;'
        . 'padding:.15rem .4rem;color:#92223b}ol{line-height:1.7;color:#3a3a3c}</style></head><body>'
        . '<div class="card"><h1>Сервис временно недоступен</h1>'
        . $details
        . '</div></body></html>';

    exit;
}
