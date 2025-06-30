<?php
// dashboard/profile.php
$pageTitle = 'Настройки профиля';
require_once 'header.php';

$message = '';
$error = '';

// Обработка обновления профиля
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        if (empty($username) || empty($email)) {
            $error = 'Заполните все обязательные поля';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Введите корректный email';
        } else {
            // Проверяем, не занят ли email
            $stmt = db()->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $currentUser['id']]);
            
            if ($stmt->fetch()) {
                $error = 'Этот email уже используется';
            } else {
                $stmt = db()->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
                $stmt->execute([$username, $email, $currentUser['id']]);
                $message = 'Профиль успешно обновлен';
                
                // Обновляем данные в сессии
                $_SESSION['user_name'] = $username;
                $_SESSION['user_email'] = $email;
                $currentUser['username'] = $username;
                $currentUser['email'] = $email;
            }
        }
    }
    
    if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = 'Заполните все поля для смены пароля';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Новые пароли не совпадают';
        } elseif (strlen($newPassword) < 6) {
            $error = 'Новый пароль должен быть не менее 6 символов';
        } else {
            // Проверяем текущий пароль
            if (!password_verify($currentPassword, $currentUser['password'])) {
                $error = 'Неверный текущий пароль';
            } else {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = db()->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashedPassword, $currentUser['id']]);
                $message = 'Пароль успешно изменен';
            }
        }
    }
}

