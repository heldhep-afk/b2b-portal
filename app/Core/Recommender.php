<?php
/**
 * Рекомендации товаров на основе прошлых закупок компании.
 *
 * Учитываются сделки «в работе» и «завершён» (отклонённые и отменённые не считаются).
 * Сигналы:
 *   1) категории, в которых компания уже покупала (завершённые сделки весят больше);
 *   2) «покупатели вместе с вами» — компании, купившие те же товары, и то, что ещё покупали они;
 *   3) поставщики, с которыми компания уже работала;
 *   4) небольшой бонус за наличие и популярность.
 * Если истории закупок нет, показываются популярные товары портала.
 */
class Recommender
{
    private const STATUS_WEIGHT = ['completed' => 3, 'in_progress' => 1];
    private const MAX_PER_CATEGORY = 3;

    public function __construct(private PDO $pdo) {}

    /**
     * @return array{items: array<int,array>, personalized: bool, interests: array<int,array{name:string,weight:int}>}
     */
    public function forCompany(int $companyId, int $limit = 6): array
    {
        $bought = [];      // product_id => true
        $catW = [];        // category_id => вес
        $catName = [];
        $sellerW = [];     // seller_id => вес
        $sellerName = [];

        foreach ($this->history($companyId) as $h) {
            $weight = self::STATUS_WEIGHT[$h['status']] ?? 0;
            if ($weight === 0) {
                continue;
            }
            $n = (int)$h['cnt'] * $weight;
            $bought[(int)$h['product_id']] = true;

            $cat = (int)$h['category_id'];
            $catW[$cat] = ($catW[$cat] ?? 0) + $n;
            $catName[$cat] = $h['category_name'] ?? '';

            $sid = (int)$h['seller_id'];
            $sellerW[$sid] = ($sellerW[$sid] ?? 0) + $n;
            $sellerName[$sid] = $h['seller_name'];
        }

        if (!$bought) {
            return ['items' => $this->popular($companyId, $limit), 'personalized' => false, 'interests' => []];
        }

        arsort($catW);
        $interests = [];
        foreach (array_slice($catW, 0, 5, true) as $cid => $w) {
            if (($catName[$cid] ?? '') !== '') {
                $interests[] = ['name' => $catName[$cid], 'weight' => $w];
            }
        }

        $coBuyers = $this->coBuyers($companyId);   // product_id => сколько компаний

        // ---- кандидаты: подходящая категория, поставщик или «покупают вместе» ----
        $cond = [];
        $params = [$companyId];
        $this->addIn($cond, $params, 'p.category_id', array_keys($catW));
        $this->addIn($cond, $params, 'p.company_id', array_keys($sellerW));
        $this->addIn($cond, $params, 'p.id', array_keys($coBuyers));

        $where = 'p.company_id <> ? AND p.in_stock > 0';
        if ($cond) {
            $where .= ' AND (' . implode(' OR ', $cond) . ')';
        }
        $boughtIds = array_keys($bought);
        $ph = implode(',', array_fill(0, count($boughtIds), '?'));
        $where .= " AND p.id NOT IN ($ph)";
        array_push($params, ...$boughtIds);

        $stmt = $this->pdo->prepare($this->productSelect() . " WHERE $where LIMIT 300");
        $stmt->execute($params);

        $scored = [];
        foreach ($stmt->fetchAll() as $p) {
            $id = (int)$p['id'];
            $score = 0.0;
            $reasons = [];

            $co = $coBuyers[$id] ?? 0;
            if ($co > 0) {
                $score += 2 * min($co, 4);
                $reasons[] = ['type' => 'co', 'text' => $co > 1
                    ? "Берут компании с похожими закупками ($co)"
                    : 'Берут компании с похожими закупками'];
            }
            $cw = $catW[(int)$p['category_id']] ?? 0;
            if ($cw > 0) {
                $score += min($cw, 6);
                $reasons[] = ['type' => 'category', 'text' => 'Вы покупали в категории «' . ($p['category_name'] ?? '') . '»'];
            }
            $sw = $sellerW[(int)$p['company_id']] ?? 0;
            if ($sw > 0) {
                $score += 0.5 * min($sw, 6);
                $reasons[] = ['type' => 'seller', 'text' => 'Вы уже работали с «' . $p['company_name'] . '»'];
            }
            if ((int)$p['in_stock'] > 0) {
                $score += 1;
            }
            $score += 0.5 * min((int)$p['popularity'], 4);

            $p['score'] = $score;
            $p['reasons'] = array_slice($reasons, 0, 2);
            $scored[] = $p;
        }

        usort($scored, fn($a, $b) => [$b['score'], (int)$b['popularity'], (int)$b['id']]
                                  <=> [$a['score'], (int)$a['popularity'], (int)$a['id']]);

        $items = $this->diversify($scored, $limit);

        // не хватило персональных — добавляем популярное
        if (count($items) < $limit) {
            $exclude = array_merge($boughtIds, array_map(fn($i) => (int)$i['id'], $items));
            $items = array_merge($items, $this->popular($companyId, $limit - count($items), $exclude));
        }

        return ['items' => $items, 'personalized' => true, 'interests' => $interests];
    }

