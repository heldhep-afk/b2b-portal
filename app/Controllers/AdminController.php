<?php
/** Админ-панель платформы: подтверждение и отмена сделок, платежи через администраторов. */
class AdminController
{
    private const STATUS_FILTERS = ['all', 'in_progress', 'completed', 'rejected', 'cancelled'];
    private const PAY_FILTERS    = ['all', 'unpaid', 'paid', 'paid_out', 'refunded'];
    private const VIEWS          = ['all', 'payment', 'confirm', 'payout', 'refund'];
    private const ACTIONS        = ['confirm_payment', 'confirm_deal', 'cancel', 'payout', 'refund'];

    /** SQL-условия «очередей» администратора. */
    private const VIEW_SQL = [
        'payment' => "(d.status = 'in_progress' AND d.payment_status = 'unpaid')",
        'confirm' => "(d.status = 'in_progress' AND d.payment_status = 'paid' AND d.buyer_approved_at IS NOT NULL AND (i.id IS NULL OR i.status IN ('approved', 'cancelled')))",
        'payout'  => "(d.status = 'completed' AND d.payment_status = 'paid')",
        'refund'  => "(d.status IN ('rejected', 'cancelled') AND d.payment_status = 'paid')",
    ];

    public function __construct(private PDO $pdo) {}

    /** Только администраторы платформы. Роль каждый раз перепроверяется в БД. */
    private function requireAdmin(): array
    {
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['after_login'] = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
                ? ($_SERVER['REQUEST_URI'] ?? '/admin')
                : '/admin';
            $_SESSION['flash_error'] = 'Войдите в аккаунт администратора.';
            header('Location: /login');
            exit;
        }

