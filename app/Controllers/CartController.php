<?php
declare(strict_types=1);

/** Корзина: просмотр заявки и действия с позициями. */
class CartController
{
    public function __construct(private PDO $pdo) {}

    public function index(): void
    {
        $rows = Cart::load($this->pdo);

        // товары, удалённые из каталога, из корзины убираем
        if (count($rows) !== Cart::count()) {
            Cart::retain(array_column($rows, 'id'));
        }

        $groups   = Cart::bySeller($rows);
        $total    = Cart::total($rows);
        $isAuth   = isset($_SESSION['user_id']);
        $canOrder = $isAuth
            && !isStaffRole($_SESSION['role'] ?? '')
            && canCompany('create_deals');

        // гость, который собрал заявку, после входа вернётся к оформлению
        if (!$isAuth && $rows !== []) {
            $_SESSION['after_login'] = '/checkout';
        }

        $pageTitle = 'Заявка';
        require BASE_PATH . '/views/cart/index.php';
    }

    /** Добавить товар в заявку. Гостям тоже можно — вход потребуется при оформлении. */
    public function add(): void
    {
        if (!csrfValid()) {
            $this->back('flash_error', 'Сессия устарела. Обновите страницу и попробуйте снова.');
        }

        if (isStaffRole($_SESSION['role'] ?? '')) {
            $this->back('flash_error', 'Сотрудники платформы не оформляют закупки.', '/');
        }

        $productId = (int)($_POST['product_id'] ?? 0);
        $product   = $this->loadProduct($productId);

        if (!$product) {
            $this->back('flash_error', 'Товар не найден.', '/');
        }
        if (isset($_SESSION['company_id']) && (int)$product['company_id'] === (int)$_SESSION['company_id']) {
            $this->back('flash_error', 'Нельзя заказать товар собственной компании.', '/');
        }

        $qty = (int)($_POST['quantity'] ?? 0);
        $qty = max(max(1, (int)$product['min_order']), min($qty, Cart::MAX_QTY));

        if ((int)$product['in_stock'] === 0 && !$this->orderedBefore($productId)) {
            $this->back(
                'flash_error',
                'Товара нет в наличии. Повторно заказать можно только товар из истории ваших заказов.',
                '/'
            );
        }

        Cart::add($productId, $qty);
        $_SESSION['flash_success'] = '«' . $product['name'] . '» добавлен в заявку.';

        if (($_POST['then'] ?? '') === 'checkout') {
            header('Location: /checkout');
            exit;
        }
        $this->back('flash_success', '');
    }

    /** Обновить количества (и заодно обработать удаление позиции). */
    public function update(): void
    {
        if (!csrfValid()) {
            $this->back('flash_error', 'Сессия устарела. Обновите страницу и повторите действие.', '/cart');
        }

        $rows = Cart::load($this->pdo);
        $byId = [];
        foreach ($rows as $row) {
            $byId[(int)$row['id']] = $row;
        }

        $removeId = (int)($_POST['remove'] ?? 0);
        if ($removeId > 0) {
            Cart::remove($removeId);
            $_SESSION['flash_success'] = 'Позиция удалена из заявки.';
            header('Location: /cart');
            exit;
        }

        $quantities = $_POST['qty'] ?? [];
        if (!is_array($quantities)) {
            $quantities = [];
        }

        foreach ($quantities as $pid => $value) {
            $pid = (int)$pid;
            if (!isset($byId[$pid])) {
                continue;
            }
            $value = (int)$value;
            if ($value <= 0) {
                Cart::remove($pid);
                continue;
            }
            $min = max(1, (int)$byId[$pid]['min_order']);
            Cart::set($pid, max($min, min($value, Cart::MAX_QTY)));
        }

        // «Оформить заявку» на странице заявки: сохраняем количества и сразу идём к оформлению
        $to = ($_POST['then'] ?? '') === 'checkout' ? '/checkout' : '/cart';
        if ($to === '/cart') {
            $_SESSION['flash_success'] = 'Заявка обновлена.';
        }
        header('Location: ' . $to);
        exit;
    }

