<?php
// logout.php
require_once 'includes/auth.php';

// Выполняем выход
logoutUser();

// Перенаправляем на главную
header('Location: /');
exit;
?>