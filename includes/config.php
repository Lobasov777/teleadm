<?php
// includes/config.php

// Настройки базы данных
define('DB_HOST', 'localhost');
define('DB_NAME', 'cu92880_teleadm');
define('DB_USER', 'cu92880_teleadm');
define('DB_PASS', '12Hybrex12!');

// Настройки сайта
define('SITE_NAME', 'TeleAdm');
define('SITE_URL', 'https://cu92880.tw1.ru'); // Измените на ваш домен
define('SITE_EMAIL', 'info@teleadm.ru');

// Настройки безопасности
define('SECURE_AUTH_KEY', 'your-random-secret-key-' . md5(DB_PASS)); // Генерируем уникальный ключ
define('SESSION_LIFETIME', 86400); // 24 часа

// Временная зона
date_default_timezone_set('Europe/Moscow');

// Режим отладки (отключите на продакшене)
define('DEBUG_MODE', true);

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Запуск сессии
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}