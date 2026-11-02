<?php
declare(strict_types=1);

/**
 * Отправка писем через mail().
 *
 * Настройки берутся из config/mail.php (необязательный файл):
 *   ['from' => 'no-reply@example.ru', 'from_name' => 'B2B Портал', 'app_url' => 'https://example.ru']
 * Если файла нет, адрес отправителя собирается из имени хоста, а ссылки —
 * из текущего домена. Для продакшена лучше задать app_url явно: иначе ссылка
 * в письме зависит от того, как сервер определил имя хоста.
 */
final class Mailer
{
    private static ?array $config = null;

    public static function config(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        $defaults = [
            'from'      => 'no-reply@' . self::host(),
            'from_name' => 'B2B Портал',
            'app_url'   => self::baseUrl(),
        ];

        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
        $file = $base . '/config/mail.php';
        $custom = is_file($file) ? (require $file) : [];

        return self::$config = array_merge($defaults, is_array($custom) ? $custom : []);
    }

    /** Имя хоста без порта и небезопасных символов. */
    public static function host(): string
    {
        $host = (string)($_SERVER['SERVER_NAME'] ?? '');
        if ($host === '') {
            $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        }
        $host = preg_replace('/:\d+$/', '', $host) ?? '';
        $host = preg_replace('/[^A-Za-z0-9.\-]/', '', $host) ?? '';
        return $host !== '' ? $host : 'localhost';
    }

    /** Базовый адрес сайта для ссылок в письмах. */
    public static function baseUrl(): string
    {
        $isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
            || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
        return ($isHttps ? 'https://' : 'http://') . self::host();
    }

    /** @return bool принял ли сервер письмо к отправке */
    public static function send(string $to, string $subject, string $text): bool
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            error_log('Mailer: некорректный адрес получателя');
            return false;
        }

        $cfg = self::config();
        $from = (string)$cfg['from'];
        $fromName = (string)$cfg['from_name'];

        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
            error_log('Mailer: некорректный адрес отправителя ' . $from);
            return false;
        }

        $headers = [
            'From: ' . mb_encode_mimeheader($fromName, 'UTF-8', 'B') . ' <' . $from . '>',
            'Reply-To: ' . $from,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'X-Mailer: B2B Portal',
        ];

        $subjectEncoded = mb_encode_mimeheader($subject, 'UTF-8', 'B');
        $body = str_replace(["\r\n", "\r"], "\n", $text);

        // -f задаёт конверт отправителя: без него на shared-хостинге письмо часто режется спам-фильтром.
        $ok = @mail($to, $subjectEncoded, $body, implode("\r\n", $headers), '-f' . $from);

        if (!$ok) {
            error_log('Mailer: mail() вернул false для ' . $to);
        }

        return (bool)$ok;
    }
}