        $stmt = $this->pdo->prepare('SELECT id, username, full_name, role FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if (!$user || $user['role'] !== 'admin') {
            $_SESSION['flash_error'] = 'Недостаточно прав для доступа к админ-панели.';
            header('Location: /');
            exit;
        }
        $_SESSION['role'] = 'admin';
        return $user;
    }

    public function index(): void
    {
        $admin = $this->requireAdmin();

        $str = fn(string $k): string => is_string($_GET[$k] ?? null) ? trim($_GET[$k]) : '';
        $status = in_array($str('status'), self::STATUS_FILTERS, true) ? $str('status') : 'all';
        $pay    = in_array($str('pay'), self::PAY_FILTERS, true) ? $str('pay') : 'all';
        $view   = in_array($str('view'), self::VIEWS, true) ? $str('view') : 'all';
        $q      = mb_substr($str('q'), 0, 100);

        $where = [];
        $params = [];
        if ($status !== 'all') {
            $where[] = 'd.status = ?';
            $params[] = $status;
        }
        if ($pay !== 'all') {
            $where[] = 'd.payment_status = ?';
            $params[] = $pay;
        }
        if ($view !== 'all') {
            $where[] = self::VIEW_SQL[$view];
        }
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where[] = '(d.deal_number LIKE ? OR b.name LIKE ? OR s.name LIKE ? OR p.name LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $deals = [];
        $events = [];
        $stats = ['payment' => 0, 'confirm' => 0, 'payout' => 0, 'refund' => 0, 'held' => 0.0, 'paid_out' => 0.0, 'total' => 0];
        $dbNotice = '';

        try {
            $stmt = $this->pdo->prepare(
                "SELECT d.id, d.deal_number, d.status, d.payment_status, d.buyer_approved_at, d.paid_at, d.paid_out_at,
                        d.quantity, d.total_amount, d.created_at, d.closed_at,
                        b.name AS buyer_name, s.name AS seller_name, p.name AS product_name, p.unit,
                        i.id AS inspection_id, i.status AS inspection_status, i.verdict AS inspection_verdict
                 FROM deals d
                 JOIN companies b ON b.id = d.buyer_company_id
                 JOIN companies s ON s.id = d.seller_company_id
                 JOIN products  p ON p.id = d.product_id
                 LEFT JOIN inspections i ON i.deal_id = d.id
                 $whereSql
                 ORDER BY d.created_at DESC, d.id DESC
                 LIMIT 200"
            );
            $stmt->execute($params);
            $deals = $stmt->fetchAll();

            foreach ($deals as &$d) {
                $meta = dealStatusMeta($d['status']);
                $payMeta = paymentStatusMeta($d['payment_status']);
                $d['status_label'] = $meta['label'];
                $d['status_badge'] = $meta['badge'];
                $d['pay_label']    = $payMeta['label'];
                $d['pay_badge']    = $payMeta['badge'];
                $d['approved']     = $d['buyer_approved_at'] !== null;
                $d['actions']      = adminDealActions($d['status'], $d['payment_status'], $d['approved'], $d['inspection_status']);
            }
            unset($d);

            $row = $this->pdo->query(
                "SELECT
                    COALESCE(SUM(" . self::VIEW_SQL['payment'] . "), 0) AS cnt_payment,
                    COALESCE(SUM(" . self::VIEW_SQL['confirm'] . "), 0) AS cnt_confirm,
                    COALESCE(SUM(" . self::VIEW_SQL['payout'] . "), 0)  AS cnt_payout,
                    COALESCE(SUM(" . self::VIEW_SQL['refund'] . "), 0)  AS cnt_refund,
                    COALESCE(SUM(CASE WHEN d.payment_status = 'paid' THEN d.total_amount END), 0)     AS held_sum,
                    COALESCE(SUM(CASE WHEN d.payment_status = 'paid_out' THEN d.total_amount END), 0) AS paid_out_sum,
                    COUNT(*) AS total
                 FROM deals d
                 LEFT JOIN inspections i ON i.deal_id = d.id"
            )->fetch();
            $stats = [
                'payment'  => (int)$row['cnt_payment'],
                'confirm'  => (int)$row['cnt_confirm'],
                'payout'   => (int)$row['cnt_payout'],
                'refund'   => (int)$row['cnt_refund'],
                'held'     => (float)$row['held_sum'],
                'paid_out' => (float)$row['paid_out_sum'],
                'total'    => (int)$row['total'],
            ];

            // сколько проверок ждут внимания администратора (отчёты на подтверждение, доплаты, задания без проверяющего)
            try {
                $stats['insp_review'] = (int)$this->pdo->query("SELECT COUNT(*) FROM inspections WHERE status = 'submitted'")->fetchColumn();
                $stats['insp_todo']   = (int)$this->pdo->query(
                    "SELECT COUNT(*) FROM inspections
                     WHERE (status IN ('requested', 'assigned', 'submitted') AND fee_status = 'unpaid')
                        OR (status = 'cancelled' AND fee_status = 'paid')
                        OR (status = 'approved' AND reward_paid_at IS NULL)"
                )->fetchColumn();
            } catch (PDOException $e) {
                // таблицы проверок ещё нет — выполните sql/migration_inspections.sql
                $stats['insp_review'] = 0;
                $stats['insp_todo'] = 0;
            }

            $events = $this->pdo->query(
                'SELECT e.action, e.comment, e.created_at, d.deal_number, u.full_name AS admin_name
                 FROM deal_events e
                 JOIN deals d ON d.id = e.deal_id
                 LEFT JOIN users u ON u.id = e.admin_id
                 ORDER BY e.created_at DESC, e.id DESC
                 LIMIT 15'
            )->fetchAll();
        } catch (PDOException $e) {
            error_log('Admin page failed: ' . $e->getMessage());
            $dbNotice = 'Не удалось загрузить данные. Проверьте, что выполнен актуальный SQL-скрипт (поля оплаты, таблицы deal_events и inspections — файл sql/migration_inspections.sql).';
        }

        $filters = ['status' => $status, 'pay' => $pay, 'view' => $view, 'q' => $q];
        $backUrl = '/admin' . ($_SERVER['QUERY_STRING'] ?? '' ? '?' . $_SERVER['QUERY_STRING'] : '');
        $pageTitle = 'Админ-панель';
        require BASE_PATH . '/views/admin/index.php';
    }

    /** Статистика продаж: год / месяц / день и календарь. Продажа = завершённая сделка (по дате завершения). */
    public function stats(): void
    {
        $admin = $this->requireAdmin();
        $dbNotice = '';

        try {
            $today = (string)$this->pdo->query('SELECT CURDATE()')->fetchColumn();
        } catch (PDOException $e) {
            $today = date('Y-m-d');
        }
        $todayDt = DateTimeImmutable::createFromFormat('!Y-m-d', $today) ?: new DateTimeImmutable('today');

        $viewRaw = is_string($_GET['view'] ?? null) ? $_GET['view'] : '';
        $view = in_array($viewRaw, ['year', 'month', 'day'], true) ? $viewRaw : 'month';

        $raw = is_string($_GET['date'] ?? null) ? $_GET['date'] : '';
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
        if (!$date || $date->format('Y-m-d') !== $raw || (int)$date->format('Y') < 2000 || (int)$date->format('Y') > 2100) {
            $date = $todayDt;
        }

        // границы периода и соседние периоды
        if ($view === 'year') {
            $start = $date->setDate((int)$date->format('Y'), 1, 1);
            $end = $start->modify('+1 year');
            $prevStart = $start->modify('-1 year');
        } elseif ($view === 'month') {
            $start = $date->modify('first day of this month');
            $end = $start->modify('+1 month');
            $prevStart = $start->modify('-1 month');
        } else {
            $start = $date;
            $end = $start->modify('+1 day');
            $prevStart = $start->modify('-1 day');
        }

        $fmt = fn(DateTimeImmutable $d): string => $d->format('Y-m-d H:i:s');
        $url = fn(string $v, DateTimeImmutable $d): string => '/admin/stats?view=' . $v . '&date=' . $d->format('Y-m-d') . '#report';

        $totals = ['cnt' => 0, 'sum' => 0.0];
        $prevTotals = ['cnt' => 0, 'sum' => 0.0];
        $byMonth = [];
        $byDay = [];
        $dayDeals = [];
        $calStart = $date->modify('first day of this month');

        try {
            $totals = $this->totals($fmt($start), $fmt($end));
            $prevTotals = $this->totals($fmt($prevStart), $fmt($start));

            if ($view === 'year') {
                $stmt = $this->pdo->prepare(
                    "SELECT MONTH(closed_at) AS k, COUNT(*) AS cnt, COALESCE(SUM(total_amount), 0) AS sum
                     FROM deals
                     WHERE status = 'completed' AND closed_at >= ? AND closed_at < ?
                     GROUP BY MONTH(closed_at)"
                );
                $stmt->execute([$fmt($start), $fmt($end)]);
                foreach ($stmt->fetchAll() as $r) {
                    $byMonth[(int)$r['k']] = ['cnt' => (int)$r['cnt'], 'sum' => (float)$r['sum']];
                }
            }

            // продажи по дням месяца — для календаря и графика месяца
            $byDay = $this->perDay($fmt($calStart), $fmt($calStart->modify('+1 month')));

            if ($view === 'day') {
                $stmt = $this->pdo->prepare(
                    "SELECT d.deal_number, d.quantity, d.total_amount, d.closed_at, d.payment_status,
                            b.name AS buyer_name, s.name AS seller_name, p.name AS product_name, p.unit
                     FROM deals d
                     JOIN companies b ON b.id = d.buyer_company_id
                     JOIN companies s ON s.id = d.seller_company_id
                     JOIN products  p ON p.id = d.product_id
                     WHERE d.status = 'completed' AND d.closed_at >= ? AND d.closed_at < ?
                     ORDER BY d.closed_at DESC, d.id DESC"
                );
                $stmt->execute([$fmt($start), $fmt($end)]);
                $dayDeals = $stmt->fetchAll();
            }
        } catch (PDOException $e) {
            error_log('Admin stats failed: ' . $e->getMessage());
            $dbNotice = 'Не удалось загрузить статистику. Проверьте, что выполнен актуальный SQL-скрипт.';
        }

        // ---- график ----
        $chart = [];
        $monthsShort = ['', 'Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн', 'Июл', 'Авг', 'Сен', 'Окт', 'Ноя', 'Дек'];
        if ($view === 'year') {
            for ($m = 1; $m <= 12; $m++) {
                $first = $start->setDate((int)$start->format('Y'), $m, 1);
                $chart[] = [
                    'label' => $monthsShort[$m],
                    'title' => ruUcfirst(ruMonthName($m)) . ' ' . $start->format('Y'),
                    'cnt'   => $byMonth[$m]['cnt'] ?? 0,
                    'sum'   => $byMonth[$m]['sum'] ?? 0.0,
                    'url'   => $url('month', $first),
                ];
            }
        } elseif ($view === 'month') {
            $daysInMonth = (int)$start->format('t');
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $day = $start->setDate((int)$start->format('Y'), (int)$start->format('n'), $d);
                $chart[] = [
                    'label' => (string)$d,
                    'title' => $d . ' ' . ruMonthName((int)$start->format('n'), true),
                    'cnt'   => $byDay[$d]['cnt'] ?? 0,
                    'sum'   => $byDay[$d]['sum'] ?? 0.0,
                    'url'   => $url('day', $day),
                ];
            }
        }
        $chartMax = 0.0;
        foreach ($chart as $c) {
            $chartMax = max($chartMax, $c['sum']);
        }

        // ---- календарь месяца (неделя с понедельника) ----
        $calMax = 0.0;
        foreach ($byDay as $v) {
            $calMax = max($calMax, $v['sum']);
        }
        $cells = array_fill(0, (int)$calStart->format('N') - 1, null);
        $selected = $view === 'day' ? $date->format('Y-m-d') : '';
        for ($d = 1, $n = (int)$calStart->format('t'); $d <= $n; $d++) {
            $day = $calStart->setDate((int)$calStart->format('Y'), (int)$calStart->format('n'), $d);
            $key = $day->format('Y-m-d');
            $sum = $byDay[$d]['sum'] ?? 0.0;
            $ratio = $calMax > 0 ? $sum / $calMax : 0;
            $cells[] = [
                'day'      => $d,
                'cnt'      => $byDay[$d]['cnt'] ?? 0,
                'sum'      => $sum,
                'level'    => $sum <= 0 ? 0 : ($ratio <= .25 ? 1 : ($ratio <= .5 ? 2 : ($ratio <= .75 ? 3 : 4))),
                'today'    => $key === $today,
                'selected' => $key === $selected,
                'future'   => $key > $today,
                'url'      => $url('day', $day),
            ];
        }
        $calPrevUrl = $url($view, $calStart->modify('-1 month'));
        $calNextUrl = $url($view, $calStart->modify('+1 month'));
        $calTitle = ruUcfirst(ruMonthName((int)$calStart->format('n'))) . ' ' . $calStart->format('Y');

        // ---- заголовок периода и навигация ----
        $weekdays = ['понедельник', 'вторник', 'среда', 'четверг', 'пятница', 'суббота', 'воскресенье'];
        $periodTitle = match ($view) {
            'year'  => $start->format('Y') . ' год',
            'month' => ruUcfirst(ruMonthName((int)$start->format('n'))) . ' ' . $start->format('Y'),
            default => (int)$start->format('j') . ' ' . ruMonthName((int)$start->format('n'), true) . ' ' . $start->format('Y')
                       . ', ' . $weekdays[(int)$start->format('N') - 1],
        };
        $prevUrl = $url($view, $prevStart);
        $nextUrl = $url($view, $end);
        $nextDisabled = $end > $todayDt;
        $viewUrls = [
            'year'  => $url('year', $date),
            'month' => $url('month', $date),
            'day'   => $url('day', $date),
        ];
        $todayUrl = $url('day', $todayDt);

        // ---- сводка ----
        $avg = $totals['cnt'] > 0 ? $totals['sum'] / $totals['cnt'] : 0.0;
        $delta = $prevTotals['sum'] > 0 ? round(($totals['sum'] - $prevTotals['sum']) / $prevTotals['sum'] * 100, 1) : null;
        $prevLabel = ['year' => 'к прошлому году', 'month' => 'к прошлому месяцу', 'day' => 'ко вчера'][$view];

        $pageTitle = 'Статистика продаж';
        require BASE_PATH . '/views/admin/stats.php';
    }

    /** @return array{cnt:int,sum:float} продажи (завершённые сделки) за период по дате завершения */
    private function totals(string $from, string $to): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amount), 0) AS sum
             FROM deals WHERE status = 'completed' AND closed_at >= ? AND closed_at < ?"
        );
        $stmt->execute([$from, $to]);
        $r = $stmt->fetch();
        return ['cnt' => (int)$r['cnt'], 'sum' => (float)$r['sum']];
    }

    /** @return array<int,array{cnt:int,sum:float}> день месяца => продажи */
    private function perDay(string $from, string $to): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT DAY(closed_at) AS k, COUNT(*) AS cnt, COALESCE(SUM(total_amount), 0) AS sum
             FROM deals
             WHERE status = 'completed' AND closed_at >= ? AND closed_at < ?
             GROUP BY DAY(closed_at)"
        );
        $stmt->execute([$from, $to]);
        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            $out[(int)$r['k']] = ['cnt' => (int)$r['cnt'], 'sum' => (float)$r['sum']];
        }
        return $out;
    }

    /** Отменяет проверку сделки (если она ещё не отправила отчёт) и пишет об этом в журнал проверки. */
    private function cancelInspectionOfDeal(int $dealId, int $adminId, string $reason): void
    {
        $stmt = $this->pdo->prepare('SELECT id FROM inspections WHERE deal_id = ? AND status IN (\'requested\', \'assigned\') LIMIT 1');
        $stmt->execute([$dealId]);
        $id = $stmt->fetchColumn();
        if ($id === false) {
            return;
        }
        $stmt = $this->pdo->prepare("UPDATE inspections SET status = 'cancelled', admin_comment = ? WHERE id = ?");
        $stmt->execute([$reason, $id]);
        logInspectionEvent($this->pdo, (int)$id, $adminId, 'cancelled', $reason);
    }

    /** Действие администратора по сделке. */
    public function act(): void
    {
        $admin = $this->requireAdmin();

        $back = is_string($_POST['back'] ?? null) && preg_match('~^/admin(?:\?[^\s#]*)?$~', $_POST['back'])
            ? $_POST['back']
            : '/admin';
        $go = function (string $key, string $message) use ($back): never {
            $_SESSION[$key] = $message;
            header('Location: ' . $back . '#deals');
            exit;
        };

        if (!csrfValid()) {
            $go('flash_error', 'Сессия устарела. Обновите страницу и повторите действие.');
        }

        $dealId  = (int)($_POST['deal_id'] ?? 0);
        $action  = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
        $comment = is_string($_POST['comment'] ?? null) ? trim($_POST['comment']) : '';

        if ($dealId <= 0 || !in_array($action, self::ACTIONS, true)) {
            $go('flash_error', 'Некорректный запрос.');
        }
        if (in_array($action, ['cancel', 'refund'], true) && $comment === '') {
            $go('flash_error', 'Укажите причину — она сохраняется в журнале действий.');
        }
        if (mb_strlen($comment) > 500) {
            $go('flash_error', 'Комментарий не должен быть длиннее 500 символов.');
        }

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                'SELECT deal_number, status, payment_status, buyer_approved_at FROM deals WHERE id = ? FOR UPDATE'
            );
            $stmt->execute([$dealId]);
            $deal = $stmt->fetch();
            if (!$deal) {
                throw new RuntimeException('Сделка не найдена.');
            }

            $inspStatus = null;
            try {
                $stmt = $this->pdo->prepare('SELECT status FROM inspections WHERE deal_id = ? LIMIT 1 FOR UPDATE');
                $stmt->execute([$dealId]);
                $found = $stmt->fetchColumn();
                $inspStatus = $found === false ? null : (string)$found;
            } catch (PDOException $e) {
                $inspStatus = null;   // таблицы проверок нет — работаем как раньше
            }

            $allowed = adminDealActions($deal['status'], $deal['payment_status'], $deal['buyer_approved_at'] !== null, $inspStatus);
            if (!in_array($action, $allowed, true)) {
                throw new RuntimeException(
                    $action === 'confirm_deal' && inspectionBlocksDeal($inspStatus)
                        ? "Сделку {$deal['deal_number']} нельзя подтвердить: по ней заказана проверка, отчёт ещё не подтверждён (раздел «Проверки»)."
                        : "Действие недоступно: состояние сделки {$deal['deal_number']} уже изменилось."
                );
            }

            $sql = match ($action) {
                'confirm_payment' => "UPDATE deals SET payment_status = 'paid', paid_at = NOW()
                                      WHERE id = ? AND status = 'in_progress' AND payment_status = 'unpaid'",
                'confirm_deal'    => "UPDATE deals SET status = 'completed', closed_at = NOW()
                                      WHERE id = ? AND status = 'in_progress' AND payment_status = 'paid' AND buyer_approved_at IS NOT NULL",
                'cancel'          => "UPDATE deals SET status = 'cancelled', closed_at = NOW()
                                      WHERE id = ? AND status = 'in_progress'",
                'payout'          => "UPDATE deals SET payment_status = 'paid_out', paid_out_at = NOW()
                                      WHERE id = ? AND status = 'completed' AND payment_status = 'paid'",
                'refund'          => "UPDATE deals SET payment_status = 'refunded'
                                      WHERE id = ? AND status IN ('rejected', 'cancelled') AND payment_status = 'paid'",
            };
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$dealId]);
            if ($stmt->rowCount() === 0) {
                throw new RuntimeException("Сделка {$deal['deal_number']} уже обработана.");
            }

            $stmt = $this->pdo->prepare(
                'INSERT INTO deal_events (deal_id, admin_id, action, comment) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$dealId, $admin['id'], $action, nullIfEmpty($comment)]);

            // сделка отменена — проверка, которую ещё не начали или не закончили, отменяется вместе с ней
            if ($action === 'cancel' && $inspStatus !== null && in_array($inspStatus, ['requested', 'assigned'], true)) {
                $this->cancelInspectionOfDeal($dealId, (int)$admin['id'], 'Сделка отменена администратором');
            }

            $this->pdo->commit();
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Admin action failed: ' . $e->getMessage());
            $go('flash_error', 'Не удалось выполнить действие. Попробуйте позже.');
        } catch (RuntimeException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $go('flash_error', $e->getMessage());
        }

        $number = $deal['deal_number'];
        $go('flash_success', match ($action) {
            'confirm_payment' => "Оплата по сделке {$number} подтверждена.",
            'confirm_deal'    => "Сделка {$number} подтверждена и завершена. Теперь можно выплатить средства продавцу.",
            'cancel'          => "Сделка {$number} отменена.",
            'payout'          => "Выплата продавцу по сделке {$number} отмечена.",
            'refund'          => "Возврат покупателю по сделке {$number} отмечен.",
        });
    }
}
