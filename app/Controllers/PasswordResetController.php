<?php
declare(strict_types=1);

/**
 * Восстановление пароля.
 *
 * Схема: пользователь вводит email → в базу кладётся хеш одноразового токена,
 * в письме уходит сам токен. Ссылка живёт час и срабатывает один раз.
 * Ответ на запрос одинаковый для существующего и несуществующего email,
 * чтобы нельзя было проверить, зарегистрирован ли адрес.
 */
class PasswordResetController
{
    private const TOKEN_TTL  = 3600;   // 1 час
    private const MIN_LENGTH = 8;
    private const MAX_BYTES  = 72;     // ограничение bcrypt

    public function __construct(private PDO $pdo) {}

    /** Форма «Забыли пароль?» и отправка ссылки. */
    public function request(): void
    {
        if (isset($_SESSION['user_id'])) {
            header('Location: /');
            exit;
        }

        $sent     = false;
        $errorMsg = '';
        $oldEmail = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!csrfValid()) {
                $errorMsg = 'Сессия устарела. Обновите страницу и попробуйте снова.';
            } else {
                $email = is_string($_POST['email'] ?? null) ? mb_strtolower(trim($_POST['email'])) : '';
                $oldEmail = $email;

                if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
                    $errorMsg = 'Укажите корректный email, например name@company.ru.';
                } else {
                    $ipKey   = 'pwreset-ip:' . RateLimiter::clientIp();
                    $mailKey = 'pwreset-mail:' . $email;

                    if (RateLimiter::tooMany($ipKey, 10, 3600) || RateLimiter::tooMany($mailKey, 3, 900)) {
                        $errorMsg = 'Слишком много запросов на восстановление. Попробуйте через 15 минут.';
                    } else {
                        RateLimiter::hit($ipKey, 3600);
                        RateLimiter::hit($mailKey, 900);
                        $this->sendLink($email);
                        $sent = true;
                    }
                }
            }
        }

        $pageTitle = 'Восстановление пароля';
        require BASE_PATH . '/views/auth/forgot.php';
    }

    /** Страница установки нового пароля по ссылке из письма. */
    public function reset(): void
    {
        $raw = '';
        if (is_string($_POST['token'] ?? null)) {
            $raw = $_POST['token'];
        } elseif (is_string($_GET['token'] ?? null)) {
            $raw = $_GET['token'];
        }
        $token = strtolower(preg_replace('/[^a-f0-9]/i', '', $raw) ?? '');

        $row      = $token === '' ? null : $this->findToken($token);
        $errors   = [];
        $errorMsg = '';

        if ($row && $_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!csrfValid()) {
                $errorMsg = 'Сессия устарела. Обновите страницу и попробуйте снова.';
            } else {
                $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
                $confirm  = is_string($_POST['password_confirm'] ?? null) ? $_POST['password_confirm'] : '';

                if (mb_strlen($password) < self::MIN_LENGTH) {
                    $errors['password'] = 'Пароль — не короче ' . self::MIN_LENGTH . ' символов.';
                } elseif (strlen($password) > self::MAX_BYTES) {
                    $errors['password'] = 'Пароль слишком длинный: не более 72 байт.';
                } elseif ($password !== $confirm) {
                    $errors['password_confirm'] = 'Пароли не совпадают.';
                }

                if (!$errors) {
                    try {
                        $this->pdo->beginTransaction();

                        $this->pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ? LIMIT 1')
                            ->execute([password_hash($password, PASSWORD_DEFAULT), (int)$row['user_id']]);

                        $this->pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?')
                            ->execute([(int)$row['id']]);

                        // остальные ссылки этого пользователя больше не нужны
                        $this->pdo->prepare('DELETE FROM password_resets WHERE user_id = ? AND id <> ?')
                            ->execute([(int)$row['user_id'], (int)$row['id']]);

                        $this->pdo->commit();

                        RateLimiter::clear('pwreset-mail:' . mb_strtolower((string)$row['email']));
                        session_regenerate_id(true);
                        $_SESSION['flash_success'] = 'Пароль изменён. Войдите с новым паролем.';
                        header('Location: /login');
                        exit;
                    } catch (PDOException $e) {
                        if ($this->pdo->inTransaction()) {
                            $this->pdo->rollBack();
                        }
                        error_log('Password reset failed: ' . $e->getMessage());
                        $errorMsg = 'Не удалось сохранить новый пароль. Попробуйте позже.';
                    }
                }
            }
        }

        $valid = $row !== null;
        if (!$valid) {
            http_response_code(410);
        }
        $pageTitle = $valid ? 'Новый пароль' : 'Ссылка недействительна';
        require BASE_PATH . '/views/auth/reset.php';
    }

    /** Создаёт токен и отправляет письмо. Молча выходит, если аккаунта нет. */
    private function sendLink(string $email): void
    {
        try {
            $stmt = $this->pdo->prepare('SELECT id, full_name, username, is_active FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || (int)$user['is_active'] !== 1) {
                return;
            }

            $token = bin2hex(random_bytes(32));
            $hash  = hash('sha256', $token);

            // прежние неиспользованные ссылки становятся недействительными
            $this->pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([(int)$user['id']]);
            $this->pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at, ip) VALUES (?, ?, ?, ?)')
                ->execute([
                    (int)$user['id'],
                    $hash,
                    date('Y-m-d H:i:s', time() + self::TOKEN_TTL),
                    substr(RateLimiter::clientIp(), 0, 45),
                ]);

            $cfg  = Mailer::config();
            $link = rtrim((string)$cfg['app_url'], '/') . '/reset?token=' . $token;
            $name = trim((string)(($user['full_name'] ?? '') !== '' ? $user['full_name'] : $user['username']));

            $text = "Здравствуйте, {$name}!\n\n"
                . "Вы запросили восстановление пароля на портале B2B.\n"
                . "Ссылка действует один час и срабатывает один раз:\n\n"
                . $link . "\n\n"
                . "Если вы не запрашивали восстановление, просто проигнорируйте письмо: пароль останется прежним.\n\n"
                . "С уважением, B2B Портал";

            Mailer::send($email, 'Восстановление пароля — B2B Портал', $text);
        } catch (PDOException $e) {
            error_log('Password reset request failed: ' . $e->getMessage());
        }
    }

    private function findToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT pr.id, pr.user_id, u.email
             FROM password_resets pr
             JOIN users u ON u.id = pr.user_id
             WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW() AND u.is_active = 1
             LIMIT 1'
        );
        $stmt->execute([hash('sha256', $token)]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
