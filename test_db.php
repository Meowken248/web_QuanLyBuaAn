<?php
require 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("DESCRIBE foods");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($columns);
