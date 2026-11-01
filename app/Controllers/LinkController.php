<?php
/** Несколько компаний у одного человека: привязка аккаунтов и переключение между ними. */
class LinkController
{
    private const MAX_FAILS = 6;
    private const FAIL_WINDOW = 900;   // секунд

    public function __construct(private PDO $pdo) {}

    private function requireMember(): void
    {
        if (!isset($_SESSION['user_id'], $_SESSION['company_id'])) {
            $_SESSION['after_login'] = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' ? ($_SERVER['REQUEST_URI'] ?? '/account/link') : '/account';
            $_SESSION['flash_error'] = 'Войдите в аккаунт компании.';
            header('Location: /login');
            exit;
        }
        if (isStaffRole($_SESSION['role'] ?? '')) {
            header('Location: ' . staffHome($_SESSION['role'] ?? ''));
            exit;
        }
    }

    private function back(string $key, string $message, string $to = '/account/link'): never
    {
        $_SESSION[$key] = $message;
        header('Location: ' . $to);
        exit;
    }

    private function requirePost(): void
    {
        $this->requireMember();
        if (!csrfValid()) {
            $this->back('flash_error', 'Сессия устарела. Обновите страницу и повторите действие.');
        }
    }

    /** Страница «Мои компании». */
    public function form(): void
    {
        $this->requireMember();
        $linked = [];
        $dbNotice = '';

        try {
            $stmt = $this->pdo->prepare(
                "SELECT u.id, u.full_name, u.email, u.company_role, u.is_active, u.join_status, c.name AS company_name
                 FROM user_links l
                 JOIN users u ON u.id = l.linked_user_id
                 JOIN companies c ON c.id = u.company_id
                 WHERE l.user_id = ?
                 ORDER BY c.name"
            );
            $stmt->execute([$_SESSION['user_id']]);
            $linked = $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('Links page failed: ' . $e->getMessage());
            $dbNotice = 'Не удалось загрузить список. Проверьте, что выполнен актуальный SQL-скрипт (таблица user_links).';
        }

        $pageTitle = 'Мои компании';
        require BASE_PATH . '/views/account/link.php';
    }

    /** Привязать аккаунт другой компании: нужны его email и пароль. */
    public function link(): void
    {
        $this->requirePost();

        $fails = $_SESSION['link_fails'] ?? ['n' => 0, 't' => time()];
        if (time() - (int)$fails['t'] > self::FAIL_WINDOW) {
            $fails = ['n' => 0, 't' => time()];
        }
        if ((int)$fails['n'] >= self::MAX_FAILS) {
            $this->back('flash_error', 'Слишком много неверных попыток. Попробуйте через 15 минут.');
        }

        $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
        $pass = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        if ($email === '' || $pass === '') {
            $this->back('flash_error', 'Введите email и пароль аккаунта другой компании.');
        }

        try {
            $stmt = $this->pdo->prepare(
                'SELECT u.id, u.password_hash, u.role, u.is_active, u.join_status, u.company_id, c.name AS company_name
                 FROM users u JOIN companies c ON c.id = u.company_id
                 WHERE u.email = ? LIMIT 1'
            );
            $stmt->execute([$email]);
            $target = $stmt->fetch();

            if (!$target || !password_verify($pass, $target['password_hash'])) {
                $fails['n'] = (int)$fails['n'] + 1;
                $_SESSION['link_fails'] = $fails;
                $this->back('flash_error', 'Неверный email или пароль.');
            }
            unset($_SESSION['link_fails']);

            if ((int)$target['id'] === (int)$_SESSION['user_id']) {
                $this->back('flash_error', 'Это ваш текущий аккаунт.');
            }
            if (isStaffRole($target['role']) || (int)$target['is_active'] !== 1 || $target['join_status'] !== 'approved') {
                $this->back('flash_error', 'Этот аккаунт нельзя привязать.');
            }
            if ((int)$target['company_id'] === (int)$_SESSION['company_id']) {
                $this->back('flash_error', 'Этот аккаунт относится к той же компании.');
            }

            $stmt = $this->pdo->prepare('INSERT IGNORE INTO user_links (user_id, linked_user_id) VALUES (?, ?), (?, ?)');
            $stmt->execute([$_SESSION['user_id'], $target['id'], $target['id'], $_SESSION['user_id']]);
        } catch (PDOException $e) {
            error_log('Link failed: ' . $e->getMessage());
            $this->back('flash_error', 'Не удалось привязать аккаунт. Попробуйте позже.');
        }

        $this->back('flash_success', 'Компания «' . $target['company_name'] . '» привязана. Переключаться между компаниями можно в меню справа вверху.');
    }

    /** Переключиться на аккаунт другой привязанной компании. */
    public function switchTo(): void
    {
        $this->requirePost();
        $targetId = (int)($_POST['user_id'] ?? 0);

        $stmt = $this->pdo->prepare(
            "SELECT u.id, u.company_id, u.role, u.company_role, u.permissions, u.join_status, u.is_active, c.name AS company_name
             FROM user_links l
             JOIN users u ON u.id = l.linked_user_id
             JOIN companies c ON c.id = u.company_id
             WHERE l.user_id = ? AND l.linked_user_id = ? LIMIT 1"
        );
        $stmt->execute([$_SESSION['user_id'], $targetId]);
        $t = $stmt->fetch();

        if (!$t || (int)$t['is_active'] !== 1 || $t['join_status'] !== 'approved' || isStaffRole($t['role'])) {
            $this->back('flash_error', 'Переключиться на эту компанию нельзя.');
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$t['id'];
        $_SESSION['company_id'] = (int)$t['company_id'];
        $_SESSION['role'] = $t['role'];
        $_SESSION['company_name'] = $t['company_name'];
        $_SESSION['company_role'] = $t['company_role'];
        $_SESSION['permissions'] = $t['permissions'];
        $_SESSION['join_status'] = $t['join_status'];

        $this->back('flash_success', 'Теперь вы работаете от имени «' . $t['company_name'] . '».', '/account');
    }

    /** Отвязать компанию (аккаунт при этом остаётся). */
    public function unlink(): void
    {
        $this->requirePost();
        $targetId = (int)($_POST['user_id'] ?? 0);
        $me = (int)$_SESSION['user_id'];

        $stmt = $this->pdo->prepare('DELETE FROM user_links WHERE (user_id = ? AND linked_user_id = ?) OR (user_id = ? AND linked_user_id = ?)');
        $stmt->execute([$me, $targetId, $targetId, $me]);

        $this->back($stmt->rowCount() ? 'flash_success' : 'flash_error', $stmt->rowCount() ? 'Компания отвязана.' : 'Привязка не найдена.');
    }
}
