CREATE TABLE IF NOT EXISTS `user_smart_menus` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `menu_data` LONGTEXT NOT NULL,
    `completed_days` VARCHAR(255) DEFAULT '[]',
    `status` ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
