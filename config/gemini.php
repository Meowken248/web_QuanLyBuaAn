<?php
// config/gemini.php

// API key Gemini do người dùng cung cấp
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');

// URL endpoint của Gemini API
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent');

