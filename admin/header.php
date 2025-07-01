<?php
/**
 * Admin header — защищённый вход
 * Требует подключение includes/auth.php, но работает безопасно даже если функции не найдены.
 */
ob_start();

// --- Загрузка авторизации --------------------------------------------------
require_once __DIR__ . '/../includes/auth.php';

// Запускаем сессию, если она ещё не запущена
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Проверяем логин и права, если функции определены
if (function_exists('requireAuth')) {
    requireAuth();      // пользователь должен быть авторизован
}
if (function_exists('requireAdmin')) {
    requireAdmin();     // пользователь должен иметь роль admin
}

// Получаем текущего пользователя, если доступно
$currentUser = function_exists('getCurrentUser') ? getCurrentUser() : null;

// --- Заголовок страницы -----------------------------------------------------
$pageTitle = $pageTitle ?? 'Админ‑панель';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> | TeleAdm</title>

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <!-- Общий стиль админ‑панели -->
  <link rel="stylesheet" href="/assets/css/admin-dashboard.css?v=1">

  <!-- (Опционально) Ваш кастомный CSS -->
  <?php
    $customCss = $_SERVER['DOCUMENT_ROOT'] . '/assets/css/admin-custom.css';
    if (file_exists($customCss)) {
        $ver = filemtime($customCss);
        echo "<link rel=\"stylesheet\" href=\"/assets/css/admin-custom.css?v={$ver}\">";
    }
  ?>
</head>
<body class="bg-light">

<?php
// --- Навбар админ‑панели -----------------------------------------------------
$navbar = __DIR__ . '/navbar.php';
if (file_exists($navbar)) {
    include $navbar;
}
?>

<main class="py-4 container-fluid">
