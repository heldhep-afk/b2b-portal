<?php
/** Админ-панель: персонал (проверяющие) и подтверждение проверок. */
class StaffController
{
    private const ACTIONS = ['confirm_fee', 'assign', 'approve', 'return', 'cancel', 'refund_fee', 'pay_reward'];
    private const VIEWS   = ['all', 'review', 'fee', 'assign', 'work', 'reward', 'refund'];

    /** SQL-условия очередей администратора по проверкам. */
    private const VIEW_SQL = [
        'review' => "i.status = 'submitted'",
        'fee'    => "(i.status IN ('requested', 'assigned', 'submitted') AND i.fee_status = 'unpaid')",
        'assign' => "(i.status = 'requested' AND i.fee_status = 'paid')",
        'work'   => "i.status = 'assigned'",
        'reward' => "(i.status = 'approved' AND i.reward_paid_at IS NULL)",
        'refund' => "(i.status = 'cancelled' AND i.fee_status = 'paid')",
    ];

    public function __construct(private PDO $pdo) {}

    private function requireAdmin(): array
    {
        return requireStaff($this->pdo, ['admin'], 'Войдите в аккаунт администратора.');
    }

    private function go(string $key, string $message, string $to): never
    {
        $_SESSION[$key] = $message;
        header('Location: ' . $to);
        exit;
    }

    /* ===================== Персонал ===================== */

    public function index(): void
    {
        $admin = $this->requireAdmin();

        $staff = [];
        $dbNotice = '';
        try {
            $staff = $this->pdo->query(
                "SELECT u.id, u.username, u.full_name, u.`position`, u.phone, u.email, u.is_active, u.created_at,
                        COALESCE(SUM(i.status IN ('assigned', 'submitted')), 0) AS in_work,
                        COALESCE(SUM(i.status = 'approved'), 0) AS approved_cnt,
                        COALESCE(SUM(CASE WHEN i.status = 'approved' AND i.reward_paid_at IS NULL THEN i.reward END), 0) AS owed,
                        COALESCE(SUM(CASE WHEN i.status = 'approved' AND i.reward_paid_at IS NOT NULL THEN i.reward END), 0) AS paid
                 FROM users u
                 LEFT JOIN inspections i ON i.inspector_id = u.id
                 WHERE u.role = 'inspector'
                 GROUP BY u.id, u.username, u.full_name, u.`position`, u.phone, u.email, u.is_active, u.created_at
                 ORDER BY u.is_active DESC, u.full_name"
            )->fetchAll();
        } catch (PDOException $e) {
            error_log('Staff page failed: ' . $e->getMessage());
            $dbNotice = 'Не удалось загрузить персонал. Выполните SQL-скрипт sql/migration_inspections.sql.';
        }

        $applications = [];
        try {
            $applications = $this->pdo->query(
                "SELECT id, full_name, email, phone, about, created_at FROM inspector_applications
                 WHERE status = 'pending' ORDER BY created_at ASC, id ASC LIMIT 100"
            )->fetchAll();
        } catch (PDOException $e) {
            error_log('Staff applications failed: ' . $e->getMessage());
        }

        $old = $_SESSION['staff_old'] ?? [];
        $errors = $_SESSION['staff_errors'] ?? [];
        unset($_SESSION['staff_old'], $_SESSION['staff_errors']);

        $pageTitle = 'Персонал';
        require BASE_PATH . '/views/admin/staff.php';
    }

