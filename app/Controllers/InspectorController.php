<?php
/** Кабинет проверяющего (персонал платформы): свободные задания, мои задания, отчёты и заработок. */
class InspectorController
{
    private const VERDICTS = ['ok', 'remarks', 'fail'];

    public function __construct(private PDO $pdo) {}

    private function requireInspector(): array
    {
        return requireStaff($this->pdo, ['inspector'], 'Войдите в аккаунт проверяющего.');
    }

    private function back(string $key, string $message, string $to = '/inspector'): never
    {
        $_SESSION[$key] = $message;
        header('Location: ' . $to);
        exit;
    }

    /** Поля задания, нужные проверяющему: сделка, товар, стороны. */
    private const TASK_SELECT =
        'SELECT i.id, i.status, i.fee, i.reward, i.reward_paid_at, i.fee_status, i.buyer_note, i.inspector_id,
                i.verdict, i.report_summary, i.report_details, i.report_links, i.checked_on, i.submitted_at,
                i.admin_comment, i.revision_count, i.assigned_at, i.created_at,
                i.visit_at, i.visit_status, i.visit_proposed_by, i.visit_note,
                i.escort_name, i.escort_number, i.escort_phone, i.visit_confirmed_at,
                d.id AS deal_id, d.deal_number, d.quantity, d.total_amount, d.status AS deal_status,
                d.delivery_address, d.comment AS deal_comment,
                p.name AS product_name, p.description AS product_description, p.unit, p.sku,
                cat.name AS category_name,
                b.name AS buyer_name, b.phone AS buyer_phone,
                s.name AS seller_name, s.phone AS seller_phone, s.address AS seller_address
         FROM inspections i
         JOIN deals d ON d.id = i.deal_id
         JOIN products p ON p.id = d.product_id
         LEFT JOIN categories cat ON cat.id = p.category_id
         JOIN companies b ON b.id = d.buyer_company_id
         JOIN companies s ON s.id = d.seller_company_id';

    public function index(): void
    {
        $me = $this->requireInspector();

        $tab = is_string($_GET['tab'] ?? null) ? $_GET['tab'] : 'mine';
        $tab = in_array($tab, ['pool', 'mine', 'done'], true) ? $tab : 'mine';

        $pool = $mine = $done = [];
        $money = ['earned' => 0.0, 'owed' => 0.0, 'approved' => 0, 'in_work' => 0];
        $dbNotice = '';

        try {
            // свободные: доплата получена, проверяющего нет, сделка ещё в работе
            $stmt = $this->pdo->query(
                self::TASK_SELECT . " WHERE i.status = 'requested' AND i.inspector_id IS NULL AND d.status = 'in_progress'
                                      ORDER BY (i.fee_status = 'paid') DESC, i.created_at ASC, i.id ASC LIMIT 100"
            );
            $pool = $stmt->fetchAll();

            $stmt = $this->pdo->prepare(
                self::TASK_SELECT . " WHERE i.inspector_id = ? AND i.status IN ('assigned', 'submitted')
                                      ORDER BY (i.status = 'assigned') DESC, i.assigned_at ASC, i.id ASC"
            );
            $stmt->execute([$me['id']]);
            $mine = $stmt->fetchAll();

            $stmt = $this->pdo->prepare(
                self::TASK_SELECT . " WHERE i.inspector_id = ? AND i.status IN ('approved', 'cancelled')
                                      ORDER BY i.updated_at DESC, i.id DESC LIMIT 100"
            );
            $stmt->execute([$me['id']]);
            $done = $stmt->fetchAll();

            $stmt = $this->pdo->prepare(
                "SELECT COALESCE(SUM(CASE WHEN status = 'approved' AND reward_paid_at IS NOT NULL THEN reward END), 0) AS earned,
                        COALESCE(SUM(CASE WHEN status = 'approved' AND reward_paid_at IS NULL THEN reward END), 0) AS owed,
                        COALESCE(SUM(status = 'approved'), 0) AS approved,
                        COALESCE(SUM(status IN ('assigned', 'submitted')), 0) AS in_work
                 FROM inspections WHERE inspector_id = ?"
            );
            $stmt->execute([$me['id']]);
            $row = $stmt->fetch();
            $money = [
                'earned'   => (float)$row['earned'],
                'owed'     => (float)$row['owed'],
                'approved' => (int)$row['approved'],
                'in_work'  => (int)$row['in_work'],
            ];
        } catch (PDOException $e) {
            error_log('Inspector page failed: ' . $e->getMessage());
            $dbNotice = 'Не удалось загрузить задания. Проверьте, что выполнен SQL-скрипт sql/migration_inspections.sql.';
        }

        $pageTitle = 'Кабинет проверяющего';
        require BASE_PATH . '/views/inspector/index.php';
    }

