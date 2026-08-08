<?php
require 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
print_r($conn->query("DESCRIBE chat_conversations")->fetchAll(PDO::FETCH_ASSOC));
print_r($conn->query("DESCRIBE chat_messages")->fetchAll(PDO::FETCH_ASSOC));
