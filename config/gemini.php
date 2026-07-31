<?php
// config/gemini.php

// API key Gemini do người dùng cung cấp
// Hãy dán API Key của bạn vào thay cho chuỗi trống bên dưới.
// Lấy API Key tại: https://aistudio.google.com/app/apikey
define('GEMINI_API_KEY', 'AQ.Ab8RN6J0f8zW9DTWyqKJw7Z4j81AZWfWTKS4UZw-DhRogNk-XA');

// gemini-pro đã bị Google ngừng hỗ trợ trên API v1beta.
// gemini-2.5-flash hỗ trợ generateContent và phù hợp cho chatbot tương tác.
define('GEMINI_MODEL', 'gemini-2.5-flash');
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent');

