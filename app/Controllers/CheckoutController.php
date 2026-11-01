<?php
/** Оформление покупки: создаёт сделку (deals) со статусом «new». */
class CheckoutController
{
    private const PAYMENT_TERMS = [
        'Предоплата 100%',
        'Предоплата 50%',
        'Постоплата 14 дней',
        'Постоплата 30 дней',
        'По договору',
    ];
    private const MAX_QTY = 100000000;
    private const MAX_TOTAL = 999999999999.99;

    public function __construct(private PDO $pdo) {}

    /** Гостя отправляем на вход и запоминаем, куда вернуть. */
    private function requireAuth(): void
    {
        if (!isset($_SESSION['user_id'], $_SESSION['company_id'])) {
            $_SESSION['after_login'] = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
                ? ($_SERVER['REQUEST_URI'] ?? '/')
                : '/';
            $_SESSION['flash_error'] = 'Войдите или зарегистрируйтесь, чтобы оформить покупку.';
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

        $product = $this->loadProduct((int)($_GET['product_id'] ?? 0));
        $old = ['quantity' => (string)(int)$product['min_order']];

        $this->render($product, $old, '');
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
            header('Location: /checkout?product_id=' . (int)($_POST['product_id'] ?? 0));
            exit;
        }

        $product = $this->loadProduct((int)($_POST['product_id'] ?? 0));
        $buyer = $this->buyerInfo();

        $quantity = (int)($_POST['quantity'] ?? 0);
        $terms    = trim((string)($_POST['payment_terms'] ?? ''));
        $address  = trim((string)($_POST['delivery_address'] ?? ''));
        $comment  = trim((string)($_POST['comment'] ?? ''));
        $wantInspection = !empty($_POST['inspection']);
        $inspectionNote = trim((string)($_POST['inspection_note'] ?? ''));
        $old = [
            'quantity'         => (string)($_POST['quantity'] ?? ''),
            'payment_terms'    => $terms,
            'delivery_address' => $address,
            'comment'          => $comment,
            'inspection'       => $wantInspection ? '1' : '',
            'inspection_note'  => $inspectionNote,
        ];

        $unitPrice = round((float)$product['price'], 2);
        $total = round($unitPrice * $quantity, 2);

        if ($quantity < (int)$product['min_order']) {
            $error = 'Минимальный заказ — ' . (int)$product['min_order'] . ' ' . $product['unit'] . '.';
        } elseif ($quantity > self::MAX_QTY || $total > self::MAX_TOTAL) {
            $error = 'Слишком большое количество.';
        } elseif (!in_array($terms, self::PAYMENT_TERMS, true)) {
            $error = 'Выберите условия оплаты.';
        } elseif ($address === '') {
            $error = 'Укажите адрес доставки.';
        } elseif (mb_strlen($address) > 255) {
            $error = 'Адрес доставки не должен быть длиннее 255 символов.';
        } elseif (mb_strlen($comment) > 500) {
            $error = 'Комментарий не должен быть длиннее 500 символов.';
        } elseif (mb_strlen($inspectionNote) > 500) {
            $error = 'Описание проверки не должно быть длиннее 500 символов.';
        } else {
            $error = '';
        }

        if ($error !== '') {
            $this->render($product, $old, $error);
            return;
        }

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                'INSERT INTO deals (deal_number, buyer_company_id, seller_company_id, product_id, created_by,
                                    quantity, unit_price, total_amount, status, payment_terms,
                                    delivery_address, comment)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'in_progress\', ?, ?, ?)'
            );
            $stmt->execute([
                'TMP-' . bin2hex(random_bytes(6)),
                $_SESSION['company_id'],
                $product['company_id'],
                $product['id'],
                $_SESSION['user_id'],
                $quantity,
                $unitPrice,
                $total,
                $terms,
                $address,
                nullIfEmpty($comment),
            ]);

            $dealId = (int)$this->pdo->lastInsertId();
            $number = sprintf('DL-%s-%04d', date('Y'), $dealId);

            $stmt = $this->pdo->prepare('UPDATE deals SET deal_number = ? WHERE id = ?');
            $stmt->execute([$number, $dealId]);

            // заказ проверки персоналом платформы: компания доплачивает, проверяющему положено вознаграждение
            $fee = 0.0;
            if ($wantInspection) {
                $fee = inspectionFeeFor($total);
                $stmt = $this->pdo->prepare(
                    'INSERT INTO inspections (deal_id, requested_by, buyer_note, fee, reward) VALUES (?, ?, ?, ?, ?)'
                );
                $stmt->execute([$dealId, $_SESSION['user_id'], nullIfEmpty($inspectionNote), $fee, inspectionRewardFor($fee)]);
                logInspectionEvent($this->pdo, (int)$this->pdo->lastInsertId(), (int)$_SESSION['user_id'], 'requested', null);
            }

            $this->pdo->commit();

            $_SESSION['flash_success'] = "Заявка {$number} оформлена и отправлена продавцу «{$product['company_name']}»."
                . ($wantInspection ? ' Проверка заказана: доплата ' . formatPrice($fee) . ' — реквизиты в разделе «Сделки».' : '');
            header('Location: /account#deals');
            exit;
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->render($product, $old, 'Не удалось оформить заявку. Попробуйте ещё раз позже.');
        }
    }

    /** Товар + продавец. Если нельзя покупать — редирект в каталог. */
    private function loadProduct(int $id): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.id, p.company_id, p.name, p.sku, p.description, p.price, p.unit, p.min_order, p.in_stock,
                    c.name AS company_name, c.phone AS company_phone, cat.name AS category_name
             FROM products p
             JOIN companies c ON c.id = p.company_id
             LEFT JOIN categories cat ON cat.id = p.category_id
             WHERE p.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $product = $stmt->fetch();

        if (!$product) {
            $_SESSION['flash_error'] = 'Товар не найден.';
            header('Location: /');
            exit;
        }
        if ((int)$product['company_id'] === (int)$_SESSION['company_id']) {
            $_SESSION['flash_error'] = 'Нельзя купить товар собственной компании.';
            header('Location: /');
            exit;
        }
        // нет в наличии: снова заказать можно только то, что компания уже получала (история заказов)
        if ((int)$product['in_stock'] === 0 && !$this->orderedBefore((int)$product['id'])) {
            $_SESSION['flash_error'] = 'Товара нет в наличии. Повторно заказать можно только товар из истории ваших заказов.';
            header('Location: /');
            exit;
        }
        return $product;
    }

    /** Есть ли у компании завершённая сделка по этому товару. */
    private function orderedBefore(int $productId): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM deals WHERE buyer_company_id = ? AND product_id = ? AND status = 'completed' LIMIT 1");
        $stmt->execute([$_SESSION['company_id'], $productId]);
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
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch() ?: [];
    }

    private function render(array $product, array $old, string $errorMsg): void
    {
        $buyer = $this->buyerInfo();
        $paymentTerms = self::PAYMENT_TERMS;
        $pricing = inspectionPricing();
        $outOfStock = (int)$product['in_stock'] === 0;
        if (!isset($old['delivery_address']) || $old['delivery_address'] === '') {
            $old['delivery_address'] = $buyer['company_address'] ?? '';
        }
        $pageTitle = 'Оформление покупки';
        require BASE_PATH . '/views/checkout/index.php';
    }
}
