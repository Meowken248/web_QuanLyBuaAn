<?php
require 'config/database.php';
$db = new Database();
print_r($db->getConnection()->query("SELECT id, name, image FROM foods")->fetchAll(PDO::FETCH_ASSOC));
