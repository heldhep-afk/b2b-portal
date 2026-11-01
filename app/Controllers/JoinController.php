<?php
/** Вступление сотрудника в компанию по коду: регистрация, ожидание решения, смена кода. */
class JoinController
{
    private const MAX_USERS = 20;      // активных сотрудников в компании
    private const MAX_FAILS = 8;       // неверных кодов за окно
    private const FAIL_WINDOW = 900;   // секунд

    public function __construct(private PDO $pdo) {}

    /** Регистрация сотрудника: личные данные и код компании. */
    public function join(): void
    {
        if (isset($_SESSION['user_id'])) {
            header('Location: ' . (($_SESSION['join_status'] ?? 'approved') === 'approved' ? '/account' : '/pending'));
            exit;
        }

        $errors = [];
        $errorMsg = '';
        $old = [];

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !csrfValid()) {
            $errorMsg = 'Сессия устарела. Обновите страницу и попробуйте снова.';
        } elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $in = $this->collect($_POST);
            $old = $in;
            $old['code'] = formatJoinCode($in['code']);
            unset($old['password'], $old['password_confirm']);

            $errors = $this->validate($in);

            $company = null;
            if (!isset($errors['code'])) {
                $company = $this->findCompany($in['code'], $codeError);
                if (!$company) {
                    $errors['code'] = $codeError;
                }
            }

