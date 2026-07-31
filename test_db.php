<?php
require 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
try {
    $conn->query("ALTER TABLE reminders ADD COLUMN last_triggered_date DATE NULL AFTER status");
    echo "Thanh cong";
} catch (Exception $e) {
    echo "Loi: " . $e->getMessage();
}
