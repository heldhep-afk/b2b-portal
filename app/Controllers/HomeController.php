<?php
require_once BASE_PATH . '/app/Core/Recommender.php';
/** Главная страница: каталог товаров с фильтрами. Доступен и гостям. */
class HomeController
{
    private const PER_PAGE = 12;

    /** ключ => [подпись, ORDER BY] */
    private const SORTS = [
        'new'        => ['Сначала новые',        'p.id DESC'],
        'price_asc'  => ['Цена: по возрастанию', 'p.price ASC, p.id DESC'],
        'price_desc' => ['Цена: по убыванию',    'p.price DESC, p.id DESC'],
        'name'       => ['По названию',          'p.name ASC, p.id DESC'],
    ];

    public function __construct(private PDO $pdo) {}

    public function index(): void
    {
        $str = fn(string $k): string => is_string($_GET[$k] ?? null) ? trim($_GET[$k]) : '';

        $q          = mb_substr($str('q'), 0, 100);
        $categoryId = (int)$str('category');
        $sellerId   = (int)$str('seller');
        $priceMin   = $this->number($str('price_min'));
        $priceMax   = $this->number($str('price_max'));
        $inStock    = $str('in_stock') !== '';

        // Рекомендации по прошлым закупкам компании: помечаем товары в каталоге и по умолчанию показываем их первыми
        $recMap = [];          // product_id => причины рекомендации
        $orderedBefore = [];   // product_id => true: компания уже заказывала (завершённые сделки)
        if (isset($_SESSION['user_id']) && !isStaffRole($_SESSION['role'] ?? '')) {
            try {
                $companyIdForRecs = (int)($_SESSION['company_id'] ?? 0);
                $res = (new Recommender($this->pdo))->forCompany($companyIdForRecs, 12);
                if ($res['personalized']) {
                    foreach ($res['items'] as $it) {
                        $type = $it['reasons'][0]['type'] ?? '';
                        if (in_array($type, ['co', 'category', 'seller'], true)) {
                            $recMap[(int)$it['id']] = $it['reasons'];
                        }
                    }
                }
                $stmtOrdered = $this->pdo->prepare("SELECT DISTINCT product_id FROM deals WHERE buyer_company_id = ? AND status = 'completed'");
                $stmtOrdered->execute([$companyIdForRecs]);
                foreach ($stmtOrdered->fetchAll(PDO::FETCH_COLUMN) as $pid) {
                    $orderedBefore[(int)$pid] = true;
                }
            } catch (PDOException $e) {
                error_log('Recommendations failed: ' . $e->getMessage());
            }
        }
        $recIds = array_keys($recMap);
        $recList = implode(',', array_map('intval', $recIds));
        $defaultSort = $recIds ? 'recommended' : 'new';
        $sortKeys = $recIds ? array_merge(['recommended'], array_keys(self::SORTS)) : array_keys(self::SORTS);
        $sort       = in_array($str('sort'), $sortKeys, true) ? $str('sort') : $defaultSort;
        $onlyRec    = $recIds && $str('rec') !== '';
        $page       = max(1, (int)$str('page'));

        // ---- условия выборки ----
        $where  = [];
        $params = [];

        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ? OR cat.name LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }
        if ($categoryId > 0) {
            $where[] = 'p.category_id = ?';
            $params[] = $categoryId;
        }
        if ($sellerId > 0) {
            $where[] = 'p.company_id = ?';
            $params[] = $sellerId;
        }
        if ($priceMin !== null) {
            $where[] = 'p.price >= ?';
            $params[] = $priceMin;
        }
        if ($priceMax !== null) {
            $where[] = 'p.price <= ?';
            $params[] = $priceMax;
        }
        if ($inStock) {
            $where[] = 'p.in_stock > 0';
        }
        if ($onlyRec) {
            $where[] = "p.id IN ($recList)";
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $items = [];
        $categories = [];
        $sellers = [];
        $total = 0;
        $dbNotice = '';
        $catalogStats = ['products' => 0, 'companies' => 0, 'categories' => 0];

        try {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*)
                 FROM products p
                 JOIN companies c ON c.id = p.company_id
                 LEFT JOIN categories cat ON cat.id = p.category_id
                 $whereSql"
            );
            $stmt->execute($params);
            $total = (int)$stmt->fetchColumn();