// Получаем статистику аккаунта
$stmt = db()->prepare("
    SELECT 
        (SELECT COUNT(*) FROM campaigns WHERE user_id = ?) as campaigns_count,
        (SELECT COUNT(*) FROM ad_placements ap JOIN campaigns c ON ap.campaign_id = c.id WHERE c.user_id = ?) as placements_count,
        (SELECT COUNT(*) FROM user_sessions WHERE user_id = ? AND expires_at > NOW()) as active_sessions
");
$stmt->execute([$currentUser['id'], $currentUser['id'], $currentUser['id']]);
$accountStats = $stmt->fetch();

// Получаем информацию о подписке
$subscriptionInfo = null;
if ($currentUser['role'] === 'premium') {
    $stmt = db()->prepare("
        SELECT * FROM subscriptions 
        WHERE user_id = ? AND is_active = TRUE 
        ORDER BY start_date DESC LIMIT 1
    ");
    $stmt->execute([$currentUser['id']]);
    $subscriptionInfo = $stmt->fetch();
}
?>

<div class="profile-container">
    <!-- Заголовок страницы -->
    <div class="page-header">
        <div class="header-content">
            <h1 class="page-title">Настройки профиля</h1>
            <p class="page-subtitle">Управление аккаунтом и персональными данными</p>
        </div>
        <div class="header-avatar">
            <div class="avatar-circle">
                <?php echo mb_strtoupper(mb_substr($currentUser['username'], 0, 1)); ?>
            </div>
        </div>
    </div>

    <!-- Уведомления -->
    <?php if ($message): ?>
    <div class="alert alert-success">
        <div class="alert-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M9 12L11 14L15 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
            </svg>
        </div>
        <span><?php echo htmlspecialchars($message); ?></span>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-error">
        <div class="alert-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                <line x1="15" y1="9" x2="9" y2="15" stroke="currentColor" stroke-width="2"/>
                <line x1="9" y1="9" x2="15" y2="15" stroke="currentColor" stroke-width="2"/>
            </svg>
        </div>
        <span><?php echo htmlspecialchars($error); ?></span>
    </div>
    <?php endif; ?>

    <!-- Основной контент -->
    <div class="profile-grid">
        <!-- Основная информация -->
        <div class="profile-main">
            <!-- Информация об аккаунте -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M20 21V19C20 17.9391 19.5786 16.9217 18.8284 16.1716C18.0783 15.4214 17.0609 15 16 15H8C6.93913 15 5.92172 15.4214 5.17157 16.1716C4.42143 16.9217 4 17.9391 4 19V21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2"/>
                        </svg>
                    </div>
                    <h3 class="card-title">Информация об аккаунте</h3>
                </div>
                <div class="card-body">
                    <div class="account-info">
                        <div class="info-row">
                            <div class="info-label">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                Статус аккаунта
                            </div>
                            <div class="info-value">
                                <?php if ($currentUser['role'] === 'premium'): ?>
                                    <span class="status-badge premium">Premium</span>
                                <?php elseif ($currentUser['role'] === 'admin'): ?>
                                    <span class="status-badge admin">Администратор</span>
                                <?php else: ?>
                                    <span class="status-badge basic">Базовый</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2" stroke="currentColor" stroke-width="2"/>
                                    <line x1="16" y1="2" x2="16" y2="6" stroke="currentColor" stroke-width="2"/>
                                    <line x1="8" y1="2" x2="8" y2="6" stroke="currentColor" stroke-width="2"/>
                                    <line x1="3" y1="10" x2="21" y2="10" stroke="currentColor" stroke-width="2"/>
                                </svg>
                                Дата регистрации
                            </div>
                            <div class="info-value"><?php echo date('d.m.Y', strtotime($currentUser['created_at'])); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                                    <polyline points="12,6 12,12 16,14" stroke="currentColor" stroke-width="2"/>
                                </svg>
                                Последний вход
                            </div>
                            <div class="info-value">
                                <?php echo $currentUser['last_login'] ? date('d.m.Y H:i', strtotime($currentUser['last_login'])) : 'Нет данных'; ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M16 21V19C16 17.9391 15.5786 16.9217 14.8284 16.1716C14.0783 15.4214 13.0609 15 12 15H5C3.93913 15 2.92172 15.4214 1.17157 16.1716C0.421427 16.9217 0 17.9391 0 19V21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <circle cx="8.5" cy="7" r="4" stroke="currentColor" stroke-width="2"/>
                                    <line x1="20" y1="8" x2="20" y2="14" stroke="currentColor" stroke-width="2"/>
                                    <line x1="23" y1="11" x2="17" y2="11" stroke="currentColor" stroke-width="2"/>
                                </svg>
                                Количество входов
                            </div>
                            <div class="info-value"><?php echo $currentUser['login_count']; ?></div>
                        </div>
                        <?php if ($subscriptionInfo): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                Premium до
                            </div>
                            <div class="info-value"><?php echo date('d.m.Y', strtotime($subscriptionInfo['end_date'])); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Основные данные -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M17 3C17.5523 3 18 3.44772 18 4V20C18 20.5523 17.5523 21 17 21H7C6.44772 21 6 20.5523 6 20V4C6 3.44772 6.44772 3 7 3H17Z" stroke="currentColor" stroke-width="2"/>
                            <path d="M9 9H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            <path d="M9 13H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            <path d="M9 17H13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <h3 class="card-title">Основные данные</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="/dashboard/profile.php" class="modern-form">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-group">
                            <label for="username" class="form-label">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M20 21V19C20 17.9391 19.5786 16.9217 18.8284 16.1716C18.0783 15.4214 17.0609 15 16 15H8C6.93913 15 5.92172 15.4214 5.17157 16.1716C4.42143 16.9217 4 17.9391 4 19V21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2"/>
                                </svg>
                                Имя пользователя <span class="required">*</span>
                            </label>
                            <input type="text" id="username" name="username" class="form-input"
                                   value="<?php echo htmlspecialchars($currentUser['username']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email" class="form-label">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M4 4H20C21.1 4 22 4.9 22 6V18C22 19.1 21.1 20 20 20H4C2.9 20 2 19.1 2 18V6C2 4.9 2.9 4 4 4Z" stroke="currentColor" stroke-width="2"/>
                                    <polyline points="22,6 12,13 2,6" stroke="currentColor" stroke-width="2"/>
                                </svg>
                                Email <span class="required">*</span>
                            </label>
                            <input type="email" id="email" name="email" class="form-input"
                                   value="<?php echo htmlspecialchars($currentUser['email']); ?>" required>
                            <div class="form-hint">Используется для входа в систему</div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M19 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V5C3 4.46957 3.21071 3.96086 3.58579 3.58579C3.96086 3.21071 4.46957 3 5 3H16L21 8V19C21 19.5304 20.7893 20.0391 20.4142 20.4142C20.0391 20.7893 19.5304 21 19 21Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <polyline points="17,21 17,13 7,13 7,21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <polyline points="7,3 7,8 15,8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                Сохранить изменения
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Смена пароля -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" stroke="currentColor" stroke-width="2"/>
                            <circle cx="12" cy="16" r="1" stroke="currentColor" stroke-width="2"/>
                            <path d="M7 11V7C7 5.67392 7.52678 4.40215 8.46447 3.46447C9.40215 2.52678 10.6739 2 12 2C13.3261 2 14.5979 2.52678 15.5355 3.46447C16.4732 4.40215 17 5.67392 17 7V11" stroke="currentColor" stroke-width="2"/>
                        </svg>
                    </div>
                    <h3 class="card-title">Смена пароля</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="/dashboard/profile.php" class="modern-form">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group">
                            <label for="current_password" class="form-label">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2" stroke="currentColor" stroke-width="2"/>
                                    <circle cx="12" cy="16" r="1" stroke="currentColor" stroke-width="2"/>
                                    <path d="M7 11V7C7 5.67392 7.52678 4.40215 8.46447 3.46447C9.40215 2.52678 10.6739 2 12 2C13.3261 2 14.5979 2.52678 15.5355 3.46447C16.4732 4.40215 17 5.67392 17 7V11" stroke="currentColor" stroke-width="2"/>
                                </svg>
                                Текущий пароль
                            </label>
                            <input type="password" id="current_password" name="current_password" class="form-input">
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password" class="form-label">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2" stroke="currentColor" stroke-width="2"/>
                                    <circle cx="12" cy="16" r="1" stroke="currentColor" stroke-width="2"/>
                                    <path d="M7 11V7C7 5.67392 7.52678 4.40215 8.46447 3.46447C9.40215 2.52678 10.6739 2 12 2C13.3261 2 14.5979 2.52678 15.5355 3.46447C16.4732 4.40215 17 5.67392 17 7V11" stroke="currentColor" stroke-width="2"/>
                                </svg>
                                Новый пароль
                            </label>
                            <input type="password" id="new_password" name="new_password" class="form-input">
                            <div class="form-hint">Минимум 6 символов</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password" class="form-label">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M9 12L11 14L15 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                                </svg>
                                Подтвердите новый пароль
                            </label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-input">
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2" stroke="currentColor" stroke-width="2"/>
                                    <circle cx="12" cy="16" r="1" stroke="currentColor" stroke-width="2"/>
                                    <path d="M7 11V7C7 5.67392 7.52678 4.40215 8.46447 3.46447C9.40215 2.52678 10.6739 2 12 2C13.3261 2 14.5979 2.52678 15.5355 3.46447C16.4732 4.40215 17 5.67392 17 7V11" stroke="currentColor" stroke-width="2"/>
                                </svg>
                                Изменить пароль
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Боковая панель -->
        <div class="profile-sidebar">
            <!-- Статистика использования -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M3 3V21H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M9 9L12 6L16 10L20 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <h3 class="card-title">Статистика использования</h3>
                </div>
                <div class="card-body">
                    <div class="usage-stats">
                        <div class="stat-item">
                            <div class="stat-icon campaigns">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                    <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                    <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                </svg>
                            </div>
                            <div class="stat-info">
                                <div class="stat-value"><?php echo $accountStats['campaigns_count']; ?></div>
                                <div class="stat-label">Кампаний создано</div>
                            </div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon placements">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2" stroke="currentColor" stroke-width="2"/>
                                    <rect x="9" y="9" width="6" height="6" stroke="currentColor" stroke-width="2"/>
                                </svg>
                            </div>
                            <div class="stat-info">
                                <div class="stat-value"><?php echo $accountStats['placements_count']; ?></div>
                                <div class="stat-label">Размещений добавлено</div>
                            </div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon sessions">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 1L3 5V11C3 16.55 6.84 21.74 12 23C17.16 21.74 21 16.55 21 11V5L12 1Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                            <div class="stat-info">
                                <div class="stat-value"><?php echo $accountStats['active_sessions']; ?></div>
                                <div class="stat-label">Активных сессий</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if ($currentUser['role'] !== 'premium' && $currentUser['role'] !== 'admin'): ?>
            <!-- Upgrade to Premium -->
            <div class="card upgrade-card">
                <div class="card-body">
                    <div class="upgrade-content">
                        <div class="upgrade-icon">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <h4 class="upgrade-title">Обновитесь до Premium</h4>
                        <ul class="premium-benefits">
                            <li>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M9 12L11 14L15 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                                </svg>
                                Безлимитные размещения
                            </li>
                            <li>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M9 12L11 14L15 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                                </svg>
                                Расширенная аналитика
                            </li>
                            <li>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M9 12L11 14L15 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                                </svg>
                                Приоритетная поддержка
                            </li>
                            <li>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M9 12L11 14L15 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                                </svg>
                                Экспорт без ограничений
                            </li>
                            <li>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M9 12L11 14L15 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                                </svg>
                                API доступ
                            </li>
                        </ul>
                        <div class="price-tag">
                            <div class="price">299 ₽</div>
                            <div class="period">в месяц</div>
                        </div>
                        <a href="/pricing.php" class="btn btn-premium">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Перейти на Premium
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Опасная зона -->
            <div class="card danger-zone">
                <div class="card-header">
                    <div class="card-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M10.29 3.86L1.82 18C1.64 18.37 1.64 18.82 1.82 19.19C2 19.56 2.37 19.78 2.77 19.78H21.23C21.63 19.78 22 19.56 22.18 19.19C22.36 18.82 22.36 18.37 22.18 18L13.71 3.86C13.53 3.49 13.16 3.27 12.76 3.27C12.36 3.27 11.99 3.49 11.81 3.86H10.29Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <line x1="12" y1="9" x2="12" y2="13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <line x1="12" y1="17" x2="12.01" y2="17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <h3 class="card-title">Опасная зона</h3>
                </div>
                <div class="card-body">
                    <p class="danger-text">
                        Удаление аккаунта приведет к безвозвратной потере всех данных, включая кампании и статистику.
                    </p>
                    <button class="btn btn-danger" onclick="alert('Для удаления аккаунта обратитесь в поддержку')">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <polyline points="3,6 5,6 21,6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M19 6V20C19 20.5304 18.7893 21.0391 18.4142 21.4142C18.0391 21.7893 17.5304 22 17 22H7C6.46957 22 5.96086 21.7893 5.58579 21.4142C5.21071 21.0391 5 20.5304 5 20V6M8 6V4C8 3.46957 8.21071 2.96086 8.58579 2.58579C8.96086 2.21071 9.46957 2 10 2H14C14.5304 2 15.0391 2.21071 15.4142 2.58579C15.7893 2.96086 16 3.46957 16 4V6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Удалить аккаунт
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

:root {
    --primary: #2563eb;
    --primary-light: #3b82f6;
    --primary-dark: #1d4ed8;
    --success: #10b981;
    --warning: #f59e0b;
    --error: #ef4444;
    --text-primary: #0f172a;
    --text-secondary: #64748b;
    --text-tertiary: #94a3b8;
    --bg-primary: #ffffff;
    --bg-secondary: #f8fafc;
    --bg-tertiary: #f1f5f9;
    --border: #e2e8f0;
    --border-light: #f1f5f9;
    --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
    --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
    --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
    --radius-sm: 6px;
    --radius-md: 8px;
    --radius-lg: 12px;
    --radius-xl: 16px;
}

* {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
}

.profile-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 24px;
    background: var(--bg-secondary);
    min-height: 100vh;
}

/* Заголовок страницы */
.page-header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    border-radius: var(--radius-lg);
    padding: 32px 40px;
    margin-bottom: 24px;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header-content h1 {
    font-size: 28px;
    font-weight: 700;
    margin: 0 0 8px 0;
    letter-spacing: -0.025em;
}

.header-content p {
    font-size: 16px;
    opacity: 0.9;
    margin: 0;
}

.header-avatar {
    display: flex;
    align-items: center;
}

.avatar-circle {
    width: 64px;
    height: 64px;
    background: rgba(255, 255, 255, 0.2);
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    font-weight: 700;
    color: white;
}

/* Уведомления */
.alert {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 20px;
    border-radius: var(--radius-md);
    margin-bottom: 24px;
    font-size: 14px;
    font-weight: 500;
}

.alert-success {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    color: var(--success);
    border: 1px solid #6ee7b7;
}

.alert-error {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    color: var(--error);
    border: 1px solid #f87171;
}

.alert-icon {
    flex-shrink: 0;
}

/* Сетка профиля */
.profile-grid {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 24px;
    align-items: start;
}

/* Карточки */
.card {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    margin-bottom: 24px;
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.card:hover {
    box-shadow: var(--shadow-md);
}

.card-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--border-light);
    display: flex;
    align-items: center;
    gap: 12px;
    background: var(--bg-secondary);
}