    public function remove(): void
    {
        if (!csrfValid()) {
            $this->back('flash_error', 'Сессия устарела. Обновите страницу и повторите действие.', '/cart');
        }

        Cart::remove((int)($_POST['product_id'] ?? 0));
        $_SESSION['flash_success'] = 'Позиция удалена из заявки.';
        header('Location: /cart');
        exit;
    }

    public function clear(): void
    {
        if (!csrfValid()) {
            $this->back('flash_error', 'Сессия устарела. Обновите страницу и повторите действие.', '/cart');
        }

        Cart::clear();
        $_SESSION['flash_success'] = 'Заявка очищена.';
        header('Location: /cart');
        exit;
    }

    /** «Повторить»: переносит все позиции прошлой сделки в новую заявку. */
    public function repeat(): void
    {
        if (!csrfValid()) {
            $this->back('flash_error', 'Сессия устарела. Обновите страницу и повторите действие.', '/account#deals');
        }

        if (!isset($_SESSION['user_id'], $_SESSION['company_id'])) {
            $this->back('flash_error', 'Войдите в аккаунт, чтобы повторить закупку.', '/login');
        }
        if (isStaffRole($_SESSION['role'] ?? '') || !canCompany('create_deals')) {
            $this->back('flash_error', 'Недостаточно прав для оформления закупки.', '/account#deals');
        }

        $dealId = (int)($_POST['deal_id'] ?? 0);
        $stmt = $this->pdo->prepare('SELECT id, deal_number FROM deals WHERE id = ? AND buyer_company_id = ? LIMIT 1');
        $stmt->execute([$dealId, (int)$_SESSION['company_id']]);
        $deal = $stmt->fetch();

        if (!$deal) {
            $this->back('flash_error', 'Сделка не найдена.', '/account#deals');
        }

        $stmt = $this->pdo->prepare(
            'SELECT product_id, quantity FROM deal_items WHERE deal_id = ? AND product_id IS NOT NULL'
        );
        $stmt->execute([$dealId]);
        $added = 0;

        foreach ($stmt->fetchAll() as $item) {
            $product = $this->loadProduct((int)$item['product_id']);
            if (!$product) {
                continue;
            }
            if ((int)$product['company_id'] === (int)$_SESSION['company_id']) {
                continue;
            }
            if ((int)$product['in_stock'] === 0 && !$this->orderedBefore((int)$product['id'])) {
                continue;
            }
            $min = max(1, (int)$product['min_order']);
            Cart::add((int)$product['id'], max($min, (int)$item['quantity']));
            $added++;
        }

        if ($added === 0) {
            $this->back('flash_error', 'Товары из этой сделки больше недоступны для заказа.', '/account#deals');
        }

        $_SESSION['flash_success'] = 'В заявку перенесено позиций: ' . $added . '. Проверьте количество и оформите закупку.';
        header('Location: /cart');
        exit;
    }

    /** @return array<string,mixed>|null */
    private function loadProduct(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT p.id, p.company_id, p.name, p.sku, p.price, p.unit, p.min_order, p.in_stock
             FROM products p
             WHERE p.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function orderedBefore(int $productId): bool
    {
        if (!isset($_SESSION['company_id'])) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM deals WHERE buyer_company_id = ? AND product_id = ? AND status = 'completed' LIMIT 1"
        );
        $stmt->execute([(int)$_SESSION['company_id'], $productId]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Возврат на предыдущую страницу сайта. Referer проверяем: нужен путь
     * на нашем же хосте, иначе уходим на $fallback (защита от open redirect).
     */
    private function back(string $key, string $message, string $fallback = '/cart'): never
    {
        if ($message !== '') {
            $_SESSION[$key] = $message;
        }

        $target = $fallback;
        $ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
        if ($ref !== '') {
            $parts = parse_url($ref);
            $host  = (string)($parts['host'] ?? '');
            $path  = (string)($parts['path'] ?? '');
            $sameHost = $host !== '' && strcasecmp($host, (string)($_SERVER['HTTP_HOST'] ?? '')) === 0;

            if ($sameHost && $path !== '' && $path[0] === '/' && !str_starts_with($path, '//')) {
                $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
                $target = $path . $query;
            }
        }

        header('Location: ' . $target);
        exit;
    }
}
