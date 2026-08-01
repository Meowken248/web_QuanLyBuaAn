<?php
// config/gemini.php

// API key Gemini do người dùng cung cấp
// Hãy dán API Key của bạn vào thay cho chuỗi trống bên dưới.
// Lấy API Key tại: https://aistudio.google.com/app/apikey
define('GEMINI_API_KEY', 'AQ.Ab8RN6IMewkhO6W3uyLjdA1pz9O93vzsXXVv2Xl6zX4v4d7W4w');

// Model ổn định, phản hồi nhanh và hiện được API key này hỗ trợ.
define('GEMINI_MODEL', 'gemini-3.1-flash-lite');
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent');
