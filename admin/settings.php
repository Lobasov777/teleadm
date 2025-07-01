<?php
$pageTitle = 'Настройки системы';
require_once 'header.php';

$message = '';
$error = '';

// Обработка сохранения настроек
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    try {
        $settings = [
            'premium_price' => max(1, min(99999, (int)($_POST['premium_price'] ?? 299))),
            'premium_duration_days' => max(1, min(365, (int)($_POST['premium_duration_days'] ?? 30))),
            'free_campaigns_limit' => max(1, min(100, (int)($_POST['free_campaigns_limit'] ?? 1))),
            'free_placements_limit' => max(1, min(1000, (int)($_POST['free_placements_limit'] ?? 50))),
            'maintenance_mode' => isset($_POST['maintenance_mode']) ? 'true' : 'false',
            'registration_enabled' => isset($_POST['registration_enabled']) ? 'true' : 'false',
            'auto_cleanup_days' => max(7, min(365, (int)($_POST['auto_cleanup_days'] ?? 30))),
            'admin_email' => filter_var(trim($_POST['admin_email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: '',
            'support_email' => filter_var(trim($_POST['support_email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: ''
        ];
        
        // Сохраняем настройки
        foreach ($settings as $key => $value) {
            $stmt = db()->prepare("
                INSERT INTO system_settings (setting_key, setting_value, setting_type, updated_at) 
                VALUES (?, ?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
            ");
            $type = is_numeric($value) ? 'number' : (in_array($value, ['true', 'false']) ? 'boolean' : 'string');
            $stmt->execute([$key, $value, $type]);
        }
        
        // Логируем действие
        $stmt = db()->prepare("
            INSERT INTO admin_logs (admin_id, action, target_type, ip_address, created_at) 
            VALUES (?, 'update_settings', 'system', ?, NOW())
        ");
        $stmt->execute([$currentUser['id'], $_SERVER['REMOTE_ADDR'] ?? '']);
        
        $message = 'Настройки успешно сохранены';
        
    } catch (Exception $e) {
        error_log("Ошибка сохранения настроек: " . $e->getMessage());
        $error = 'Ошибка при сохранении настроек. Попробуйте еще раз.';
    }
}

// Обработка специальных действий
if (isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'clear_logs':
                $stmt = db()->query("DELETE FROM admin_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
                $deletedRows = $stmt->rowCount();
                $message = "Удалено записей логов: $deletedRows";
                break;
                
            case 'clear_sessions':
                $stmt = db()->query("DELETE FROM user_sessions WHERE expires_at < NOW()");
                $deletedSessions = $stmt->rowCount();
                $message = "Удалено истекших сессий: $deletedSessions";
                break;
                
            case 'optimize_db':
                // Простая оптимизация - удаление старых данных
                $queries = [
                    "DELETE FROM admin_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)",
                    "DELETE FROM user_sessions WHERE expires_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
                ];
                
                $totalOptimized = 0;
                foreach ($queries as $query) {
                    $stmt = db()->query($query);
                    $totalOptimized += $stmt->rowCount();
                }
                
                $message = "База данных оптимизирована. Удалено записей: $totalOptimized";
                break;
        }
        
        // Логируем административное действие
        if (!empty($message)) {
            $stmt = db()->prepare("
                INSERT INTO admin_logs (admin_id, action, target_type, ip_address, created_at) 
                VALUES (?, ?, 'system', ?, NOW())
            ");
            $stmt->execute([$currentUser['id'], $_POST['action'], $_SERVER['REMOTE_ADDR'] ?? '']);
        }
        
    } catch (Exception $e) {
        error_log("Ошибка административного действия: " . $e->getMessage());
        $error = 'Ошибка при выполнении операции. Попробуйте еще раз.';
    }
}

// Получаем текущие настройки
try {
    $stmt = db()->query("SELECT setting_key, setting_value FROM system_settings");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    error_log("Ошибка загрузки настроек: " . $e->getMessage());
    $settings = [];
}

// Функция для получения значения настройки
function getSetting($key, $default = '') {
    global $settings;
    return $settings[$key] ?? $default;
}

// Статистика системы
try {
    $stmt = db()->query("
        SELECT 
            (SELECT COUNT(*) FROM users WHERE role != 'admin') as total_users,
            (SELECT COUNT(*) FROM users WHERE role = 'premium') as premium_users,
            (SELECT COUNT(*) FROM campaigns) as total_campaigns,
            (SELECT COUNT(*) FROM ad_placements) as total_placements,
            (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed') as total_revenue,
            (SELECT COUNT(*) FROM admin_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as logs_24h,
            (SELECT COUNT(*) FROM user_sessions WHERE expires_at > NOW()) as active_sessions
    ");
    $systemStats = $stmt->fetch();
} catch (Exception $e) {
    error_log("Ошибка загрузки статистики: " . $e->getMessage());
    $systemStats = [
        'total_users' => 0, 'premium_users' => 0, 'total_campaigns' => 0,
        'total_placements' => 0, 'total_revenue' => 0, 'logs_24h' => 0, 'active_sessions' => 0
    ];
}

// Статистика подписок
try {
    $stmt = db()->query("
        SELECT 
            COUNT(*) as active_subscriptions,
            MIN(end_date) as next_expiry,
            COUNT(CASE WHEN end_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as expiring_soon
        FROM subscriptions 
        WHERE is_active = TRUE AND type = 'premium' AND end_date > CURDATE()
    ");
    $subscriptionStats = $stmt->fetch();
} catch (Exception $e) {
    error_log("Ошибка загрузки статистики подписок: " . $e->getMessage());
    $subscriptionStats = [
        'active_subscriptions' => 0, 'next_expiry' => null, 'expiring_soon' => 0
    ];
}

// Проверки системы
$systemChecks = [
    'php_version' => version_compare(PHP_VERSION, '7.4.0', '>='),
    'pdo_mysql' => extension_loaded('pdo_mysql'),
    'memory_limit' => (int)str_replace('M', '', ini_get('memory_limit')) >= 128,
    'max_execution_time' => (int)ini_get('max_execution_time') >= 30
];
?>

<style>
    /* Основные стили страницы */
    .page-container {
        padding: 32px;
        max-width: 1400px;
        margin: 0 auto;
    }

    .settings-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 32px;
    }

    /* Сообщения */
    .alert {
        padding: 16px 20px;
        border-radius: 12px;
        margin-bottom: 24px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 12px;
        animation: slideIn 0.3s ease;
    }

    .alert-success {
        background: rgba(34, 197, 94, 0.1);
        color: #16a34a;
        border: 1px solid rgba(34, 197, 94, 0.2);
    }

    .alert-error {
        background: rgba(239, 68, 68, 0.1);
        color: #dc2626;
        border: 1px solid rgba(239, 68, 68, 0.2);
    }

    @keyframes slideIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Секции настроек */
    .settings-section {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 28px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        margin-bottom: 24px;
        transition: all 0.3s ease;
        opacity: 0;
        transform: translateY(20px);
    }

    .settings-section.animate-in {
        opacity: 1;
        transform: translateY(0);
    }

    .settings-section:hover {
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    }

    .section-title {
        font-size: 20px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 24px;
        padding-bottom: 16px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        gap: 12px;
        letter-spacing: -0.025em;
    }

    .section-icon {
        font-size: 24px;
    }

    /* Формы */
    .settings-form {
        display: flex;
        flex-direction: column;
        gap: 24px;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .form-group.full-width {
        grid-column: 1 / -1;
    }

    .form-label {
        font-size: 14px;
        font-weight: 600;
        color: #374151;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .form-help {
        font-size: 12px;
        color: #64748b;
        margin-top: 4px;
        line-height: 1.4;
    }

    .form-input, .form-select {
        padding: 12px 16px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 14px;
        background: white;
        transition: all 0.2s ease;
        font-family: inherit;
    }

    .form-input:focus, .form-select:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        transform: translateY(-1px);
    }

    /* Чекбоксы */
    .checkbox-group {
        display: flex;
        align-items: flex-start;
        gap: 16px;
        padding: 16px 20px;
        background: #f8fafc;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        transition: all 0.2s ease;
    }

    .checkbox-group:hover {
        border-color: #3b82f6;
        background: #f1f5f9;
    }

    .checkbox {
        width: 20px;
        height: 20px;
        accent-color: #3b82f6;
        flex-shrink: 0;
        margin-top: 2px;
    }

    .checkbox-content {
        flex: 1;
        min-width: 0;
    }

    .checkbox-label {
        font-size: 15px;
        color: #0f172a;
        cursor: pointer;
        font-weight: 600;
        margin-bottom: 4px;
        display: block;
    }

    .checkbox-description {
        font-size: 13px;
        color: #64748b;
        line-height: 1.4;
    }

    /* Кнопки */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 12px 24px;
        border: none;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .btn-primary {
        background: linear-gradient(135deg, #3b82f6, #1e40af);
        color: white;
        box-shadow: 0 2px 4px rgba(59, 130, 246, 0.3);
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #1e40af, #1e3a8a);
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(59, 130, 246, 0.4);
    }

    .btn-secondary {
        background: white;
        color: #64748b;
        border: 1px solid #d1d5db;
    }

    .btn-secondary:hover {
        border-color: #9ca3af;
        color: #374151;
        text-decoration: none;
    }

    .btn-danger {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
        box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3);
    }

    .btn-danger:hover {
        background: linear-gradient(135deg, #dc2626, #b91c1c);
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(239, 68, 68, 0.4);
    }

    .btn-small {
        padding: 8px 16px;
        font-size: 12px;
    }

    .save-button {
        align-self: flex-start;
        margin-top: 8px;
    }

    /* Опасная зона */
    .danger-zone {
        border-color: #ef4444;
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.02), rgba(239, 68, 68, 0.01));
    }

    .danger-zone .section-title {
        color: #dc2626;
    }

    .action-buttons {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    /* Правая колонка */
    .system-overview {
        display: grid;
        gap: 24px;
    }

    .overview-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        opacity: 0;
        transform: translateY(20px);
    }

    .overview-card.animate-in {
        opacity: 1;
        transform: translateY(0);
    }

    .overview-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    }

    .overview-title {
        font-size: 18px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        letter-spacing: -0.025em;
    }

    .overview-stat {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid #f1f5f9;
    }

    .overview-stat:last-child {
        border-bottom: none;
    }

    .stat-label {
        font-size: 14px;
        color: #64748b;
        font-weight: 500;
    }

    .stat-value {
        font-weight: 700;
        color: #0f172a;
        font-size: 15px;
    }

    .stat-value.success {
        color: #10b981;
    }

    .stat-value.warning {
        color: #f59e0b;
    }

    .stat-value.error {
        color: #ef4444;
    }

    /* Проверки системы */
    .system-check {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid #f1f5f9;
    }

    .system-check:last-child {
        border-bottom: none;
    }

    .check-status {
        font-weight: 700;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .check-status.ok {
        color: #10b981;
    }

    .check-status.fail {
        color: #ef4444;
    }

    /* Анимации */
    .settings-section:nth-child(1) { transition-delay: 0.1s; }
    .settings-section:nth-child(2) { transition-delay: 0.2s; }
    .settings-section:nth-child(3) { transition-delay: 0.3s; }
    .settings-section:nth-child(4) { transition-delay: 0.4s; }

    .overview-card:nth-child(1) { transition-delay: 0.1s; }
    .overview-card:nth-child(2) { transition-delay: 0.2s; }
    .overview-card:nth-child(3) { transition-delay: 0.3s; }
    .overview-card:nth-child(4) { transition-delay: 0.4s; }

    /* Responsive */
    @media (max-width: 1024px) {
        .settings-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .page-container {
            padding: 20px;
        }
        
        .form-row {
            grid-template-columns: 1fr;
        }
        
        .checkbox-group {
            flex-direction: column;
            gap: 12px;
        }
    }
</style>

<div class="page-container">
    <!-- Сообщения -->
    <?php if ($message): ?>
        <div class="alert alert-success">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                <path d="M9 12L11 14L15 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
            </svg>
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                <line x1="15" y1="9" x2="9" y2="15" stroke="currentColor" stroke-width="2"/>
                <line x1="9" y1="9" x2="15" y2="15" stroke="currentColor" stroke-width="2"/>
            </svg>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="settings-grid">
        <!-- Основные настройки -->
        <div class="settings-main">
            <form method="POST" class="settings-form">
                <!-- Общие настройки -->
                <div class="settings-section">
                    <h3 class="section-title">
                        <svg class="section-icon" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
                            <path d="M12 1V3" stroke="currentColor" stroke-width="2"/>
                            <path d="M12 21V23" stroke="currentColor" stroke-width="2"/>
                            <path d="M4.22 4.22L5.64 5.64" stroke="currentColor" stroke-width="2"/>
                            <path d="M18.36 18.36L19.78 19.78" stroke="currentColor" stroke-width="2"/>
                            <path d="M1 12H3" stroke="currentColor" stroke-width="2"/>
                            <path d="M21 12H23" stroke="currentColor" stroke-width="2"/>
                            <path d="M4.22 19.78L5.64 18.36" stroke="currentColor" stroke-width="2"/>
                            <path d="M18.36 5.64L19.78 4.22" stroke="currentColor" stroke-width="2"/>
                        </svg>
                        Основные настройки
                    </h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                    <path d="M21 15C21 15.5304 20.7893 16.0391 20.4142 16.4142C20.0391 16.7893 19.5304 17 19 17H7L3 21V5C3 4.46957 3.21071 3.96086 3.58579 3.58579C3.96086 3.21071 4.46957 3 5 3H19C19.5304 3 20.0391 3.21071 20.4142 3.58579C20.7893 3.96086 21 4.46957 21 5V15Z" stroke="currentColor" stroke-width="2"/>
                                </svg>
                                Email администратора
                            </label>
                            <input type="email" name="admin_email" class="form-input" 
                                   value="<?php echo htmlspecialchars(getSetting('admin_email')); ?>" 
                                   placeholder="admin@example.com">
                            <div class="form-help">Для получения уведомлений о работе системы</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                    <path d="M21 15C21 15.5304 20.7893 16.0391 20.4142 16.4142C20.0391 16.7893 19.5304 17 19 17H7L3 21V5C3 4.46957 3.21071 3.96086 3.58579 3.58579C3.96086 3.21071 4.46957 3 5 3H19C19.5304 3 20.0391 3.21071 20.4142 3.58579C20.7893 3.96086 21 4.46957 21 5V15Z" stroke="currentColor" stroke-width="2"/>
                                </svg>
                                Email поддержки
                            </label>
                            <input type="email" name="support_email" class="form-input" 
                                   value="<?php echo htmlspecialchars(getSetting('support_email')); ?>" 
                                   placeholder="support@example.com">
                            <div class="form-help">Отображается пользователям для обратной связи</div>
                        </div>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" name="registration_enabled" class="checkbox" id="registration_enabled"
                               <?php echo getSetting('registration_enabled', 'true') === 'true' ? 'checked' : ''; ?>>
                        <div class="checkbox-content">
                            <label for="registration_enabled" class="checkbox-label">Разрешить регистрацию новых пользователей</label>
                            <div class="checkbox-description">Если отключено, новые пользователи не смогут создавать аккаунты</div>
                        </div>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" name="maintenance_mode" class="checkbox" id="maintenance_mode"
                               <?php echo getSetting('maintenance_mode', 'false') === 'true' ? 'checked' : ''; ?>>
                        <div class="checkbox-content">
                            <label for="maintenance_mode" class="checkbox-label">Режим обслуживания</label>
                            <div class="checkbox-description">Сайт будет недоступен для обычных пользователей (администраторы смогут войти)</div>
                        </div>
                    </div>
                </div>

                <!-- Настройки тарифов -->
                <div class="settings-section">
                    <h3 class="section-title">
                        <svg class="section-icon" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" stroke="currentColor" stroke-width="2"/>
                        </svg>
                        Настройки тарифов
                    </h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                    <line x1="12" y1="1" x2="12" y2="23" stroke="currentColor" stroke-width="2"/>
                                    <path d="M17 5H9.5C8.57174 5 7.6815 5.36875 7.02513 6.02513C6.36875 6.6815 6 7.57174 6 8.5C6 9.42826 6.36875 10.3185 7.02513 10.9749C7.6815 11.6313 8.57174 12 9.5 12H14.5C15.4283 12 16.3185 12.3687 16.9749 13.0251C17.6313 13.6815 18 14.5717 18 15.5C18 16.4283 17.6313 17.3185 16.9749 17.9749C16.3185 18.6313 15.4283 19 14.5 19H6" stroke="currentColor" stroke-width="2"/>
                                </svg>
                                Цена Premium (₽)
                            </label>
                            <input type="number" name="premium_price" class="form-input" min="1" max="99999"
                                   value="<?php echo getSetting('premium_price', 299); ?>" required>
                            <div class="form-help">Стоимость Premium подписки</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2" stroke="currentColor" stroke-width="2"/>
                                    <line x1="16" y1="2" x2="16" y2="6" stroke="currentColor" stroke-width="2"/>
                                    <line x1="8" y1="2" x2="8" y2="6" stroke="currentColor" stroke-width="2"/>
                                    <line x1="3" y1="10" x2="21" y2="10" stroke="currentColor" stroke-width="2"/>
                                </svg>
                                Длительность Premium (дней)
                            </label>
                            <input type="number" name="premium_duration_days" class="form-input" min="1" max="365"
                                   value="<?php echo getSetting('premium_duration_days', 30); ?>" required>
                            <div class="form-help">На сколько дней активируется Premium подписка</div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2" stroke="currentColor" stroke-width="2"/>
                                    <line x1="8" y1="21" x2="16" y2="21" stroke="currentColor" stroke-width="2"/>
                                    <line x1="12" y1="17" x2="12" y2="21" stroke="currentColor" stroke-width="2"/>
                                </svg>
                                Лимит кампаний (Базовый)
                            </label>
                            <input type="number" name="free_campaigns_limit" class="form-input" min="1" max="100"
                                   value="<?php echo getSetting('free_campaigns_limit', 1); ?>" required>
                            <div class="form-help">Максимальное количество кампаний для бесплатных пользователей</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                    <path d="M18 20V10" stroke="currentColor" stroke-width="2"/>
                                    <path d="M12 20V4" stroke="currentColor" stroke-width="2"/>
                                    <path d="M6 20V14" stroke="currentColor" stroke-width="2"/>
                                </svg>
                                Лимит размещений/месяц (Базовый)
                            </label>
                            <input type="number" name="free_placements_limit" class="form-input" min="1" max="1000"
                                   value="<?php echo getSetting('free_placements_limit', 50); ?>" required>
                            <div class="form-help">Максимальное количество размещений в месяц для бесплатных пользователей</div>
                        </div>
                    </div>
                </div>

                <!-- Системные настройки -->
                <div class="settings-section">
                    <h3 class="section-title">
                        <svg class="section-icon" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <path d="M13 2L3 14H12L11 22L21 10H12L13 2Z" stroke="currentColor" stroke-width="2"/>
                        </svg>
                        Системные настройки
                    </h3>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                <path d="M14 2H6C5.46957 2 4.96086 2.21071 4.58579 2.58579C4.21071 2.96086 4 3.46957 4 4V20C4 20.5304 4.21071 21.0391 4.58579 21.4142C4.96086 21.7893 5.46957 22 6 22H18C18.5304 22 19.0391 21.7893 19.4142 21.4142C19.7893 21.0391 20 20.5304 20 20V8L14 2Z" stroke="currentColor" stroke-width="2"/>
                                <polyline points="14,2 14,8 20,8" stroke="currentColor" stroke-width="2"/>
                            </svg>
                            Автоочистка данных (дней)
                        </label>
                        <input type="number" name="auto_cleanup_days" class="form-input" min="7" max="365"
                               value="<?php echo getSetting('auto_cleanup_days', 30); ?>" required>
                        <div class="form-help">Через сколько дней удалять старые логи и неактивные сессии</div>
                    </div>
                </div>

                <!-- Административные действия -->
                <div class="settings-section danger-zone">
                    <h3 class="section-title">
                        <svg class="section-icon" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <path d="M12 9V13" stroke="currentColor" stroke-width="2"/>
                            <path d="M10.29 3.86L1.82 18C1.64822 18.3024 1.55506 18.6453 1.55506 18.995C1.55506 19.3447 1.64822 19.6876 1.82 19.99C1.99178 20.2924 2.23498 20.5356 2.52736 20.7074C2.81975 20.8791 3.15244 20.9721 3.49 20.975H20.51C20.8576 20.9721 21.1903 20.8791 21.4826 20.7074C21.775 20.5356 22.0182 20.2924 22.19 19.99C22.3618 19.6876 22.4549 19.3447 22.4549 18.995C22.4549 18.6453 22.3618 18.3024 22.19 18L13.71 3.86C13.5346 3.56611 13.2903 3.32424 12.9964 3.15912C12.7025 2.994 12.3676 2.90967 12.0275 2.90967C11.6874 2.90967 11.3525 2.994 11.0586 3.15912C10.7647 3.32424 10.5204 3.56611 10.345 3.86L10.29 3.86Z" stroke="currentColor" stroke-width="2"/>
                            <line x1="12" y1="17" x2="12.01" y2="17" stroke="currentColor" stroke-width="2"/>
                        </svg>
                        Административные действия
                    </h3>
                    
                    <div class="form-group">
                        <label class="form-label">Операции с системными данными</label>
                        <div class="action-buttons">
                            <button type="submit" name="action" value="clear_logs" class="btn btn-danger btn-small" 
                                    onclick="return confirm('Удалить все логи старше 90 дней? Это действие нельзя отменить!')">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                    <polyline points="3,6 5,6 21,6" stroke="currentColor" stroke-width="2"/>
                                    <path d="M19 6V20C19 20.5304 18.7893 21.0391 18.4142 21.4142C18.0391 21.7893 17.5304 22 17 22H7C6.46957 22 5.96086 21.7893 5.58579 21.4142C5.21071 21.0391 5 20.5304 5 20V6M8 6V4C8 3.46957 8.21071 2.96086 8.58579 2.58579C8.96086 2.21071 9.46957 2 10 2H14C14.5304 2 15.0391 2.21071 15.4142 2.58579C15.7893 2.96086 16 3.46957 16 4V6" stroke="currentColor" stroke-width="2"/>
                                </svg>
                                Очистить старые логи
                            </button>
                            <button type="submit" name="action" value="clear_sessions" class="btn btn-danger btn-small" 
                                    onclick="return confirm('Удалить все истекшие сессии? Пользователям придется войти заново.')">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                    <path d="M9 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V5C3 4.46957 3.21071 3.96086 3.58579 3.58579C3.96086 3.21071 4.46957 3 5 3H9" stroke="currentColor" stroke-width="2"/>
                                    <polyline points="16,17 21,12 16,7" stroke="currentColor" stroke-width="2"/>
                                    <line x1="21" y1="12" x2="9" y2="12" stroke="currentColor" stroke-width="2"/>
                                </svg>
                                Очистить истекшие сессии
                            </button>
                            <button type="submit" name="action" value="optimize_db" class="btn btn-secondary btn-small" 
                                    onclick="return confirm('Оптимизировать базу данных? Удалятся старые данные.')">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                    <ellipse cx="12" cy="5" rx="9" ry="3" stroke="currentColor" stroke-width="2"/>
                                    <path d="M21 12C21 13.66 16.97 15 12 15S3 13.66 3 12" stroke="currentColor" stroke-width="2"/>
                                    <path d="M3 5V19C3 20.66 7.03 22 12 22S21 20.66 21 19V5" stroke="currentColor" stroke-width="2"/>
                                </svg>
                                Оптимизировать БД
                            </button>
                        </div>
                        <div class="form-help">⚠️ Все операции необратимы! Создайте резервную копию перед выполнением.</div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary save-button">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                        <path d="M19 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V5C3 4.46957 3.21071 3.96086 3.58579 3.58579C3.96086 3.21071 4.46957 3 5 3H16L21 8V19C21 19.5304 20.7893 20.0391 20.4142 20.4142C20.0391 20.7893 19.5304 21 19 21Z" stroke="currentColor" stroke-width="2"/>
                        <polyline points="17,21 17,13 7,13 7,21" stroke="currentColor" stroke-width="2"/>
                        <polyline points="7,3 7,8 15,8" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    Сохранить настройки
                </button>
            </form>
        </div>

        <!-- Информационная панель -->
        <div class="system-overview">
            <!-- Статистика системы -->
            <div class="overview-card">
                <h3 class="overview-title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                        <path d="M3 3V21H21" stroke="currentColor" stroke-width="2"/>
                        <path d="M9 9L12 6L16 10L20 6" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    Статистика системы
                </h3>
                <div class="overview-stat">
                    <span class="stat-label">Всего пользователей</span>
                    <span class="stat-value"><?php echo number_format($systemStats['total_users']); ?></span>
                </div>
                <div class="overview-stat">
                    <span class="stat-label">Premium пользователей</span>
                    <span class="stat-value success"><?php echo number_format($systemStats['premium_users']); ?></span>
                </div>
                <div class="overview-stat">
                    <span class="stat-label">Всего кампаний</span>
                    <span class="stat-value"><?php echo number_format($systemStats['total_campaigns']); ?></span>
                </div>
                <div class="overview-stat">
                    <span class="stat-label">Всего размещений</span>
                    <span class="stat-value"><?php echo number_format($systemStats['total_placements']); ?></span>
                </div>
                <div class="overview-stat">
                    <span class="stat-label">Общая выручка</span>
                    <span class="stat-value success">₽<?php echo number_format($systemStats['total_revenue'], 0, ',', ' '); ?></span>
                </div>
                <div class="overview-stat">
                    <span class="stat-label">Активных сессий</span>
                    <span class="stat-value"><?php echo number_format($systemStats['active_sessions']); ?></span>
                </div>
            </div>

            <!-- Подписки -->
            <div class="overview-card">
                <h3 class="overview-title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                        <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    Premium подписки
                </h3>
                <div class="overview-stat">
                    <span class="stat-label">Активных подписок</span>
                    <span class="stat-value success"><?php echo number_format($subscriptionStats['active_subscriptions']); ?></span>
                </div>
                <div class="overview-stat">
                    <span class="stat-label">Истекают в течение недели</span>
                    <span class="stat-value <?php echo $subscriptionStats['expiring_soon'] > 0 ? 'warning' : ''; ?>">
                        <?php echo number_format($subscriptionStats['expiring_soon']); ?>
                    </span>
                </div>
                <div class="overview-stat">
                    <span class="stat-label">Ближайшее истечение</span>
                    <span class="stat-value">
                        <?php if ($subscriptionStats['next_expiry']): ?>
                            <?php echo date('d.m.Y', strtotime($subscriptionStats['next_expiry'])); ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <!-- Проверка системы -->
            <div class="overview-card">
                <h3 class="overview-title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                        <path d="M9 12L11 14L15 10" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    Проверка системы
                </h3>
                <div class="system-check">
                    <span class="stat-label">PHP версия ≥ 7.4</span>
                    <span class="check-status <?php echo $systemChecks['php_version'] ? 'ok' : 'fail'; ?>">
                        <?php echo $systemChecks['php_version'] ? '✅ OK' : '❌ FAIL'; ?>
                    </span>
                </div>
                <div class="system-check">
                    <span class="stat-label">PDO MySQL</span>
                    <span class="check-status <?php echo $systemChecks['pdo_mysql'] ? 'ok' : 'fail'; ?>">
                        <?php echo $systemChecks['pdo_mysql'] ? '✅ OK' : '❌ FAIL'; ?>
                    </span>
                </div>
                <div class="system-check">
                    <span class="stat-label">Memory limit ≥ 128MB</span>
                    <span class="check-status <?php echo $systemChecks['memory_limit'] ? 'ok' : 'fail'; ?>">
                        <?php echo $systemChecks['memory_limit'] ? '✅ OK' : '❌ FAIL'; ?>
                    </span>
                </div>
                <div class="system-check">
                    <span class="stat-label">Max execution time ≥ 30s</span>
                    <span class="check-status <?php echo $systemChecks['max_execution_time'] ? 'ok' : 'fail'; ?>">
                        <?php echo $systemChecks['max_execution_time'] ? '✅ OK' : '❌ FAIL'; ?>
                    </span>
                </div>
            </div>

            <!-- Быстрые действия -->
            <div class="overview-card">
                <h3 class="overview-title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                        <path d="M13 2L3 14H12L11 22L21 10H12L13 2Z" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    Быстрые действия
                </h3>
                <div class="action-buttons">
                    <a href="users.php" class="btn btn-secondary btn-small">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                            <path d="M20 21V19C20 17.9391 19.5786 16.9217 18.8284 16.1716C18.0783 15.4214 17.0609 15 16 15H8C6.93913 15 5.92172 15.4214 5.17157 16.1716C4.42143 16.9217 4 17.9391 4 19V21" stroke="currentColor" stroke-width="2"/>
                            <circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2"/>
                        </svg>
                        Управление пользователями
                    </a>
                    <a href="payments.php" class="btn btn-secondary btn-small">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                            <rect x="1" y="4" width="22" height="16" rx="2" ry="2" stroke="currentColor" stroke-width="2"/>
                            <line x1="1" y1="10" x2="23" y2="10" stroke="currentColor" stroke-width="2"/>
                        </svg>
                        Платежи и подписки
                    </a>
                    <a href="monitor.php" class="btn btn-secondary btn-small">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                            <path d="M3 3V21H21" stroke="currentColor" stroke-width="2"/>
                            <path d="M9 9L12 6L16 10L20 6" stroke="currentColor" stroke-width="2"/>
                        </svg>
                        Мониторинг системы
                    </a>
                    <a href="../" class="btn btn-primary btn-small" target="_blank">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                            <path d="M18 13V19C18 19.5304 17.7893 20.0391 17.4142 20.4142C17.0391 20.7893 16.5304 21 16 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V8C3 7.46957 3.21071 6.96086 3.58579 6.58579C3.96086 6.21071 4.46957 6 5 6H11" stroke="currentColor" stroke-width="2"/>
                            <path d="M15 3H21V9" stroke="currentColor" stroke-width="2"/>
                            <path d="M10 14L21 3" stroke="currentColor" stroke-width="2"/>
                        </svg>
                        Открыть сайт
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Анимация элементов при загрузке
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-in');
                }
            });
        }, { threshold: 0.1 });

        // Наблюдаем за элементами
        document.querySelectorAll('.settings-section, .overview-card').forEach(el => {
            observer.observe(el);
        });

        // Подтверждение для чекбоксов критических настроек
        const criticalCheckboxes = ['maintenance_mode'];
        criticalCheckboxes.forEach(checkboxId => {
            const checkbox = document.getElementById(checkboxId);
            if (checkbox) {
                checkbox.addEventListener('change', function() {
                    if (this.checked && checkboxId === 'maintenance_mode') {
                        if (!confirm('Включить режим обслуживания? Сайт станет недоступен для пользователей.')) {
                            this.checked = false;
                        }
                    }
                });
            }
        });

        // Валидация формы
        const form = document.querySelector('.settings-form');
        if (form) {
            form.addEventListener('submit', function(e) {
                const premiumPrice = document.querySelector('input[name="premium_price"]').value;
                const premiumDuration = document.querySelector('input[name="premium_duration_days"]').value;
                
                if (premiumPrice < 1 || premiumPrice > 99999) {
                    e.preventDefault();
                    alert('Цена Premium должна быть от 1 до 99999 рублей');
                    return;
                }
                
                if (premiumDuration < 1 || premiumDuration > 365) {
                    e.preventDefault();
                    alert('Длительность Premium должна быть от 1 до 365 дней');
                    return;
                }
            });
        }
    });
</script>

<?php require_once 'footer.php'; ?>