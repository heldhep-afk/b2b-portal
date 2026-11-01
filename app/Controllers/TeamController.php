<?php
/** Команда компании: сотрудники, заявки по коду компании, роли и права. */
class TeamController
{
    private const MAX_USERS = 20;                      // активных сотрудников в компании
    private const REVIEWER_ASSIGNS = ['employee'];     // какие роли выдаёт при одобрении не директор

    public function __construct(private PDO $pdo) {}

    private function requireMember(): void
    {
        if (!isset($_SESSION['user_id'], $_SESSION['company_id'])) {
            $_SESSION['after_login'] = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' ? ($_SERVER['REQUEST_URI'] ?? '/team') : '/team';
            $_SESSION['flash_error'] = 'Войдите в аккаунт компании.';
            header('Location: /login');
            exit;
        }
        if (isStaffRole($_SESSION['role'] ?? '')) {
            header('Location: ' . staffHome($_SESSION['role'] ?? ''));
            exit;
        }
    }

    /** Действия над командой: только директор и только с CSRF-токеном. */
    private function requireOwner(): void
    {
        $this->requireMember();
        if (!canCompany('manage_team')) {
            $this->back('flash_error', 'Управлять сотрудниками может только директор компании.');
        }
        if (!csrfValid()) {
            $this->back('flash_error', 'Сессия устарела. Обновите страницу и повторите действие.');
        }
    }

    /** Заявки принимают директор и сотрудники с правом «Заявки на вступление». */
    private function requireReviewer(): void
    {
        $this->requireMember();
        if (!canCompany('review_requests')) {
            $this->back('flash_error', 'Недостаточно прав для работы с заявками. Обратитесь к директору компании.', '#requests');
        }
        if (!csrfValid()) {
            $this->back('flash_error', 'Сессия устарела. Обновите страницу и повторите действие.', '#requests');
        }
    }

    private function back(string $key, string $message, string $anchor = ''): never
    {
        $_SESSION[$key] = $message;
        header('Location: /team' . $anchor);
        exit;
    }

    public function index(): void
    {
        $this->requireMember();
        $companyId = (int)$_SESSION['company_id'];
        $isOwner = canCompany('manage_team');
        $canReview = canCompany('review_requests');
        $members = [];
        $requests = [];
        $company = ['join_code' => null, 'join_enabled' => 1];
        $dbNotice = '';

        try {
            $stmt = $this->pdo->prepare(
                "SELECT u.id, u.username, u.full_name, u.`position`, u.email, u.phone, u.company_role, u.permissions,
                        u.is_active, u.created_at,
                        (SELECT COUNT(*) FROM deals d WHERE d.created_by = u.id) AS deals_cnt
                 FROM users u
                 WHERE u.company_id = ? AND u.join_status = 'approved'
                 ORDER BY FIELD(u.company_role, 'owner', 'manager', 'employee'), u.is_active DESC, u.full_name"
            );
            $stmt->execute([$companyId]);
            $members = $stmt->fetchAll();
            foreach ($members as &$m) {
                $m['custom'] = $m['permissions'] !== null;
                $m['perms'] = companyEffectivePermissions($m['company_role'], $m['permissions']);
            }
            unset($m);

            if ($canReview) {
                $stmt = $this->pdo->prepare(
                    "SELECT id, username, full_name, `position`, email, phone, join_requested_at
                     FROM users
                     WHERE company_id = ? AND join_status = 'pending'
                     ORDER BY join_requested_at, id"
                );
                $stmt->execute([$companyId]);
                $requests = $stmt->fetchAll();

                $stmt = $this->pdo->prepare('SELECT join_code, join_enabled FROM companies WHERE id = ? LIMIT 1');
                $stmt->execute([$companyId]);
                $company = $stmt->fetch() ?: $company;
            }
        } catch (PDOException $e) {
            error_log('Team page failed: ' . $e->getMessage());
            $dbNotice = 'Не удалось загрузить команду. Проверьте, что выполнен актуальный SQL-скрипт.';
        }

        $activeCount = count(array_filter($members, fn($m) => (int)$m['is_active'] === 1));
        $slotsLeft = max(0, self::MAX_USERS - $activeCount);
        $assignable = $isOwner ? ['manager', 'employee'] : self::REVIEWER_ASSIGNS;
        $permissionCatalog = companyPermissionCatalog();
        $roleDefaults = ['manager' => companyRoleDefaults('manager'), 'employee' => companyRoleDefaults('employee')];

        $companyName = $_SESSION['company_name'] ?? '';
        $pageTitle = 'Команда компании';
        require BASE_PATH . '/views/team/index.php';
    }

