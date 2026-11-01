<?php
declare(strict_types=1);

/**
 * Базовые средства защиты приложения.
 *
 * RateLimiter — ограничение частоты попыток (вход, заявки, коды).
 * Счётчики хранятся в файлах вне веб-корня (BASE_PATH/storage/ratelimit)
 * и защищены flock, поэтому не зависят от сессии: очистка cookie лимит не сбрасывает.
 *
 * Если каталог недоступен для записи, ограничение молча отключается —
 * лучше пустить пользователя, чем сломать вход на сайте.
 */
final class RateLimiter
{
    private static ?string $dir = null;

    /** IP клиента. Доверяем только REMOTE_ADDR, заголовки подделываются. */
    public static function clientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        return is_string($ip) && $ip !== '' ? $ip : 'unknown';
    }

    /** Каталог счётчиков (создаётся при первом обращении). */
    private static function dir(): string
    {
        if (self::$dir !== null) {
            return self::$dir;
        }

        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
        $dir = $base . '/storage/ratelimit';

        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            error_log('RateLimiter: не удалось создать каталог ' . $dir . ' — ограничение отключено.');
            return self::$dir = '';
        }
        if (!is_writable($dir)) {
            error_log('RateLimiter: каталог ' . $dir . ' недоступен для записи — ограничение отключено.');
            return self::$dir = '';
        }

        // На случай, если storage когда-нибудь окажется внутри веб-корня.
        if (!is_file($dir . '/.htaccess')) {
            @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
        }

        return self::$dir = $dir;
    }

    private static function file(string $key): string
    {
        $dir = self::dir();
        return $dir === '' ? '' : $dir . '/' . hash('sha256', $key) . '.json';
    }

    /**
     * Читает и обновляет список отметок времени под блокировкой файла.
     *
     * @param callable(int[]):int[]|null $mutate
     * @return int[] актуальные отметки
     */
    private static function update(string $key, int $window, ?callable $mutate = null): array
    {
        $file = self::file($key);
        if ($file === '') {
            return [];
        }

        $fh = @fopen($file, 'c+');
        if (!$fh) {
            return [];
        }

        try {
            if (!flock($fh, LOCK_EX)) {
                return [];
            }

            $raw = stream_get_contents($fh);
            $data = is_string($raw) ? json_decode($raw, true) : null;
            $hits = is_array($data['hits'] ?? null) ? array_map('intval', $data['hits']) : [];

            $cutoff = time() - $window;
            $hits = array_values(array_filter($hits, static fn(int $t): bool => $t > $cutoff));

            if ($mutate !== null) {
                $hits = $mutate($hits);
            }

            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, (string)json_encode(['hits' => $hits]));
            fflush($fh);

            return $hits;
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    /** Сколько попыток за последние $window секунд. */
    public static function attempts(string $key, int $window): int
    {
        return count(self::update($key, $window));
    }

    /** Превышен ли лимит попыток. */
    public static function tooMany(string $key, int $max, int $window): bool
    {
        return self::attempts($key, $window) >= $max;
    }

    /** Отмечает попытку и возвращает новое количество за окно. */
    public static function hit(string $key, int $window): int
    {
        return count(self::update($key, $window, static fn(array $hits): array => [...$hits, time()]));
    }

    /** Сбрасывает счётчик (например, после успешного входа). */
    public static function clear(string $key): void
    {
        $file = self::file($key);
        if ($file !== '' && is_file($file)) {
            @unlink($file);
        }
    }

    /** Через сколько секунд счётчик опустится ниже лимита (для сообщения пользователю). */
    public static function retryAfter(string $key, int $max, int $window): int
    {
        $hits = self::update($key, $window);
        if (count($hits) < $max) {
            return 0;
        }
        sort($hits);
        $oldest = $hits[count($hits) - $max];
        return max(1, $oldest + $window - time());
    }
}
