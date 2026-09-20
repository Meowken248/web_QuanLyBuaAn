<?php
// user/meal-logs.php - Alias redirect to user/meals.php
require_once __DIR__ . '/../config/app.php';

$queryString = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: ' . BASE_URL . '/user/meals.php' . $queryString, true, 301);
exit;
