<?php
$html = file_get_contents('http://localhost/web_QuanLyBuaAn/foods.php');
preg_match_all('/<img[^>]+src="([^"]+)"[^>]*alt="Whey Protein"/i', $html, $matches);
print_r($matches);