    /** Страница задания: данные сделки, форма отчёта (если задание моё и в работе). */
    public function task(): void
    {
        $me = $this->requireInspector();
        $task = $this->loadTask((int)($_GET['id'] ?? 0), (int)$me['id']);

        $old = [
            'verdict' => (string)($task['verdict'] ?? ''),
            'checked_on' => $task['checked_on'] ?: date('Y-m-d'),
            'report_summary' => (string)($task['report_summary'] ?? ''),
            'report_details' => (string)($task['report_details'] ?? ''),
            'report_links' => (string)($task['report_links'] ?? ''),
        ];
        $errors = [];
        $this->renderTask($task, $old, $errors);
    }

    /** Взять свободное задание. UPDATE с условиями защищает от гонки: задание достанется одному. */
    public function take(): void
    {
        $me = $this->requireInspector();
        if (!csrfValid()) {
            $this->back('flash_error', 'Сессия устарела. Обновите страницу и повторите действие.', '/inspector?tab=pool');
        }
        $id = (int)($_POST['inspection_id'] ?? 0);

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                "UPDATE inspections i JOIN deals d ON d.id = i.deal_id
                 SET i.inspector_id = ?, i.status = 'assigned', i.assigned_at = NOW()
                 WHERE i.id = ? AND i.status = 'requested' AND i.inspector_id IS NULL
                   AND i.fee_status = 'paid' AND d.status = 'in_progress'"
            );
            $stmt->execute([$me['id'], $id]);
            if ($stmt->rowCount() === 0) {
                $this->pdo->rollBack();
                $this->back('flash_error', 'Это задание уже взял другой проверяющий или оно недоступно.', '/inspector?tab=pool');
            }
            logInspectionEvent($this->pdo, $id, (int)$me['id'], 'taken', null);

