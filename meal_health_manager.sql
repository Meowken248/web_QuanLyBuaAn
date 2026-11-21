-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1:3306
-- Thời gian đã tạo: Th9 17, 2026 lúc 03:53 AM
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
-- Cấu trúc bảng cho bảng `ai_generated_meal_plans`
--

DROP TABLE IF EXISTS `ai_generated_meal_plans`;
CREATE TABLE IF NOT EXISTS `ai_generated_meal_plans` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `source_type` enum('theo_mua','theo_muc_tieu','theo_nguyen_lieu') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `goal_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `input_params` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Mùa hoặc danh sách nguyên liệu người dùng nhập, tuỳ nguồn',
  `days` tinyint UNSIGNED NOT NULL DEFAULT '1',
  `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'JSON chứa danh sách món/thực đơn từng ngày',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ai_generated_meal_plans_user` (`user_id`),
  KEY `idx_ai_generated_meal_plans_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `title` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Cuộc trò chuyện mới',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_chat_conversations_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `chat_messages`
--

DROP TABLE IF EXISTS `chat_messages`;
CREATE TABLE IF NOT EXISTS `chat_messages` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint UNSIGNED NOT NULL,
  `sender` enum('user','assistant','system') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokens_used` int UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_chat_messages_conversation` (`conversation_id`),
  KEY `idx_chat_messages_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=75 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `classes`
--

DROP TABLE IF EXISTS `classes`;
CREATE TABLE IF NOT EXISTS `classes` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `class_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `class_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `stt` int UNSIGNED DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_classes_code` (`class_code`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `classes`
--

INSERT INTO `classes` (`id`, `class_code`, `class_name`, `stt`, `created_at`, `updated_at`) VALUES
(1, 'CD24TT1', 'KIểm thử phầm mềm', 1, '2026-08-22 19:10:05', '2026-08-22 19:10:05'),
(3, 'CD24TT2', 'LỚP 2', 2, '2026-08-22 19:40:15', '2026-08-22 19:40:15'),
(4, 'CD24TT3', 'Kiểm Thử', 3, '2026-08-22 19:40:44', '2026-08-22 19:40:44');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `contact_messages`
--

DROP TABLE IF EXISTS `contact_messages`;
CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('new','read','replied') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_contact_messages_status` (`status`),
  KEY `idx_contact_messages_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `foods`
--

DROP TABLE IF EXISTS `foods`;
CREATE TABLE IF NOT EXISTS `foods` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` bigint UNSIGNED DEFAULT NULL,
  `name` varchar(180) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ingredients` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `instructions` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `serving_size` decimal(8,2) NOT NULL DEFAULT '100.00',
  `serving_unit` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'g',
  `calories` decimal(8,2) NOT NULL DEFAULT '0.00',
  `protein` decimal(8,2) NOT NULL DEFAULT '0.00',
  `carbs` decimal(8,2) NOT NULL DEFAULT '0.00',
  `fat` decimal(8,2) NOT NULL DEFAULT '0.00',
  `fiber` decimal(8,2) NOT NULL DEFAULT '0.00',
  `sugar` decimal(8,2) NOT NULL DEFAULT '0.00',
  `sodium` decimal(10,2) NOT NULL DEFAULT '0.00',
  `diet_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'normal',
  `season` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Mùa phù hợp: xuan,he,thu,dong (phân tách bằng dấu phẩy)',
  `goals` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_premium` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('active','inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `fk_foods_created_by` (`created_by`),
  KEY `idx_foods_name` (`name`),
  KEY `idx_foods_category` (`category_id`),
  KEY `idx_foods_status` (`status`),
  KEY `idx_foods_premium` (`is_premium`),
  KEY `idx_foods_season` (`season`)
) ENGINE=InnoDB AUTO_INCREMENT=56 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `food_categories`
--

DROP TABLE IF EXISTS `food_categories`;
CREATE TABLE IF NOT EXISTS `food_categories` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('active','inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_food_categories_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `food_goals`
--

DROP TABLE IF EXISTS `food_goals`;
CREATE TABLE IF NOT EXISTS `food_goals` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `food_id` bigint UNSIGNED NOT NULL,
  `goal_type` enum('lose_weight','gain_weight','maintain_weight','gain_muscle','cooling','warming','vegetarian') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'cooling = thanh mát, warming = giữ ấm',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_food_goal` (`food_id`,`goal_type`),
  KEY `idx_food_goals_goal` (`goal_type`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `food_ingredients`
--

DROP TABLE IF EXISTS `food_ingredients`;
CREATE TABLE IF NOT EXISTS `food_ingredients` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `food_id` bigint UNSIGNED NOT NULL,
  `ingredient_id` bigint UNSIGNED NOT NULL,
  `quantity` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Số lượng gợi ý, ví dụ: 200g, 2 quả',
  `is_optional` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Nguyên liệu có thể bỏ qua/thay thế mà vẫn ra món',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_food_ingredient` (`food_id`,`ingredient_id`),
  KEY `idx_food_ingredients_ingredient` (`ingredient_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `health_hourly_logs`
--

DROP TABLE IF EXISTS `health_hourly_logs`;
CREATE TABLE IF NOT EXISTS `health_hourly_logs` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `log_date` date NOT NULL,
  `log_hour` tinyint UNSIGNED NOT NULL,
  `water_ml` int UNSIGNED NOT NULL DEFAULT '0',
  `steps` int UNSIGNED NOT NULL DEFAULT '0',
  `active_minutes` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `calories_burned` decimal(8,2) UNSIGNED NOT NULL DEFAULT '0.00',
  `heart_rate` smallint UNSIGNED DEFAULT NULL,
  `sleep_minutes` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `mood_level` tinyint UNSIGNED DEFAULT NULL,
  `note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_health_hourly_user_date_hour` (`user_id`,`log_date`,`log_hour`),
  KEY `idx_health_hourly_user_date` (`user_id`,`log_date`)
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `ingredients`
--

DROP TABLE IF EXISTS `ingredients`;
CREATE TABLE IF NOT EXISTS `ingredients` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_ingredients_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `meal_logs`
--

DROP TABLE IF EXISTS `meal_logs`;
CREATE TABLE IF NOT EXISTS `meal_logs` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `log_date` date NOT NULL,
  `meal_type` enum('breakfast','morning_snack','lunch','afternoon_snack','dinner','evening_snack') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `consumed_at` time DEFAULT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_meal_logs_user_date_type` (`user_id`,`log_date`,`meal_type`),
  KEY `idx_meal_logs_user_date` (`user_id`,`log_date`),
  KEY `idx_meal_logs_date` (`log_date`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `unit` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'g',
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
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `meal_plans`
--

DROP TABLE IF EXISTS `meal_plans`;
CREATE TABLE IF NOT EXISTS `meal_plans` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(180) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `goal_type` enum('lose_weight','gain_weight','maintain_weight','gain_muscle') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `diet_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'normal',
  `total_calories` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_protein` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_carbs` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_fat` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_fiber` decimal(10,2) NOT NULL DEFAULT '0.00',
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_premium` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('active','inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
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
  `unit` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'g',
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

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `meal_plan_meals`
--

DROP TABLE IF EXISTS `meal_plan_meals`;
CREATE TABLE IF NOT EXISTS `meal_plan_meals` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `meal_plan_id` bigint UNSIGNED NOT NULL,
  `meal_type` enum('breakfast','morning_snack','lunch','afternoon_snack','dinner','evening_snack') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(180) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `time_frame` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_meal_plan_meals_plan` (`meal_plan_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `title` varchar(180) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('info','success','warning','danger') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'info',
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user_read` (`user_id`,`is_read`),
  KEY `idx_notifications_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mood` enum('very_bad','bad','normal','good','very_good') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'normal',
  `hunger_level` tinyint UNSIGNED DEFAULT NULL,
  `exercise_status` enum('none','light','moderate','hard') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'none',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_personal_notes_user_date` (`user_id`,`note_date`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `reminders`
--

DROP TABLE IF EXISTS `reminders`;
CREATE TABLE IF NOT EXISTS `reminders` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `reminder_type` enum('breakfast','lunch','dinner','snack','weight','water','custom') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'custom',
  `title` varchar(180) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reminder_time` time NOT NULL,
  `repeat_type` enum('once','daily','weekdays','weekly') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'daily',
  `status` enum('active','inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `last_triggered_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reminders_user_status` (`user_id`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `subscription_plans`
--

DROP TABLE IF EXISTS `subscription_plans`;
CREATE TABLE IF NOT EXISTS `subscription_plans` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `duration_days` int UNSIGNED NOT NULL DEFAULT '0',
  `features` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `chatbot_limit_per_day` int UNSIGNED NOT NULL DEFAULT '5',
  `status` enum('active','inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `support_chats`
--

DROP TABLE IF EXISTS `support_chats`;
CREATE TABLE IF NOT EXISTS `support_chats` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `status` enum('open','closed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'open',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `support_chats`
--

INSERT INTO `support_chats` (`id`, `user_id`, `status`, `created_at`, `updated_at`) VALUES
(2, 1, 'open', '2026-08-22 19:16:07', '2026-08-22 19:16:07');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `support_messages`
--

DROP TABLE IF EXISTS `support_messages`;
CREATE TABLE IF NOT EXISTS `support_messages` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `chat_id` bigint UNSIGNED NOT NULL,
  `sender_type` enum('user','admin') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `chat_id` (`chat_id`)
) ENGINE=MyISAM AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `support_messages`
--

INSERT INTO `support_messages` (`id`, `chat_id`, `sender_type`, `message`, `is_read`, `created_at`) VALUES
(16, 2, 'admin', 'dvdd', 0, '2026-08-22 19:16:07');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `transactions`
--

DROP TABLE IF EXISTS `transactions`;
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `plan_id` bigint UNSIGNED NOT NULL,
  `transaction_code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `payment_method` enum('visa','mastercard','ewallet','bank_transfer') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_reference` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','success','failed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `message` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `avatar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mssv` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `class_id` bigint UNSIGNED DEFAULT NULL,
  `stt` int UNSIGNED DEFAULT '0',
  `role` enum('user','admin') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `status` enum('active','inactive','locked') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `email_verified_at` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `uk_users_mssv` (`mssv`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_status` (`status`),
  KEY `idx_users_created_at` (`created_at`),
  KEY `fk_users_class` (`class_id`)
) ENGINE=InnoDB AUTO_INCREMENT=109 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `avatar`, `mssv`, `birth_date`, `class_id`, `stt`, `role`, `status`, `email_verified_at`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 'Quản trị viên', 'admin@example.com', '$2y$10$gjTFrTiQs9/Cwxho/CnHjecqD/cdrM7XfVU62md6F2ZKEMwSG3cYu', NULL, NULL, NULL, NULL, 0, 'admin', 'active', '2026-07-31 21:05:22', NULL, '2026-07-31 14:05:22', '2026-07-31 17:18:49'),
(2, 'Nguyễn Văn Demo', 'user@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.', NULL, NULL, NULL, NULL, 0, 'user', 'active', '2026-07-31 21:05:22', NULL, '2026-07-31 14:05:22', '2026-07-31 14:05:22'),
(3, 'Anh Tú Huỳnh', 'anh2482006@gmail.com', '$2y$10$a1vL9jNPpLTESFYvmjNWAe./bLNeHATLlyYhnGh.tztXltmJ8wOcK', NULL, NULL, NULL, NULL, 0, 'user', 'active', NULL, NULL, '2026-07-31 14:17:35', '2026-07-31 15:10:28'),
(14, 'Tú Huỳnh', 'tuh225095@gmail.com', '$2y$10$rC6T5zLtsH3Ms0587773zOCmAU7AHOnIk/cclvs..GxzjETTG1.am', NULL, NULL, NULL, NULL, 0, 'user', 'active', NULL, NULL, '2026-08-01 07:44:09', '2026-08-01 07:44:09'),
(59, 'Nguyễn Quốc Bảo', '24211TT1126@mail.tdc.edu.vn', '$2y$10$u6CVstMknO1VogSpkC.sFOO27D0.meEXFqUr.gqqOYO3ZGnkfPSLi', NULL, '24211TT1126', '2006-10-18', 3, 17, 'user', 'active', NULL, NULL, '2026-08-22 19:47:00', '2026-08-22 20:37:45'),
(60, 'Đỗ Khánh An', '24211TT1127@mail.tdc.edu.vn', '$2y$10$zWrfLUWj7LWkQUUH0A9rK.JyuK0mk257QIBzY/GQ2dDEWNufW5YAK', NULL, '24211TT1127', '2006-10-19', 3, 18, 'user', 'active', NULL, NULL, '2026-08-22 19:47:00', '2026-08-22 20:37:46'),
(61, 'Phan Tuấn Kiệt', '24211TT1128@mail.tdc.edu.vn', '$2y$10$gs9Y5MRCKGpfHN6IckPE/OZ5C6tupKldacFdYKox5werd6fkLmxpe', NULL, '24211TT1128', '2006-10-20', 3, 19, 'user', 'active', NULL, NULL, '2026-08-22 19:47:00', '2026-08-22 20:37:46'),
(62, 'Hồ Ngọc Trâm', '24211TT1129@mail.tdc.edu.vn', '$2y$10$t5q8S.QyXSB1vtvlRv4Rv.WI3NPN4m7JYvpEPPtcJxjHPX5RQkgqG', NULL, '24211TT1129', '2006-10-21', 3, 20, 'user', 'active', NULL, NULL, '2026-08-22 19:47:00', '2026-08-22 20:37:46'),
(85, 'Nguyễn Minh Anh', '24211TT1111@mail.tdc.edu.vn', '$2y$10$g8vFUeiWQfcIs9KyuW3v0OUotJi8v1oBxCm/Toyezop3FPKyusTJG', NULL, '24211TT1111', '2006-10-03', 3, 1, 'user', 'active', NULL, NULL, '2026-08-22 20:37:44', '2026-08-22 20:38:56'),
(86, 'Trần Hoàng Nam', '24211TT1112@mail.tdc.edu.vn', '$2y$10$blhi6M735ugbZWLFtKqC8.KM7CLrOOjoJfjhe2XGtKpRjLeNvshQ6', NULL, '24211TT1112', '2006-10-04', 3, 2, 'user', 'active', NULL, NULL, '2026-08-22 20:37:44', '2026-08-22 20:38:56'),
(87, 'Lê Thị Ngọc Hân', '24211TT1113@mail.tdc.edu.vn', '$2y$10$3gycVt.OFVDg8yqCis8yG.30CQO.bgfCWfcKj.3Jl6yIS8K0l3S7G', NULL, '24211TT1113', '2006-10-05', 3, 3, 'user', 'active', NULL, NULL, '2026-08-22 20:37:44', '2026-08-22 20:38:56'),
(88, 'Phạm Gia Huy', '24211TT1114@mail.tdc.edu.vn', '$2y$10$CFwCuzgASaK95n4DcpSnb.EIpuiLggr/YxIHK4ybYutVOF0OqSbym', NULL, '24211TT1114', '2006-10-06', 3, 4, 'user', 'active', NULL, NULL, '2026-08-22 20:37:44', '2026-08-22 20:38:56'),
(89, 'Võ Khánh Linh', '24211TT1115@mail.tdc.edu.vn', '$2y$10$N9AfznZal8NBB/VS8ET9mOFNvuScBPlVeaunuQa/17ZPstzpu36Ii', NULL, '24211TT1115', '2006-10-07', 3, 5, 'user', 'active', NULL, NULL, '2026-08-22 20:37:44', '2026-08-22 20:38:56'),
(90, 'Đặng Minh Khang', '24211TT1116@mail.tdc.edu.vn', '$2y$10$N0SvseKDRoYpDF47CFB3suGi4DWOZHZm08aJw25wkMK7lpb9aOKUC', NULL, '24211TT1116', '2006-10-08', 3, 6, 'user', 'active', NULL, NULL, '2026-08-22 20:37:44', '2026-08-22 20:37:44'),
(91, 'Huỳnh Thảo Vy', '24211TT1117@mail.tdc.edu.vn', '$2y$10$EZgdrL87VwK4K57JuRlf0Oy6wOhXmxUGR1VgQstSnKLg4o.19Qrvu', NULL, '24211TT1117', '2006-10-09', 3, 7, 'user', 'active', NULL, NULL, '2026-08-22 20:37:45', '2026-08-22 20:37:45'),
(92, 'Bùi Quang Huy', '24211TT1118@mail.tdc.edu.vn', '$2y$10$8bnG2.up7gYzi3b9t.X6Tu.2ykpsrnwjCG4zYUDg07sSMYjyh64ki', NULL, '24211TT1118', '2006-10-10', 3, 8, 'user', 'active', NULL, NULL, '2026-08-22 20:37:45', '2026-08-22 20:37:45'),
(93, 'Nguyễn Phương Thảo', '24211TT1119@mail.tdc.edu.vn', '$2y$10$3GBK2Q8dVjaq3XdC/lKJ1ul669uGcQsU9tLaq6uas4TJx/XNgfOGG', NULL, '24211TT1119', '2006-10-11', 3, 9, 'user', 'active', NULL, NULL, '2026-08-22 20:37:45', '2026-08-22 20:37:45'),
(94, 'Trần Đức Anh', '24211TT1120@mail.tdc.edu.vn', '$2y$10$e0z8UL2egMo46znVKO8s1ecKx32PVNdGVGvPwlEURog4mgUkqZQOm', NULL, '24211TT1120', '2006-10-12', 3, 10, 'user', 'active', NULL, NULL, '2026-08-22 20:37:45', '2026-08-22 20:37:45'),
(95, 'Nguyễn Thanh Tùng', '24211TT1121@mail.tdc.edu.vn', '$2y$10$bpIZ8lfjXvSeMjJD2d59j.kl8B7dSS5HZ102KQH7cwiUpPFKLrZja', NULL, '24211TT1121', '2006-10-13', 3, 11, 'user', 'active', NULL, NULL, '2026-08-22 20:37:45', '2026-08-22 20:37:45'),
(96, 'Lê Hoàng Anh', '24211TT1122@mail.tdc.edu.vn', '$2y$10$KZGZtGPR8DYI/iKhyS4RheKvjGMHETp3dAIzESOaEe55GlsZrTy.W', NULL, '24211TT1122', '2006-10-14', 3, 12, 'user', 'active', NULL, NULL, '2026-08-22 20:37:45', '2026-08-22 20:37:45'),
(97, 'Phạm Ngọc Mai', '24211TT1123@mail.tdc.edu.vn', '$2y$10$AC8yW41sDmUbvX9431cZTODQ6xngLeb2iFIw8GCXB83frpUFFyWqS', NULL, '24211TT1123', '2006-10-15', 3, 13, 'user', 'active', NULL, NULL, '2026-08-22 20:37:45', '2026-08-22 20:37:45'),
(98, 'Trần Minh Khôi', '24211TT1124@mail.tdc.edu.vn', '$2y$10$15Too5SdaM5w9UH2LzQ3ze/2Pn8eI2CBcsNKx2Xn/wECgaXw/ImHa', NULL, '24211TT1124', '2006-10-16', 3, 14, 'user', 'active', NULL, NULL, '2026-08-22 20:37:45', '2026-08-22 20:37:45'),
(99, 'Võ Thùy Dương', '24211TT1125@mail.tdc.edu.vn', '$2y$10$VET0Mp1PORTcfbkCJko3qeGEr0ZoODRyf0KZynMKXMDgB8zaEKGR6', NULL, '24211TT1125', '2006-10-17', 3, 15, 'user', 'active', NULL, NULL, '2026-08-22 20:37:45', '2026-08-22 20:37:45');

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
  `gender` enum('male','female','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `height_cm` decimal(5,2) DEFAULT NULL,
  `current_weight_kg` decimal(6,2) DEFAULT NULL,
  `target_weight_kg` decimal(6,2) DEFAULT NULL,
  `activity_level` enum('sedentary','light','moderate','very_active','extra_active') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'sedentary',
  `health_goal` enum('lose_weight','gain_weight','maintain_weight','gain_muscle') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'maintain_weight',
  `goal_pace` enum('slow','moderate','fast') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'moderate',
  `diet_type` enum('normal','vegetarian','vegan','low_carb','low_sugar','gluten_free','high_protein') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'normal',
  `allergies` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `disliked_foods` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `user_smart_menus`
--

DROP TABLE IF EXISTS `user_smart_menus`;
CREATE TABLE IF NOT EXISTS `user_smart_menus` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `menu_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `completed_days` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '[]',
  `status` enum('active','completed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `status` enum('active','expired','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_user_subscriptions_plan` (`plan_id`),
  KEY `idx_user_subscriptions_user` (`user_id`),
  KEY `idx_user_subscriptions_status` (`status`),
  KEY `idx_user_subscriptions_end_date` (`end_date`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_weight_logs_user_date` (`user_id`,`log_date`),
  KEY `idx_weight_logs_user_date` (`user_id`,`log_date`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Ràng buộc đối với các bảng kết xuất
--

--
-- Ràng buộc cho bảng `ai_generated_meal_plans`
--
ALTER TABLE `ai_generated_meal_plans`
  ADD CONSTRAINT `fk_ai_generated_meal_plans_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

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
-- Ràng buộc cho bảng `food_goals`
--
ALTER TABLE `food_goals`
  ADD CONSTRAINT `fk_food_goals_food` FOREIGN KEY (`food_id`) REFERENCES `foods` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ràng buộc cho bảng `food_ingredients`
--
ALTER TABLE `food_ingredients`
  ADD CONSTRAINT `fk_food_ingredients_food` FOREIGN KEY (`food_id`) REFERENCES `foods` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_food_ingredients_ingredient` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ràng buộc cho bảng `health_hourly_logs`
--
ALTER TABLE `health_hourly_logs`
  ADD CONSTRAINT `fk_health_hourly_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ràng buộc cho bảng `meal_logs`
--
ALTER TABLE `meal_logs`
  ADD CONSTRAINT `fk_meal_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ràng buộc cho bảng `meal_log_items`
--
ALTER TABLE `meal_log_items`
  ADD CONSTRAINT `fk_meal_log_items_food` FOREIGN KEY (`food_id`) REFERENCES `foods` (`id`) ON UPDATE CASCADE,
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
  ADD CONSTRAINT `fk_meal_plan_items_food` FOREIGN KEY (`food_id`) REFERENCES `foods` (`id`) ON UPDATE CASCADE,
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
  ADD CONSTRAINT `fk_transactions_plan` FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_transactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ràng buộc cho bảng `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ràng buộc cho bảng `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD CONSTRAINT `fk_user_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ràng buộc cho bảng `user_smart_menus`
--
ALTER TABLE `user_smart_menus`
  ADD CONSTRAINT `user_smart_menus_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ràng buộc cho bảng `user_subscriptions`
--
ALTER TABLE `user_subscriptions`
  ADD CONSTRAINT `fk_user_subscriptions_plan` FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`id`) ON UPDATE CASCADE,
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
