<?php
/** Заявка «Стать проверяющим»: публичная форма, отдельная от регистрации компании. Заявку одобряет администратор. */
class InspectorApplyController
{
    public function __construct(private PDO $pdo) {}

    public function apply(): void
    {
        if (isset($_SESSION['user_id'])) {
            header('Location: ' . (isStaffRole($_SESSION['role'] ?? '') ? staffHome($_SESSION['role'] ?? '') : '/'));
            exit;
        }

        $errors = [];
        $old = ['full_name' => '', 'email' => '', 'phone' => '', 'about' => ''];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $str = fn(string $k): string => is_string($_POST[$k] ?? null) ? trim($_POST[$k]) : '';
            $old = [
                'full_name' => $str('full_name'),
                'email'     => mb_strtolower($str('email')),
                'phone'     => $str('phone'),
                'about'     => $str('about'),
            ];
            $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
            $confirm  = is_string($_POST['password_confirm'] ?? null) ? $_POST['password_confirm'] : '';

            if (!csrfValid()) {
                $errors['form'] = 'Сессия устарела. Обновите страницу и отправьте форму ещё раз.';
            }
            if (mb_strlen($old['full_name']) < 5 || mb_strlen($old['full_name']) > 150) {
                $errors['full_name'] = 'Укажите ФИО полностью (5–150 символов).';
            }
            if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['email']) > 100) {
                $errors['email'] = 'Некорректный email, например name@mail.ru.';
            }
            if (mb_strlen($old['phone']) > 30) {
                $errors['phone'] = 'Телефон — не длиннее 30 символов.';
            }
            if (mb_strlen($old['about']) > 1000) {
                $errors['about'] = 'Рассказ о себе — не длиннее 1000 символов.';
            }
            if (mb_strlen($password) < 8) {
                $errors['password'] = 'Пароль — не короче 8 символов.';
            } elseif ($password !== $confirm) {
                $errors['password_confirm'] = 'Пароли не совпадают.';
            }

            if (!$errors) {
                $applyKey = 'apply-ip:' . RateLimiter::clientIp();
                if (RateLimiter::tooMany($applyKey, 3, 3600)) {
                    $errors['form'] = 'Слишком много заявок с вашего адреса. Попробуйте через час.';
                }
            }

            if (!$errors) {
                try {
                    $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
                    $stmt->execute([$old['email']]);
                    if ($stmt->fetchColumn()) {
                        $errors['email'] = 'Этот email уже зарегистрирован. Войдите или используйте другой адрес.';
                    } else {
                        $stmt = $this->pdo->prepare("SELECT 1 FROM inspector_applications WHERE email = ? AND status = 'pending' LIMIT 1");
                        $stmt->execute([$old['email']]);
                        if ($stmt->fetchColumn()) {
                            $errors['email'] = 'Заявка с этим email уже отправлена и ждёт решения администратора.';
                        }
                    }

                    if (!$errors) {
                        $stmt = $this->pdo->prepare(
                            'INSERT INTO inspector_applications (full_name, email, phone, about, password_hash) VALUES (?, ?, ?, ?, ?)'
                        );
                        $stmt->execute([
                            $old['full_name'], $old['email'], nullIfEmpty($old['phone']), nullIfEmpty($old['about']),
                            password_hash($password, PASSWORD_DEFAULT),
                        ]);
                        RateLimiter::hit($applyKey, 3600);
                        $_SESSION['flash_success'] = 'Заявка отправлена. После одобрения администратором вы сможете войти по email и паролю, которые указали.';
                        header('Location: /login');
                        exit;
                    }
                } catch (PDOException $e) {
                    error_log('Inspector application failed: ' . $e->getMessage());
                    $errors['form'] = 'Не удалось отправить заявку. Попробуйте позже.';
                }
            }
        }

        $pageTitle = 'Стать проверяющим';
        require BASE_PATH . '/views/inspector/apply.php';
    }
}