    // ---------------------------------------------------------------

    private function history(int $companyId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT d.product_id, p.category_id, p.company_id AS seller_id, d.status, COUNT(*) AS cnt,
                    cat.name AS category_name, s.name AS seller_name
             FROM deals d
             JOIN products p ON p.id = d.product_id
             JOIN companies s ON s.id = p.company_id
             LEFT JOIN categories cat ON cat.id = p.category_id
             WHERE d.buyer_company_id = ? AND d.status IN ('completed', 'in_progress')
             GROUP BY d.product_id, p.category_id, p.company_id, d.status, cat.name, s.name"
        );
        $stmt->execute([$companyId]);
        return $stmt->fetchAll();
    }

    /** Товары, которые покупали другие компании, купившие то же, что и мы: product_id => число компаний. */
    private function coBuyers(int $companyId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT x2.product_id, COUNT(DISTINCT x2.buyer_company_id) AS co_buyers
             FROM deals mine
             JOIN deals x1 ON x1.product_id = mine.product_id
                          AND x1.buyer_company_id <> mine.buyer_company_id
                          AND x1.status IN ('completed', 'in_progress')
             JOIN deals x2 ON x2.buyer_company_id = x1.buyer_company_id
                          AND x2.product_id <> mine.product_id
                          AND x2.status IN ('completed', 'in_progress')
             WHERE mine.buyer_company_id = ? AND mine.status IN ('completed', 'in_progress')
             GROUP BY x2.product_id"
        );
        $stmt->execute([$companyId]);

        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            $out[(int)$r['product_id']] = (int)$r['co_buyers'];
        }
        return $out;
    }

    /** Популярные товары других компаний (холодный старт и добор). */
    private function popular(int $companyId, int $limit, array $excludeIds = []): array
    {
        $params = [$companyId];
        $where = 'p.company_id <> ? AND p.in_stock > 0';
        if ($excludeIds) {
            $where .= ' AND p.id NOT IN (' . implode(',', array_fill(0, count($excludeIds), '?')) . ')';
            array_push($params, ...$excludeIds);
        }
        $stmt = $this->pdo->prepare(
            $this->productSelect() . " WHERE $where
             ORDER BY popularity DESC, p.in_stock DESC, p.id DESC
             LIMIT " . max(1, $limit)
        );
        $stmt->execute($params);

        $items = $stmt->fetchAll();
        foreach ($items as &$p) {
            $p['score'] = 0.0;
            $p['reasons'] = [(int)$p['popularity'] > 0
                ? ['type' => 'popular', 'text' => 'Популярно на портале']
                : ['type' => 'new', 'text' => 'Новое в каталоге']];
        }
        unset($p);
        return $items;
    }

    private function productSelect(): string
    {
        return "SELECT p.id, p.name, p.sku, p.description, p.price, p.unit, p.min_order, p.in_stock,
                       p.company_id, p.category_id, c.name AS company_name, cat.name AS category_name,
                       (SELECT COUNT(*) FROM deals dd
                         WHERE dd.product_id = p.id AND dd.status IN ('completed', 'in_progress')) AS popularity
                FROM products p
                JOIN companies c ON c.id = p.company_id
                LEFT JOIN categories cat ON cat.id = p.category_id";
    }

    private function addIn(array &$cond, array &$params, string $column, array $ids): void
    {
        $ids = array_values(array_map('intval', $ids));
        if (!$ids) {
            return;
        }
        $cond[] = "$column IN (" . implode(',', array_fill(0, count($ids), '?')) . ')';
        array_push($params, ...$ids);
    }

    /** Не больше MAX_PER_CATEGORY из одной категории — пока хватает вариантов. */
    private function diversify(array $sorted, int $limit): array
    {
        $out = [];
        $perCat = [];
        $rest = [];
        foreach ($sorted as $p) {
            $cat = (int)$p['category_id'];
            if (count($out) < $limit && ($perCat[$cat] ?? 0) < self::MAX_PER_CATEGORY) {
                $out[] = $p;
                $perCat[$cat] = ($perCat[$cat] ?? 0) + 1;
            } else {
                $rest[] = $p;
            }
        }
        foreach ($rest as $p) {
            if (count($out) >= $limit) {
                break;
            }
            $out[] = $p;
        }
        return $out;
    }
}
