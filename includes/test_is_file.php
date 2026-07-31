<?php
define('BASE_URL', 'http://localhost/web_QuanLyBuaAn');
$image = '/uploads/foods/1785511671_6a6cbef743cff.jpg';
$fallback = BASE_URL . '/img/bg1.jpg';
$image = str_replace('\\', '/', trim($image));
$relative = '/' . ltrim($image, '/');
$full_path = dirname(__DIR__) . str_replace('/', DIRECTORY_SEPARATOR, $relative);
echo "Relative: $relative\n";
echo "Full path: $full_path\n";
echo "is_file: " . (is_file($full_path) ? 'YES' : 'NO') . "\n";
