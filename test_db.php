<?php
require 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("DESCRIBE subscription_plans");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($columns);
