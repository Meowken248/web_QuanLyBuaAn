-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1:3306
-- Thời gian đã tạo: Th7 31, 2026 lúc 02:09 PM
-- Phiên bản máy phục vụ: 8.4.7
-- Phiên bản PHP: 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `meal_health_manager`
--
CREATE DATABASE IF NOT EXISTS `meal_health_manager` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `meal_health_manager`;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `chatbot_usage_logs`
--

DROP TABLE IF EXISTS `chatbot_usage_logs`;
CREATE TABLE IF NOT EXISTS `chatbot_usage_logs` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `usage_date` date NOT NULL,
  `request_count` int UNSIGNED NOT NULL DEFAULT '0',
  `total_tokens` int UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_chatbot_usage_user_date` (`user_id`,`usage_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `chat_conversations`
--

DROP TABLE IF EXISTS `chat_conversations`;
CREATE TABLE IF NOT EXISTS `chat_conversations` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT 'Cuộc trò chuyện mới',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_chat_conversations_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `chat_messages`
--

DROP TABLE IF EXISTS `chat_messages`;
CREATE TABLE IF NOT EXISTS `chat_messages` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint UNSIGNED NOT NULL,
  `sender` enum('user','assistant','system') COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokens_used` int UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_chat_messages_conversation` (`conversation_id`),
  KEY `idx_chat_messages_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `contact_messages`
--

DROP TABLE IF EXISTS `contact_messages`;
CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('new','read','replied') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_contact_messages_status` (`status`),
  KEY `idx_contact_messages_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `favorite_meal_plans`
--

DROP TABLE IF EXISTS `favorite_meal_plans`;
CREATE TABLE IF NOT EXISTS `favorite_meal_plans` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `meal_plan_id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_favorite_user_plan` (`user_id`,`meal_plan_id`),
  KEY `fk_favorite_meal_plans_plan` (`meal_plan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `foods`
--

