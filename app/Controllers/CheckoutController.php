<?php
declare(strict_types=1);

/**
 * Оформление закупки.
 *
 * Заявка (корзина) может содержать несколько товаров. Одна сделка в базе —
 * это договор с одним продавцом, поэтому заявка делится на сделки
 * по количеству продавцов; цены и состав фиксируются в deal_items.
 */
class CheckoutController
{
    private const PAYMENT_TERMS = [
        'Предоплата 100%',
        'Предоплата 50%',
        'Постоплата 14 дней',
        'Постоплата 30 дней',
        'По договору',
    ];
    private const MAX_TOTAL = 999999999999.99;

    public function __construct(private PDO $pdo) {}

    /** Гостя отправляем на вход и запоминаем, куда вернуть. */
    private function requireAuth(): void
    {
        if (!isset($_SESSION['user_id'], $_SESSION['company_id'])) {
            $_SESSION['after_login'] = '/checkout';
            $_SESSION['flash_error'] = 'Войдите или зарегистрируйтесь, чтобы оформить закупку.';
            header('Location: /login');
            exit;
        }
        // администраторы и проверяющие не покупают
        if (isStaffRole($_SESSION['role'] ?? '')) {
            $_SESSION['flash_error'] = 'Сотрудники платформы не оформляют закупки.';
            header('Location: ' . staffHome($_SESSION['role'] ?? ''));
            exit;
        }
    }

    public function show(): void
    {
        $this->requireAuth();
        if (!canCompany('create_deals')) {
            $_SESSION['flash_error'] = 'Вам не разрешено оформлять закупки. Обратитесь к директору компании.';
            header('Location: /');
            exit;
        }

        // обратная совместимость: /checkout?product_id=5 добавляет товар в заявку
        $legacy = (int)($_GET['product_id'] ?? 0);
        if ($legacy > 0) {
            if ($this->addLegacyProduct($legacy)) {
                header('Location: /checkout');
                exit;
            }
            header('Location: /');
            exit;
        }

        $items = Cart::load($this->pdo);
        if ($items === []) {
            $_SESSION['flash_error'] = 'Заявка пуста. Добавьте товары из каталога.';
            header('Location: /cart');
            exit;
        }

        $buyer = $this->buyerInfo();
        $old = [
            'payment_terms'    => '',
            'delivery_address' => (string)($buyer['company_address'] ?? ''),
            'comment'          => '',
            'inspection'       => '',
            'inspection_note'  => '',
        ];

        $this->render($items, $old, '');
    }

