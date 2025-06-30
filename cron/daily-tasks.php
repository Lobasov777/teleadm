<?php
// cron/daily-tasks.php
// Запускать каждый день в 00:00 через cron: 0 0 * * * /usr/bin/php /path/to/cron/daily-tasks.php

require_once '../includes/db.php';

echo "Starting daily tasks at " . date('Y-m-d H:i:s') . "\n";

// 1. Проверка истекших Premium подписок
echo "Checking expired subscriptions...\n";
$stmt = db()->prepare("
    UPDATE users u
    JOIN subscriptions s ON u.id = s.user_id
    SET u.role = 'user'
    WHERE u.role = 'premium' 
    AND s.is_active = TRUE 
    AND s.end_date < CURDATE()
");
$stmt->execute();
$expiredCount = $stmt->rowCount();
echo "Expired subscriptions: $expiredCount\n";

// Деактивируем истекшие подписки
$stmt = db()->prepare("
    UPDATE subscriptions 
    SET is_active = FALSE 
    WHERE is_active = TRUE AND end_date < CURDATE()
");
$stmt->execute();

// 2. Очистка старых сессий
echo "Cleaning old sessions...\n";
$stmt = db()->prepare("
    DELETE FROM user_sessions 
    WHERE expires_at < NOW() OR created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$stmt->execute();
$cleanedSessions = $stmt->rowCount();
echo "Cleaned sessions: $cleanedSessions\n";

// 3. Сброс месячных лимитов для бесплатных пользователей (1 числа каждого месяца)
if (date('j') == 1) {
    echo "Resetting monthly limits...\n";
    // Здесь можно добавить логику сброса счетчиков, если они хранятся отдельно
}

// 4. Отправка уведомлений об истекающих подписках (за 3 дня)
echo "Checking expiring subscriptions...\n";
$stmt = db()->prepare("
    SELECT u.id, u.email, u.username, s.end_date
    FROM users u
    JOIN subscriptions s ON u.id = s.user_id
    WHERE s.is_active = TRUE 
    AND s.end_date = DATE_ADD(CURDATE(), INTERVAL 3 DAY)
");
$stmt->execute();
$expiringUsers = $stmt->fetchAll();

foreach ($expiringUsers as $user) {
    echo "User {$user['username']} subscription expires on {$user['end_date']}\n";
    // Здесь можно добавить отправку email-уведомлений
}

// 5. Генерация статистики за предыдущий день
echo "Generating daily statistics...\n";
$yesterday = date('Y-m-d', strtotime('-1 day'));

$stmt = db()->prepare("
    SELECT 
        COUNT(DISTINCT c.user_id) as active_users,
        COUNT(ap.id) as total_placements,
        SUM(ap.price) as total_spent,
        AVG(ap.cpm) as avg_cpm
    FROM ad_placements ap
    JOIN campaigns c ON ap.campaign_id = c.id
    WHERE DATE(ap.created_at) = ?
");
$stmt->execute([$yesterday]);
$stats = $stmt->fetch();

echo "Yesterday statistics:\n";
echo "- Active users: {$stats['active_users']}\n";
echo "- Placements: {$stats['total_placements']}\n";
echo "- Total spent: {$stats['total_spent']} RUB\n";
echo "- Average CPM: {$stats['avg_cpm']} RUB\n";

// 6. Оптимизация таблиц (раз в неделю - по воскресеньям)
if (date('w') == 0) {
    echo "Optimizing database tables...\n";
    $tables = ['users', 'campaigns', 'ad_placements', 'payments', 'admin_logs', 'user_sessions'];
    
    foreach ($tables as $table) {
        try {
            db()->exec("OPTIMIZE TABLE $table");
            echo "Optimized table: $table\n";
        } catch (Exception $e) {
            echo "Error optimizing $table: " . $e->getMessage() . "\n";
        }
    }
}

echo "Daily tasks completed at " . date('Y-m-d H:i:s') . "\n";
echo "----------------------------------------\n";
?>