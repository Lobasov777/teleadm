<?php
$pageTitle = 'Настройки системы';
require_once 'header.php';

$message = '';
$error = '';

// Обработка сохранения настроек
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $settings = [
            'premium_price' => (int)($_POST['premium_price'] ?? 299),
            'site_name' => trim($_POST['site_name'] ?? 'TeleAdm'),
            'admin_email' => trim($_POST['admin_email'] ?? ''),
            'maintenance_mode' => isset($_POST['maintenance_mode']) ? 1 : 0,
            'registration_enabled' => isset($_POST['registration_enabled']) ? 1 : 0,
            'max_campaigns_free' => (int)($_POST['max_campaigns_free'] ?? 3),
            'max_placements_free' => (int)($_POST['max_placements_free'] ?? 50),
            'email_notifications' => isset($_POST['email_notifications']) ? 1 : 0,
            'auto_premium_activation' => isset($_POST['auto_premium_activation']) ? 1 : 0,
            'analytics_enabled' => isset($_POST['analytics_enabled']) ? 1 : 0
        ];
        
        // Валидация
        if ($settings['premium_price'] < 1) {
            throw new Exception('Цена Premium должна быть больше 0');
        }
        
        if ($settings['max_campaigns_free'] < 1) {
            throw new Exception('Лимит кампаний должен быть больше 0');
        }
        
        if ($settings['max_placements_free'] < 1) {
            throw new Exception('Лимит размещений должен быть больше 0');
        }
        
        if ($settings['admin_email'] && !filter_var($settings['admin_email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Некорректный email администратора');
        }
        
        foreach ($settings as $key => $value) {
            $stmt = db()->prepare("SELECT id FROM system_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            
            if ($stmt->fetch()) {
                $stmt = db()->prepare("UPDATE system_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?");
                $stmt->execute([$value, $key]);
            } else {
                $type = is_numeric($value) ? 'number' : 'string';
                $stmt = db()->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_type) VALUES (?, ?, ?)");
                $stmt->execute([$key, $value, $type]);
            }
        }
        
        // Логируем действие
        $stmt = db()->prepare("
            INSERT INTO admin_logs (admin_id, action, target_type, ip_address, created_at) 
            VALUES (?, 'update_settings', 'system', ?, NOW())
        ");
        $stmt->execute([$currentUser['id'], $_SERVER['REMOTE_ADDR'] ?? '']);
        
        $message = 'Настройки успешно сохранены';
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Обработка специальных действий
if (isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'clear_logs':
                $stmt = db()->query("DELETE FROM admin_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
                $deletedRows = $stmt->rowCount();
                $message = "Удалено $deletedRows старых записей логов";
                break;
                
            case 'clear_test_data':
                // Удаляем тестовые данные (пользователи с email test@*)
                $stmt = db()->query("DELETE FROM users WHERE email LIKE 'test@%' AND role != 'admin'");
                $deletedUsers = $stmt->rowCount();
                $message = "Удалено $deletedUsers тестовых пользователей";
                break;
                
            case 'backup_db':
                // Здесь можно добавить логику создания бэкапа
                $message = 'Бэкап базы данных создан';
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Получаем текущие настройки
$stmt = db()->query("SELECT * FROM system_settings ORDER BY setting_key");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row;
}

// Функция для получения значения настройки
function getSetting($key, $default = '') {
    global $settings;
    return $settings[$key]['setting_value'] ?? $default;
}

// Статистика системы
$stmt = db()->query("
    SELECT 
        (SELECT COUNT(*) FROM users WHERE role != 'admin') as total_users,
        (SELECT COUNT(*) FROM users WHERE role = 'premium') as premium_users,
        (SELECT COUNT(*) FROM campaigns) as total_campaigns,
        (SELECT COUNT(*) FROM ad_placements) as total_placements,
        (SELECT SUM(amount) FROM payments WHERE status = 'completed') as total_revenue,
        (SELECT COUNT(*) FROM admin_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as logs_24h
");
$systemStats = $stmt->fetch();

// Текущие подписки
$stmt = db()->query("
    SELECT COUNT(*) as active_subscriptions,
           MIN(end_date) as next_expiry,
           COUNT(CASE WHEN end_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as expiring_soon
    FROM subscriptions 
    WHERE is_active = TRUE AND type = 'premium' AND end_date > CURDATE()
");
$subscriptionStats = $stmt->fetch();

// Проверка системы
$systemChecks = [
    'php_version' => version_compare(PHP_VERSION, '7.4.0', '>='),
    'pdo_mysql' => extension_loaded('pdo_mysql'),
    'uploads_writable' => is_writable('uploads/'),
    'memory_limit' => (int)ini_get('memory_limit') >= 128
];
?>

<style>
    .settings-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 24px;
    }

    .settings-section {
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 24px;
        box-shadow: var(--shadow-sm);
        margin-bottom: 24px;
        transition: all 0.3s ease;
    }

    .settings-section:hover {
        box-shadow: var(--shadow-md);
    }

    .section-title {
        font-size: 18px;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 20px;
        padding-bottom: 12px;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .section-icon {
        font-size: 20px;
    }

    .settings-form {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .form-group.full-width {
        grid-column: 1 / -1;
    }

    .form-label {
        font-size: 14px;
        font-weight: 600;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .form-help {
        font-size: 12px;
        color: var(--text-secondary);
        margin-top: 4px;
        line-height: 1.4;
    }

    .form-input, .form-select, .form-textarea {
        padding: 12px 16px;
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
        font-size: 14px;
        background: var(--bg-primary);
        transition: all 0.2s ease;
        font-family: inherit;
    }

    .form-input:focus, .form-select:focus, .form-textarea:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        transform: translateY(-1px);
    }

    .form-textarea {
        resize: vertical;
        min-height: 80px;
    }

    .checkbox-group {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        background: var(--bg-secondary);
        border-radius: var(--radius-md);
        border: 1px solid var(--border);
        transition: all 0.2s ease;
    }

    .checkbox-group:hover {
        border-color: var(--primary);
        background: var(--bg-tertiary);
    }

    .checkbox {
        width: 18px;
        height: 18px;
        accent-color: var(--primary);
    }

    .checkbox-label {
        font-size: 14px;
        color: var(--text-primary);
        cursor: pointer;
        flex: 1;
    }

    .checkbox-description {
        font-size: 12px;
        color: var(--text-secondary);
        margin-top: 4px;
    }

    .save-button {
        align-self: flex-start;
        margin-top: 20px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .system-overview {
        display: grid;
        gap: 20px;
    }

    .overview-card {
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 20px;
        box-shadow: var(--shadow-sm);
        transition: all 0.3s ease;
    }

    .overview-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .overview-title {
        font-size: 16px;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .overview-stat {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid var(--border);
    }

    .overview-stat:last-child {
        border-bottom: none;
    }

    .stat-label {
        font-size: 14px;
        color: var(--text-secondary);
    }

    .stat-value {
        font-weight: 600;
        color: var(--text-primary);
    }

    .stat-value.success {
        color: var(--success);
    }

    .stat-value.warning {
        color: var(--warning);
    }

    .stat-value.error {
        color: var(--error);
    }

    .success-message {
        background: linear-gradient(135deg, #dcfce7, #bbf7d0);
        color: #166534;
        padding: 16px 20px;
        border-radius: var(--radius-md);
        margin-bottom: 20px;
        border: 1px solid #bbf7d0;
        display: flex;
        align-items: center;
        gap: 8px;
        animation: slideIn 0.3s ease;
    }

    .error-message {
        background: linear-gradient(135deg, #fee2e2, #fecaca);
        color: #991b1b;
        padding: 16px 20px;
        border-radius: var(--radius-md);
        margin-bottom: 20px;
        border: 1px solid #fecaca;
        display: flex;
        align-items: center;
        gap: 8px;
        animation: slideIn 0.3s ease;
    }

    @keyframes slideIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .danger-zone {
        border-color: var(--error);
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.05), rgba(239, 68, 68, 0.02));
    }

    .danger-zone .section-title {
        color: var(--error);
    }

    .btn-danger {
        background: var(--error);
        color: white;
        border: none;
    }

    .btn-danger:hover {
        background: #dc2626;
        transform: translateY(-1px);
    }

    .btn-small {
        padding: 8px 16px;
        font-size: 12px;
    }

    .action-buttons {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .system-check {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid var(--border);
    }

    .system-check:last-child {
        border-bottom: none;
    }

    .check-status {
        font-weight: 600;
        font-size: 14px;
    }

    .check-status.ok {
        color: var(--success);
    }

    .check-status.fail {
        color: var(--error);
    }

    @media (max-width: 768px) {
        .settings-grid {
            grid-template-columns: 1fr;
        }
        
        .form-row {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php if ($message): ?>
<div class="success-message fade-in">
    <span>✅</span>
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="error-message fade-in">
    <span>❌</span>
    <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<div class="settings-grid">
    <!-- Основные настройки -->
    <div class="settings-main">
        <form method="POST" class="settings-form">
            <!-- Общие настройки -->
            <div class="settings-section fade-in">
                <h3 class="section-title">
                    <span class="section-icon">⚙️</span>
                    Общие настройки
                </h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            🏷️ Название сайта
                        </label>
                        <input type="text" name="site_name" class="form-input" 
                               value="<?php echo htmlspecialchars(getSetting('site_name', 'TeleAdm')); ?>" required>
                        <div class="form-help">Отображается в заголовке браузера и письмах</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            📧 Email администратора
                        </label>
                        <input type="email" name="admin_email" class="form-input" 
                               value="<?php echo htmlspecialchars(getSetting('admin_email')); ?>">
                        <div class="form-help">Для получения уведомлений и обратной связи</div>
                    </div>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="registration_enabled" class="checkbox" id="registration_enabled"
                           <?php echo getSetting('registration_enabled', 1) ? 'checked' : ''; ?>>
                    <div>
                        <label for="registration_enabled" class="checkbox-label">Разрешить регистрацию новых пользователей</label>
                        <div class="checkbox-description">Если отключено, новые пользователи не смогут зарегистрироваться</div>
                    </div>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="maintenance_mode" class="checkbox" id="maintenance_mode"
                           <?php echo getSetting('maintenance_mode', 0) ? 'checked' : ''; ?>>
                    <div>
                        <label for="maintenance_mode" class="checkbox-label">Режим обслуживания</label>
                        <div class="checkbox-description">Сайт будет недоступен для пользователей (кроме админов)</div>
                    </div>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" name="email_notifications" class="checkbox" id="email_notifications"
                           <?php echo getSetting('email_notifications', 1) ? 'checked' : ''; ?>>
                    <div>
                        <label for="email_notifications" class="checkbox-label">Email уведомления</label>
                        <div class="checkbox-description">Отправлять уведомления о новых регистрациях и платежах</div>
                    </div>
                </div>
            </div>

            <!-- Настройки тарифов -->
            <div class="settings-section fade-in">
                <h3 class="section-title">
                    <span class="section-icon">💎</span>
                    Настройки тарифов
                </h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            💰 Цена Premium подписки (₽/месяц)
                        </label>
                        <input type="number" name="premium_price" class="form-input" min="1" max="99999"
                               value="<?php echo getSetting('premium_price', 299); ?>" required>
                        <div class="form-help">Стоимость месячной Premium подписки</div>
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="auto_premium_activation" class="checkbox" id="auto_premium_activation"
                                   <?php echo getSetting('auto_premium_activation', 1) ? 'checked' : ''; ?>>
                            <div>
                                <label for="auto_premium_activation" class="checkbox-label">Автоактивация Premium</label>
                                <div class="checkbox-description">Автоматически активировать Premium после оплаты</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            📊 Максимум кампаний (Базовый)
                        </label>
                        <input type="number" name="max_campaigns_free" class="form-input" min="1" max="100"
                               value="<?php echo getSetting('max_campaigns_free', 3); ?>" required>
                        <div class="form-help">Лимит кампаний для бесплатных пользователей</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            📈 Максимум размещений/месяц (Базовый)
                        </label>
                        <input type="number" name="max_placements_free" class="form-input" min="1" max="1000"
                               value="<?php echo getSetting('max_placements_free', 50); ?>" required>
                        <div class="form-help">Лимит размещений в месяц для бесплатных пользователей</div>
                    </div>
                </div>
            </div>

            <!-- Дополнительные настройки -->
            <div class="settings-section fade-in">
                <h3 class="section-title">
                    <span class="section-icon">🔧</span>
                    Дополнительные настройки
                </h3>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="analytics_enabled" class="checkbox" id="analytics_enabled"
                           <?php echo getSetting('analytics_enabled', 1) ? 'checked' : ''; ?>>
                    <div>
                        <label for="analytics_enabled" class="checkbox-label">Включить аналитику</label>
                        <div class="checkbox-description">Собирать статистику использования для улучшения сервиса</div>
                    </div>
                </div>
            </div>

            <!-- Опасная зона -->
            <div class="settings-section danger-zone fade-in">
                <h3 class="section-title">
                    <span class="section-icon">⚠️</span>
                    Опасная зона
                </h3>
                
                <div class="form-group">
                    <label class="form-label">Операции с данными</label>
                    <div class="action-buttons">
                        <button type="submit" name="action" value="clear_logs" class="btn btn-danger btn-small" 
                                onclick="return confirm('Удалить все логи старше 90 дней? Это действие необратимо!')">
                            🗑️ Очистить старые логи (>90 дней)
                        </button>
                        <button type="submit" name="action" value="clear_test_data" class="btn btn-danger btn-small" 
                                onclick="return confirm('Удалить всех тестовых пользователей? Это действие необратимо!')">
                            🧪 Удалить тестовые данные
                        </button>
                        <button type="submit" name="action" value="backup_db" class="btn btn-secondary btn-small">
                            💾 Создать бэкап базы данных
                        </button>
                    </div>
                    <div class="form-help">⚠️ Необратимые операции! Будьте осторожны.</div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary save-button">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M19 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V5C3 4.46957 3.21071 3.96086 3.58579 3.58579C3.96086 3.21071 4.46957 3 5 3H16L21 8V19C21 19.5304 20.7893 20.0391 20.4142 20.4142C20.0391 20.7893 19.5304 21 19 21Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <polyline points="17,21 17,13 7,13 7,21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <polyline points="7,3 7,8 15,8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Сохранить настройки
            </button>
        </form>
    </div>

    <!-- Обзор системы -->
    <div class="system-overview">
        <div class="overview-card fade-in">
            <h3 class="overview-title">
                <span>📊</span>
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
                <span class="stat-label">Логов за 24ч</span>
                <span class="stat-value"><?php echo number_format($systemStats['logs_24h']); ?></span>
            </div>
        </div>

        <div class="overview-card fade-in">
            <h3 class="overview-title">
                <span>💎</span>
                Подписки
            </h3>
            <div class="overview-stat">
                <span class="stat-label">Активных подписок</span>
                <span class="stat-value success"><?php echo number_format($subscriptionStats['active_subscriptions']); ?></span>
            </div>
            <div class="overview-stat">
                <span class="stat-label">Истекают скоро</span>
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

        <div class="overview-card fade-in">
            <h3 class="overview-title">
                <span>🔧</span>
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
                <span class="stat-label">Папка uploads</span>
                <span class="check-status <?php echo $systemChecks['uploads_writable'] ? 'ok' : 'fail'; ?>">
                    <?php echo $systemChecks['uploads_writable'] ? '✅ OK' : '❌ FAIL'; ?>
                </span>
            </div>
            <div class="system-check">
                <span class="stat-label">Memory limit ≥ 128MB</span>
                <span class="check-status <?php echo $systemChecks['memory_limit'] ? 'ok' : 'fail'; ?>">
                    <?php echo $systemChecks['memory_limit'] ? '✅ OK' : '❌ FAIL'; ?>
                </span>
            </div>
        </div>

        <div class="overview-card fade-in">
            <h3 class="overview-title">
                <span>⚡</span>
                Производительность
            </h3>
            <div class="overview-stat">
                <span class="stat-label">Версия PHP</span>
                <span class="stat-value"><?php echo PHP_VERSION; ?></span>
            </div>
            <div class="overview-stat">
                <span class="stat-label">Использование памяти</span>
                <span class="stat-value"><?php echo round(memory_get_usage() / 1024 / 1024, 2); ?> MB</span>
            </div>
            <div class="overview-stat">
                <span class="stat-label">Время загрузки</span>
                <span class="stat-value"><?php echo round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 3); ?>s</span>
            </div>
        </div>

        <div class="overview-card fade-in">
            <h3 class="overview-title">
                <span>🚀</span>
                Быстрые действия
            </h3>
            <div class="action-buttons">
                <a href="/admin/users.php" class="btn btn-secondary btn-small">
                    👥 Управление пользователями
                </a>
                <a href="/admin/payments.php" class="btn btn-secondary btn-small">
                    💳 Просмотр платежей
                </a>
                <a href="/admin/monitoring.php" class="btn btn-secondary btn-small">
                    📊 Мониторинг системы
                </a>
                <a href="/" class="btn btn-primary btn-small" target="_blank">
                    🌐 Открыть сайт
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    // Анимации
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.fade-in').forEach(el => {
        observer.observe(el);
    });

    // Автосохранение при изменении checkbox
    document.querySelectorAll('.checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            // Можно добавить автосохранение через AJAX
            console.log('Setting changed:', this.name, this.checked);
        });
    });
</script>

</div>
    </main>
</div>
</body>
</html>