    public function store(): void
    {
        $this->requireAuth();
        if (!canCompany('create_deals')) {
            $_SESSION['flash_error'] = 'Вам не разрешено оформлять закупки. Обратитесь к директору компании.';
            header('Location: /');
            exit;
        }

        if (!csrfValid()) {
            $_SESSION['flash_error'] = 'Сессия устарела. Обновите страницу и повторите оформление.';
            header('Location: /checkout');
            exit;
        }

        $items = Cart::load($this->pdo);
        if ($items === []) {
            $_SESSION['flash_error'] = 'Заявка пуста. Добавьте товары из каталога.';
            header('Location: /cart');
            exit;
        }

        $terms          = trim((string)($_POST['payment_terms'] ?? ''));
        $address        = trim((string)($_POST['delivery_address'] ?? ''));
        $comment        = trim((string)($_POST['comment'] ?? ''));
        $wantInspection = !empty($_POST['inspection']);
        $inspectionNote = trim((string)($_POST['inspection_note'] ?? ''));

        $old = [
            'payment_terms'    => $terms,
            'delivery_address' => $address,
            'comment'          => $comment,
            'inspection'       => $wantInspection ? '1' : '',
            'inspection_note'  => $inspectionNote,
        ];

        $error = $this->validate($items, $terms, $address, $comment, $inspectionNote);
        if ($error !== '') {
            $this->render($items, $old, $error);
            return;
        }

        $groups   = Cart::bySeller($items);
        $totalAll = Cart::total($items);

        try {
            $this->pdo->beginTransaction();

            $numbers = [];
            $fees    = 0.0;

            foreach ($groups as $group) {
                $first      = $group['items'][0];
                $groupTotal = (float)$group['total'];

                $stmt = $this->pdo->prepare(
                    'INSERT INTO deals (deal_number, buyer_company_id, seller_company_id, product_id, created_by,
                                        quantity, unit_price, total_amount, status, payment_terms,
                                        delivery_address, comment)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'in_progress\', ?, ?, ?)'
                );
                $stmt->execute([
                    'TMP-' . bin2hex(random_bytes(6)),
                    (int)$_SESSION['company_id'],
                    (int)$group['company_id'],
                    (int)$first['id'],
                    (int)$_SESSION['user_id'],
                    (int)$first['qty'],
                    (float)$first['price'],
                    $groupTotal,
                    $terms,
                    $address,
                    nullIfEmpty($comment),
                ]);

                $dealId = (int)$this->pdo->lastInsertId();
                $number = sprintf('DL-%s-%04d', date('Y'), $dealId);
                $this->pdo->prepare('UPDATE deals SET deal_number = ? WHERE id = ?')->execute([$number, $dealId]);

                $insItem = $this->pdo->prepare(
                    'INSERT INTO deal_items (deal_id, product_id, name, sku, unit, quantity, unit_price, total)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );
                foreach ($group['items'] as $item) {
                    $insItem->execute([
                        $dealId,
                        (int)$item['id'],
                        $item['name'],
                        nullIfEmpty((string)($item['sku'] ?? '')),
                        $item['unit'],
                        (int)$item['qty'],
                        (float)$item['price'],
                        (float)$item['line_total'],
                    ]);
                }

                // заказ проверки: компания доплачивает, проверяющему положено вознаграждение
                if ($wantInspection) {
                    $fee = inspectionFeeFor($groupTotal);
                    $fees += $fee;

                    $stmt = $this->pdo->prepare(
                        'INSERT INTO inspections (deal_id, requested_by, buyer_note, fee, reward) VALUES (?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([
                        $dealId,
                        (int)$_SESSION['user_id'],
                        nullIfEmpty($inspectionNote),
                        $fee,
                        inspectionRewardFor($fee),
                    ]);
                    logInspectionEvent($this->pdo, (int)$this->pdo->lastInsertId(), (int)$_SESSION['user_id'], 'requested', null);
                }

                $numbers[] = $number;
            }

            $this->pdo->commit();
            Cart::clear();

            $sellers = array_map(static fn(array $g): string => (string)$g['company_name'], $groups);

            if (count($numbers) === 1) {
                $message = "Заявка {$numbers[0]} оформлена и отправлена продавцу «{$sellers[0]}».";
            } else {
                $message = 'Оформлено заявок: ' . count($numbers) . ' (' . implode(', ', $numbers) . ') — по одной с каждым продавцом.';
            }
            if ($wantInspection) {
                $message .= ' Проверка заказана: доплата ' . formatPrice($fees) . ' — реквизиты в разделе «Сделки».';
            }

            $_SESSION['flash_success'] = $message;
            header('Location: /account#deals');
            exit;
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Checkout failed: ' . $e->getMessage());
            $this->render($items, $old, 'Не удалось оформить заявку. Попробуйте ещё раз позже.');
        }
    }

    /** Проверка формы и актуальности позиций (цены и наличие могли измениться). */
    private function validate(array $items, string $terms, string $address, string $comment, string $note): string
    {
        if (!in_array($terms, self::PAYMENT_TERMS, true)) {
            return 'Выберите условия оплаты.';
        }
        if ($address === '') {
            return 'Укажите адрес доставки.';
        }
        if (mb_strlen($address) > 255) {
            return 'Адрес доставки не должен быть длиннее 255 символов.';
        }
        if (mb_strlen($comment) > 500) {
            return 'Комментарий не должен быть длиннее 500 символов.';
        }
        if (mb_strlen($note) > 500) {
            return 'Описание проверки не должно быть длиннее 500 символов.';
        }

        foreach ($items as $item) {
            if ((int)$item['company_id'] === (int)$_SESSION['company_id']) {
                return 'В заявке есть товар вашей компании — удалите его.';
            }
            if ((int)$item['qty'] < max(1, (int)$item['min_order'])) {
                return '«' . $item['name'] . '»: минимальный заказ — ' . (int)$item['min_order'] . ' ' . $item['unit'] . '.';
            }
            if ((int)$item['qty'] > Cart::MAX_QTY) {
                return 'Слишком большое количество: «' . $item['name'] . '».';
            }
            if ((int)$item['in_stock'] === 0 && !$this->orderedBefore((int)$item['id'])) {
                return '«' . $item['name'] . '» закончился. Удалите позицию из заявки.';
            }
        }

        if (Cart::total($items) > self::MAX_TOTAL) {
            return 'Слишком большая сумма заказа.';
        }

        return '';
    }

    /** Старая ссылка «Купить» с одним товаром: кладём его в заявку. */
    private function addLegacyProduct(int $productId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.id, p.company_id, p.name, p.min_order, p.in_stock
             FROM products p WHERE p.id = ? LIMIT 1'
        );
        $stmt->execute([$productId]);
        $product = $stmt->fetch();

        if (!$product) {
            $_SESSION['flash_error'] = 'Товар не найден.';
            return false;
        }
        if ((int)$product['company_id'] === (int)$_SESSION['company_id']) {
            $_SESSION['flash_error'] = 'Нельзя купить товар собственной компании.';
            return false;
        }
        if ((int)$product['in_stock'] === 0 && !$this->orderedBefore($productId)) {
            $_SESSION['flash_error'] = 'Товара нет в наличии. Повторно заказать можно только товар из истории ваших заказов.';
            return false;
        }

        Cart::add($productId, max(1, (int)$product['min_order']));
        return true;
    }

    /** Есть ли у компании завершённая сделка по этому товару. */
    private function orderedBefore(int $productId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM deals WHERE buyer_company_id = ? AND product_id = ? AND status = 'completed' LIMIT 1"
        );
        $stmt->execute([(int)$_SESSION['company_id'], $productId]);
        return (bool)$stmt->fetchColumn();
    }

    private function buyerInfo(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.full_name, u.username, u.`position`, c.name AS company_name, c.address AS company_address
             FROM users u
             JOIN companies c ON c.id = u.company_id
             WHERE u.id = ? LIMIT 1'
        );
        $stmt->execute([(int)$_SESSION['user_id']]);
        return $stmt->fetch() ?: [];
    }

    private function render(array $items, array $old, string $errorMsg): void
    {
        $buyer        = $this->buyerInfo();
        $groups       = Cart::bySeller($items);
        $total        = Cart::total($items);
        $groupTotals  = array_map(static fn(array $g): float => (float)$g['total'], $groups);
        $paymentTerms = self::PAYMENT_TERMS;
        $pricing      = inspectionPricing();

        $pageTitle = 'Оформление закупки';
        require BASE_PATH . '/views/checkout/index.php';
    }
}
