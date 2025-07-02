<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$pageTitle = 'Админ-панель';

if (!file_exists('header.php')) {
    die('Ошибка: Файл header.php не найден в папке admin/');
}

require_once 'header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Проверка прав админа
if (function_exists('isAdmin') && !isAdmin()) {
    die('Доступ запрещен. Необходимы права администратора.');
}

if (!function_exists('isAdmin')) {
    $stmt = db()->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user || $user['role'] !== 'admin') {
        die('Доступ запрещен. Необходимы права администратора.');
    }
}

try {
    // Статистика пользователей
    $stmt = db()->query("
        SELECT 
            COUNT(*) as total_users,
            SUM(CASE WHEN role = 'premium' THEN 1 ELSE 0 END) as premium_users,
            SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) as free_users,
            SUM(CASE WHEN is_blocked = TRUE THEN 1 ELSE 0 END) as blocked_users,
            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as registered_today,
            SUM(CASE WHEN DATE(last_login) = CURDATE() THEN 1 ELSE 0 END) as active_today
        FROM users 
        WHERE role != 'admin'
    ");
    $userStats = $stmt->fetch();

    // Статистика размещений и доходов
    $stmt = db()->query("
        SELECT 
            COUNT(id) as total_placements,
            COALESCE(SUM(price), 0) as total_revenue,
            COUNT(DISTINCT campaign_id) as total_campaigns,
            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as placements_today
        FROM ad_placements
    ");
    $placementStats = $stmt->fetch();

    // Статистика платежей
    $stmt = db()->query("
        SELECT 
            COUNT(*) as payments_count,
            COALESCE(SUM(amount), 0) as payments_amount
        FROM payments 
        WHERE status = 'completed' 
        AND MONTH(paid_at) = MONTH(CURRENT_DATE()) 
        AND YEAR(paid_at) = YEAR(CURRENT_DATE())
    ");
    $paymentStats = $stmt->fetch();

    // Последние пользователи
    $stmt = db()->query("
        SELECT id, username, email, role, created_at 
        FROM users 
        WHERE role != 'admin' 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $recentUsers = $stmt->fetchAll();

    // Активные пользователи
    $stmt = db()->query("
        SELECT 
            u.id, u.username, u.email, u.role, u.last_login,
            COUNT(DISTINCT c.id) as campaigns_count,
            COUNT(ap.id) as placements_count,
            COALESCE(SUM(ap.price), 0) as total_spent
        FROM users u
        LEFT JOIN campaigns c ON u.id = c.user_id
        LEFT JOIN ad_placements ap ON c.id = ap.campaign_id
        WHERE u.role != 'admin' AND u.last_login IS NOT NULL
        GROUP BY u.id
        ORDER BY u.last_login DESC
        LIMIT 5
    ");
    $activeUsers = $stmt->fetchAll();

    // График регистраций за 7 дней
    $stmt = db()->query("
        SELECT 
            DATE(created_at) as date, 
            COUNT(*) as count
        FROM users 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
        AND role != 'admin'
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $registrationData = $stmt->fetchAll();

    // График доходов за 7 дней
    $stmt = db()->query("
        SELECT 
            DATE(created_at) as date, 
            COALESCE(SUM(price), 0) as revenue
        FROM ad_placements
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $revenueData = $stmt->fetchAll();

    // Подготовка данных для графиков
    $chartLabels = [];
    $registrationChartData = [];
    $revenueChartData = [];
    $chartDates = [];
    
    // Заполняем все дни за последние 7 дней
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $chartDates[$date] = 0;
        $chartLabels[] = date('d.m', strtotime($date));
    }
    
    // Данные регистраций
    $regDates = $chartDates;
    foreach ($registrationData as $day) {
        if (isset($regDates[$day['date']])) {
            $regDates[$day['date']] = (int)$day['count'];
        }
    }
    $registrationChartData = array_values($regDates);
    
    // Данные доходов
    $revDates = $chartDates;
    foreach ($revenueData as $day) {
        if (isset($revDates[$day['date']])) {
            $revDates[$day['date']] = (float)$day['revenue'];
        }
    }
    $revenueChartData = array_values($revDates);

} catch (Exception $e) {
    error_log("Ошибка в админ-дашборде: " . $e->getMessage());
    
    $userStats = [
        'total_users' => 0, 'premium_users' => 0, 'free_users' => 0,
        'blocked_users' => 0, 'registered_today' => 0, 'active_today' => 0
    ];
    $placementStats = [
        'total_placements' => 0, 'total_revenue' => 0, 
        'total_campaigns' => 0, 'placements_today' => 0
    ];
    $paymentStats = ['payments_count' => 0, 'payments_amount' => 0];
    $recentUsers = $activeUsers = [];
    $chartLabels = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
    $registrationChartData = $revenueChartData = [0, 0, 0, 0, 0, 0, 0];
}

$userStats = $userStats ?: [];
$placementStats = $placementStats ?: [];
$paymentStats = $paymentStats ?: [];
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
    
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        min-height: 100vh;
        color: #334155;
    }

    .dashboard-container {
        padding: 32px;
        max-width: 1400px;
        margin: 0 auto;
    }

    .dashboard-header {
        margin-bottom: 40px;
        text-align: center;
    }

    .dashboard-title {
        color: #0f172a;
        font-size: 32px;
        font-weight: 800;
        margin-bottom: 8px;
        letter-spacing: -0.025em;
    }

    .dashboard-subtitle {
        color: #64748b;
        font-size: 16px;
        font-weight: 500;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 24px;
        margin-bottom: 40px;
    }

    .stat-card {
        background: white;
        border-radius: 16px;
        padding: 28px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06);
        border: 1px solid #f1f5f9;
        position: relative;
        overflow: hidden;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        opacity: 0;
        transform: translateY(20px);
    }

    .stat-card.animate-in {
        opacity: 1;
        transform: translateY(0);
    }

    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1), 0 4px 6px rgba(0, 0, 0, 0.05);
    }

    .stat-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 20px;
    }

    .stat-icon {
        width: 64px;
        height: 64px;
        border-radius: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        position: relative;
        overflow: hidden;
    }

    .stat-icon::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: var(--gradient);
        opacity: 0.9;
    }

    .stat-icon svg {
        position: relative;
        z-index: 1;
    }

    .stat-icon.users {
        --gradient: linear-gradient(135deg, #3b82f6, #1e40af);
    }
    
    .stat-icon.premium {
        --gradient: linear-gradient(135deg, #f59e0b, #d97706);
    }
    
    .stat-icon.placements {
        --gradient: linear-gradient(135deg, #10b981, #047857);
    }
    
    .stat-icon.revenue {
        --gradient: linear-gradient(135deg, #8b5cf6, #7c3aed);
    }

    .stat-content {
        flex: 1;
        margin-right: 20px;
    }

    .stat-value {
        font-size: 36px;
        font-weight: 800;
        color: #0f172a;
        line-height: 1;
        margin-bottom: 8px;
        letter-spacing: -0.025em;
    }

    .stat-label {
        font-size: 15px;
        color: #64748b;
        font-weight: 600;
        margin-bottom: 12px;
    }

    .stat-change {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 13px;
        font-weight: 600;
        padding: 6px 12px;
        border-radius: 20px;
        background: rgba(34, 197, 94, 0.1);
        color: #16a34a;
    }

    .content-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 32px;
        margin-bottom: 40px;
    }

    .chart-card {
        background: white;
        border-radius: 20px;
        padding: 32px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06);
        border: 1px solid #f1f5f9;
        opacity: 0;
        transform: translateY(20px);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .chart-card.animate-in {
        opacity: 1;
        transform: translateY(0);
    }

    .chart-header {
        margin-bottom: 28px;
    }

    .chart-title {
        font-size: 22px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 6px;
        letter-spacing: -0.025em;
    }

    .chart-subtitle {
        font-size: 14px;
        color: #64748b;
        font-weight: 500;
    }

    .chart-container {
        height: 360px;
        position: relative;
        border-radius: 12px;
        overflow: hidden;
        background: #fafafa;
    }

    .quick-actions {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 40px;
    }

    .quick-action {
        background: white;
        border-radius: 16px;
        padding: 24px;
        text-decoration: none;
        color: #0f172a;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06);
        border: 1px solid #f1f5f9;
        text-align: center;
        opacity: 0;
        transform: translateY(20px);
        position: relative;
        overflow: hidden;
    }

    .quick-action.animate-in {
        opacity: 1;
        transform: translateY(0);
    }

    .quick-action::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(135deg, #3b82f6, #1e40af);
    }

    .quick-action:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1), 0 4px 6px rgba(0, 0, 0, 0.05);
        text-decoration: none;
        color: #0f172a;
    }

    .quick-action-icon {
        width: 56px;
        height: 56px;
        margin: 0 auto 16px;
        background: linear-gradient(135deg, #3b82f6, #1e40af);
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        position: relative;
        overflow: hidden;
    }

    .quick-action-icon::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: inherit;
        opacity: 0.9;
    }

    .quick-action-icon svg {
        position: relative;
        z-index: 1;
    }

    .quick-action-title {
        font-weight: 700;
        font-size: 16px;
        margin-bottom: 8px;
        color: #0f172a;
        letter-spacing: -0.025em;
    }

    .quick-action-desc {
        font-size: 13px;
        color: #64748b;
        font-weight: 500;
    }

    .data-card {
        background: white;
        border-radius: 20px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06);
        border: 1px solid #f1f5f9;
        overflow: hidden;
        opacity: 0;
        transform: translateY(20px);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .data-card.animate-in {
        opacity: 1;
        transform: translateY(0);
    }

    .data-header {
        padding: 24px 32px;
        background: linear-gradient(135deg, #f8fafc, #f1f5f9);
        border-bottom: 1px solid #e2e8f0;
    }

    .data-title {
        font-size: 18px;
        font-weight: 700;
        color: #0f172a;
        letter-spacing: -0.025em;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
    }

    .data-table th {
        background: #f8fafc;
        padding: 16px 24px;
        text-align: left;
        font-weight: 700;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #475569;
        border-bottom: 1px solid #e2e8f0;
    }

    .data-table td {
        padding: 20px 24px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 14px;
    }

    .data-table tr:hover {
        background: #f8fafc;
    }

    .data-table tr:last-child td {
        border-bottom: none;
    }

    .user-info {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .user-name {
        font-weight: 600;
        color: #0f172a;
        font-size: 15px;
    }

    .user-email {
        font-size: 12px;
        color: #64748b;
    }

    .user-badge {
        display: inline-flex;
        align-items: center;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .user-badge.premium {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: white;
    }

    .user-badge.user {
        background: #f1f5f9;
        color: #64748b;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.2s ease;
        border: none;
        cursor: pointer;
    }

    .btn-primary {
        background: linear-gradient(135deg, #3b82f6, #1e40af);
        color: white;
        box-shadow: 0 2px 4px rgba(59, 130, 246, 0.3);
    }

    .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(59, 130, 246, 0.4);
        text-decoration: none;
        color: white;
    }

    .empty-state {
        text-align: center;
        padding: 60px 40px;
        color: #64748b;
    }

    .empty-icon {
        width: 64px;
        height: 64px;
        margin: 0 auto 20px;
        opacity: 0.4;
        color: #94a3b8;
    }

    .empty-title {
        font-size: 16px;
        font-weight: 600;
        color: #475569;
        margin-bottom: 8px;
    }

    .empty-desc {
        font-size: 14px;
        color: #64748b;
    }

    /* Анимации */
    .stat-card:nth-child(1) { transition-delay: 0.1s; }
    .stat-card:nth-child(2) { transition-delay: 0.2s; }
    .stat-card:nth-child(3) { transition-delay: 0.3s; }
    .stat-card:nth-child(4) { transition-delay: 0.4s; }

    .quick-action:nth-child(1) { transition-delay: 0.1s; }
    .quick-action:nth-child(2) { transition-delay: 0.2s; }
    .quick-action:nth-child(3) { transition-delay: 0.3s; }
    .quick-action:nth-child(4) { transition-delay: 0.4s; }

    /* Специфичные стили для иконок */
    .quick-action:nth-child(1) .quick-action-icon {
        background: linear-gradient(135deg, #3b82f6, #1e40af);
    }
    
    .quick-action:nth-child(2) .quick-action-icon {
        background: linear-gradient(135deg, #10b981, #047857);
    }
    
    .quick-action:nth-child(3) .quick-action-icon {
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    }
    
    .quick-action:nth-child(4) .quick-action-icon {
        background: linear-gradient(135deg, #f59e0b, #d97706);
    }

    /* Responsive */
    @media (max-width: 1024px) {
        .content-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .dashboard-container {
            padding: 20px;
        }
        
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .dashboard-title {
            font-size: 26px;
        }
        
        .stat-value {
            font-size: 30px;
        }
        
        .quick-actions {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 480px) {
        .quick-actions {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="dashboard-container">
    <!-- Header -->
    <div class="dashboard-header">
        <h1 class="dashboard-title">Панель администратора TeleADM</h1>
        <p class="dashboard-subtitle">Управление платформой рекламы в Telegram каналах</p>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($userStats['total_users'] ?? 0); ?></div>
                    <div class="stat-label">Всего пользователей</div>
                    <div class="stat-change">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                            <path d="M7 14L12 9L17 14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        +<?php echo $userStats['registered_today'] ?? 0; ?> сегодня
                    </div>
                </div>
                <div class="stat-icon users">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none">
                        <path d="M17 21V19C17 17.9391 16.5786 16.9217 15.8284 16.1716C15.0783 15.4214 14.0609 15 13 15H5C3.93913 15 2.92172 15.4214 2.17157 16.1716C1.42143 16.9217 1 17.9391 1 19V21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="9" cy="7" r="4" stroke="currentColor" stroke-width="2"/>
                        <path d="M23 21V19C22.9993 18.1137 22.7044 17.2528 22.1614 16.5523C21.6184 15.8519 20.8581 15.3516 20 15.13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M16 3.13C16.8604 3.35031 17.623 3.85071 18.1676 4.55232C18.7122 5.25392 19.0078 6.11683 19.0078 7.005C19.0078 7.89318 18.7122 8.75608 18.1676 9.45769C17.623 10.1593 16.8604 10.6597 16 10.88" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($userStats['premium_users'] ?? 0); ?></div>
                    <div class="stat-label">Premium подписки</div>
                    <div class="stat-change">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                            <path d="M7 14L12 9L17 14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        <?php 
                        $total = $userStats['total_users'] ?? 0;
                        $premium = $userStats['premium_users'] ?? 0;
                        echo $total > 0 ? round(($premium / $total) * 100, 1) : 0; 
                        ?>% конверсия
                    </div>
                </div>
                <div class="stat-icon premium">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none">
                        <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($placementStats['total_placements'] ?? 0); ?></div>
                    <div class="stat-label">Размещений всего</div>
                    <div class="stat-change">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                            <path d="M7 14L12 9L17 14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        +<?php echo $placementStats['placements_today'] ?? 0; ?> сегодня
                    </div>
                </div>
                <div class="stat-icon placements">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2" stroke="currentColor" stroke-width="2"/>
                        <line x1="8" y1="21" x2="16" y2="21" stroke="currentColor" stroke-width="2"/>
                        <line x1="12" y1="17" x2="12" y2="21" stroke="currentColor" stroke-width="2"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-content">
                    <div class="stat-value">₽<?php echo number_format($placementStats['total_revenue'] ?? 0, 0, ',', ' '); ?></div>
                    <div class="stat-label">Общая выручка</div>
                    <div class="stat-change">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                            <path d="M7 14L12 9L17 14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        ₽<?php echo number_format($paymentStats['payments_amount'] ?? 0, 0, ',', ' '); ?> в месяц
                    </div>
                </div>
                <div class="stat-icon revenue">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none">
                        <path d="M12 1V23" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M17 5H9.5C8.57174 5 7.6815 5.36875 7.02513 6.02513C6.36875 6.6815 6 7.57174 6 8.5C6 9.42826 6.36875 10.3185 7.02513 10.9749C7.6815 11.6313 8.57174 12 9.5 12H14.5C15.4283 12 16.3185 12.3687 16.9749 13.0251C17.6313 13.6815 18 14.5717 18 15.5C18 16.4283 17.6313 17.3185 16.9749 17.9749C16.3185 18.6313 15.4283 19 14.5 19H6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <a href="users.php" class="quick-action">
            <div class="quick-action-icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none">
                    <path d="M16 21V19C16 17.9391 15.5786 16.9217 14.8284 16.1716C14.0783 15.4214 13.0609 15 12 15H5C3.93913 15 2.92172 15.4214 2.17157 16.1716C1.42143 16.9217 1 17.9391 1 19V21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="8.5" cy="7" r="4" stroke="currentColor" stroke-width="2"/>
                    <path d="M20 8V14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M23 11H17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <div class="quick-action-title">Управление пользователями</div>
            <div class="quick-action-desc">Просмотр, редактирование и модерация пользователей</div>
        </a>
        
        <a href="payments.php" class="quick-action">
            <div class="quick-action-icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none">
                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2" stroke="currentColor" stroke-width="2"/>
                    <line x1="1" y1="10" x2="23" y2="10" stroke="currentColor" stroke-width="2"/>
                    <path d="M7 15.5H9.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </div>
            <div class="quick-action-title">Платежи и подписки</div>
            <div class="quick-action-desc">История транзакций и управление биллингом</div>
        </a>
        
        <a href="settings.php" class="quick-action">
            <div class="quick-action-icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
                    <path d="M12 1V3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M12 21V23" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M4.22 4.22L5.64 5.64" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M18.36 18.36L19.78 19.78" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M1 12H3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M21 12H23" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M4.22 19.78L5.64 18.36" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M18.36 5.64L19.78 4.22" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <div class="quick-action-title">Настройки системы</div>
            <div class="quick-action-desc">Конфигурация параметров и лимитов</div>
        </a>
        
        <a href="monitor.php" class="quick-action">
            <div class="quick-action-icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none">
                    <path d="M3 3V21H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M9 9L12 6L16 10L20 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="9" cy="9" r="1" fill="currentColor"/>
                    <circle cx="12" cy="6" r="1" fill="currentColor"/>
                    <circle cx="16" cy="10" r="1" fill="currentColor"/>
                    <circle cx="20" cy="6" r="1" fill="currentColor"/>
                </svg>
            </div>
            <div class="quick-action-title">Мониторинг активности</div>
            <div class="quick-action-desc">Логи системы и статистика в реальном времени</div>
        </a>
    </div>

    <!-- Content Grid -->
    <div class="content-grid">
        <!-- Charts -->
        <div class="chart-card">
            <div class="chart-header">
                <h3 class="chart-title">Аналитика за неделю</h3>
                <p class="chart-subtitle">Регистрации новых пользователей и доходы от размещений</p>
            </div>
            <div class="chart-container">
                <canvas id="mainChart" style="width: 100%; height: 100%;"></canvas>
            </div>
        </div>

        <!-- Recent Users -->
        <div class="data-card">
            <div class="data-header">
                <h3 class="data-title">Последние регистрации</h3>
            </div>
            <?php if (empty($recentUsers)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none">
                            <path d="M20 21V19C20 17.9391 19.5786 16.9217 18.8284 16.1716C18.0783 15.4214 17.0609 15 16 15H8C6.93913 15 5.92172 15.4214 5.17157 16.1716C4.42143 16.9217 4 17.9391 4 19V21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2"/>
                        </svg>
                    </div>
                    <div class="empty-title">Пока нет новых пользователей</div>
                    <div class="empty-desc">Новые регистрации появятся здесь</div>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Пользователь</th>
                            <th>Тип</th>
                            <th>Дата</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentUsers as $user): ?>
                        <tr>
                            <td>
                                <div class="user-info">
                                    <div class="user-name"><?php echo htmlspecialchars($user['username']); ?></div>
                                    <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                                </div>
                            </td>
                            <td>
                                <span class="user-badge <?php echo $user['role']; ?>">
                                    <?php echo $user['role'] === 'premium' ? 'Premium' : 'Базовый'; ?>
                                </span>
                            </td>
                            <td style="font-size: 12px; color: #64748b;">
                                <?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Active Users -->
    <div class="data-card">
        <div class="data-header">
            <h3 class="data-title">Активные пользователи</h3>
        </div>
        <?php if (empty($activeUsers)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                        <path d="M8 14S9.5 16 12 16S16 14 16 14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <line x1="9" y1="9" x2="9.01" y2="9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <line x1="15" y1="9" x2="15.01" y2="9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </div>
                <div class="empty-title">Пока нет активных пользователей</div>
                <div class="empty-desc">Активность пользователей появится здесь</div>
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Пользователь</th>
                        <th>Кампаний</th>
                        <th>Размещений</th>
                        <th>Потрачено</th>
                        <th>Последний вход</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activeUsers as $user): ?>
                    <tr>
                        <td>
                            <div class="user-info">
                                <div class="user-name"><?php echo htmlspecialchars($user['username']); ?></div>
                                <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                            </div>
                        </td>
                        <td>
                            <span style="font-weight: 700; color: #3b82f6; font-size: 16px;">
                                <?php echo $user['campaigns_count']; ?>
                            </span>
                        </td>
                        <td>
                            <span style="font-weight: 700; color: #10b981; font-size: 16px;">
                                <?php echo $user['placements_count']; ?>
                            </span>
                        </td>
                        <td>
                            <span style="font-weight: 700; color: #8b5cf6; font-size: 16px;">
                                ₽<?php echo number_format($user['total_spent'], 0, ',', ' '); ?>
                            </span>
                        </td>
                        <td style="font-size: 12px; color: #64748b;">
                            <?php echo $user['last_login'] ? date('d.m.Y H:i', strtotime($user['last_login'])) : 'Никогда'; ?>
                        </td>
                        <td>
                            <a href="users.php?id=<?php echo $user['id']; ?>" class="btn btn-primary">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                    <path d="M1 12S5 4 12 4S23 12 23 12S19 20 12 20S1 12 1 12Z" stroke="currentColor" stroke-width="2"/>
                                    <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
                                </svg>
                                Просмотр
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
    // Данные для графиков
    const chartLabels = <?php echo json_encode($chartLabels); ?>;
    const registrationData = <?php echo json_encode($registrationChartData); ?>;
    const revenueData = <?php echo json_encode($revenueChartData); ?>;

    // Анимации при загрузке
    document.addEventListener('DOMContentLoaded', function() {
        // Анимация карточек
        const statCards = document.querySelectorAll('.stat-card');
        const quickActions = document.querySelectorAll('.quick-action');
        const dataCards = document.querySelectorAll('.data-card');
        const chartCards = document.querySelectorAll('.chart-card');

        function animateElements(elements, baseDelay = 0) {
            elements.forEach((element, index) => {
                setTimeout(() => {
                    element.classList.add('animate-in');
                }, baseDelay + (index * 100));
            });
        }

        // Запуск анимаций
        setTimeout(() => animateElements(statCards), 100);
        setTimeout(() => animateElements(quickActions), 400);
        setTimeout(() => animateElements(chartCards), 600);
        setTimeout(() => animateElements(dataCards), 700);

        // Инициализация графика
        setTimeout(initChart, 800);
    });

    // Современный мягкий график
    function initChart() {
        const canvas = document.getElementById('mainChart');
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        const rect = canvas.parentElement.getBoundingClientRect();
        
        // Устанавливаем размеры с учетом DPI
        const dpr = window.devicePixelRatio || 1;
        canvas.width = rect.width * dpr;
        canvas.height = rect.height * dpr;
        ctx.scale(dpr, dpr);
        canvas.style.width = rect.width + 'px';
        canvas.style.height = rect.height + 'px';

        const padding = 60;
        const chartWidth = rect.width - padding * 2;
        const chartHeight = rect.height - padding * 2;
        
        const maxReg = Math.max(...registrationData, 1);
        const maxRev = Math.max(...revenueData, 1);
        const stepX = chartWidth / (chartLabels.length - 1);
        
        // Очистка
        ctx.clearRect(0, 0, rect.width, rect.height);
        
        // Мягкая сетка
        ctx.strokeStyle = 'rgba(148, 163, 184, 0.3)';
        ctx.lineWidth = 1;
        
        for (let i = 0; i <= 4; i++) {
            const y = padding + (chartHeight / 4) * i;
            ctx.beginPath();
            ctx.moveTo(padding, y);
            ctx.lineTo(padding + chartWidth, y);
            ctx.stroke();
        }

        // График доходов (мягкая область)
        if (revenueData.some(v => v > 0)) {
            const gradient = ctx.createLinearGradient(0, padding, 0, padding + chartHeight);
            gradient.addColorStop(0, 'rgba(139, 92, 246, 0.2)');
            gradient.addColorStop(1, 'rgba(139, 92, 246, 0.02)');
            
            ctx.beginPath();
            ctx.moveTo(padding, padding + chartHeight);
            
            revenueData.forEach((value, index) => {
                const x = padding + index * stepX;
                const y = padding + chartHeight - (value / maxRev) * chartHeight;
                
                if (index === 0) {
                    ctx.lineTo(x, y);
                } else {
                    // Мягкие кривые
                    const prevX = padding + (index - 1) * stepX;
                    const prevY = padding + chartHeight - (revenueData[index - 1] / maxRev) * chartHeight;
                    const cpX = (prevX + x) / 2;
                    ctx.quadraticCurveTo(cpX, prevY, x, y);
                }
            });
            
            ctx.lineTo(padding + chartWidth, padding + chartHeight);
            ctx.closePath();
            ctx.fillStyle = gradient;
            ctx.fill();
            
            // Мягкая линия доходов
            ctx.strokeStyle = '#8b5cf6';
            ctx.lineWidth = 3;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.beginPath();
            
            revenueData.forEach((value, index) => {
                const x = padding + index * stepX;
                const y = padding + chartHeight - (value / maxRev) * chartHeight;
                
                if (index === 0) {
                    ctx.moveTo(x, y);
                } else {
                    const prevX = padding + (index - 1) * stepX;
                    const prevY = padding + chartHeight - (revenueData[index - 1] / maxRev) * chartHeight;
                    const cpX = (prevX + x) / 2;
                    ctx.quadraticCurveTo(cpX, prevY, x, y);
                }
            });
            
            ctx.stroke();
        }

        // График регистраций (мягкая линия)
        if (registrationData.some(v => v > 0)) {
            ctx.strokeStyle = '#3b82f6';
            ctx.lineWidth = 3;
            ctx.beginPath();
            
            registrationData.forEach((value, index) => {
                const x = padding + index * stepX;
                const y = padding + chartHeight - (value / maxReg) * chartHeight;
                
                if (index === 0) {
                    ctx.moveTo(x, y);
                } else {
                    const prevX = padding + (index - 1) * stepX;
                    const prevY = padding + chartHeight - (registrationData[index - 1] / maxReg) * chartHeight;
                    const cpX = (prevX + x) / 2;
                    ctx.quadraticCurveTo(cpX, prevY, x, y);
                }
            });
            
            ctx.stroke();
            
            // Мягкие точки
            ctx.fillStyle = '#3b82f6';
            registrationData.forEach((value, index) => {
                const x = padding + index * stepX;
                const y = padding + chartHeight - (value / maxReg) * chartHeight;
                
                ctx.beginPath();
                ctx.arc(x, y, 5, 0, Math.PI * 2);
                ctx.fill();
                
                // Белый центр
                ctx.beginPath();
                ctx.arc(x, y, 2, 0, Math.PI * 2);
                ctx.fillStyle = 'white';
                ctx.fill();
                ctx.fillStyle = '#3b82f6';
            });
        }
        
        // Мягкие подписи
        ctx.fillStyle = '#64748b';
        ctx.font = '500 12px Inter, system-ui';
        ctx.textAlign = 'center';
        
        // Подписи дней
        chartLabels.forEach((label, index) => {
            const x = padding + index * stepX;
            ctx.fillText(label, x, padding + chartHeight + 25);
        });
        
        // Подписи значений регистраций (левая ось)
        ctx.textAlign = 'right';
        ctx.fillStyle = '#3b82f6';
        for (let i = 0; i <= 4; i++) {
            const value = Math.round((maxReg / 4) * (4 - i));
            const y = padding + (chartHeight / 4) * i + 4;
            ctx.fillText(value.toString(), padding - 15, y);
        }
        
        // Подписи значений доходов (правая ось)
        ctx.textAlign = 'left';
        ctx.fillStyle = '#8b5cf6';
        for (let i = 0; i <= 4; i++) {
            const value = Math.round((maxRev / 4) * (4 - i));
            const y = padding + (chartHeight / 4) * i + 4;
            ctx.fillText('₽' + value.toLocaleString(), padding + chartWidth + 15, y);
        }

        // Элегантная легенда
        ctx.font = '600 14px Inter, system-ui';
        ctx.textAlign = 'left';
        
        // Регистрации
        ctx.fillStyle = '#3b82f6';
        ctx.beginPath();
        ctx.arc(padding + 10, 25, 6, 0, Math.PI * 2);
        ctx.fill();
        ctx.fillText('Новые пользователи', padding + 25, 30);
        
        // Доходы
        ctx.fillStyle = '#8b5cf6';
        ctx.beginPath();
        ctx.arc(padding + 180, 25, 6, 0, Math.PI * 2);
        ctx.fill();
        ctx.fillText('Доходы (₽)', padding + 195, 30);
    }

    // Обновление графика при изменении размера
    window.addEventListener('resize', function() {
        setTimeout(initChart, 100);
    });
</script>

<?php require_once 'footer.php'; ?>