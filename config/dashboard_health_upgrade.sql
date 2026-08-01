-- Nâng cấp dashboard sức khỏe theo giờ
-- Import tệp này vào cơ sở dữ liệu meal_health_manager hiện có.
USE `meal_health_manager`;

CREATE TABLE IF NOT EXISTS `health_hourly_logs` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `log_date` date NOT NULL,
  `log_hour` tinyint UNSIGNED NOT NULL,
  `water_ml` int UNSIGNED NOT NULL DEFAULT 0,
  `steps` int UNSIGNED NOT NULL DEFAULT 0,
  `active_minutes` tinyint UNSIGNED NOT NULL DEFAULT 0,
  `calories_burned` decimal(8,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `heart_rate` smallint UNSIGNED DEFAULT NULL,
  `sleep_minutes` tinyint UNSIGNED NOT NULL DEFAULT 0,
  `mood_level` tinyint UNSIGNED DEFAULT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_health_hourly_user_date_hour` (`user_id`, `log_date`, `log_hour`),
  KEY `idx_health_hourly_user_date` (`user_id`, `log_date`),
  CONSTRAINT `fk_health_hourly_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_health_hourly_hour` CHECK (`log_hour` BETWEEN 0 AND 23),
  CONSTRAINT `chk_health_hourly_active` CHECK (`active_minutes` BETWEEN 0 AND 60),
  CONSTRAINT `chk_health_hourly_sleep` CHECK (`sleep_minutes` BETWEEN 0 AND 60),
  CONSTRAINT `chk_health_hourly_mood` CHECK (`mood_level` IS NULL OR `mood_level` BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ghi lại thời điểm ăn thực tế cho dữ liệu mới.
SET @has_consumed_at = (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'meal_logs'
    AND column_name = 'consumed_at'
);
SET @add_consumed_at_sql = IF(
  @has_consumed_at = 0,
  'ALTER TABLE `meal_logs` ADD COLUMN `consumed_at` time DEFAULT NULL AFTER `meal_type`',
  'SELECT 1'
);
PREPARE add_consumed_at_stmt FROM @add_consumed_at_sql;
EXECUTE add_consumed_at_stmt;
DEALLOCATE PREPARE add_consumed_at_stmt;

UPDATE `meal_logs`
SET `consumed_at` = CASE `meal_type`
  WHEN 'breakfast' THEN '07:00:00'
  WHEN 'morning_snack' THEN '10:00:00'
  WHEN 'lunch' THEN '12:00:00'
  WHEN 'afternoon_snack' THEN '15:00:00'
  WHEN 'dinner' THEN '19:00:00'
  WHEN 'evening_snack' THEN '21:00:00'
  ELSE '12:00:00'
END
WHERE `consumed_at` IS NULL;
