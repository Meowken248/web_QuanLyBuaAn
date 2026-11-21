<?php
// scratch/test_render_mystery.php
$_SESSION['user_id'] = 1;
$_SESSION['user_name'] = 'Test User';
ob_start();
try {
    include __DIR__ . '/../mystery-box.php';
    $output = ob_get_clean();
    echo "RENDER SUCCESS!\n";
    echo "Length: " . strlen($output) . "\n";
    echo "Contains 'MỞ HỘP GỢI Ý': " . (str_contains($output, 'MỞ HỘP GỢI Ý') ? 'YES' : 'NO') . "\n";
    echo "Contains 'lucky_mystery_box.jpg': " . (str_contains($output, 'lucky_mystery_box.jpg') ? 'YES' : 'NO') . "\n";
    echo "Contains 'banner_healthy_food.jpg': " . (str_contains($output, 'banner_healthy_food.jpg') ? 'YES' : 'NO') . "\n";
} catch (Throwable $e) {
    ob_end_clean();
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString();
}
