<?php
class CompanyController
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
        // проверяющие — персонал платформы, у них нет страницы компании и товаров
        if (($_SESSION['role'] ?? '') === 'inspector') {
            header('Location: /inspector');
            exit;
        }
    }

    /** Страница компании, под которой выполнен вход, и её товары. */
    public function index(): void
    {
        $this->checkAuth();

        $stmt = $this->pdo->prepare(
            'SELECT c.id, c.name AS company_name, c.inn,
                    c.address AS legal_address, c.description,
                    c.phone, c.email,
                    u.username, u.full_name AS contact_person
             FROM users u
             JOIN companies c ON c.id = u.company_id
             WHERE u.id = ? LIMIT 1'
        );
        $stmt->execute([$_SESSION['user_id']]);
        $company = $stmt->fetch();

        if (!$company) {
            session_destroy();
            header('Location: /login');
            exit;
        }

        $stmt = $this->pdo->prepare(
            'SELECT p.id, p.name, p.sku, p.description, p.price, p.unit, p.min_order, p.in_stock,
                    cat.name AS category_name
             FROM products p
             LEFT JOIN categories cat ON cat.id = p.category_id
             WHERE p.company_id = ?
             ORDER BY p.id DESC'
        );
        $stmt->execute([$_SESSION['company_id']]);
        $products = $stmt->fetchAll();

        // категории, выбранные компанией в личном кабинете
        $stmt = $this->pdo->prepare(
            'SELECT cat.id, cat.name
             FROM company_categories cc
             JOIN categories cat ON cat.id = cc.category_id
             WHERE cc.company_id = ?
             ORDER BY cat.name'
        );
        $stmt->execute([$_SESSION['company_id']]);
        $myCategories = $stmt->fetchAll();

        $pageTitle = $company['company_name'];
        require BASE_PATH . '/views/company/index.php';
    }

    public function addProduct(): void
    {
        $this->checkAuth();
        if (!canCompany('manage_products')) {
            $_SESSION['flash_error'] = 'Недостаточно прав для работы с товарами. Обратитесь к директору компании.';
            header('Location: /company#products');
            exit;
        }

        if (!csrfValid()) {
            $_SESSION['flash_error'] = 'Сессия устарела. Обновите страницу и повторите действие.';
            header('Location: /company#products');
            exit;
        }

        $post = fn(string $k): string => is_string($_POST[$k] ?? null) ? trim($_POST[$k]) : '';
        $name = $post('name');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $sku = $post('sku');
        $description = $post('description');
        $price = str_replace([' ', ','], ['', '.'], $post('price'));
        $unit = $post('unit') ?: 'шт.';
        $minOrder = (int)($_POST['min_order'] ?? 1);
        $inStock = isset($_POST['in_stock']) ? 1 : 0;

        if ($name === '') {
            $error = 'Укажите название товара.';
        } elseif (!$this->companyHasCategory($categoryId)) {
            $error = 'Выберите категорию из списка категорий вашей компании (они настраиваются в личном кабинете).';
        } elseif (!is_numeric($price) || (float)$price < 0 || (float)$price > 9999999999.99) {
            $error = 'Некорректная цена.';
        } elseif (mb_strlen($name) > 255 || mb_strlen($sku) > 64 || mb_strlen($unit) > 20 || mb_strlen($description) > 2000) {
            $error = 'Слишком длинное значение в одном из полей.';
        } else {
            $error = '';
        }

        if ($error !== '') {
            $_SESSION['flash_error'] = $error;
            header('Location: /company#products');
            exit;
        }

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO products (company_id, category_id, name, sku, description, price, unit, min_order, in_stock)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $_SESSION['company_id'],
                $categoryId,
                $name,
                nullIfEmpty($sku),
                nullIfEmpty($description),
                round((float)$price, 2),
                $unit,
                max(1, $minOrder),
                $inStock,
            ]);

            $_SESSION['flash_success'] = 'Товар добавлен.';
        } catch (PDOException $e) {
            // UNIQUE (company_id, sku)
            $_SESSION['flash_error'] = ((int)($e->errorInfo[1] ?? 0) === 1062)
                ? 'Товар с таким артикулом у вашей компании уже есть.'
                : 'Не удалось добавить товар.';
        }

        header('Location: /company#products');
        exit;
    }

    /** Категория должна быть среди выбранных компанией. */
    private function companyHasCategory(int $categoryId): bool
    {
        if ($categoryId <= 0) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM company_categories WHERE company_id = ? AND category_id = ? LIMIT 1'
        );
        $stmt->execute([$_SESSION['company_id'], $categoryId]);
        return (bool)$stmt->fetchColumn();
    }

    public function deleteProduct(): void
    {
        $this->checkAuth();
        if (!canCompany('manage_products')) {
            $_SESSION['flash_error'] = 'Недостаточно прав для работы с товарами. Обратитесь к директору компании.';
            header('Location: /company#products');
            exit;
        }

        if (!csrfValid()) {
            $_SESSION['flash_error'] = 'Сессия устарела. Обновите страницу и повторите действие.';
            header('Location: /company#products');
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);

        // company_id в условии — нельзя удалить товар чужой компании
        $stmt = $this->pdo->prepare('DELETE FROM products WHERE id = ? AND company_id = ?');
        $stmt->execute([$id, $_SESSION['company_id']]);

        $_SESSION['flash_success'] = $stmt->rowCount() ? 'Товар удалён.' : 'Товар не найден.';
        header('Location: /company#products');
        exit;
    }
}
