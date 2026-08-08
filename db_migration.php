<?php
require_once __DIR__ . '/config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Check if column exists
    $stmt = $conn->query("SHOW COLUMNS FROM foods LIKE 'goals'");
    if ($stmt->rowCount() == 0) {
        $conn->exec("ALTER TABLE foods ADD COLUMN goals VARCHAR(255) NULL AFTER season");
        echo "Column 'goals' added successfully.\n";
    } else {
        echo "Column 'goals' already exists.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