            if (!$errors && $company) {
                try {
                    $stmt = $this->pdo->prepare(
                        "INSERT INTO users (company_id, username, full_name, phone, email, password_hash, role,
                                            company_role, join_status, join_requested_at)
                         VALUES (?, ?, ?, ?, ?, ?, 'client', 'employee', 'pending', NOW())"
                    );
                    $stmt->execute([
                        $company['id'], $in['username'], $in['contact_person'], $in['phone'],
                        $in['email'], password_hash($in['password'], PASSWORD_DEFAULT),
                    ]);
                    $userId = (int)$this->pdo->lastInsertId();

                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['company_id'] = (int)$company['id'];
                    $_SESSION['role'] = 'client';
                    $_SESSION['company_name'] = $company['name'];
                    $_SESSION['company_role'] = 'employee';
                    $_SESSION['permissions'] = null;
                    $_SESSION['join_status'] = 'pending';
                    unset($_SESSION['join_fails']);

                    header('Location: /pending');
                    exit;
                } catch (PDOException $e) {
                    $key = '';
                    if ((int)($e->errorInfo[1] ?? 0) === 1062 && preg_match("/for key '(?:\\w+\\.)?(\\w+)'/", $e->getMessage(), $m)) {
                        $key = $m[1];
                    }
                    if ($key === 'email') {
                        $errors['email'] = 'Пользователь с таким email уже зарегистрирован.';
                    } elseif ($key === 'username') {
                        $errors['username'] = 'Этот логин уже занят.';
                    } else {
                        error_log('Join failed: ' . $e->getMessage());
                        $errorMsg = 'Не удалось отправить заявку. Попробуйте позже.';
                    }
                }
            }
        }

        $pageTitle = 'Регистрация сотрудника';
        require BASE_PATH . '/views/join/index.php';
    }

    /** Страница ожидания решения (и отказа). */
    public function pending(): void
    {
        $this->requireLogin();

        $stmt = $this->pdo->prepare(
            'SELECT u.join_status, u.join_requested_at, u.full_name, c.name AS company_name
             FROM users u JOIN companies c ON c.id = u.company_id
             WHERE u.id = ? LIMIT 1'
        );
        $stmt->execute([$_SESSION['user_id']]);
        $me = $stmt->fetch();

        if (!$me || $me['join_status'] === 'approved') {
            header('Location: /account');
            exit;
        }

        $pageTitle = 'Заявка на вступление';
        require BASE_PATH . '/views/join/pending.php';
    }

    /** Отправить заявку в другую компанию (после ошибки в коде или отказа). */
    public function changeCode(): void
    {
        $this->requireLogin();

        if (!csrfValid()) {
            $this->back('flash_error', 'Сессия устарела. Обновите страницу и повторите.');
        }

        $stmt = $this->pdo->prepare('SELECT join_status FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        $status = $stmt->fetchColumn();
        if (!in_array($status, ['pending', 'rejected'], true)) {
            header('Location: /account');
            exit;
        }

        $code = normalizeJoinCode(is_string($_POST['code'] ?? null) ? $_POST['code'] : '');
        if (strlen($code) !== 8) {
            $this->back('flash_error', 'Код состоит из 8 символов, например ABCD-EF23.');
        }
        $company = $this->findCompany($code, $error);
        if (!$company) {
            $this->back('flash_error', $error);
        }

        $stmt = $this->pdo->prepare(
            "UPDATE users SET company_id = ?, join_status = 'pending', join_requested_at = NOW(),
                              join_decided_by = NULL, join_decided_at = NULL
             WHERE id = ? AND join_status IN ('pending', 'rejected')"
        );
        $stmt->execute([$company['id'], $_SESSION['user_id']]);

        $_SESSION['company_id'] = (int)$company['id'];
        $_SESSION['company_name'] = $company['name'];
        $_SESSION['join_status'] = 'pending';
        $this->back('flash_success', 'Заявка отправлена в компанию «' . $company['name'] . '».');
    }

    // ---------------------------------------------------------------

    private function requireLogin(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }
    }

    private function back(string $key, string $message): never
    {
        $_SESSION[$key] = $message;
        header('Location: /pending');
        exit;
    }

    /**
     * Компания по коду. Возвращает null и текст ошибки, если код неверный,
     * приём заявок выключен, в компании нет мест или слишком много неудачных попыток.
     */
    private function findCompany(string $code, ?string &$error = null): ?array
    {
        $fails = $_SESSION['join_fails'] ?? ['n' => 0, 't' => time()];
        if (time() - (int)$fails['t'] > self::FAIL_WINDOW) {
            $fails = ['n' => 0, 't' => time()];
        }
        if ((int)$fails['n'] >= self::MAX_FAILS) {
            $error = 'Слишком много неверных попыток. Попробуйте через 15 минут.';
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT id, name, join_enabled FROM companies WHERE join_code = ? LIMIT 1');
        $stmt->execute([$code]);
        $company = $stmt->fetch();

        if (!$company || (int)$company['join_enabled'] !== 1) {
            $fails['n'] = (int)$fails['n'] + 1;
            $_SESSION['join_fails'] = $fails;
            $error = 'Неверный код или компания сейчас не принимает заявки.';
            return null;
        }

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE company_id = ? AND is_active = 1 AND join_status = 'approved'");
        $stmt->execute([$company['id']]);
        if ((int)$stmt->fetchColumn() >= self::MAX_USERS) {
            $error = 'В компании достигнут лимит сотрудников.';
            return null;
        }
        return $company;
    }

    private function collect(array $src): array
    {
        $str = fn(string $k): string => is_string($src[$k] ?? null) ? trim($src[$k]) : '';
        $raw = fn(string $k): string => is_string($src[$k] ?? null) ? $src[$k] : '';
        return [
            'code'             => normalizeJoinCode($str('code')),
            'contact_person'   => $str('contact_person'),
            'phone'            => $str('phone'),
            'email'            => $str('email'),
            'username'         => $str('username'),
            'password'         => $raw('password'),
            'password_confirm' => $raw('password_confirm'),
        ];
    }

    /** @return array<string,string> */
    private function validate(array $in): array
    {
        $e = [];

        if ($in['code'] === '') {
            $e['code'] = 'Введите код компании.';
        } elseif (strlen($in['code']) !== 8) {
            $e['code'] = 'Код состоит из 8 символов, например ABCD-EF23.';
        }

        $words = preg_split('/\s+/u', $in['contact_person'], -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($in['contact_person'] === '') {
            $e['contact_person'] = 'Укажите ваше ФИО.';
        } elseif (count($words) < 2) {
            $e['contact_person'] = 'Укажите фамилию и имя.';
        } elseif (mb_strlen($in['contact_person']) > 150) {
            $e['contact_person'] = 'ФИО не должно быть длиннее 150 символов.';
        }

        $digits = strlen(preg_replace('/\D/', '', $in['phone']));
        if ($in['phone'] === '') {
            $e['phone'] = 'Укажите номер телефона.';
        } elseif ($digits < 10 || $digits > 15 || strlen($in['phone']) > 30 || !preg_match('/^[\d\s()+\-]+$/', $in['phone'])) {
            $e['phone'] = 'Введите корректный номер, например +7 900 000-00-00.';
        }

        if ($in['email'] === '') {
            $e['email'] = 'Укажите email.';
        } elseif (!filter_var($in['email'], FILTER_VALIDATE_EMAIL)) {
            $e['email'] = 'Некорректный email, например name@company.ru.';
        } elseif (mb_strlen($in['email']) > 100) {
            $e['email'] = 'Email не должен быть длиннее 100 символов.';
        } elseif ($this->exists('email', $in['email'])) {
            $e['email'] = 'Пользователь с таким email уже зарегистрирован.';
        }

        if ($in['username'] === '') {
            $e['username'] = 'Придумайте логин.';
        } elseif (mb_strlen($in['username']) < 3) {
            $e['username'] = 'Логин — минимум 3 символа.';
        } elseif (mb_strlen($in['username']) > 50) {
            $e['username'] = 'Логин не должен быть длиннее 50 символов.';
        } elseif (!preg_match('/^[A-Za-z0-9_.-]+$/', $in['username'])) {
            $e['username'] = 'Только латинские буквы, цифры и символы _ . -';
        } elseif ($this->exists('username', $in['username'])) {
            $e['username'] = 'Этот логин уже занят.';
        }

        if ($in['password'] === '') {
            $e['password'] = 'Введите пароль.';
        } elseif (mb_strlen($in['password']) < 8) {
            $e['password'] = 'Пароль — минимум 8 символов.';
        } elseif (strlen($in['password']) > 72) {
            $e['password'] = 'Пароль не должен быть длиннее 72 символов.';
        } elseif (!preg_match('/[A-Za-z]/', $in['password']) || !preg_match('/\d/', $in['password'])) {
            $e['password'] = 'Нужна хотя бы одна буква и одна цифра.';
        }

        if ($in['password_confirm'] === '') {
            $e['password_confirm'] = 'Повторите пароль.';
        } elseif ($in['password_confirm'] !== $in['password']) {
            $e['password_confirm'] = 'Пароли не совпадают.';
        }
        return $e;
    }

    private function exists(string $column, string $value): bool
    {
        // колонка передаётся только константой из validate()
        $stmt = $this->pdo->prepare("SELECT 1 FROM users WHERE `$column` = ? LIMIT 1");
        $stmt->execute([$value]);
        return (bool)$stmt->fetchColumn();
    }
}