.card-icon {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    flex-shrink: 0;
}

.card-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.card-body {
    padding: 24px;
}

/* Информация об аккаунте */
.account-info {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 0;
    border-bottom: 1px solid var(--border-light);
}

.info-row:last-child {
    border-bottom: none;
}

.info-label {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--text-secondary);
    font-size: 14px;
    font-weight: 500;
}

.info-value {
    font-weight: 600;
    font-size: 14px;
    color: var(--text-primary);
}

.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-badge.premium {
    background: linear-gradient(135deg, var(--warning), #ea580c);
    color: white;
}

.status-badge.admin {
    background: linear-gradient(135deg, var(--error), #dc2626);
    color: white;
}

.status-badge.basic {
    background: linear-gradient(135deg, var(--text-tertiary), var(--text-secondary));
    color: white;
}

/* Формы */
.modern-form {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
}

.required {
    color: var(--error);
}

.form-input {
    padding: 12px 16px;
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    font-size: 14px;
    transition: all 0.2s;
    background: var(--bg-primary);
}

.form-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.form-hint {
    font-size: 12px;
    color: var(--text-secondary);
}

.form-actions {
    margin-top: 8px;
}

/* Кнопки */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 24px;
    border: none;
    border-radius: var(--radius-md);
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    white-space: nowrap;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
}

.btn-primary:hover {
    background: linear-gradient(135deg, var(--primary-dark), var(--primary));
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.btn-premium {
    background: linear-gradient(135deg, var(--warning), #ea580c);
    color: white;
    width: 100%;
}

.btn-premium:hover {
    background: linear-gradient(135deg, #ea580c, #d97706);
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.btn-danger {
    background: var(--error);
    color: white;
    width: 100%;
}

.btn-danger:hover {
    background: #dc2626;
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

/* Статистика использования */
.usage-stats {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 16px;
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: var(--radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.stat-icon.campaigns {
    background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
    color: var(--primary);
}

.stat-icon.placements {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    color: var(--success);
}

.stat-icon.sessions {
    background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
    color: #8b5cf6;
}

.stat-info {
    flex: 1;
}

.stat-value {
    font-size: 24px;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 2px;
}

.stat-label {
    font-size: 13px;
    color: var(--text-secondary);
    font-weight: 500;
}

/* Upgrade карточка */
.upgrade-card {
    background: linear-gradient(135deg, #fef7ed 0%, #fed7aa 100%);
    border: 1px solid #fdba74;
}

.upgrade-content {
    text-align: center;
}

.upgrade-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 20px;
    background: rgba(251, 146, 60, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ea580c;
}

.upgrade-title {
    font-size: 18px;
    font-weight: 700;
    color: #ea580c;
    margin: 0 0 20px 0;
}

.premium-benefits {
    list-style: none;
    margin: 0 0 24px 0;
    padding: 0;
    text-align: left;
}

.premium-benefits li {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 0;
    font-size: 14px;
    color: #9a3412;
    font-weight: 500;
}

.premium-benefits svg {
    color: var(--success);
    flex-shrink: 0;
}

.price-tag {
    text-align: center;
    margin-bottom: 20px;
}

.price {
    font-size: 32px;
    font-weight: 700;
    color: #ea580c;
}

.period {
    font-size: 14px;
    color: #9a3412;
    font-weight: 500;
}

/* Опасная зона */
.danger-zone {
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.danger-zone .card-header {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    border-bottom-color: rgba(239, 68, 68, 0.2);
}

.danger-zone .card-icon {
    background: linear-gradient(135deg, var(--error), #dc2626);
}

.danger-zone .card-title {
    color: var(--error);
}

.danger-text {
    font-size: 14px;
    color: var(--text-secondary);
    margin-bottom: 16px;
    line-height: 1.5;
}

/* Адаптивность */
@media (max-width: 1024px) {
    .profile-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .profile-container {
        padding: 16px;
    }
    
    .page-header {
        padding: 24px;
        flex-direction: column;
        text-align: center;
        gap: 20px;
    }
    
    .header-content h1 {
        font-size: 24px;
    }
    
    .card-body {
        padding: 20px;
    }
    
    .card-header {
        padding: 16px 20px;
    }
}

@media (max-width: 480px) {
    .avatar-circle {
        width: 48px;
        height: 48px;
        font-size: 18px;
    }
    
    .info-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
        padding: 12px 0;
    }
    
    .stat-item {
        flex-direction: column;
        text-align: center;
        gap: 12px;
    }
}
</style>

<?php require_once 'footer.php'; ?>
