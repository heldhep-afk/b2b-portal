<?php
class ProfileController
{
    public function __construct(private PDO $pdo) {}

    private function checkAuth(): void
    {
        // company_id появился в сессии после смены схемы БД — старые сессии сбрасываем
        if (!isset($_SESSION['user_id'], $_SESSION['company_id'])) {
            $_SESSION = [];
            header('Location: /login');
            exit;
        }
    }

    public function index(): void
    {
        $this->checkAuth();

        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.username, u.phone, u.email, u.role, u.company_role, u.`position`,
                    u.full_name AS contact_person,
                    c.name AS company_name, c.inn,
                    c.address AS legal_address, c.phone AS company_phone, c.description
             FROM users u
             JOIN companies c ON c.id = u.company_id
             WHERE u.id = ? LIMIT 1'
        );
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if (!$user) {
            session_destroy();
            header('Location: /login');
            exit;
        }

        $pageTitle = 'Профиль';
        require BASE_PATH . '/views/profile/index.php';
    }

    public function edit(): void
    {
        $this->checkAuth();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /profile');
            exit;
        }
        if (!csrfValid()) {
            $this->fail('Сессия устарела. Обновите страницу и повторите действие.');
        }

        $post = fn(string $k): string => is_string($_POST[$k] ?? null) ? trim($_POST[$k]) : '';
        $username = $post('username');
        $position = $post('position');
        $phone = $post('phone');
        $companyPhone = $post('company_phone');
        $company = collectCompanyData($_POST);
        $isOwner = canCompany('edit_company');   // данные компании меняет только директор

        if ($username === '') {
            $this->fail('Логин не может быть пустым.');
        }
        if (mb_strlen($username) < 3) {
            $this->fail('Логин — минимум 3 символа.');
        }
        if (!preg_match('/^[A-Za-z0-9_.-]+$/', $username)) {
            $this->fail('Логин может содержать только латинские буквы, цифры и символы _ . -');
        }
        if ($isOwner && $companyPhone === '') {
            $this->fail('Укажите номер для связи.');
        }

        // ИНН — идентификатор компании, его не меняем
        $error = $isOwner
            ? validateCompanyData($company, false)
            : ($company['contact_person'] === '' ? 'Укажите ФИО.' : '');
        if ($error === '') {
            $limits = [
                [$company['company_name'], 150, 'Название компании'],
                [$company['legal_address'], 255, 'Адрес'],
                [$company['contact_person'], 150, 'Контактное лицо'],
                [$username, 50, 'Логин'],
                [$position, 100, 'Должность'],
                [$phone, 30, 'Личный телефон'],
                [$companyPhone, 30, 'Номер для связи'],
                [$company['description'], 2000, 'О компании'],
            ];
            foreach ($limits as [$value, $max, $label]) {
                if (mb_strlen($value) > $max) {
                    $error = "Поле «{$label}» не должно быть длиннее {$max} символов.";
                    break;
                }
            }
        }
        if ($error !== '') {
            $this->fail($error);
        }

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                'UPDATE users
                 SET username = :username, `position` = :position, phone = :phone, full_name = :full_name
                 WHERE id = :id'
            );
            $stmt->execute([
                ':username'  => $username,
                ':position'  => nullIfEmpty($position),
                ':phone'     => $phone,
                ':full_name' => $company['contact_person'],
                ':id'        => $_SESSION['user_id'],
            ]);

            if ($isOwner) {
                $stmt = $this->pdo->prepare(
                    'UPDATE companies
                     SET name = :name, address = :address,
                         description = :description, phone = :phone
                     WHERE id = :id'
                );
                $stmt->execute([
                    ':name'        => $company['company_name'],
                    ':address'     => $company['legal_address'],
                    ':description' => nullIfEmpty($company['description']),
                    ':phone'       => $companyPhone,
                    ':id'          => $_SESSION['company_id'],
                ]);
            }

            $this->pdo->commit();

            if ($isOwner) {
                $_SESSION['company_name'] = $company['company_name'];
            }
            $_SESSION['flash_success'] = 'Изменения сохранены.';
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $_SESSION['flash_error'] = ((int)($e->errorInfo[1] ?? 0) === 1062)
                ? 'Не удалось сохранить: такой логин уже занят.'
                : 'Не удалось сохранить изменения.';
        }

        header('Location: /profile');
        exit;
    }

    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
        header('Location: /login');
        exit;
    }

    private function fail(string $message): never
    {
        $_SESSION['flash_error'] = $message;
        header('Location: /profile');
        exit;
    }
}
