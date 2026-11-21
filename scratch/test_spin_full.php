<?php
// scratch/test_spin_full.php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

session_start();
$_SESSION['user_id'] = 1;

// Simulate spin request
$_SERVER['REQUEST_METHOD'] = 'POST';
$input = [
    'meal_type' => 'lunch',
    'health_goal' => 'eat_clean'
];

ob_start();
// simulate php://input via custom stream or direct require with php input override
$jsonInput = json_encode($input);

// Test spin logic directly or via curl/require
$db = new Database();
$conn = $db->getConnection();

$stmt = $conn->query("SELECT id, name, calories, protein, carbs, fat FROM foods WHERE status = 'active' LIMIT 3");
$foods = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Available foods for spin: " . count($foods) . "\n";
print_r($foods[0]);
