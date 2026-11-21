<?php
require 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
print_r($conn->query("DESCRIBE contact_messages")->fetchAll(PDO::FETCH_ASSOC));