            $pages  = max(1, (int)ceil($total / self::PER_PAGE));
            $page   = min($page, $pages);
            $offset = ($page - 1) * self::PER_PAGE;
            $limit  = self::PER_PAGE;
            $order  = $sort === 'recommended'
                ? "(FIELD(p.id, $recList) = 0), FIELD(p.id, $recList), p.id DESC"
                : self::SORTS[$sort][1];

            $stmt = $this->pdo->prepare(
                "SELECT p.id, p.name, p.sku, p.description, p.price, p.unit, p.min_order, p.in_stock,
                        p.company_id, c.name AS company_name, cat.name AS category_name
                 FROM products p
                 JOIN companies c ON c.id = p.company_id
                 LEFT JOIN categories cat ON cat.id = p.category_id
                 $whereSql
                 ORDER BY $order
                 LIMIT $limit OFFSET $offset"
            );
            $stmt->execute($params);
            $items = $stmt->fetchAll();

            $categories = $this->pdo->query(
                'SELECT cat.id, cat.name, COUNT(p.id) AS cnt
                 FROM categories cat
                 JOIN products p ON p.category_id = cat.id
                 GROUP BY cat.id, cat.name
                 ORDER BY cat.name'
            )->fetchAll();

            $sellers = $this->pdo->query(
                'SELECT c.id, c.name
                 FROM companies c
                 WHERE EXISTS (SELECT 1 FROM products p WHERE p.company_id = c.id)
                 ORDER BY c.name'
            )->fetchAll();

            $catalogStats = $this->pdo->query(
                'SELECT (SELECT COUNT(*) FROM products)   AS products,
                        (SELECT COUNT(*) FROM companies)  AS companies,
                        (SELECT COUNT(*) FROM categories) AS categories'
            )->fetch() ?: $catalogStats;
        } catch (PDOException $e) {
            $pages = 1;
            $dbNotice = 'Не удалось загрузить каталог. Проверьте, что выполнены SQL-скрипты (categories, products.category_id).';
        }

        // параметры, которые нужно сохранять в ссылках пагинации
        $baseQuery = array_filter([
            'q'         => $q,
            'category'  => $categoryId ?: '',
            'seller'    => $sellerId ?: '',
            'price_min' => $priceMin !== null ? $str('price_min') : '',
            'price_max' => $priceMax !== null ? $str('price_max') : '',
            'in_stock'  => $inStock ? '1' : '',
            'rec'       => $onlyRec ? '1' : '',
            'sort'      => $sort !== $defaultSort ? $sort : '',
        ], fn($v) => $v !== '');

        $filters = [
            'q' => $q, 'category' => $categoryId, 'seller' => $sellerId,
            'price_min' => $priceMin !== null ? $str('price_min') : '',
            'price_max' => $priceMax !== null ? $str('price_max') : '',
            'in_stock' => $inStock, 'rec' => $onlyRec, 'sort' => $sort,
        ];
        $sorts = array_map(fn($s) => $s[0], self::SORTS);
        if ($recIds) {
            $sorts = ['recommended' => 'Сначала рекомендованные'] + $sorts;
        }
        $currentCompanyId = (int)($_SESSION['company_id'] ?? 0);
        $isAuth = isset($_SESSION['user_id']);

        $pageTitle = 'Каталог';
        require BASE_PATH . '/views/home/index.php';
    }

    private function number(string $raw): ?float
    {
        $raw = str_replace([' ', ','], ['', '.'], $raw);
        return ($raw !== '' && is_numeric($raw) && (float)$raw >= 0) ? (float)$raw : null;
    }
}
