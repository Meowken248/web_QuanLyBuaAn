<?php
require 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("DESCRIBE notifications");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
$stmt = $conn->query("DESCRIBE reminders");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