            $this->pdo->commit();
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Inspection take failed: ' . $e->getMessage());
            $this->back('flash_error', 'Не удалось взять задание. Попробуйте позже.', '/inspector?tab=pool');
        }

        $this->back('flash_success', 'Задание ваше. Выполните проверку и отправьте отчёт.', '/inspector/task?id=' . $id);
    }

    /** Отправить (или повторно отправить после доработки) отчёт администратору. */
    public function report(): void
    {
        $me = $this->requireInspector();
        $id = (int)($_POST['inspection_id'] ?? 0);
        $to = '/inspector/task?id=' . $id;

        if (!csrfValid()) {
            $this->back('flash_error', 'Сессия устарела. Обновите страницу и повторите действие.', $to);
        }

        $str = fn(string $k): string => is_string($_POST[$k] ?? null) ? trim($_POST[$k]) : '';
        $verdict = $str('verdict');
        $checkedOn = $str('checked_on');
        $summary = $str('report_summary');
        $details = $str('report_details');
        $links = $str('report_links');

        $errors = [];
        if (!in_array($verdict, self::VERDICTS, true)) {
            $errors['verdict'] = 'Выберите итог проверки.';
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $checkedOn);
        if (!$date || $date->format('Y-m-d') !== $checkedOn) {
            $errors['checked_on'] = 'Укажите дату проверки.';
        } elseif ($date > new DateTimeImmutable('today')) {
            $errors['checked_on'] = 'Дата проверки не может быть в будущем.';
        } elseif ($date < new DateTimeImmutable('-1 year')) {
            $errors['checked_on'] = 'Слишком давняя дата проверки.';
        }
        if (mb_strlen($summary) < 10) {
            $errors['report_summary'] = 'Опишите результат проверки (не короче 10 символов).';
        } elseif (mb_strlen($summary) > 1000) {
            $errors['report_summary'] = 'Краткий вывод — не длиннее 1000 символов.';
        }
        if (mb_strlen($details) > 5000) {
            $errors['report_details'] = 'Подробности — не длиннее 5000 символов.';
        }
        if (mb_strlen($links) > 1000) {
            $errors['report_links'] = 'Ссылки — не длиннее 1000 символов.';
        } elseif ($links !== '') {
            foreach (preg_split('/\s+/u', $links, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $url) {
                if (!preg_match('~^https?://~i', $url) || !filter_var($url, FILTER_VALIDATE_URL)) {
                    $errors['report_links'] = 'Укажите ссылки полностью (https://…), по одной через пробел или с новой строки.';
                    break;
                }
            }
        }

        $task = $this->loadTask($id, (int)$me['id']);
        if (!$errors && $task['status'] !== 'assigned') {
            $this->back('flash_error', 'Отчёт можно отправить только по заданию «Проверяется».', $to);
        }
        if (!$errors && $task['visit_status'] !== 'confirmed') {
            $this->back('flash_error', 'Сначала согласуйте с продавцом время проверки и получите данные сопровождающего.', $to);
        }

        if ($errors) {
            $old = ['verdict' => $verdict, 'checked_on' => $checkedOn, 'report_summary' => $summary,
                    'report_details' => $details, 'report_links' => $links];
            $this->renderTask($task, $old, $errors);
            return;
        }

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                "UPDATE inspections
                 SET status = 'submitted', verdict = ?, checked_on = ?, report_summary = ?, report_details = ?,
                     report_links = ?, submitted_at = NOW()
                 WHERE id = ? AND inspector_id = ? AND status = 'assigned'"
            );
            $stmt->execute([$verdict, $checkedOn, $summary, nullIfEmpty($details), nullIfEmpty($links), $id, $me['id']]);
            if ($stmt->rowCount() === 0) {
                $this->pdo->rollBack();
                $this->back('flash_error', 'Задание уже изменилось — обновите страницу.', $to);
            }
            logInspectionEvent($this->pdo, $id, (int)$me['id'], 'submitted', null);

            $this->pdo->commit();
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Inspection report failed: ' . $e->getMessage());
            $this->back('flash_error', 'Не удалось сохранить отчёт. Попробуйте позже.', $to);
        }

        $this->back('flash_success', 'Отчёт отправлен администратору. После подтверждения вознаграждение будет выплачено.', '/inspector');
    }

    /** Проверяющий предлагает время визита или подтверждает время, предложенное продавцом. */
    public function visit(): void
    {
        $me = $this->requireInspector();
        $id = (int)($_POST['inspection_id'] ?? 0);
        $to = '/inspector/task?id=' . $id;
        if (!csrfValid()) {
            $this->back('flash_error', 'Сессия устарела. Обновите страницу и повторите действие.', $to);
        }

        $mode = is_string($_POST['mode'] ?? null) ? $_POST['mode'] : '';
        $note = is_string($_POST['visit_note'] ?? null) ? trim($_POST['visit_note']) : '';
        if (!in_array($mode, ['confirm', 'propose'], true) || mb_strlen($note) > 300) {
            $this->back('flash_error', 'Некорректный запрос.', $to);
        }

        $newTime = null;
        if ($mode === 'propose') {
            $newTime = parseVisitDateTime(is_string($_POST['visit_at'] ?? null) ? $_POST['visit_at'] : '');
            if (!$newTime) {
                $this->back('flash_error', 'Укажите дату и время проверки.', $to);
            }
            if (($err = visitDateError($newTime)) !== null) {
                $this->back('flash_error', $err, $to);
            }
        }

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                'SELECT id, status, inspector_id, visit_status, visit_proposed_by, visit_at FROM inspections WHERE id = ? FOR UPDATE'
            );
            $stmt->execute([$id]);
            $insp = $stmt->fetch();
            if (!$insp || (int)$insp['inspector_id'] !== (int)$me['id'] || $insp['status'] !== 'assigned') {
                throw new LogicException('Согласовать время можно только по вашему заданию «Проверяется».');
            }

            if ($mode === 'confirm') {
                if ($insp['visit_status'] !== 'proposed' || $insp['visit_proposed_by'] !== 'seller' || !$insp['visit_at']) {
                    throw new LogicException('Нет времени, предложенного продавцом, — предложите своё.');
                }
                $stmt = $this->pdo->prepare(
                    "UPDATE inspections SET visit_status = 'confirmed', visit_confirmed_at = NOW()
                     WHERE id = ? AND status = 'assigned' AND visit_status = 'proposed' AND visit_proposed_by = 'seller'"
                );
                $stmt->execute([$id]);
                $event = 'visit_confirmed';
                $eventComment = 'Время ' . formatDateTime($insp['visit_at']) . ' согласовано проверяющим';
            } else {
                $stmt = $this->pdo->prepare(
                    "UPDATE inspections SET visit_status = 'proposed', visit_proposed_by = 'inspector', visit_at = ?,
                            visit_confirmed_at = NULL, visit_note = ?
                     WHERE id = ? AND status = 'assigned'"
                );
                $stmt->execute([$newTime->format('Y-m-d H:i:00'), nullIfEmpty($note), $id]);
                $event = 'visit_proposed';
                $eventComment = 'Проверяющий предлагает ' . formatDateTime($newTime->format('Y-m-d H:i:00'));
            }
            if ($stmt->rowCount() === 0) {
                throw new LogicException('Состояние задания уже изменилось — обновите страницу.');
            }
            logInspectionEvent($this->pdo, $id, (int)$me['id'], $event, $eventComment);

            $this->pdo->commit();
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Inspection visit (inspector) failed: ' . $e->getMessage());
            $this->back('flash_error', 'Не удалось сохранить. Проверьте, что выполнен SQL-скрипт sql/migration_inspection_visits.sql.', $to);
        } catch (LogicException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->back('flash_error', $e->getMessage(), $to);
        }

        $this->back('flash_success', $mode === 'confirm'
            ? 'Время проверки согласовано. После проверки отправьте отчёт.'
            : 'Время отправлено продавцу на согласование.', $to);
    }

    /** Задание: чужое показать нельзя; свободное (оплаченное) — можно посмотреть и взять. */
    private function loadTask(int $id, int $inspectorId): array
    {
        $task = false;
        if ($id > 0) {
            try {
                $stmt = $this->pdo->prepare(self::TASK_SELECT . ' WHERE i.id = ? LIMIT 1');
                $stmt->execute([$id]);
                $task = $stmt->fetch();
            } catch (PDOException $e) {
                error_log('Inspector task failed: ' . $e->getMessage());
            }
        }
        $mine = $task && $task['inspector_id'] !== null && (int)$task['inspector_id'] === $inspectorId;
        $free = $task && $task['inspector_id'] === null && $task['status'] === 'requested';
        if (!$task || (!$mine && !$free)) {
            $this->back('flash_error', 'Задание не найдено или недоступно.');
        }
        $task['mine'] = $mine;
        return $task;
    }

    private function renderTask(array $task, array $old, array $errors): void
    {
        $statusMeta = inspectionStatusMeta($task['status']);
        $canReport = $task['mine'] && $task['status'] === 'assigned';
        $events = [];
        try {
            $stmt = $this->pdo->prepare(
                'SELECT e.action, e.comment, e.created_at, u.full_name
                 FROM inspection_events e LEFT JOIN users u ON u.id = e.user_id
                 WHERE e.inspection_id = ? ORDER BY e.created_at DESC, e.id DESC LIMIT 20'
            );
            $stmt->execute([(int)$task['id']]);
            $events = $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('Inspector events failed: ' . $e->getMessage());
        }
        $pageTitle = 'Задание ' . $task['deal_number'];
        require BASE_PATH . '/views/inspector/task.php';
    }
}
