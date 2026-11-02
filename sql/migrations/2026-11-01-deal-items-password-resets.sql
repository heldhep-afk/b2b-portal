-- Миграция: мультипозиционные закупки + восстановление пароля.
--
-- Применяется поверх существующей базы. Скрипт идемпотентен:
-- повторный запуск не создаёт дубликаты и не портит данные.
--
-- 1) deal_items — позиции сделки. deals.product_id/quantity/unit_price
--    остаются как «первая позиция» для обратной совместимости со старым кодом.
-- 2) password_resets — одноразовые токены восстановления пароля (хранится хеш).

CREATE TABLE IF NOT EXISTS `deal_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `deal_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL COMMENT 'NULL — товар удалён, данные сохранены в снимке',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'название на момент сделки',
  `sku` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'шт.',
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL COMMENT 'цена на момент сделки',
  `total` decimal(14,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_di_deal` (`deal_id`),
  KEY `idx_di_product` (`product_id`),
  CONSTRAINT `fk_di_deal` FOREIGN KEY (`deal_id`) REFERENCES `deals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_di_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'sha256 от токена из письма',
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pr_token` (`token_hash`),
  KEY `idx_pr_user` (`user_id`),
  KEY `idx_pr_expires` (`expires_at`),
  CONSTRAINT `fk_pr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Переносим уже существующие сделки в позиции: одна сделка = одна позиция.
INSERT INTO `deal_items` (`deal_id`, `product_id`, `name`, `sku`, `unit`, `quantity`, `unit_price`, `total`)
SELECT d.`id`, d.`product_id`, p.`name`, p.`sku`, p.`unit`, d.`quantity`, d.`unit_price`, d.`total_amount`
FROM `deals` d
JOIN `products` p ON p.`id` = d.`product_id`
LEFT JOIN `deal_items` di ON di.`deal_id` = d.`id`
WHERE di.`id` IS NULL;
