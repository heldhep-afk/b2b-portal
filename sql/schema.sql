-- B2B Portal - schema (MySQL 5.7+ / MariaDB 10.3+)
--
-- Import:
--   CREATE DATABASE b2b_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--   USE b2b_portal;
--   SOURCE sql/schema.sql;
--
-- Tables are listed alphabetically, so foreign key checks are disabled
-- during import and re-enabled at the end of the file.
--
-- B2B Portal: database schema (MySQL)

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
-- B2B Portal: database schema (MySQL)

CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `fk_categories_parent` (`parent_id`),
  CONSTRAINT `fk_categories_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `companies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `inn` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `join_code` char(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'код компании: сотрудники подают по нему заявки',
  `join_enabled` tinyint(1) NOT NULL DEFAULT '1' COMMENT '0 — приём заявок выключен',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inn` (`inn`),
  UNIQUE KEY `join_code` (`join_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `company_categories` (
  `company_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`company_id`,`category_id`),
  KEY `fk_cc_category` (`category_id`),
  CONSTRAINT `fk_cc_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cc_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `deal_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `deal_id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `action` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'confirm_payment, confirm_deal, cancel, payout, refund',
  `comment` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_events_deal` (`deal_id`),
  KEY `fk_events_admin` (`admin_id`),
  CONSTRAINT `fk_events_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_events_deal` FOREIGN KEY (`deal_id`) REFERENCES `deals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `deal_items` (
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

CREATE TABLE `deals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `deal_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `buyer_company_id` int(11) NOT NULL,
  `seller_company_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL COMMENT 'users.id — кто оформил сделку',
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL COMMENT 'цена за единицу на момент сделки',
  `total_amount` decimal(14,2) NOT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RUB',
  `status` enum('in_progress','completed','rejected','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in_progress',
  `payment_status` enum('unpaid','paid','paid_out','refunded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unpaid',
  `buyer_approved_at` datetime DEFAULT NULL COMMENT 'когда покупатель одобрил сделку',
  `paid_at` datetime DEFAULT NULL COMMENT 'администратор подтвердил поступление оплаты',
  `paid_out_at` datetime DEFAULT NULL COMMENT 'выплата продавцу',
  `payment_terms` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivery_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comment` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `closed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `deal_number` (`deal_number`),
  KEY `idx_deals_buyer` (`buyer_company_id`),
  KEY `idx_deals_seller` (`seller_company_id`),
  KEY `idx_deals_product` (`product_id`),
  KEY `idx_deals_status` (`status`),
  KEY `idx_deals_payment` (`payment_status`),
  KEY `fk_deals_user` (`created_by`),
  CONSTRAINT `fk_deals_buyer` FOREIGN KEY (`buyer_company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_deals_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `fk_deals_seller` FOREIGN KEY (`seller_company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_deals_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `inspection_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `inspection_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'requested, fee_confirmed, assigned, taken, submitted, approved, returned, cancelled, fee_refunded, reward_paid',
  `comment` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ievents_inspection` (`inspection_id`),
  KEY `fk_ievents_user` (`user_id`),
  CONSTRAINT `fk_ievents_inspection` FOREIGN KEY (`inspection_id`) REFERENCES `inspections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ievents_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `inspections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `deal_id` int(11) NOT NULL COMMENT 'по одной сделке — одна проверка',
  `requested_by` int(11) DEFAULT NULL COMMENT 'кто из компании-покупателя заказал проверку',
  `buyer_note` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'что именно нужно проверить',
  `inspector_id` int(11) DEFAULT NULL COMMENT 'проверяющий (users.id, role = inspector)',
  `fee` decimal(12,2) NOT NULL COMMENT 'доплата компании за проверку',
  `fee_status` enum('unpaid','paid','refunded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unpaid',
  `fee_paid_at` datetime DEFAULT NULL,
  `reward` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'вознаграждение проверяющему',
  `reward_paid_at` datetime DEFAULT NULL,
  `status` enum('requested','assigned','submitted','approved','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'requested',
  `assigned_at` datetime DEFAULT NULL,
  `verdict` enum('ok','remarks','fail') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `report_summary` text COLLATE utf8mb4_unicode_ci,
  `report_details` text COLLATE utf8mb4_unicode_ci,
  `report_links` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `checked_on` date DEFAULT NULL COMMENT 'дата проведения проверки',
  `visit_at` datetime DEFAULT NULL COMMENT 'согласуемые дата и время проверки на месте',
  `visit_status` enum('none','proposed','confirmed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
  `visit_proposed_by` enum('inspector','seller') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'кто предложил текущее время',
  `visit_note` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `escort_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'сотрудник продавца, сопровождающий проверяющего',
  `escort_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'номер (табельный/пропуск) сопровождающего сотрудника',
  `escort_phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `visit_confirmed_at` datetime DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `admin_comment` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `revision_count` int(11) NOT NULL DEFAULT '0' COMMENT 'сколько раз отчёт возвращали на доработку',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_insp_deal` (`deal_id`),
  KEY `idx_insp_inspector` (`inspector_id`),
  KEY `idx_insp_status` (`status`),
  KEY `fk_insp_requester` (`requested_by`),
  KEY `fk_insp_reviewer` (`reviewed_by`),
  CONSTRAINT `fk_insp_deal` FOREIGN KEY (`deal_id`) REFERENCES `deals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_insp_inspector` FOREIGN KEY (`inspector_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_insp_requester` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_insp_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `inspector_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `about` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'опыт и сферы, в которых может проверять',
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','approved','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_apps_status` (`status`),
  KEY `idx_apps_email` (`email`),
  KEY `fk_apps_reviewer` (`reviewed_by`),
  CONSTRAINT `fk_apps_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `password_resets` (
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

CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sku` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `price` decimal(12,2) NOT NULL,
  `unit` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'шт.',
  `min_order` int(11) NOT NULL DEFAULT '1',
  `in_stock` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_company_sku` (`company_id`,`sku`),
  KEY `idx_products_category` (`category_id`),
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  CONSTRAINT `fk_products_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_links` (
  `user_id` int(11) NOT NULL,
  `linked_user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`,`linked_user_id`),
  KEY `fk_ul_linked` (`linked_user_id`),
  CONSTRAINT `fk_ul_linked` FOREIGN KEY (`linked_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ul_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('client','manager','admin','inspector') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'client',
  `company_role` enum('owner','manager','employee') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'employee' COMMENT 'роль внутри компании: owner — директор',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '0 — сотрудник отключён директором',
  `permissions` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'индивидуальные права через запятую; NULL — права роли',
  `join_status` enum('approved','pending','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'approved' COMMENT 'заявка на вступление в компанию',
  `join_requested_at` datetime DEFAULT NULL,
  `join_decided_by` int(11) DEFAULT NULL COMMENT 'кто принял или отклонил заявку',
  `join_decided_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_company` (`company_id`),
  KEY `fk_users_decider` (`join_decided_by`),
  CONSTRAINT `fk_users_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_users_decider` FOREIGN KEY (`join_decided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SET FOREIGN_KEY_CHECKS = 1;