<?php
class AccountController
{
    public function __construct(private PDO $pdo) {}

    private function checkAuth(): void
    {
        if (!isset($_SESSION['user_id'], $_SESSION['company_id'])) {
            $_SESSION = [];
            header('Location: /login');
            exit;
        }
    }

    /** Личный кабинет: пользователь, компания, категории, сделки. */
    public function index(): void
    {
        $this->checkAuth();
        if (isStaffRole($_SESSION['role'] ?? '')) {
            header('Location: ' . staffHome($_SESSION['role'] ?? ''));
            exit;
        }
        $companyId = (int)$_SESSION['company_id'];

        // 1) Кто вошёл и в какой компании работает
        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.username, u.full_name, u.`position`, u.phone, u.email, u.role, u.company_role, u.created_at,
                    c.id AS company_id, c.name AS company_name, c.inn, c.address AS company_address,
                    c.phone AS company_phone
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

        // сколько активных сотрудников в компании
        $memberCount = 1;
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE company_id = ? AND is_active = 1 AND join_status = 'approved'");
            $stmt->execute([$companyId]);
            $memberCount = (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('Member count failed: ' . $e->getMessage());
        }

        $dbNotice = '';

        // 2) Категории: все существующие + отметка «выбрана» + число товаров компании в категории
        $categories = [];
        try {
            $stmt = $this->pdo->prepare(
                'SELECT cat.id, cat.name, cat.description,
                        (cc.company_id IS NOT NULL) AS selected,
                        (SELECT COUNT(*) FROM products p
                          WHERE p.company_id = :company_products AND p.category_id = cat.id) AS products_cnt
                 FROM categories cat
                 LEFT JOIN company_categories cc
                        ON cc.category_id = cat.id AND cc.company_id = :company_selected
                 ORDER BY cat.name'
            );
            $stmt->execute([
                ':company_products' => $companyId,
                ':company_selected' => $companyId,
            ]);
            $categories = $stmt->fetchAll();
        } catch (PDOException $e) {
            $dbNotice = 'Таблицы категорий недоступны — выполните SQL-миграцию (файл migration_account.sql).';
        }

        // 3) Сделки компании: как покупатель и как продавец (+ заказанные проверки качества)
        $deals = [];
        $inspectionsAvailable = true;
        $dealsSql = 'SELECT d.id, d.deal_number, d.quantity, d.unit_price, d.total_amount, d.currency,
                        d.status, d.payment_status, d.buyer_approved_at, d.payment_terms, d.created_at, d.closed_at,
                        d.buyer_company_id, d.seller_company_id,
                        b.name AS buyer_name, s.name AS seller_name,
                        p.id AS product_id, p.name AS product_name, p.unit,
                        cat.name AS category_name,
                        cu.full_name AS creator_name, cu.`position` AS creator_position%s
                 FROM deals d
                 JOIN companies b ON b.id = d.buyer_company_id
                 JOIN companies s ON s.id = d.seller_company_id
                 JOIN products  p ON p.id = d.product_id
                 LEFT JOIN categories cat ON cat.id = p.category_id
                 LEFT JOIN users cu ON cu.id = d.created_by%s
                 WHERE d.buyer_company_id = :as_buyer OR d.seller_company_id = :as_seller
                 ORDER BY d.created_at DESC, d.id DESC';
        $inspCols = ',
                        i.id AS insp_id, i.status AS insp_status, i.fee AS insp_fee, i.fee_status AS insp_fee_status,
                        i.verdict AS insp_verdict, i.report_summary AS insp_summary, i.report_details AS insp_details,
                        i.report_links AS insp_links, i.checked_on AS insp_checked_on, i.buyer_note AS insp_note,
                        i.visit_at AS insp_visit_at, i.visit_status AS insp_visit_status, i.visit_proposed_by AS insp_visit_by,
                        i.visit_note AS insp_visit_note, i.escort_name AS insp_escort_name,
                        i.escort_number AS insp_escort_number, i.escort_phone AS insp_escort_phone,
                        iu.full_name AS insp_inspector';
        $inspJoin = '
                 LEFT JOIN inspections i ON i.deal_id = d.id
                 LEFT JOIN users iu ON iu.id = i.inspector_id';
        try {
            try {
                $stmt = $this->pdo->prepare(sprintf($dealsSql, $inspCols, $inspJoin));
                $stmt->execute([':as_buyer' => $companyId, ':as_seller' => $companyId]);
            } catch (PDOException $e) {
                // таблицы проверок ещё нет (не выполнен sql/migration_inspections.sql) — показываем сделки без проверок
                $inspectionsAvailable = false;
                $stmt = $this->pdo->prepare(sprintf($dealsSql, '', ''));
                $stmt->execute([':as_buyer' => $companyId, ':as_seller' => $companyId]);
            }
            $deals = $stmt->fetchAll();
        } catch (PDOException $e) {
            $dbNotice = $dbNotice ?: 'Таблица сделок недоступна — проверьте, что она создана.';
        }

        // Роль компании в каждой сделке, контрагент, группа статуса
        $stats = ['all' => 0, 'progress' => 0, 'done' => 0, 'rejected' => 0,
                  'cancelled' => 0, 'buyer' => 0, 'seller' => 0, 'done_sum' => 0.0, 'awaiting' => 0];
        foreach ($deals as &$d) {
            $isBuyer = (int)$d['buyer_company_id'] === $companyId;
            $meta = dealStatusMeta($d['status']);

            $d['my_role']      = $isBuyer ? 'buyer' : 'seller';
            $d['counterparty'] = $isBuyer ? $d['seller_name'] : $d['buyer_name'];
            $d['status_label'] = $meta['label'];
            $d['status_badge'] = $meta['badge'];
            $d['group']        = $meta['group'];
            $pay = paymentStatusMeta($d['payment_status']);
            $d['pay_label']    = $pay['label'];
            $d['pay_badge']    = $pay['badge'];
            $d['approved']     = $d['buyer_approved_at'] !== null;
            // одобрить или отклонить сделку «В работе» может только покупатель (пока не одобрил)
            $d['can_decide']   = $isBuyer && $d['status'] === 'in_progress' && !$d['approved'] && canCompany('decide_deals');
            // «Повторить заказ» из истории: единственный способ снова открыть товар, которого нет в наличии
            $d['can_repeat']   = $isBuyer && $d['status'] === 'completed' && canCompany('create_deals');
            // оплата проходит через администратора платформы
            $d['can_pay']      = $isBuyer && $d['status'] === 'in_progress' && $d['payment_status'] === 'unpaid';

            // проверка качества: заказывает и оплачивает покупатель; результат после подтверждения видят обе стороны
            $d['insp_id']        = $d['insp_id'] ?? null;
            $d['insp_status']    = $d['insp_status'] ?? null;
            $d['insp_meta']      = $d['insp_status'] ? inspectionStatusMeta($d['insp_status']) : null;
            $d['insp_approved']  = $d['insp_status'] === 'approved';
            $d['insp_fee_preview'] = inspectionFeeFor((float)$d['total_amount']);
            $d['can_order_insp'] = $inspectionsAvailable && $isBuyer && $d['status'] === 'in_progress' && !$d['insp_id'] && canCompany('create_deals');
            $d['can_pay_insp']   = $isBuyer && $d['insp_id'] && ($d['insp_fee_status'] ?? '') === 'unpaid'
                                   && in_array($d['insp_status'], ['requested', 'assigned', 'submitted'], true);
            // продавец согласует время визита и называет сопровождающего сотрудника
            $d['can_visit_insp'] = $d['my_role'] === 'seller' && $d['insp_status'] === 'assigned' && canCompany('decide_deals');
            $d['visit_meta']     = $d['insp_id'] ? visitStatusMeta($d['insp_visit_status'] ?? 'none', $d['insp_visit_by'] ?? null) : null;
            $d['can_cancel_insp'] = $isBuyer && $d['insp_status'] === 'requested' && canCompany('create_deals');

            $stats['all']++;
            if ($d['can_decide']) {
                $stats['awaiting']++;
            }
            $stats[$meta['group']]++;
            $stats[$d['my_role']]++;
            if ($meta['group'] === 'done') {
                $stats['done_sum'] += (float)$d['total_amount'];
            }
        }
        unset($d);

        $selectedCount = count(array_filter($categories, fn($c) => (int)$c['selected'] === 1));

        $requisites = platformRequisites();
        $pageTitle = 'Личный кабинет';
        require BASE_PATH . '/views/account/index.php';
    }