DROP TABLE IF EXISTS `foods`;
CREATE TABLE IF NOT EXISTS `foods` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` bigint UNSIGNED DEFAULT NULL,
  `name` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `serving_size` decimal(8,2) NOT NULL DEFAULT '100.00',
  `serving_unit` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'g',
  `calories` decimal(8,2) NOT NULL DEFAULT '0.00',
  `protein` decimal(8,2) NOT NULL DEFAULT '0.00',
  `carbs` decimal(8,2) NOT NULL DEFAULT '0.00',
  `fat` decimal(8,2) NOT NULL DEFAULT '0.00',
  `fiber` decimal(8,2) NOT NULL DEFAULT '0.00',
  `sugar` decimal(8,2) NOT NULL DEFAULT '0.00',
  `sodium` decimal(10,2) NOT NULL DEFAULT '0.00',
  `diet_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'normal',
  `is_premium` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `fk_foods_created_by` (`created_by`),
  KEY `idx_foods_name` (`name`),
  KEY `idx_foods_category` (`category_id`),
  KEY `idx_foods_status` (`status`),
  KEY `idx_foods_premium` (`is_premium`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `foods`
--

INSERT INTO `foods` (`id`, `category_id`, `name`, `slug`, `image`, `description`, `serving_size`, `serving_unit`, `calories`, `protein`, `carbs`, `fat`, `fiber`, `sugar`, `sodium`, `diet_type`, `is_premium`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 'Cơm trắng', 'com-trang', NULL, NULL, 100.00, 'g', 130.00, 2.70, 28.20, 0.30, 0.40, 0.10, 1.00, 'normal,vegetarian,vegan', 0, 'active', NULL, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(2, 1, 'Cơm gạo lứt', 'com-gao-lut', NULL, NULL, 100.00, 'g', 123.00, 2.70, 25.60, 1.00, 1.60, 0.40, 4.00, 'normal,vegetarian,vegan,high_fiber', 0, 'active', NULL, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(3, 2, 'Phở bò', 'pho-bo', NULL, NULL, 500.00, 'tô', 430.00, 25.00, 55.00, 12.00, 3.00, 4.00, 850.00, 'normal', 0, 'active', NULL, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(4, 2, 'Phở gà', 'pho-ga', NULL, NULL, 500.00, 'tô', 380.00, 28.00, 50.00, 8.00, 2.50, 3.00, 780.00, 'normal', 0, 'active', NULL, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(5, 2, 'Bún bò Huế', 'bun-bo-hue', NULL, NULL, 550.00, 'tô', 530.00, 30.00, 60.00, 18.00, 3.00, 5.00, 1100.00, 'normal', 0, 'active', NULL, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(6, 2, 'Bún thịt nướng', 'bun-thit-nuong', NULL, NULL, 450.00, 'phần', 480.00, 24.00, 62.00, 15.00, 4.00, 8.00, 720.00, 'normal', 0, 'active', NULL, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(7, 2, 'Hủ tiếu', 'hu-tieu', NULL, NULL, 500.00, 'tô', 400.00, 22.00, 58.00, 9.00, 2.50, 4.00, 800.00, 'normal', 0, 'active', NULL, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(8, 3, 'Ức gà luộc', 'uc-ga-luoc', NULL, NULL, 100.00, 'g', 165.00, 31.00, 0.00, 3.60, 0.00, 0.00, 74.00, 'normal,high_protein,low_carb', 0, 'active', NULL, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(9, 3, 'Thịt bò nạc', 'thit-bo-nac', NULL, NULL, 100.00, 'g', 250.00, 26.00, 0.00, 15.00, 0.00, 0.00, 72.00, 'normal,high_protein,low_carb', 0, 'active', NULL, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(10, 3, 'Thịt heo nạc', 'thit-heo-nac', NULL, NULL, 100.00, 'g', 242.00, 27.00, 0.00, 14.00, 0.00, 0.00, 62.00, 'normal,high_protein,low_carb', 0, 'active', NULL, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(11, 4, 'Cá hồi áp chảo', 'ca-hoi-ap-chao', NULL, NULL, 100.00, 'g', 208.00, 20.00, 0.00, 13.00, 0.00, 0.00, 59.00, 'normal,high_protein,low_carb', 0, 'active', NULL, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(12, 4, 'Cá thu', 'ca-thu', NULL, NULL, 100.00, 'g', 205.00, 19.00, 0.00, 14.00, 0.00, 0.00, 90.00, 'normal,high_protein,low_carb', 0, 'active', NULL, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(13, 4, 'Tôm luộc', 'tom-luoc', NULL, NULL, 100.00, 'g', 99.00, 24.00, 0.20, 0.30, 0.00, 0.00, 111.00, 'normal,high_protein,low_carb', 0, 'active', NULL, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(14, 5, 'Trứng gà luộc', 'trung-ga-luoc', NULL, NULL, 50.00, 'quả', 78.00, 6.30, 0.60, 5.30, 0.00, 0.60, 62.00, 'normal,vegetarian,high_protein', 0, 'active', NULL, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(15, 5, 'Sữa chua không đường', 'sua-chua-khong-duong', NULL, NULL, 100.00, 'hộp', 61.00, 3.50, 4.70, 3.30, 0.00, 4.70, 46.00, 'normal,vegetarian', 0, 'active', NULL, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(16, 5, 'Sữa tươi không đường', 'sua-tuoi-khong-duong', NULL, NULL, 200.00, 'ml', 122.00, 6.40, 9.60, 6.60, 0.00, 9.60, 86.00, 'normal,vegetarian', 0, 'active', NULL, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(17, 6, 'Rau luộc thập cẩm', 'rau-luoc-thap-cam', NULL, NULL, 100.00, 'g', 35.00, 2.00, 7.00, 0.30, 3.00, 2.00, 40.00, 'normal,vegetarian,vegan,gluten_free', 0, 'active', NULL, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(18, 6, 'Khoai lang luộc', 'khoai-lang-luoc', NULL, NULL, 100.00, 'g', 86.00, 1.60, 20.10, 0.10, 3.00, 4.20, 55.00, 'normal,vegetarian,vegan,gluten_free', 0, 'active', NULL, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(19, 6, 'Salad rau củ', 'salad-rau-cu', NULL, NULL, 200.00, 'phần', 120.00, 4.00, 18.00, 4.00, 6.00, 7.00, 180.00, 'normal,vegetarian,vegan,gluten_free', 0, 'active', NULL, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(20, 7, 'Chuối', 'chuoi', NULL, NULL, 100.00, 'g', 89.00, 1.10, 22.80, 0.30, 2.60, 12.20, 1.00, 'normal,vegetarian,vegan,gluten_free', 0, 'active', NULL, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(21, 7, 'Táo', 'tao', NULL, NULL, 100.00, 'g', 52.00, 0.30, 13.80, 0.20, 2.40, 10.40, 1.00, 'normal,vegetarian,vegan,gluten_free', 0, 'active', NULL, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(22, 7, 'Cam', 'cam', NULL, NULL, 100.00, 'g', 47.00, 0.90, 11.80, 0.10, 2.40, 9.40, 0.00, 'normal,vegetarian,vegan,gluten_free', 0, 'active', NULL, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(23, 8, 'Đậu hũ', 'dau-hu', NULL, NULL, 100.00, 'g', 76.00, 8.00, 1.90, 4.80, 0.30, 0.60, 7.00, 'vegetarian,vegan,high_protein,low_carb', 0, 'active', NULL, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(24, 9, 'Nước cam không đường', 'nuoc-cam-khong-duong', NULL, NULL, 250.00, 'ml', 110.00, 1.70, 25.80, 0.50, 0.50, 20.80, 2.00, 'normal,vegetarian,vegan,gluten_free', 0, 'active', NULL, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(25, 10, 'Whey Protein', 'whey-protein', NULL, NULL, 30.00, 'muỗng', 120.00, 24.00, 3.00, 2.00, 0.00, 1.00, 130.00, 'normal,high_protein,low_carb', 0, 'active', NULL, '2026-07-31 14:05:22', '2026-07-31 14:05:22');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `food_categories`
--

DROP TABLE IF EXISTS `food_categories`;
CREATE TABLE IF NOT EXISTS `food_categories` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_food_categories_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `food_categories`
--

INSERT INTO `food_categories` (`id`, `name`, `slug`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Cơm', 'com', 'Các món cơm phổ biến', 'active', '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(2, 'Phở và bún', 'pho-va-bun', 'Các món phở, bún, hủ tiếu', 'active', '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(3, 'Thịt', 'thit', 'Các loại thịt và món từ thịt', 'active', '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(4, 'Cá và hải sản', 'ca-va-hai-san', 'Cá, tôm và hải sản', 'active', '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(5, 'Trứng và sữa', 'trung-va-sua', 'Trứng, sữa và sản phẩm từ sữa', 'active', '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(6, 'Rau củ', 'rau-cu', 'Các loại rau và củ', 'active', '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(7, 'Trái cây', 'trai-cay', 'Các loại trái cây', 'active', '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(8, 'Món chay', 'mon-chay', 'Các món phù hợp người ăn chay', 'active', '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(9, 'Đồ uống', 'do-uong', 'Các loại đồ uống', 'active', '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(10, 'Đồ ăn nhẹ', 'do-an-nhe', 'Các món ăn nhẹ và bữa phụ', 'active', '2026-07-31 14:05:22', '2026-07-31 14:05:22');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `meal_logs`
--

DROP TABLE IF EXISTS `meal_logs`;
CREATE TABLE IF NOT EXISTS `meal_logs` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `log_date` date NOT NULL,
  `meal_type` enum('breakfast','morning_snack','lunch','afternoon_snack','dinner','evening_snack') COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_meal_logs_user_date_type` (`user_id`,`log_date`,`meal_type`),
  KEY `idx_meal_logs_user_date` (`user_id`,`log_date`),
  KEY `idx_meal_logs_date` (`log_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `meal_log_items`
--

DROP TABLE IF EXISTS `meal_log_items`;
CREATE TABLE IF NOT EXISTS `meal_log_items` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `meal_log_id` bigint UNSIGNED NOT NULL,
  `food_id` bigint UNSIGNED NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT '1.00',
  `unit` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'g',
  `calculated_grams` decimal(10,2) NOT NULL DEFAULT '0.00',
  `calories` decimal(10,2) NOT NULL DEFAULT '0.00',
  `protein` decimal(10,2) NOT NULL DEFAULT '0.00',
  `carbs` decimal(10,2) NOT NULL DEFAULT '0.00',
  `fat` decimal(10,2) NOT NULL DEFAULT '0.00',
  `fiber` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_meal_log_items_log` (`meal_log_id`),
  KEY `idx_meal_log_items_food` (`food_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `meal_plans`
--

DROP TABLE IF EXISTS `meal_plans`;
CREATE TABLE IF NOT EXISTS `meal_plans` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `goal_type` enum('lose_weight','gain_weight','maintain_weight','gain_muscle') COLLATE utf8mb4_unicode_ci NOT NULL,
  `diet_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'normal',
  `total_calories` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_protein` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_carbs` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_fat` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_fiber` decimal(10,2) NOT NULL DEFAULT '0.00',
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_premium` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `fk_meal_plans_created_by` (`created_by`),
  KEY `idx_meal_plans_goal` (`goal_type`),
  KEY `idx_meal_plans_status` (`status`),
  KEY `idx_meal_plans_premium` (`is_premium`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `meal_plans`
--

INSERT INTO `meal_plans` (`id`, `name`, `slug`, `description`, `goal_type`, `diet_type`, `total_calories`, `total_protein`, `total_carbs`, `total_fat`, `total_fiber`, `image`, `is_premium`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Thực đơn giảm cân cơ bản', 'thuc-don-giam-can-co-ban', 'Thực đơn mẫu khoảng 1.500 calories, ưu tiên rau xanh và protein nạc.', 'lose_weight', 'normal', 1500.00, 110.00, 160.00, 45.00, 28.00, NULL, 0, 'active', 1, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(2, 'Thực đơn tăng cơ giàu Protein', 'thuc-don-tang-co-giau-protein', 'Thực đơn giàu protein dành cho người tập gym.', 'gain_muscle', 'high_protein', 2400.00, 180.00, 280.00, 65.00, 32.00, NULL, 0, 'active', 1, '2026-07-31 14:05:22', '2026-07-31 14:05:22');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `meal_plan_items`
--

DROP TABLE IF EXISTS `meal_plan_items`;
CREATE TABLE IF NOT EXISTS `meal_plan_items` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `meal_plan_meal_id` bigint UNSIGNED NOT NULL,
  `food_id` bigint UNSIGNED NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT '1.00',
  `unit` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'g',
  `calculated_grams` decimal(10,2) NOT NULL DEFAULT '0.00',
  `calories` decimal(10,2) NOT NULL DEFAULT '0.00',
  `protein` decimal(10,2) NOT NULL DEFAULT '0.00',
  `carbs` decimal(10,2) NOT NULL DEFAULT '0.00',
  `fat` decimal(10,2) NOT NULL DEFAULT '0.00',
  `fiber` decimal(10,2) NOT NULL DEFAULT '0.00',
  `sort_order` int UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_meal_plan_items_meal` (`meal_plan_meal_id`),
  KEY `idx_meal_plan_items_food` (`food_id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `meal_plan_items`
--

INSERT INTO `meal_plan_items` (`id`, `meal_plan_meal_id`, `food_id`, `quantity`, `unit`, `calculated_grams`, `calories`, `protein`, `carbs`, `fat`, `fiber`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 1, 14, 2.00, 'quả', 100.00, 156.00, 12.60, 1.20, 10.60, 0.00, 1, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(2, 1, 21, 1.00, 'quả', 150.00, 78.00, 0.45, 20.70, 0.30, 3.60, 2, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(3, 2, 2, 150.00, 'g', 150.00, 184.50, 4.05, 38.40, 1.50, 2.40, 1, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(4, 2, 8, 150.00, 'g', 150.00, 247.50, 46.50, 0.00, 5.40, 0.00, 2, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(5, 2, 17, 200.00, 'g', 200.00, 70.00, 4.00, 14.00, 0.60, 6.00, 3, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(6, 3, 15, 1.00, 'hộp', 100.00, 61.00, 3.50, 4.70, 3.30, 0.00, 1, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(7, 3, 20, 100.00, 'g', 100.00, 89.00, 1.10, 22.80, 0.30, 2.60, 2, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(8, 4, 11, 150.00, 'g', 150.00, 312.00, 30.00, 0.00, 19.50, 0.00, 1, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(9, 4, 19, 200.00, 'phần', 200.00, 120.00, 4.00, 18.00, 4.00, 6.00, 2, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(10, 5, 14, 3.00, 'quả', 150.00, 234.00, 18.90, 1.80, 15.90, 0.00, 1, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(11, 5, 18, 200.00, 'g', 200.00, 172.00, 3.20, 40.20, 0.20, 6.00, 2, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(12, 6, 1, 250.00, 'g', 250.00, 325.00, 6.75, 70.50, 0.75, 1.00, 1, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(13, 6, 8, 200.00, 'g', 200.00, 330.00, 62.00, 0.00, 7.20, 0.00, 2, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(14, 6, 17, 200.00, 'g', 200.00, 70.00, 4.00, 14.00, 0.60, 6.00, 3, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(15, 7, 25, 1.00, 'muỗng', 30.00, 120.00, 24.00, 3.00, 2.00, 0.00, 1, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(16, 7, 20, 150.00, 'g', 150.00, 133.50, 1.65, 34.20, 0.45, 3.90, 2, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(17, 8, 11, 200.00, 'g', 200.00, 416.00, 40.00, 0.00, 26.00, 0.00, 1, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(18, 8, 2, 200.00, 'g', 200.00, 246.00, 5.40, 51.20, 2.00, 3.20, 2, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(19, 8, 19, 200.00, 'phần', 200.00, 120.00, 4.00, 18.00, 4.00, 6.00, 3, '2026-07-31 14:05:22', '2026-07-31 14:05:22');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `meal_plan_meals`
--

DROP TABLE IF EXISTS `meal_plan_meals`;
CREATE TABLE IF NOT EXISTS `meal_plan_meals` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `meal_plan_id` bigint UNSIGNED NOT NULL,
  `meal_type` enum('breakfast','morning_snack','lunch','afternoon_snack','dinner','evening_snack') COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_meal_plan_meals_plan` (`meal_plan_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `meal_plan_meals`
--

INSERT INTO `meal_plan_meals` (`id`, `meal_plan_id`, `meal_type`, `title`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 1, 'breakfast', 'Bữa sáng', 1, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(2, 1, 'lunch', 'Bữa trưa', 2, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(3, 1, 'afternoon_snack', 'Bữa phụ', 3, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(4, 1, 'dinner', 'Bữa tối', 4, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(5, 2, 'breakfast', 'Bữa sáng', 1, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(6, 2, 'lunch', 'Bữa trưa', 2, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(7, 2, 'afternoon_snack', 'Bữa phụ', 3, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(8, 2, 'dinner', 'Bữa tối', 4, '2026-07-31 14:05:22', '2026-07-31 14:05:22');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `title` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('info','success','warning','danger') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'info',
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user_read` (`user_id`,`is_read`),
  KEY `idx_notifications_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_password_resets_email` (`email`),
  KEY `idx_password_resets_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `personal_notes`
--

DROP TABLE IF EXISTS `personal_notes`;
CREATE TABLE IF NOT EXISTS `personal_notes` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `note_date` date NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `mood` enum('very_bad','bad','normal','good','very_good') COLLATE utf8mb4_unicode_ci DEFAULT 'normal',
  `hunger_level` tinyint UNSIGNED DEFAULT NULL,
  `exercise_status` enum('none','light','moderate','hard') COLLATE utf8mb4_unicode_ci DEFAULT 'none',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_personal_notes_user_date` (`user_id`,`note_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `reminders`
--

DROP TABLE IF EXISTS `reminders`;
CREATE TABLE IF NOT EXISTS `reminders` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `reminder_type` enum('breakfast','lunch','dinner','snack','weight','water','custom') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'custom',
  `title` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reminder_time` time NOT NULL,
  `repeat_type` enum('once','daily','weekdays','weekly') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'daily',
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reminders_user_status` (`user_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `subscription_plans`
--

DROP TABLE IF EXISTS `subscription_plans`;
CREATE TABLE IF NOT EXISTS `subscription_plans` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `duration_days` int UNSIGNED NOT NULL DEFAULT '0',
  `features` longtext COLLATE utf8mb4_unicode_ci,
  `chatbot_limit_per_day` int UNSIGNED NOT NULL DEFAULT '5',
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `subscription_plans`
--

INSERT INTO `subscription_plans` (`id`, `name`, `code`, `description`, `price`, `duration_days`, `features`, `chatbot_limit_per_day`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Free', 'FREE', 'Gói miễn phí với các tính năng cơ bản', 0.00, 0, 'Quản lý bữa ăn cơ bản, theo dõi calories, cân nặng, dashboard cơ bản', 5, 'active', '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(2, 'Premium tháng', 'PREMIUM_MONTHLY', 'Gói Premium sử dụng trong 30 ngày', 99000.00, 30, 'Toàn bộ thực đơn, báo cáo nâng cao, chatbot hạn mức cao, gợi ý cá nhân hóa', 50, 'active', '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(3, 'Premium năm', 'PREMIUM_YEARLY', 'Gói Premium sử dụng trong 365 ngày', 999000.00, 365, 'Toàn bộ tính năng Premium với thời hạn một năm', 100, 'active', '2026-07-31 14:05:22', '2026-07-31 14:05:22');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `transactions`
--

DROP TABLE IF EXISTS `transactions`;
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `plan_id` bigint UNSIGNED NOT NULL,
  `transaction_code` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `payment_method` enum('visa','mastercard','ewallet','bank_transfer') COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_reference` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','success','failed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `expired_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transaction_code` (`transaction_code`),
  KEY `fk_transactions_plan` (`plan_id`),
  KEY `idx_transactions_user` (`user_id`),
  KEY `idx_transactions_status` (`status`),
  KEY `idx_transactions_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('user','admin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `status` enum('active','inactive','locked') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `email_verified_at` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_status` (`status`),
  KEY `idx_users_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `avatar`, `role`, `status`, `email_verified_at`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 'Quản trị viên', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.', NULL, 'admin', 'active', '2026-07-31 21:05:22', NULL, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(2, 'Nguyễn Văn Demo', 'user@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.', NULL, 'user', 'active', '2026-07-31 21:05:22', NULL, '2026-07-31 14:05:22', '2026-07-31 14:05:22');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `user_profiles`
--

DROP TABLE IF EXISTS `user_profiles`;
CREATE TABLE IF NOT EXISTS `user_profiles` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `age` tinyint UNSIGNED DEFAULT NULL,
  `gender` enum('male','female','other') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `height_cm` decimal(5,2) DEFAULT NULL,
  `current_weight_kg` decimal(6,2) DEFAULT NULL,
  `target_weight_kg` decimal(6,2) DEFAULT NULL,
  `activity_level` enum('sedentary','light','moderate','very_active','extra_active') COLLATE utf8mb4_unicode_ci DEFAULT 'sedentary',
  `health_goal` enum('lose_weight','gain_weight','maintain_weight','gain_muscle') COLLATE utf8mb4_unicode_ci DEFAULT 'maintain_weight',
  `goal_pace` enum('slow','moderate','fast') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'moderate',
  `diet_type` enum('normal','vegetarian','vegan','low_carb','low_sugar','gluten_free','high_protein') COLLATE utf8mb4_unicode_ci DEFAULT 'normal',
  `allergies` text COLLATE utf8mb4_unicode_ci,
  `disliked_foods` text COLLATE utf8mb4_unicode_ci,
  `meals_per_day` tinyint UNSIGNED NOT NULL DEFAULT '3',
  `bmr` decimal(8,2) DEFAULT NULL,
  `tdee` decimal(8,2) DEFAULT NULL,
  `bmi` decimal(5,2) DEFAULT NULL,
  `calorie_target` decimal(8,2) DEFAULT NULL,
  `protein_target` decimal(8,2) DEFAULT NULL,
  `carb_target` decimal(8,2) DEFAULT NULL,
  `fat_target` decimal(8,2) DEFAULT NULL,
  `fiber_target` decimal(8,2) DEFAULT '25.00',
  `water_target_ml` int UNSIGNED DEFAULT '2000',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `user_profiles`
--

INSERT INTO `user_profiles` (`id`, `user_id`, `date_of_birth`, `age`, `gender`, `height_cm`, `current_weight_kg`, `target_weight_kg`, `activity_level`, `health_goal`, `goal_pace`, `diet_type`, `allergies`, `disliked_foods`, `meals_per_day`, `bmr`, `tdee`, `bmi`, `calorie_target`, `protein_target`, `carb_target`, `fat_target`, `fiber_target`, `water_target_ml`, `created_at`, `updated_at`) VALUES
(1, 2, '2000-01-01', 26, 'male', 170.00, 70.00, 65.00, 'moderate', 'lose_weight', 'moderate', 'normal', NULL, NULL, 4, 1637.50, 2538.13, 24.22, 2038.13, 152.86, 203.81, 67.94, 30.00, 2000, '2026-07-31 14:05:22', '2026-07-31 14:05:22');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `user_subscriptions`
--

DROP TABLE IF EXISTS `user_subscriptions`;
CREATE TABLE IF NOT EXISTS `user_subscriptions` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `plan_id` bigint UNSIGNED NOT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime DEFAULT NULL,
  `status` enum('active','expired','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_user_subscriptions_plan` (`plan_id`),
  KEY `idx_user_subscriptions_user` (`user_id`),
  KEY `idx_user_subscriptions_status` (`status`),
  KEY `idx_user_subscriptions_end_date` (`end_date`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `user_subscriptions`
--

INSERT INTO `user_subscriptions` (`id`, `user_id`, `plan_id`, `start_date`, `end_date`, `status`, `created_at`, `updated_at`) VALUES
(1, 2, 1, '2026-07-31 21:05:22', NULL, 'active', '2026-07-31 14:05:22', '2026-07-31 14:05:22');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `weight_logs`
--

DROP TABLE IF EXISTS `weight_logs`;
CREATE TABLE IF NOT EXISTS `weight_logs` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `weight_kg` decimal(6,2) NOT NULL,
  `bmi` decimal(5,2) DEFAULT NULL,
  `log_date` date NOT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_weight_logs_user_date` (`user_id`,`log_date`),
  KEY `idx_weight_logs_user_date` (`user_id`,`log_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Ràng buộc đối với các bảng kết xuất
--

--
-- Ràng buộc cho bảng `chatbot_usage_logs`
--
ALTER TABLE `chatbot_usage_logs`
  ADD CONSTRAINT `fk_chatbot_usage_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ràng buộc cho bảng `chat_conversations`
--
ALTER TABLE `chat_conversations`
  ADD CONSTRAINT `fk_chat_conversations_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ràng buộc cho bảng `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD CONSTRAINT `fk_chat_messages_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `chat_conversations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ràng buộc cho bảng `favorite_meal_plans`
--
ALTER TABLE `favorite_meal_plans`
  ADD CONSTRAINT `fk_favorite_meal_plans_plan` FOREIGN KEY (`meal_plan_id`) REFERENCES `meal_plans` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_favorite_meal_plans_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ràng buộc cho bảng `foods`
--
ALTER TABLE `foods`
  ADD CONSTRAINT `fk_foods_category` FOREIGN KEY (`category_id`) REFERENCES `food_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_foods_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ràng buộc cho bảng `meal_logs`
--
ALTER TABLE `meal_logs`
  ADD CONSTRAINT `fk_meal_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ràng buộc cho bảng `meal_log_items`
--
ALTER TABLE `meal_log_items`
  ADD CONSTRAINT `fk_meal_log_items_food` FOREIGN KEY (`food_id`) REFERENCES `foods` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_meal_log_items_log` FOREIGN KEY (`meal_log_id`) REFERENCES `meal_logs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ràng buộc cho bảng `meal_plans`
--
ALTER TABLE `meal_plans`
  ADD CONSTRAINT `fk_meal_plans_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ràng buộc cho bảng `meal_plan_items`
--
ALTER TABLE `meal_plan_items`
  ADD CONSTRAINT `fk_meal_plan_items_food` FOREIGN KEY (`food_id`) REFERENCES `foods` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_meal_plan_items_meal` FOREIGN KEY (`meal_plan_meal_id`) REFERENCES `meal_plan_meals` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ràng buộc cho bảng `meal_plan_meals`
--
ALTER TABLE `meal_plan_meals`
  ADD CONSTRAINT `fk_meal_plan_meals_plan` FOREIGN KEY (`meal_plan_id`) REFERENCES `meal_plans` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ràng buộc cho bảng `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ràng buộc cho bảng `personal_notes`
--
ALTER TABLE `personal_notes`
  ADD CONSTRAINT `fk_personal_notes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ràng buộc cho bảng `reminders`
--
ALTER TABLE `reminders`
  ADD CONSTRAINT `fk_reminders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ràng buộc cho bảng `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `fk_transactions_plan` FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_transactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ràng buộc cho bảng `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD CONSTRAINT `fk_user_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ràng buộc cho bảng `user_subscriptions`
--
ALTER TABLE `user_subscriptions`
  ADD CONSTRAINT `fk_user_subscriptions_plan` FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_user_subscriptions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ràng buộc cho bảng `weight_logs`
--
ALTER TABLE `weight_logs`
  ADD CONSTRAINT `fk_weight_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
