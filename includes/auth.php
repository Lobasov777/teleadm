<?php
// includes/auth.php

require_once 'db.php';

// Проверка авторизации
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Проверка роли админа
function isAdmin() {
    return isLoggedIn() && $_SESSION['user_role'] === 'admin';
}

// Проверка Premium
function isPremium() {
    return isLoggedIn() && ($_SESSION['user_role'] === 'premium' || $_SESSION['user_role'] === 'admin');
}

// Получение данных текущего пользователя
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $stmt = db()->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

// Регистрация пользователя
function registerUser($email, $password, $username) {
    // Проверяем, существует ли пользователь
    $stmt = db()->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->fetch()) {
        return ['success' => false, 'error' => 'Пользователь с таким email уже существует'];
    }
    
    // Хешируем пароль
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    try {
        // Создаем пользователя
        $stmt = db()->prepare("
            INSERT INTO users (email, password, username, role) 
            VALUES (?, ?, ?, 'user')
        ");
        $stmt->execute([$email, $hashedPassword, $username]);
        
        $userId = db()->lastInsertId();
        
        // Создаем бесплатную подписку
        $stmt = db()->prepare("
            INSERT INTO subscriptions (user_id, type, start_date, is_active) 
            VALUES (?, 'free', CURDATE(), TRUE)
        ");
        $stmt->execute([$userId]);
        
        return ['success' => true, 'user_id' => $userId];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Ошибка при регистрации'];
    }
}

// Авторизация пользователя
function loginUser($email, $password) {
    $stmt = db()->prepare("
        SELECT id, email, username, password, role, is_blocked 
        FROM users 
        WHERE email = ?
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return ['success' => false, 'error' => 'Неверный email или пароль'];
    }
    
    if ($user['is_blocked']) {
        return ['success' => false, 'error' => 'Ваш аккаунт заблокирован'];
    }
    
    if (!password_verify($password, $user['password'])) {
        return ['success' => false, 'error' => 'Неверный email или пароль'];
    }
    
    // Создаем сессию
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['username'];
    $_SESSION['user_role'] = $user['role'];
    
    // Обновляем данные последнего входа
    $stmt = db()->prepare("
        UPDATE users 
        SET last_login = NOW(), login_count = login_count + 1 
        WHERE id = ?
    ");
    $stmt->execute([$user['id']]);
    
    // Создаем токен сессии
    $sessionToken = bin2hex(random_bytes(32));
    $stmt = db()->prepare("
        INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, expires_at) 
        VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))
    ");
    $stmt->execute([
        $user['id'], 
        $sessionToken, 
        $_SERVER['REMOTE_ADDR'] ?? '', 
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
    
    $_SESSION['session_token'] = $sessionToken;
    
    return ['success' => true];
}

// Выход из системы
function logoutUser() {
    if (isset($_SESSION['session_token'])) {
        $stmt = db()->prepare("DELETE FROM user_sessions WHERE session_token = ?");
        $stmt->execute([$_SESSION['session_token']]);
    }
    
    session_destroy();
    session_start();
}

// Проверка доступа (для защищенных страниц)
function requireAuth() {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}

// Проверка доступа админа
function requireAdmin() {
    if (!isAdmin()) {
        header('Location: /dashboard/');
        exit;
    }
}