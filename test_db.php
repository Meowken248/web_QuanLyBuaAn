<?php
require 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

$tables = [
    'weight_logs', 
    'personal_notes', 
    'chat_conversations', 
    'chat_messages',
    'meal_plans',
    'meal_plan_meals',
    'meal_plan_items',
    'favorite_meal_plans'
];

foreach ($tables as $t) {
    echo "--- $t ---\n";
    $stmt = $conn->query("DESCRIBE $t");
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        echo $col['Field'] . " : " . $col['Type'] . "\n";
    }
}