    /** Добавить проверяющего: администратор создаёт аккаунт и передаёт логин и пароль сотруднику. */
    public function add(): void
    {
        $admin = $this->requireAdmin();
        if (!csrfValid()) {
            $this->go('flash_error', 'Сессия устарела. Обновите страницу и повторите действие.', '/admin/staff');
        }

        $str = fn(string $k): string => is_string($_POST[$k] ?? null) ? trim($_POST[$k]) : '';
        $in = [
            'full_name' => $str('full_name'),
            'position'  => $str('position') ?: 'Проверяющий качества',
            'phone'     => $str('phone'),
            'email'     => mb_strtolower($str('email')),
            'username'  => $str('username'),
        ];
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

        $errors = [];
        if (mb_strlen($in['full_name']) < 5 || mb_strlen($in['full_name']) > 150) {
            $errors['full_name'] = 'Укажите ФИО полностью (5–150 символов).';
        }
        if (mb_strlen($in['position']) > 100) {
            $errors['position'] = 'Должность — не длиннее 100 символов.';
        }
        if (mb_strlen($in['phone']) > 30) {
            $errors['phone'] = 'Телефон — не длиннее 30 символов.';
        }
        if (!filter_var($in['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($in['email']) > 100) {
            $errors['email'] = 'Некорректный email, например name@company.ru.';
        }
        if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $in['username'])) {
            $errors['username'] = 'Логин: 3–50 символов — латиница, цифры, точка, дефис, подчёркивание.';
        }
        $generated = false;
        if ($password === '') {
            $password = $this->randomPassword();
            $generated = true;
        } elseif (mb_strlen($password) < 8) {
            $errors['password'] = 'Пароль — не короче 8 символов (или оставьте пустым — он создастся автоматически).';
        }

        if (!$errors) {
            try {
                $stmt = $this->pdo->prepare('SELECT email, username FROM users WHERE email = ? OR username = ?');
                $stmt->execute([$in['email'], $in['username']]);
                foreach ($stmt->fetchAll() as $row) {
                    if (mb_strtolower($row['email']) === $in['email']) {
                        $errors['email'] = 'Этот email уже зарегистрирован.';
                    }
                    if (mb_strtolower($row['username']) === mb_strtolower($in['username'])) {
                        $errors['username'] = 'Этот логин уже занят.';
                    }
                }
            } catch (PDOException $e) {
                error_log('Staff duplicate check failed: ' . $e->getMessage());
                $this->go('flash_error', 'Не удалось проверить данные. Попробуйте позже.', '/admin/staff');
            }
        }

        if ($errors) {
            $_SESSION['staff_old'] = $in;
            $_SESSION['staff_errors'] = $errors;
            $this->go('flash_error', 'Проверьте поля формы.', '/admin/staff#add');
        }

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO users (company_id, username, full_name, `position`, phone, email, password_hash, role, company_role)
                 VALUES (?, ?, ?, ?, ?, ?, ?, \'inspector\', \'employee\')'
            );
            $stmt->execute([
                $admin['company_id'], $in['username'], $in['full_name'], $in['position'],
                nullIfEmpty($in['phone']), $in['email'], password_hash($password, PASSWORD_DEFAULT),
            ]);
        } catch (PDOException $e) {
            error_log('Staff add failed: ' . $e->getMessage());
            // чаще всего — в БД ещё нет значения роли inspector
            $this->go('flash_error', 'Не удалось добавить сотрудника. Проверьте, что выполнен SQL-скрипт sql/migration_inspections.sql.', '/admin/staff#add');
        }