    /** Решение по заявке: директор или менеджер принимает (и выдаёт роль) либо отклоняет. */
    public function decide(): void
    {
        $this->requireReviewer();
        $companyId = (int)$_SESSION['company_id'];
        $userId = (int)($_POST['user_id'] ?? 0);
        $decision = is_string($_POST['decision'] ?? null) ? $_POST['decision'] : '';
        $role = is_string($_POST['company_role'] ?? null) ? $_POST['company_role'] : '';
        $position = is_string($_POST['position'] ?? null) ? trim($_POST['position']) : '';

        if (!in_array($decision, ['approve', 'reject'], true)) {
            $this->back('flash_error', 'Некорректный запрос.', '#requests');
        }
        if (mb_strlen($position) > 100) {
            $this->back('flash_error', 'Должность не должна быть длиннее 100 символов.', '#requests');
        }

        try {
            $stmt = $this->pdo->prepare("SELECT id, full_name FROM users WHERE id = ? AND company_id = ? AND join_status = 'pending' LIMIT 1");
            $stmt->execute([$userId, $companyId]);
            $target = $stmt->fetch();
            if (!$target) {
                $this->back('flash_error', 'Заявка не найдена или уже обработана.', '#requests');
            }

            if ($decision === 'approve') {
                $allowed = canCompany('manage_team') ? ['manager', 'employee'] : self::REVIEWER_ASSIGNS;
                if (!in_array($role, $allowed, true)) {
                    $this->back('flash_error', canCompany('manage_team')
                        ? 'Выберите роль для сотрудника.'
                        : 'Вы можете выдавать только роль «Сотрудник». Роль «Менеджер» назначает директор.', '#requests');
                }

                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE company_id = ? AND is_active = 1 AND join_status = 'approved'");
                $stmt->execute([$companyId]);
                if ((int)$stmt->fetchColumn() >= self::MAX_USERS) {
                    $this->back('flash_error', 'Достигнут лимит сотрудников (' . self::MAX_USERS . '). Отключите кого-то, чтобы принять заявку.', '#requests');
                }

                $stmt = $this->pdo->prepare(
                    "UPDATE users SET join_status = 'approved', company_role = ?, permissions = NULL, is_active = 1,
                                      `position` = COALESCE(NULLIF(?, ''), `position`),
                                      join_decided_by = ?, join_decided_at = NOW()
                     WHERE id = ? AND company_id = ? AND join_status = 'pending'"
                );
                $stmt->execute([$role, $position, $_SESSION['user_id'], $userId, $companyId]);
            } else {
                $stmt = $this->pdo->prepare(
                    "UPDATE users SET join_status = 'rejected', join_decided_by = ?, join_decided_at = NOW()
                     WHERE id = ? AND company_id = ? AND join_status = 'pending'"
                );
                $stmt->execute([$_SESSION['user_id'], $userId, $companyId]);
            }

            if ($stmt->rowCount() === 0) {
                $this->back('flash_error', 'Заявка уже обработана.', '#requests');
            }
        } catch (PDOException $e) {
            error_log('Join decision failed: ' . $e->getMessage());
            $this->back('flash_error', 'Не удалось сохранить решение. Попробуйте позже.', '#requests');
        }

        $this->back('flash_success', $decision === 'approve'
            ? 'Заявка принята: «' . $target['full_name'] . '» теперь в команде с ролью «' . companyRoleLabel($role) . '».'
            : 'Заявка «' . $target['full_name'] . '» отклонена.', '#requests');
    }

    /** Новый код компании: старый перестаёт работать. */
    public function regenerateCode(): void
    {
        $this->requireOwner();
        $stmt = $this->pdo->prepare('UPDATE companies SET join_code = ? WHERE id = ?');

        for ($i = 0; $i < 5; $i++) {
            try {
                $stmt->execute([generateJoinCode(), $_SESSION['company_id']]);
                $this->back('flash_success', 'Код компании обновлён. Старый код больше не работает.', '#requests');
            } catch (PDOException $e) {
                if ((int)($e->errorInfo[1] ?? 0) !== 1062) {
                    error_log('Code regenerate failed: ' . $e->getMessage());
                    break;
                }
            }
        }
        $this->back('flash_error', 'Не удалось создать код. Попробуйте ещё раз.', '#requests');
    }

    /** Включить или выключить приём заявок по коду. */
    public function toggleCode(): void
    {
        $this->requireOwner();
        $stmt = $this->pdo->prepare('UPDATE companies SET join_enabled = 1 - join_enabled WHERE id = ?');
        $stmt->execute([$_SESSION['company_id']]);

        $stmt = $this->pdo->prepare('SELECT join_enabled FROM companies WHERE id = ?');
        $stmt->execute([$_SESSION['company_id']]);
        $this->back('flash_success', (int)$stmt->fetchColumn() === 1
            ? 'Приём заявок включён.'
            : 'Приём заявок выключен: по коду компании присоединиться нельзя.', '#requests');
    }

