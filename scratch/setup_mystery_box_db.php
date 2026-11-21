<?php
require_once __DIR__ . '/../config/database.php';
$db = new Database();
$conn = $db->getConnection();

echo "1. Checking / Adding `estimated_price` to `foods` table...\n";
$cols = $conn->query("SHOW COLUMNS FROM foods LIKE 'estimated_price'")->fetchAll();
if (empty($cols)) {
    $conn->exec("ALTER TABLE foods ADD COLUMN estimated_price INT DEFAULT 35000 AFTER goals");
    echo "-> Column `estimated_price` added.\n";
} else {
    echo "-> Column `estimated_price` already exists.\n";
}

echo "2. Populating realistic estimated prices for existing foods...\n";
// Set realistic Vietnamese dish prices based on keywords:
// Premium / seafood / beef / salmon: 55k - 85k
// Standard meals / chicken / pork / bun / pho: 35k - 45k
// Light meals / breakfast / snacks / eggs / tofu / smoothies: 20k - 30k
$foods = $conn->query("SELECT id, name, category_id, calories, protein FROM foods")->fetchAll(PDO::FETCH_ASSOC);
$stmtUpdate = $conn->prepare("UPDATE foods SET estimated_price = :price WHERE id = :id");

foreach ($foods as $f) {
    $nameLower = mb_strtolower($f['name'], 'UTF-8');
    $price = 35000; // default

    if (str_contains($nameLower, 'hồi') || str_contains($nameLower, 'bò') || str_contains($nameLower, 'hải sản') || str_contains($nameLower, 'tôm') || str_contains($nameLower, 'cá thu') || str_contains($nameLower, 'lúc lắc') || str_contains($nameLower, 'bít tết')) {
        $price = 65000;
    } elseif (str_contains($nameLower, 'phở') || str_contains($nameLower, 'bún chả') || str_contains($nameLower, 'cơm tấm') || str_contains($nameLower, 'cơm gà') || str_contains($nameLower, 'bún riêu') || str_contains($nameLower, 'cơm sườn') || str_contains($nameLower, 'cá kho')) {
        $price = 45000;
    } elseif (str_contains($nameLower, 'bánh mì') || str_contains($nameLower, 'xôi') || str_contains($nameLower, 'trứng') || str_contains($nameLower, 'đậu phụ') || str_contains($nameLower, 'đậu hũ') || str_contains($nameLower, 'canh rau') || str_contains($nameLower, 'cháo')) {
        $price = 25000;
    } elseif (str_contains($nameLower, 'sinh tố') || str_contains($nameLower, 'nước ép') || str_contains($nameLower, 'sữa chua') || str_contains($nameLower, 'chè') || str_contains($nameLower, 'chuối')) {
        $price = 20000;
    } elseif (str_contains($nameLower, 'salad')) {
        $price = 40000;
    }

    $stmtUpdate->execute([':price' => $price, ':id' => $f['id']]);
}
echo "-> Updated estimated prices for " . count($foods) . " foods.\n";

echo "3. Creating `mystery_box_history` table...\n";
$sqlTable = "CREATE TABLE IF NOT EXISTS `mystery_box_history` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NULL,
    `session_id` VARCHAR(100) NULL,
    `food_id` BIGINT UNSIGNED NOT NULL,
    `meal_type` VARCHAR(50) NOT NULL,
    `health_goal` VARCHAR(50) NOT NULL,
    `budget` VARCHAR(50) NULL,
    `spin_date` DATE NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_user_date` (`user_id`, `spin_date`),
    KEY `idx_session_date` (`session_id`, `spin_date`),
    CONSTRAINT `fk_mystery_box_food` FOREIGN KEY (`food_id`) REFERENCES `foods`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
$conn->exec($sqlTable);
echo "-> Table `mystery_box_history` is ready.\n";

echo "Database setup completed successfully!\n";