        $this->go(
            'flash_success',
            "Проверяющий {$in['full_name']} добавлен. Вход: {$in['email']}, пароль: {$password}"
            . ($generated ? ' (создан автоматически)' : '') . ' — передайте их сотруднику, повторно пароль не показывается.',
            '/admin/staff'
        );
    }

    /** Одобрить заявку (создаётся аккаунт проверяющего с паролем из заявки) или отклонить. */
    public function application(): void
    {
        $admin = $this->requireAdmin();
        if (!csrfValid()) {
            $this->go('flash_error', 'Сессия устарела. Обновите страницу и повторите действие.', '/admin/staff');
        }
        $id = (int)($_POST['application_id'] ?? 0);
        $decision = is_string($_POST['decision'] ?? null) ? $_POST['decision'] : '';
        if ($id <= 0 || !in_array($decision, ['approve', 'reject'], true)) {
            $this->go('flash_error', 'Некорректный запрос.', '/admin/staff');
        }

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("SELECT * FROM inspector_applications WHERE id = ? AND status = 'pending' FOR UPDATE");
            $stmt->execute([$id]);
            $app = $stmt->fetch();
            if (!$app) {
                $this->pdo->rollBack();
                $this->go('flash_error', 'Заявка уже обработана.', '/admin/staff');
            }

            if ($decision === 'approve') {
                $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
                $stmt->execute([$app['email']]);
                if ($stmt->fetchColumn()) {
                    $this->pdo->rollBack();
                    $this->go('flash_error', "Email {$app['email']} уже зарегистрирован — заявку можно только отклонить.", '/admin/staff');
                }

                // имя пользователя: часть email до @ + число, если занято
                $base = preg_replace('/[^A-Za-z0-9_.-]/', '', explode('@', $app['email'])[0]) ?: 'inspector';
                $base = substr($base, 0, 40);
                if (strlen($base) < 3) {
                    $base .= 'user';
                }
                $username = $base;
                for ($n = 2; $n < 100; $n++) {
                    $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE username = ? LIMIT 1');
                    $stmt->execute([$username]);
                    if (!$stmt->fetchColumn()) {
                        break;
                    }
                    $username = $base . $n;
                }

                $stmt = $this->pdo->prepare(
                    'INSERT INTO users (company_id, username, full_name, `position`, phone, email, password_hash, role, company_role)
                     VALUES (?, ?, ?, \'Проверяющий качества\', ?, ?, ?, \'inspector\', \'employee\')'
                );
                $stmt->execute([$admin['company_id'], $username, $app['full_name'], $app['phone'], $app['email'], $app['password_hash']]);
            }

            $stmt = $this->pdo->prepare(
                'UPDATE inspector_applications SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?'
            );
            $stmt->execute([$decision === 'approve' ? 'approved' : 'rejected', $admin['id'], $id]);

            $this->pdo->commit();
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Staff application failed: ' . $e->getMessage());
            $this->go('flash_error', 'Не удалось обработать заявку. Попробуйте позже.', '/admin/staff');
        }

        $this->go(
            'flash_success',
            $decision === 'approve'
                ? "Заявка принята: {$app['full_name']} теперь проверяющий и входит по email {$app['email']} и своему паролю."
                : "Заявка {$app['full_name']} отклонена.",
            '/admin/staff'
        );
    }

    /** Отключить или вернуть проверяющего. При отключении его задания «Проверяется» возвращаются в свободные. */
    public function toggle(): void
    {
        $admin = $this->requireAdmin();
        if (!csrfValid()) {
            $this->go('flash_error', 'Сессия устарела. Обновите страницу и повторите действие.', '/admin/staff');
        }
        $id = (int)($_POST['user_id'] ?? 0);

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("SELECT id, full_name, is_active FROM users WHERE id = ? AND role = 'inspector' FOR UPDATE");
            $stmt->execute([$id]);
            $user = $stmt->fetch();
            if (!$user) {
                $this->pdo->rollBack();
                $this->go('flash_error', 'Сотрудник не найден.', '/admin/staff');
            }

            $newState = (int)$user['is_active'] === 1 ? 0 : 1;
            $stmt = $this->pdo->prepare('UPDATE users SET is_active = ? WHERE id = ?');
            $stmt->execute([$newState, $id]);

            $released = 0;
            if ($newState === 0) {
                $stmt = $this->pdo->prepare("SELECT id FROM inspections WHERE inspector_id = ? AND status = 'assigned' FOR UPDATE");
                $stmt->execute([$id]);
                $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
                foreach ($ids as $inspId) {
                    $upd = $this->pdo->prepare(
                        "UPDATE inspections SET status = 'requested', inspector_id = NULL, assigned_at = NULL,
                                visit_status = 'none', visit_proposed_by = NULL, visit_at = NULL, visit_confirmed_at = NULL WHERE id = ?"
                    );
                    $upd->execute([$inspId]);
                    logInspectionEvent($this->pdo, (int)$inspId, (int)$admin['id'], 'released', 'Проверяющий отключён: ' . $user['full_name']);
                    $released++;
                }
            }

            $this->pdo->commit();
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Staff toggle failed: ' . $e->getMessage());
            $this->go('flash_error', 'Не удалось изменить доступ. Попробуйте позже.', '/admin/staff');
        }

        $this->go(
            'flash_success',
            $newState === 1
                ? "Доступ проверяющего {$user['full_name']} восстановлен."
                : "Проверяющий {$user['full_name']} отключён." . ($released ? " Заданий возвращено в свободные: {$released}." : ''),
            '/admin/staff'
        );
    }

    /** Новый пароль проверяющему (введённый администратором или созданный автоматически). */
    public function password(): void
    {
        $this->requireAdmin();
        if (!csrfValid()) {
            $this->go('flash_error', 'Сессия устарела. Обновите страницу и повторите действие.', '/admin/staff');
        }
        $id = (int)($_POST['user_id'] ?? 0);
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $generated = $password === '';
        if ($generated) {
            $password = $this->randomPassword();
        } elseif (mb_strlen($password) < 8) {
            $this->go('flash_error', 'Пароль — не короче 8 символов (или оставьте поле пустым — он создастся автоматически).', '/admin/staff');
        }

        try {
            $stmt = $this->pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ? AND role = 'inspector'");
            $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
            $stmt = $this->pdo->prepare("SELECT full_name, email FROM users WHERE id = ? AND role = 'inspector'");
            $stmt->execute([$id]);
            $user = $stmt->fetch();
        } catch (PDOException $e) {
            error_log('Staff password failed: ' . $e->getMessage());
            $this->go('flash_error', 'Не удалось сменить пароль. Попробуйте позже.', '/admin/staff');
        }
        if (!$user) {
            $this->go('flash_error', 'Сотрудник не найден.', '/admin/staff');
        }

        $this->go(
            'flash_success',
            "Новый пароль для {$user['full_name']} ({$user['email']}): {$password}" . ($generated ? ' (создан автоматически)' : '')
            . ' — передайте его сотруднику, повторно пароль не показывается.',
            '/admin/staff'
        );
    }

    private function randomPassword(): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $max = strlen($alphabet) - 1;
        $out = '';
        for ($i = 0; $i < 10; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }
        return $out;
    }

    /* ===================== Проверки ===================== */

    public function inspections(): void
    {
        $admin = $this->requireAdmin();

        $str = fn(string $k): string => is_string($_GET[$k] ?? null) ? trim($_GET[$k]) : '';
        $view = in_array($str('view'), self::VIEWS, true) ? $str('view') : 'all';
        $q = mb_substr($str('q'), 0, 100);

        $where = [];
        $params = [];
        if ($view !== 'all') {
            $where[] = self::VIEW_SQL[$view];
        }
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where[] = '(d.deal_number LIKE ? OR b.name LIKE ? OR s.name LIKE ? OR p.name LIKE ? OR u.full_name LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like);
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $list = [];
        $counts = array_fill_keys(self::VIEWS, 0);
        $dbNotice = '';

        try {
            $stmt = $this->pdo->prepare(
                "SELECT i.id, i.status, i.fee, i.fee_status, i.reward, i.reward_paid_at, i.verdict, i.submitted_at, i.created_at,
                        i.revision_count, d.deal_number, p.name AS product_name,
                        b.name AS buyer_name, s.name AS seller_name, u.full_name AS inspector_name
                 FROM inspections i
                 JOIN deals d ON d.id = i.deal_id
                 JOIN products p ON p.id = d.product_id
                 JOIN companies b ON b.id = d.buyer_company_id
                 JOIN companies s ON s.id = d.seller_company_id
                 LEFT JOIN users u ON u.id = i.inspector_id
                 $whereSql
                 ORDER BY (i.status = 'submitted') DESC, i.created_at DESC, i.id DESC
                 LIMIT 200"
            );
            $stmt->execute($params);
            $list = $stmt->fetchAll();

            $cols = [];
            foreach (self::VIEW_SQL as $key => $sql) {
                $cols[] = "COALESCE(SUM($sql), 0) AS c_$key";
            }
            $row = $this->pdo->query('SELECT COUNT(*) AS c_all, ' . implode(', ', $cols) . ' FROM inspections i')->fetch();
            foreach (self::VIEWS as $key) {
                $counts[$key] = (int)$row['c_' . $key];
            }
        } catch (PDOException $e) {
            error_log('Admin inspections failed: ' . $e->getMessage());
            $dbNotice = 'Не удалось загрузить проверки. Выполните SQL-скрипт sql/migration_inspections.sql.';
        }

        $filters = ['view' => $view, 'q' => $q];
        $pageTitle = 'Проверки';
        require BASE_PATH . '/views/admin/inspections.php';
    }

    public function inspection(): void
    {
        $admin = $this->requireAdmin();
        $id = (int)($_GET['id'] ?? 0);

        $insp = false;
        $events = [];
        $inspectors = [];
        try {
            $stmt = $this->pdo->prepare(
                "SELECT i.*, d.id AS deal_id, d.deal_number, d.status AS deal_status, d.quantity, d.total_amount,
                        d.delivery_address, p.name AS product_name, p.unit,
                        b.name AS buyer_name, b.phone AS buyer_phone, s.name AS seller_name, s.phone AS seller_phone,
                        s.address AS seller_address,
                        u.full_name AS inspector_name, u.email AS inspector_email,
                        r.full_name AS requester_name, rv.full_name AS reviewer_name
                 FROM inspections i
                 JOIN deals d ON d.id = i.deal_id
                 JOIN products p ON p.id = d.product_id
                 JOIN companies b ON b.id = d.buyer_company_id
                 JOIN companies s ON s.id = d.seller_company_id
                 LEFT JOIN users u  ON u.id  = i.inspector_id
                 LEFT JOIN users r  ON r.id  = i.requested_by
                 LEFT JOIN users rv ON rv.id = i.reviewed_by
                 WHERE i.id = ? LIMIT 1"
            );
            $stmt->execute([$id]);
            $insp = $stmt->fetch();

            if ($insp) {
                $stmt = $this->pdo->prepare(
                    'SELECT e.action, e.comment, e.created_at, u.full_name
                     FROM inspection_events e LEFT JOIN users u ON u.id = e.user_id
                     WHERE e.inspection_id = ? ORDER BY e.created_at DESC, e.id DESC'
                );
                $stmt->execute([$id]);
                $events = $stmt->fetchAll();

                $inspectors = $this->pdo->query(
                    "SELECT id, full_name FROM users WHERE role = 'inspector' AND is_active = 1 ORDER BY full_name"
                )->fetchAll();
            }
        } catch (PDOException $e) {
            error_log('Admin inspection view failed: ' . $e->getMessage());
        }

        if (!$insp) {
            $this->go('flash_error', 'Проверка не найдена.', '/admin/inspections');
        }

        $actions = inspectionAdminActions($insp);
        $pageTitle = 'Проверка ' . $insp['deal_number'];
        require BASE_PATH . '/views/admin/inspection.php';
    }

    /** Действие администратора по проверке. Состояние перепроверяется под блокировкой строки. */
    public function act(): void
    {
        $admin = $this->requireAdmin();

        $back = is_string($_POST['back'] ?? null) && preg_match('~^/admin/inspections(?:/view)?(?:\?[^\s#]*)?$~', $_POST['back'])
            ? $_POST['back']
            : '/admin/inspections';

        if (!csrfValid()) {
            $this->go('flash_error', 'Сессия устарела. Обновите страницу и повторите действие.', $back);
        }

        $id = (int)($_POST['inspection_id'] ?? 0);
        $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
        $comment = is_string($_POST['comment'] ?? null) ? trim($_POST['comment']) : '';

        if ($id <= 0 || !in_array($action, self::ACTIONS, true)) {
            $this->go('flash_error', 'Некорректный запрос.', $back);
        }
        if (in_array($action, ['return', 'cancel', 'refund_fee'], true) && $comment === '') {
            $this->go('flash_error', 'Укажите причину — она сохраняется в журнале и показывается проверяющему.', $back);
        }
        if (mb_strlen($comment) > 500) {
            $this->go('flash_error', 'Комментарий не должен быть длиннее 500 символов.', $back);
        }

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                'SELECT i.id, i.status, i.fee, i.fee_status, i.reward, i.reward_paid_at, i.inspector_id, d.deal_number
                 FROM inspections i JOIN deals d ON d.id = i.deal_id WHERE i.id = ? FOR UPDATE'
            );
            $stmt->execute([$id]);
            $insp = $stmt->fetch();
            if (!$insp) {
                throw new LogicException('Проверка не найдена.');
            }
            if (!in_array($action, inspectionAdminActions($insp), true)) {
                throw new LogicException("Действие недоступно: состояние проверки по сделке {$insp['deal_number']} уже изменилось.");
            }

            $eventComment = $comment;
            switch ($action) {
                case 'confirm_fee':
                    $sql = "UPDATE inspections SET fee_status = 'paid', fee_paid_at = NOW()
                            WHERE id = ? AND fee_status = 'unpaid' AND status IN ('requested', 'assigned', 'submitted')";
                    $args = [$id];
                    $event = 'fee_confirmed';
                    break;

                case 'assign':
                    $inspectorId = (int)($_POST['inspector_id'] ?? 0);
                    $stmt = $this->pdo->prepare("SELECT full_name FROM users WHERE id = ? AND role = 'inspector' AND is_active = 1");
                    $stmt->execute([$inspectorId]);
                    $inspectorName = $stmt->fetchColumn();
                    if ($inspectorName === false) {
                        throw new LogicException('Выберите действующего проверяющего.');
                    }
                    $sql = "UPDATE inspections SET inspector_id = ?, status = 'assigned', assigned_at = NOW(),
                                   visit_status = 'none', visit_proposed_by = NULL, visit_at = NULL, visit_confirmed_at = NULL
                            WHERE id = ? AND status IN ('requested', 'assigned') AND fee_status = 'paid'";
                    $args = [$inspectorId, $id];
                    $event = 'assigned';
                    $eventComment = trim('Проверяющий: ' . $inspectorName . ($comment !== '' ? '. ' . $comment : ''));
                    break;

                case 'approve':
                    $raw = is_string($_POST['reward'] ?? null) ? str_replace([' ', ','], ['', '.'], $_POST['reward']) : '';
                    if ($raw === '' || !is_numeric($raw) || (float)$raw < 0 || (float)$raw > (float)$insp['fee']) {
                        throw new LogicException('Вознаграждение проверяющему: число от 0 до суммы доплаты (' . formatPrice($insp['fee']) . ').');
                    }
                    $reward = round((float)$raw, 2);
                    $sql = "UPDATE inspections SET status = 'approved', reward = ?, reviewed_by = ?, reviewed_at = NOW(), admin_comment = ?
                            WHERE id = ? AND status = 'submitted'";
                    $args = [$reward, $admin['id'], nullIfEmpty($comment), $id];
                    $event = 'approved';
                    $eventComment = trim('Вознаграждение ' . formatPrice($reward) . ($comment !== '' ? '. ' . $comment : ''));
                    break;

                case 'return':
                    $sql = "UPDATE inspections SET status = 'assigned', revision_count = revision_count + 1,
                                   reviewed_by = ?, reviewed_at = NOW(), admin_comment = ?
                            WHERE id = ? AND status = 'submitted'";
                    $args = [$admin['id'], $comment, $id];
                    $event = 'returned';
                    break;

                case 'cancel':
                    $sql = "UPDATE inspections SET status = 'cancelled', reviewed_by = ?, reviewed_at = NOW(), admin_comment = ?
                            WHERE id = ? AND status IN ('requested', 'assigned', 'submitted')";
                    $args = [$admin['id'], $comment, $id];
                    $event = 'cancelled';
                    break;

                case 'refund_fee':
                    $sql = "UPDATE inspections SET fee_status = 'refunded'
                            WHERE id = ? AND status = 'cancelled' AND fee_status = 'paid'";
                    $args = [$id];
                    $event = 'fee_refunded';
                    break;

                default: // pay_reward
                    $sql = "UPDATE inspections SET reward_paid_at = NOW()
                            WHERE id = ? AND status = 'approved' AND reward_paid_at IS NULL";
                    $args = [$id];
                    $event = 'reward_paid';
                    break;
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($args);
            if ($stmt->rowCount() === 0) {
                throw new LogicException("Проверка по сделке {$insp['deal_number']} уже обработана.");
            }
            logInspectionEvent($this->pdo, $id, (int)$admin['id'], $event, $eventComment);

            $this->pdo->commit();
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Admin inspection action failed: ' . $e->getMessage());
            $this->go('flash_error', 'Не удалось выполнить действие. Попробуйте позже.', $back);
        } catch (LogicException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->go('flash_error', $e->getMessage(), $back);
        }

        $number = $insp['deal_number'];
        $this->go('flash_success', match ($action) {
            'confirm_fee' => "Доплата за проверку по сделке {$number} подтверждена — задание доступно проверяющим.",
            'assign'      => "Проверяющий назначен на сделку {$number}.",
            'approve'     => "Отчёт по сделке {$number} подтверждён. Теперь можно подтвердить саму сделку и выплатить вознаграждение проверяющему.",
            'return'      => "Отчёт по сделке {$number} возвращён проверяющему на доработку.",
            'cancel'      => "Проверка по сделке {$number} отменена.",
            'refund_fee'  => "Возврат доплаты по сделке {$number} отмечен.",
            'pay_reward'  => "Выплата вознаграждения по сделке {$number} отмечена.",
        }, $back);
    }
}
