<?php
declare(strict_types=1);

/**
 * Корзина (будущая заявка) в сессии: product_id => количество.
 *
 * Цены, наличие и продавец здесь не хранятся: они всегда перечитываются
 * из базы при показе и при оформлении. Подмена данных в cookie/сессии
 * не влияет на сумму сделки — она считается по актуальным ценам.
 */
final class Cart
{
    public const MAX_QTY = 100000000;

    private const KEY = 'cart';

    /** @return array<int,int> product_id => количество */
    public static function items(): array
    {
        $raw = $_SESSION[self::KEY] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $pid => $qty) {
            $pid = (int)$pid;
            $qty = (int)$qty;
            if ($pid > 0 && $qty > 0) {
                $out[$pid] = min($qty, self::MAX_QTY);
            }
        }
        return $out;
    }

    /** Добавляет количество к позиции (или создаёт её). */
    public static function add(int $productId, int $qty): void
    {
        if ($productId <= 0 || $qty <= 0) {
            return;
        }
        $items = self::items();
        $items[$productId] = min(($items[$productId] ?? 0) + $qty, self::MAX_QTY);
        $_SESSION[self::KEY] = $items;
    }

    /** Задаёт точное количество; 0 — удаляет позицию. */
    public static function set(int $productId, int $qty): void
    {
        if ($productId <= 0) {
            return;
        }
        if ($qty <= 0) {
            self::remove($productId);
            return;
        }
        $items = self::items();
        $items[$productId] = min($qty, self::MAX_QTY);
        $_SESSION[self::KEY] = $items;
    }

    public static function remove(int $productId): void
    {
        $items = self::items();
        unset($items[$productId]);
        $_SESSION[self::KEY] = $items;
    }

    public static function clear(): void
    {
        unset($_SESSION[self::KEY]);
    }

    /** Оставляет в корзине только перечисленные товары (удалённые из базы отбрасываем). */
    public static function retain(array $productIds): void
    {
        $keep = array_flip(array_map('intval', $productIds));
        $_SESSION[self::KEY] = array_intersect_key(self::items(), $keep);
    }

    public static function count(): int
    {
        return count(self::items());
    }

    public static function isEmpty(): bool
    {
        return self::items() === [];
    }

    /**
     * Позиции с данными товара и продавца. Товары, которых больше нет в базе,
     * в результат не попадают.
     */
    public static function load(PDO $pdo): array
    {
        $items = self::items();
        if ($items === []) {
            return [];
        }

        $ids = array_keys($items);
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare(
            "SELECT p.id, p.company_id, p.name, p.sku, p.description, p.price, p.unit, p.min_order, p.in_stock,
                    c.name AS company_name, c.phone AS company_phone, cat.name AS category_name
             FROM products p
             JOIN companies c ON c.id = p.company_id
             LEFT JOIN categories cat ON cat.id = p.category_id
             WHERE p.id IN ($in)"
        );
        $stmt->execute($ids);

        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $qty = $items[(int)$row['id']] ?? 0;
            if ($qty <= 0) {
                continue;
            }
            $row['qty'] = $qty;
            $row['line_total'] = round((float)$row['price'] * $qty, 2);
            $rows[] = $row;
        }

        return $rows;
    }

    /** Группировка позиций по продавцу: одна группа — одна сделка. */
    public static function bySeller(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $sid = (int)$row['company_id'];
            if (!isset($groups[$sid])) {
                $groups[$sid] = ['company_id' => $sid, 'company_name' => $row['company_name'], 'items' => [], 'total' => 0.0];
            }
            $groups[$sid]['items'][] = $row;
            $groups[$sid]['total'] = round($groups[$sid]['total'] + (float)$row['line_total'], 2);
        }
        return array_values($groups);
    }

    /** Сумма по всем позициям. */
    public static function total(array $rows): float
    {
        $sum = 0.0;
        foreach ($rows as $row) {
            $sum += (float)$row['line_total'];
        }
        return round($sum, 2);
    }
}