    public function changeRole(): void
    {
        $this->requireOwner();
        $userId = (int)($_POST['user_id'] ?? 0);
        $role = is_string($_POST['company_role'] ?? null) ? $_POST['company_role'] : '';

        if (!in_array($role, ['manager', 'employee'], true)) {
            $this->back('flash_error', 'Некорректная роль.');
        }
        $target = $this->member($userId);
        if (!$target) {
            $this->back('flash_error', 'Сотрудник не найден.');
        }
        if ($target['company_role'] === 'owner' || $userId === (int)$_SESSION['user_id']) {
            $this->back('flash_error', 'Роль директора и свою собственную изменить нельзя.');
        }

        $stmt = $this->pdo->prepare("UPDATE users SET company_role = ?, permissions = NULL WHERE id = ? AND company_id = ? AND company_role <> 'owner'");
        $stmt->execute([$role, $userId, $_SESSION['company_id']]);

        $this->back('flash_success', 'Роль сотрудника «' . $target['full_name'] . '» изменена: ' . companyRoleLabel($role) . '. Права установлены по умолчанию для этой роли.');
    }

    /** Отключить или вернуть сотрудника. История его сделок сохраняется. */
    public function toggle(): void
    {
        $this->requireOwner();
        $userId = (int)($_POST['user_id'] ?? 0);

        $target = $this->member($userId);
        if (!$target) {
            $this->back('flash_error', 'Сотрудник не найден.');
        }
        if ($target['company_role'] === 'owner' || $userId === (int)$_SESSION['user_id']) {
            $this->back('flash_error', 'Директора и свой собственный аккаунт отключить нельзя.');
        }

        $new = (int)$target['is_active'] === 1 ? 0 : 1;
        $stmt = $this->pdo->prepare("UPDATE users SET is_active = ? WHERE id = ? AND company_id = ? AND company_role <> 'owner'");
        $stmt->execute([$new, $userId, $_SESSION['company_id']]);

        $this->back('flash_success', $new === 1
            ? 'Доступ сотрудника «' . $target['full_name'] . '» восстановлен.'
            : 'Сотрудник «' . $target['full_name'] . '» отключён и больше не сможет войти.');
    }

    /** Права всей команды одним действием: большая панель во вкладке «Права». */
    public function savePermissions(): void
    {
        $this->requireOwner();
        $companyId = (int)$_SESSION['company_id'];

        // обрабатываем только тех, чьи колонки были на странице (чтобы не сбросить права новым сотрудникам)
        $shown = is_array($_POST['perm_users'] ?? null) ? array_map('intval', $_POST['perm_users']) : [];
        $submitted = is_array($_POST['perm'] ?? null) ? $_POST['perm'] : [];
        $keys = array_keys(companyPermissionCatalog());

        try {
            $stmt = $this->pdo->prepare("SELECT id, company_role FROM users WHERE company_id = ? AND company_role <> 'owner' AND join_status = 'approved'");
            $stmt->execute([$companyId]);
            $update = $this->pdo->prepare("UPDATE users SET permissions = ? WHERE id = ? AND company_id = ? AND company_role <> 'owner'");

            foreach ($stmt->fetchAll() as $m) {
                $uid = (int)$m['id'];
                if (!in_array($uid, $shown, true) || $uid === (int)$_SESSION['user_id']) {
                    continue;
                }
                $picked = is_array($submitted[$uid] ?? null) ? array_filter($submitted[$uid], 'is_string') : [];
                $picked = array_values(array_intersect($keys, $picked));
                $defaults = companyRoleDefaults($m['company_role']);
                // набор совпал с правами роли — храним «по роли» (NULL)
                $same = !array_diff($picked, $defaults) && !array_diff($defaults, $picked);
                $update->execute([$same ? null : implode(',', $picked), $uid, $companyId]);
            }
        } catch (PDOException $e) {
            error_log('Permissions save failed: ' . $e->getMessage());
            $this->back('flash_error', 'Не удалось сохранить права. Попробуйте позже.', '#permissions');
        }

        $this->back('flash_success', 'Права сотрудников сохранены. Они действуют сразу.', '#permissions');
    }

    private function member(int $userId)
    {
        $stmt = $this->pdo->prepare("SELECT id, full_name, company_role, is_active FROM users WHERE id = ? AND company_id = ? AND join_status = 'approved' LIMIT 1");
        $stmt->execute([$userId, $_SESSION['company_id']]);
        return $stmt->fetch();
    }
}
