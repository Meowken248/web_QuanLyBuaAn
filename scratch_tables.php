<?php
require 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$tables = $conn->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
echo "Tables:\n" . implode("\n", $tables) . "\n";