    /** Сохраняет набор категорий, в которых компания предоставляет товары. */
    public function saveCategories(): void
    {
        $this->checkAuth();
        if (!canCompany('manage_categories')) {
            $_SESSION['flash_error'] = 'Недостаточно прав для выбора категорий. Обратитесь к директору компании.';
            header('Location: /account#categories');
            exit;
        }
        if (!csrfValid()) {
            $_SESSION['flash_error'] = 'Сессия устарела. Обновите страницу и повторите действие.';
            header('Location: /account#categories');
            exit;
        }
        $companyId = (int)$_SESSION['company_id'];

        $ids = array_values(array_unique(array_map('intval', (array)($_POST['categories'] ?? []))));

        try {
            // оставляем только реально существующие категории
            if ($ids) {
                $in = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $this->pdo->prepare("SELECT id FROM categories WHERE id IN ($in)");
                $stmt->execute($ids);
                $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
            }

            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare('DELETE FROM company_categories WHERE company_id = ?');
            $stmt->execute([$companyId]);

            if ($ids) {
                $ins = $this->pdo->prepare(
                    'INSERT INTO company_categories (company_id, category_id) VALUES (?, ?)'
                );
                foreach ($ids as $categoryId) {
                    $ins->execute([$companyId, $categoryId]);
                }
            }

            $this->pdo->commit();
            $_SESSION['flash_success'] = $ids
                ? 'Категории сохранены (' . count($ids) . ').'
                : 'Выбор категорий очищен.';
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $_SESSION['flash_error'] = 'Не удалось сохранить категории.';
        }

        header('Location: /account#categories');
        exit;
    }
}
