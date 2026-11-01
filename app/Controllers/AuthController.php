<?php
class AuthController
{
    /** Хеш-заглушка: сравнение выполняется и для несуществующего email (защита от утечки по времени ответа). */
    private const DUMMY_HASH = '$2y$10$DtZt0YCRQJ469ZdIz5rUIO4Y20/dmuhVxhdzxUtoZflkbARbIO65m';

    /** Лимиты подбора пароля. */
    private const LOGIN_MAX_PER_IP    = 20;
    private const LOGIN_MAX_PER_EMAIL = 5;
    private const LOGIN_WINDOW        = 900;   // 15 минут

    /** Поле формы регистрации => номер шага мастера (с 0). */
    private const STEP_OF = [
        'company_name' => 0, 'inn' => 0, 'legal_address' => 0, 'company_phone' => 0, 'description' => 0,
        'contact_person' => 1, 'position' => 1, 'phone' => 1, 'email' => 1,
        'username' => 2, 'password' => 2, 'password_confirm' => 2,
    ];

    public function __construct(private PDO $pdo) {}

    public function login(): void
    {
        if (isset($_SESSION['user_id'])) {
            header('Location: /account');
            exit;
        }

        $errorMsg = '';
        $errors = [];
        $oldEmail = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrfValid()) {
            $errorMsg = 'Сессия устарела. Обновите страницу и попробуйте снова.';
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
            $pass = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
            $oldEmail = $email;

            if ($email === '') {
                $errors['email'] = 'Введите email.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Некорректный email, например name@company.ru.';
            }
            if ($pass === '') {
                $errors['password'] = 'Введите пароль.';
            }

            if (!$errors) {
                $ipKey    = 'login-ip:' . RateLimiter::clientIp();
                $emailKey = 'login-email:' . mb_strtolower($email);

                if (RateLimiter::tooMany($ipKey, self::LOGIN_MAX_PER_IP, self::LOGIN_WINDOW)
                    || RateLimiter::tooMany($emailKey, self::LOGIN_MAX_PER_EMAIL, self::LOGIN_WINDOW)) {
                    $errorMsg = 'Слишком много попыток входа. Подождите 15 минут и попробуйте снова.';
                } else {
                    $stmt = $this->pdo->prepare(
                        'SELECT u.id, u.company_id, u.password_hash, u.role, u.company_role, u.join_status, u.is_active, c.name AS company_name
                         FROM users u
                         JOIN companies c ON c.id = u.company_id
                         WHERE u.email = ? LIMIT 1'
                    );
                    $stmt->execute([$email]);
                    $user = $stmt->fetch();

                    // password_verify вызывается всегда: иначе по времени ответа можно понять, есть ли такой email
                    $passwordOk = password_verify($pass, $user['password_hash'] ?? self::DUMMY_HASH);

                    if ($user && $passwordOk) {
                        if ((int)$user['is_active'] !== 1) {
                            $errorMsg = 'Аккаунт отключён директором компании.';
                        } else {
                            RateLimiter::clear($emailKey);

                            // обновляем устаревший хеш (например, после смены параметров в PHP)
                            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                                try {
                                    $upd = $this->pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ? LIMIT 1');
                                    $upd->execute([password_hash($pass, PASSWORD_DEFAULT), $user['id']]);
                                } catch (PDOException $e) {
                                    error_log('Password rehash failed: ' . $e->getMessage());
                                }
                            }

                            $next = popAfterLogin();
                            $this->startSession((int)$user['id'], (int)$user['company_id'], $user['role'], $user['company_name'], $user['company_role'], $user['join_status']);

                            $target = $user['join_status'] !== 'approved'
                                ? '/pending'
                                : ($next !== '' ? $next : ($user['role'] === 'inspector' ? '/inspector' : ($user['role'] === 'admin' ? '/admin' : '/account')));
                            header('Location: ' . $target);
                            exit;
                        }
                    } else {
                        RateLimiter::hit($ipKey, self::LOGIN_WINDOW);
                        RateLimiter::hit($emailKey, self::LOGIN_WINDOW);
                        $errorMsg = 'Неверный email или пароль.';
                    }
                }
            }
        }

        $pageTitle = 'Вход';
        require BASE_PATH . '/views/auth/login.php';
    }

    /** Регистрация компании в три шага: компания → контактное лицо → доступ. */
    public function register(): void
    {
        if (isset($_SESSION['user_id'])) {
            header('Location: /account');
            exit;
        }

        $errors = [];     // ошибки по полям
        $errorMsg = '';   // общая ошибка (сбой сервера)
        $old = [];

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !csrfValid()) {
            $errorMsg = 'Сессия устарела. Обновите страницу и попробуйте снова.';
        } elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $in = $this->collect($_POST);
            $old = $in;
            unset($old['password'], $old['password_confirm']);

            $errors = $this->validate($in);

            if (!$errors) {
                try {
                    $this->pdo->beginTransaction();

                    $stmt = $this->pdo->prepare(
                        'INSERT INTO companies (name, description, inn, address, phone, email, join_code)
                         VALUES (:name, :description, :inn, :address, :phone, :email, :join_code)'
                    );
                    $stmt->execute([
                        ':name'        => $in['company_name'],
                        ':description' => nullIfEmpty($in['description']),
                        ':inn'         => $in['inn'],
                        ':address'     => $in['legal_address'],
                        ':phone'       => $in['company_phone'],
                        ':email'       => $in['email'],
                        ':join_code'   => $this->uniqueJoinCode(),   // код для заявок сотрудников
                    ]);
                    $companyId = (int)$this->pdo->lastInsertId();

                    $stmt = $this->pdo->prepare(
                        'INSERT INTO users (company_id, username, full_name, `position`, phone, email, password_hash, role, company_role)
                         VALUES (:company_id, :username, :full_name, :position, :phone, :email, :password_hash, :role, :company_role)'
                    );
                    $stmt->execute([
                        ':company_id'    => $companyId,
                        ':username'      => $in['username'],
                        ':full_name'     => $in['contact_person'],
                        ':position'      => $in['position'],
                        ':phone'         => $in['phone'],
                        ':email'         => $in['email'],
                        ':password_hash' => password_hash($in['password'], PASSWORD_DEFAULT),
                        ':role'          => 'client',
                        ':company_role'  => 'owner',   // кто зарегистрировал компанию — её директор
                    ]);

                    $userId = (int)$this->pdo->lastInsertId();
                    $this->pdo->commit();

                    $this->startSession($userId, $companyId, 'client', $in['company_name'], 'owner');
                    $next = popAfterLogin();
                    $_SESSION['flash_success'] = $next !== ''
                        ? 'Компания зарегистрирована! Можно оформить покупку.'
                        : 'Компания зарегистрирована! Выберите категории товаров, которые вы предоставляете.';
                    header('Location: ' . ($next !== '' ? $next : '/account#categories'));
                    exit;
                } catch (PDOException $e) {
                    if ($this->pdo->inTransaction()) {
                        $this->pdo->rollBack();
                    }

                    // гонка: пока заполняли форму, значение заняли — показываем у конкретного поля
                    $key = '';
                    if ((int)($e->errorInfo[1] ?? 0) === 1062
                        && preg_match("/for key '(?:\\w+\\.)?(\\w+)'/", $e->getMessage(), $m)) {
                        $key = $m[1];
                    }
                    if ($key === 'inn') {
                        $errors['inn'] = 'Компания с таким ИНН уже зарегистрирована.';
                    } elseif ($key === 'email') {
                        $errors['email'] = 'Пользователь с таким email уже зарегистрирован.';
                    } elseif ($key === 'username') {
                        $errors['username'] = 'Этот логин уже занят.';
                    } else {
                        error_log('Registration failed: ' . $e->getMessage());
                        $errorMsg = 'Не удалось завершить регистрацию. Попробуйте ещё раз чуть позже.';
                    }
                }
            }
        }

        // на каком шаге открыть форму и какие шаги содержат ошибки
        $errorSteps = array_values(array_unique(array_map(
            fn(string $field): int => self::STEP_OF[$field] ?? 0,
            array_keys($errors)
        )));
        sort($errorSteps);
        $startStep = $errorSteps ? $errorSteps[0] : 0;

        $pageTitle = 'Регистрация компании';
        require BASE_PATH . '/views/auth/register.php';
    }

    private function collect(array $src): array
    {
        $str = fn(string $k): string => is_string($src[$k] ?? null) ? trim($src[$k]) : '';
        $raw = fn(string $k): string => is_string($src[$k] ?? null) ? $src[$k] : '';

        return [
            'company_name'     => $str('company_name'),
            'inn'              => preg_replace('/\s+/', '', $str('inn')),
            'legal_address'    => $str('legal_address'),
            'company_phone'    => $str('company_phone'),
            'description'      => $str('description'),
            'contact_person'   => $str('contact_person'),
            'position'         => $str('position'),
            'phone'            => $str('phone'),
            'email'            => $str('email'),
            'username'         => $str('username'),
            'password'         => $raw('password'),
            'password_confirm' => $raw('password_confirm'),
        ];
    }

    /** @return array<string,string> поле => текст ошибки */
    private function validate(array $in): array
    {
        $e = [];

        // ---- шаг 1: компания ----
        if ($in['company_name'] === '') {
            $e['company_name'] = 'Укажите название компании.';
        } elseif (mb_strlen($in['company_name']) < 2) {
            $e['company_name'] = 'Слишком короткое название.';
        } elseif (mb_strlen($in['company_name']) > 150) {
            $e['company_name'] = 'Название не должно быть длиннее 150 символов.';
        }

        if ($in['inn'] === '') {
            $e['inn'] = 'Укажите ИНН.';
        } elseif (!ctype_digit($in['inn'])) {
            $e['inn'] = 'ИНН состоит только из цифр.';
        } elseif (!in_array(strlen($in['inn']), [10, 12], true)) {
            $e['inn'] = 'ИНН — 10 цифр (организация) или 12 (ИП), сейчас: ' . strlen($in['inn']) . '.';
        } elseif ($this->exists('companies', 'inn', $in['inn'])) {
            $e['inn'] = 'Компания с таким ИНН уже зарегистрирована.';
        }

        if ($in['legal_address'] === '') {
            $e['legal_address'] = 'Укажите юридический адрес.';
        } elseif (mb_strlen($in['legal_address']) < 5) {
            $e['legal_address'] = 'Адрес слишком короткий.';
        } elseif (mb_strlen($in['legal_address']) > 255) {
            $e['legal_address'] = 'Адрес не должен быть длиннее 255 символов.';
        }

        if ($err = $this->phoneError($in['company_phone'])) {
            $e['company_phone'] = $err;
        }

        if (mb_strlen($in['description']) > 2000) {
            $e['description'] = 'Описание не должно быть длиннее 2000 символов.';
        }

        // ---- шаг 2: контактное лицо ----
        $words = preg_split('/\s+/u', $in['contact_person'], -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($in['contact_person'] === '') {
            $e['contact_person'] = 'Укажите ФИО контактного лица.';
        } elseif (count($words) < 2) {
            $e['contact_person'] = 'Укажите фамилию и имя.';
        } elseif (mb_strlen($in['contact_person']) > 150) {
            $e['contact_person'] = 'ФИО не должно быть длиннее 150 символов.';
        }

        if ($in['position'] === '') {
            $e['position'] = 'Укажите должность.';
        } elseif (mb_strlen($in['position']) > 100) {
            $e['position'] = 'Должность не должна быть длиннее 100 символов.';
        }

        if ($err = $this->phoneError($in['phone'])) {
            $e['phone'] = $err;
        }

        if ($in['email'] === '') {
            $e['email'] = 'Укажите email.';
        } elseif (!filter_var($in['email'], FILTER_VALIDATE_EMAIL)) {
            $e['email'] = 'Некорректный email, например name@company.ru.';
        } elseif (mb_strlen($in['email']) > 100) {
            $e['email'] = 'Email не должен быть длиннее 100 символов.';
        } elseif ($this->exists('users', 'email', $in['email'])) {
            $e['email'] = 'Пользователь с таким email уже зарегистрирован.';
        }

        // ---- шаг 3: доступ ----
        if ($in['username'] === '') {
            $e['username'] = 'Придумайте логин.';
        } elseif (mb_strlen($in['username']) < 3) {
            $e['username'] = 'Логин — минимум 3 символа.';
        } elseif (mb_strlen($in['username']) > 50) {
            $e['username'] = 'Логин не должен быть длиннее 50 символов.';
        } elseif (!preg_match('/^[A-Za-z0-9_.-]+$/', $in['username'])) {
            $e['username'] = 'Только латинские буквы, цифры и символы _ . -';
        } elseif ($this->exists('users', 'username', $in['username'])) {
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

    private function phoneError(string $v): string
    {
        if ($v === '') {
            return 'Укажите номер телефона.';
        }
        $digits = strlen(preg_replace('/\D/', '', $v));
        if ($digits < 10 || $digits > 15 || strlen($v) > 30 || !preg_match('/^[\d\s()+\-]+$/', $v)) {
            return 'Введите корректный номер, например +7 900 000-00-00.';
        }
        return '';
    }

    /** Проверка занятости значения. Таблица и колонка передаются только константами. */
    private function exists(string $table, string $column, string $value): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM `$table` WHERE `$column` = ? LIMIT 1");
        $stmt->execute([$value]);
        return (bool)$stmt->fetchColumn();
    }

    private function startSession(int $userId, int $companyId, string $role, string $companyName, string $companyRole = 'owner', string $joinStatus = 'approved'): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['company_id'] = $companyId;
        $_SESSION['role'] = $role;
        $_SESSION['company_name'] = $companyName;
        $_SESSION['company_role'] = $companyRole;
        $_SESSION['join_status'] = $joinStatus;
        $_SESSION['permissions'] = null;
    }

    /** Свободный код компании. */
    private function uniqueJoinCode(): string
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM companies WHERE join_code = ? LIMIT 1');
        for ($i = 0; $i < 10; $i++) {
            $code = generateJoinCode();
            $stmt->execute([$code]);
            if (!$stmt->fetchColumn()) {
                return $code;
            }
        }
        return generateJoinCode();
    }
}
