<?php
$pageTitle = 'Дашборд';
require_once 'header.php';

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

// Статистика по размещениям
$stmt = db()->query("
    SELECT 
        COUNT(*) as total_placements,
        SUM(price) as total_revenue,
        COUNT(DISTINCT campaign_id) as total_campaigns,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as placements_today
    FROM ad_placements
");
$placementStats = $stmt->fetch();

// Статистика платежей за текущий месяц
$stmt = db()->query("
    SELECT 
        COUNT(*) as payments_count,
        SUM(amount) as total_amount
    FROM payments 
    WHERE status = 'completed' 
    AND MONTH(paid_at) = MONTH(CURRENT_DATE()) 
    AND YEAR(paid_at) = YEAR(CURRENT_DATE())
");
$paymentStats = $stmt->fetch();

// Последние регистрации
$stmt = db()->query("
    SELECT id, username, email, role, created_at 
    FROM users 
    WHERE role != 'admin' 
    ORDER BY created_at DESC 
    LIMIT 5
");
$recentUsers = $stmt->fetchAll();

// Последние активные пользователи
$stmt = db()->query("
    SELECT 
        u.id, u.username, u.email, u.role, u.last_login,
        COUNT(DISTINCT c.id) as campaigns_count,
        COUNT(ap.id) as placements_count
    FROM users u
    LEFT JOIN campaigns c ON u.id = c.user_id
    LEFT JOIN ad_placements ap ON c.id = ap.campaign_id
    WHERE u.role != 'admin' AND u.last_login IS NOT NULL
    GROUP BY u.id
    ORDER BY u.last_login DESC
    LIMIT 5
");
$activeUsers = $stmt->fetchAll();

// График регистраций за последние 7 дней
$stmt = db()->query("
    SELECT DATE(created_at) as date, COUNT(*) as count
    FROM users 
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
    AND role != 'admin'
    GROUP BY DATE(created_at)
    ORDER BY date
");
$registrationData = $stmt->fetchAll();

$chartLabels = [];
$chartData = [];
foreach ($registrationData as $day) {
    $chartLabels[] = date('d.m', strtotime($day['date']));
    $chartData[] = $day['count'];
}
?>

<style>
    /* Dashboard Styles */
    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 24px;
        margin-bottom: 32px;
    }

    .stats-card {
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 24px;
        box-shadow: var(--shadow-sm);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .stats-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(135deg, var(--primary), var(--primary-light));
    }

    .stats-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-lg);
    }

    .stats-card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 16px;
    }

    .stats-card-icon {
        width: 48px;
        height: 48px;
        border-radius: var(--radius-lg);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
    }

    .stats-card-icon.users {
        background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    }

    .stats-card-icon.placements {
        background: linear-gradient(135deg, #10b981, #059669);
    }

    .stats-card-icon.revenue {
        background: linear-gradient(135deg, #f59e0b, #d97706);
    }

    .stats-card-icon.growth {
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    }

    .stats-card-value {
        font-size: 32px;
        font-weight: 800;
        color: var(--text-primary);
        margin-bottom: 4px;
        line-height: 1;
    }

    .stats-card-label {
        font-size: 14px;
        color: var(--text-secondary);
        font-weight: 500;
    }

    .stats-card-change {
        font-size: 12px;
        font-weight: 600;
        padding: 4px 8px;
        border-radius: 12px;
        margin-top: 8px;
        display: inline-block;
    }

    .stats-card-change.positive {
        background: #dcfce7;
        color: #166534;
    }

    .stats-card-change.negative {
        background: #fee2e2;
        color: #991b1b;
    }

    .content-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 32px;
        margin-bottom: 32px;
    }

    .chart-card {
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 24px;
        box-shadow: var(--shadow-sm);
    }

    .chart-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
    }

    .chart-title {
        font-size: 18px;
        font-weight: 600;
        color: var(--text-primary);
    }

    .chart-container {
        height: 300px;
        position: relative;
        background: var(--bg-secondary);
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-secondary);
    }

    .table-card {
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
    }

    .table-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border);
        background: var(--bg-secondary);
    }

    .table-title {
        font-size: 18px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }

    .table-container {
        overflow-x: auto;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
    }

    .data-table th {
        background: var(--bg-tertiary);
        padding: 12px 16px;
        text-align: left;
        font-weight: 600;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-secondary);
        border-bottom: 1px solid var(--border);
    }

    .data-table td {
        padding: 16px;
        border-bottom: 1px solid var(--border);
        font-size: 14px;
    }

    .data-table tr:hover {
        background: var(--bg-secondary);
    }

    .user-badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .user-badge.premium {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .user-badge.user {
        background: #f3f4f6;
        color: #374151;
    }

    .user-badge.blocked {
        background: #fee2e2;
        color: #991b1b;
    }

    .quick-actions {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 32px;
    }

    .quick-action {
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 20px;
        text-align: center;
        transition: all 0.3s ease;
        text-decoration: none;
        color: var(--text-primary);
    }

    .quick-action:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
        border-color: var(--primary);
    }

    .quick-action-icon {
        width: 40px;
        height: 40px;
        margin: 0 auto 12px;
        background: linear-gradient(135deg, var(--primary), var(--primary-light));
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
    }

    .quick-action-title {
        font-weight: 600;
        margin-bottom: 4px;
    }

    .quick-action-desc {
        font-size: 12px;
        color: var(--text-secondary);
    }

    @media (max-width: 768px) {
        .content-grid {
            grid-template-columns: 1fr;
        }
        
        .dashboard-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<!-- Dashboard Stats -->
<div class="dashboard-grid fade-in">
    <div class="stats-card">
        <div class="stats-card-header">
            <div>
                <div class="stats-card-value"><?php echo number_format($userStats['total_users']); ?></div>
                <div class="stats-card-label">Всего пользователей</div>
                <div class="stats-card-change positive">+<?php echo $userStats['registered_today']; ?> сегодня</div>
            </div>
            <div class="stats-card-icon users">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M20 21V19C20 17.9391 19.5786 16.9217 18.8284 16.1716C18.0783 15.4214 17.0609 15 16 15H8C6.93913 15 5.92172 15.4214 5.17157 16.1716C4.42143 16.9217 4 17.9391 4 19V21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2"/>
                </svg>
            </div>
        </div>
    </div>

    <div class="stats-card">
        <div class="stats-card-header">
            <div>
                <div class="stats-card-value"><?php echo number_format($userStats['premium_users']); ?></div>
                <div class="stats-card-label">Premium пользователей</div>
                <div class="stats-card-change positive"><?php echo round(($userStats['premium_users'] / max($userStats['total_users'], 1)) * 100, 1); ?>% от общего</div>
            </div>
            <div class="stats-card-icon revenue">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
        </div>
    </div>

    <div class="stats-card">
        <div class="stats-card-header">
            <div>
                <div class="stats-card-value"><?php echo number_format($placementStats['total_placements']); ?></div>
                <div class="stats-card-label">Всего размещений</div>
                <div class="stats-card-change positive">+<?php echo $placementStats['placements_today']; ?> сегодня</div>
            </div>
            <div class="stats-card-icon placements">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2" stroke="currentColor" stroke-width="2"/>
                    <rect x="9" y="9" width="6" height="6" stroke="currentColor" stroke-width="2"/>
                </svg>
            </div>
        </div>
    </div>

    <div class="stats-card">
        <div class="stats-card-header">
            <div>
                <div class="stats-card-value">₽<?php echo number_format($placementStats['total_revenue'], 0, ',', ' '); ?></div>
                <div class="stats-card-label">Общая выручка</div>
                <div class="stats-card-change positive">₽<?php echo number_format($paymentStats['total_amount'], 0, ',', ' '); ?> в этом месяце</div>
            </div>
            <div class="stats-card-icon growth">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <line x1="12" y1="1" x2="12" y2="23" stroke="currentColor" stroke-width="2"/>
                    <path d="M17 5H9.5C8.57174 5 7.6815 5.36875 7.02513 6.02513C6.36875 6.6815 6 7.57174 6 8.5C6 9.42826 6.36875 10.3185 7.02513 10.9749C7.6815 11.6313 8.57174 12 9.5 12H14.5C15.4283 12 16.3185 12.3687 16.9749 13.0251C17.6313 13.6815 18 14.5717 18 15.5C18 16.4283 17.6313 17.3185 16.9749 17.9749C16.3185 18.6313 15.4283 19 14.5 19H6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="quick-actions fade-in">
    <a href="/admin/users.php" class="quick-action">
        <div class="quick-action-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M20 21V19C20 17.9391 19.5786 16.9217 18.8284 16.1716C18.0783 15.4214 17.0609 15 16 15H8C6.93913 15 5.92172 15.4214 5.17157 16.1716C4.42143 16.9217 4 17.9391 4 19V21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2"/>
            </svg>
        </div>
        <div class="quick-action-title">Управление пользователями</div>
        <div class="quick-action-desc">Просмотр и редактирование</div>
    </a>
    
    <a href="/admin/payments.php" class="quick-action">
        <div class="quick-action-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="1" y="4" width="22" height="16" rx="2" ry="2" stroke="currentColor" stroke-width="2"/>
                <line x1="1" y1="10" x2="23" y2="10" stroke="currentColor" stroke-width="2"/>
            </svg>
        </div>
        <div class="quick-action-title">Платежи</div>
        <div class="quick-action-desc">История транзакций</div>
    </a>
    
    <a href="/admin/settings.php" class="quick-action">
        <div class="quick-action-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
                <path d="M19.4 15C19.2669 15.3016 19.2272 15.6362 19.286 15.9606C19.3448 16.285 19.4995 16.5843 19.73 16.82L19.79 16.88C19.976 17.0657 20.1235 17.2863 20.2241 17.5291C20.3248 17.7719 20.3766 18.0322 20.3766 18.295C20.3766 18.5578 20.3248 18.8181 20.2241 19.0609C20.1235 19.3037 19.976 19.5243 19.79 19.71C19.6043 19.896 19.3837 20.0435 19.1409 20.1441C18.8981 20.2448 18.6378 20.2966 18.375 20.2966C18.1122 20.2966 17.8519 20.2448 17.6091 20.1441C17.3663 20.0435 17.1457 19.896 16.96 19.71L16.9 19.65C16.6643 19.4195 16.365 19.2648 16.0406 19.206C15.7162 19.1472 15.3816 19.1869 15.08 19.32C14.7842 19.4468 14.532 19.6572 14.3543 19.9255C14.1766 20.1938 14.0813 20.5082 14.08 20.83V21C14.08 21.5304 13.8693 22.0391 13.4942 22.4142C13.1191 22.7893 12.6104 23 12.08 23C11.5496 23 11.0409 22.7893 10.6658 22.4142C10.2907 22.0391 10.08 21.5304 10.08 21V20.91C10.0723 20.579 9.96512 20.2573 9.77251 19.9887C9.5799 19.7201 9.31074 19.5176 9 19.41C8.69838 19.2769 8.36381 19.2372 8.03941 19.296C7.71502 19.3548 7.41568 19.5095 7.18 19.74L7.12 19.8C6.93425 19.986 6.71368 20.1335 6.47088 20.2341C6.22808 20.3348 5.96783 20.3866 5.705 20.3866C5.44217 20.3866 5.18192 20.3348 4.93912 20.2341C4.69632 20.1335 4.47575 19.986 4.29 19.8C4.10405 19.6143 3.95653 19.3937 3.85588 19.1509C3.75523 18.9081 3.70343 18.6478 3.70343 18.385C3.70343 18.1222 3.75523 17.8619 3.85588 17.6191C3.95653 17.3763 4.10405 17.1557 4.29 16.97L4.35 16.91C4.58054 16.6743 4.73519 16.375 4.794 16.0506C4.85282 15.7262 4.81312 15.3916 4.68 15.09C4.55324 14.7942 4.34276 14.542 4.07447 14.3643C3.80618 14.1866 3.49179 14.0913 3.17 14.09H3C2.46957 14.09 1.96086 13.8793 1.58579 13.5042C1.21071 13.1291 1 12.6204 1 12.09C1 11.5596 1.21071 11.0509 1.58579 10.6758C1.96086 10.3007 2.46957 10.09 3 10.09H3.09C3.42099 10.0823 3.742 9.97512 4.01062 9.78251C4.27925 9.5899 4.48167 9.32074 4.59 9.01C4.72312 8.70838 4.76282 8.37381 4.704 8.04941C4.64519 7.72502 4.49054 7.42568 4.26 7.19L4.2 7.13C4.01405 6.94425 3.86653 6.72368 3.76588 6.48088C3.66523 6.23808 3.61343 5.97783 3.61343 5.715C3.61343 5.45217 3.66523 5.19192 3.76588 4.94912C3.86653 4.70632 4.01405 4.48575 4.2 4.3C4.38575 4.11405 4.60632 3.96653 4.84912 3.86588C5.09192 3.76523 5.35217 3.71343 5.615 3.71343C5.87783 3.71343 6.13808 3.76523 6.38088 3.86588C6.62368 3.96653 6.84425 4.11405 7.03 4.3L7.09 4.36C7.32568 4.59054 7.62502 4.74519 7.94941 4.804C8.27381 4.86282 8.60838 4.82312 8.91 4.69H9C9.29577 4.56324 9.54802 4.35276 9.72569 4.08447C9.90337 3.81618 9.99872 3.50179 10 3.18V3C10 2.46957 10.2107 1.96086 10.5858 1.58579C10.9609 1.21071 11.4696 1 12 1C12.5304 1 13.0391 1.21071 13.4142 1.58579C13.7893 1.96086 14 2.46957 14 3V3.09C14.0013 3.41179 14.0966 3.72618 14.2743 3.99447C14.452 4.26276 14.7042 4.47324 15 4.6C15.3016 4.73312 15.6362 4.77282 15.9606 4.714C16.285 4.65519 16.5843 4.50054 16.82 4.27L16.88 4.21C17.0657 4.02405 17.2863 3.87653 17.5291 3.77588C17.7719 3.67523 18.0322 3.62343 18.295 3.62343C18.5578 3.62343 18.8181 3.67523 19.0609 3.77588C19.3037 3.87653 19.5243 4.02405 19.71 4.21C19.896 4.39575 20.0435 4.61632 20.1441 4.85912C20.2448 5.10192 20.2966 5.36217 20.2966 5.625C20.2966 5.88783 20.2448 6.14808 20.1441 6.39088C20.0435 6.63368 19.896 6.85425 19.71 7.04L19.65 7.1C19.4195 7.33568 19.2648 7.63502 19.206 7.95941C19.1472 8.28381 19.1869 8.61838 19.32 8.92V9C19.4468 9.29577 19.6572 9.54802 19.9255 9.72569C20.1938 9.90337 20.5082 9.99872 20.83 10H21C21.5304 10 22.0391 10.2107 22.4142 10.5858C22.7893 10.9609 23 11.4696 23 12C23 12.5304 22.7893 13.0391 22.4142 13.4142C22.0391 13.7893 21.5304 14 21 14H20.91C20.5882 14.0013 20.2738 14.0966 20.0055 14.2743C19.7372 14.452 19.5268 14.7042 19.4 15Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <div class="quick-action-title">Настройки системы</div>
        <div class="quick-action-desc">Конфигурация</div>
    </a>
    
    <a href="/admin/monitoring.php" class="quick-action">
        <div class="quick-action-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M3 3V21H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M9 9L12 6L16 10L20 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <div class="quick-action-title">Мониторинг</div>
        <div class="quick-action-desc">Активность системы</div>
    </a>
</div>

<!-- Content Grid -->
<div class="content-grid fade-in">
    <!-- Chart -->
    <div class="chart-card">
        <div class="chart-header">
            <h3 class="chart-title">Регистрации за последние 7 дней</h3>
        </div>
        <div class="chart-container">
            <p>График будет здесь (Chart.js)</p>
        </div>
    </div>

    <!-- Recent Users -->
    <div class="table-card">
        <div class="table-header">
            <h3 class="table-title">Последние регистрации</h3>
        </div>
        <div class="table-container">
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
                            <div>
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($user['username']); ?></div>
                                <div style="font-size: 12px; color: var(--text-secondary);"><?php echo htmlspecialchars($user['email']); ?></div>
                            </div>
                        </td>
                        <td>
                            <span class="user-badge <?php echo $user['role']; ?>">
                                <?php echo $user['role'] === 'premium' ? 'Premium' : 'Базовый'; ?>
                            </span>
                        </td>
                        <td style="font-size: 12px; color: var(--text-secondary);">
                            <?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Active Users -->
<div class="table-card fade-in">
    <div class="table-header">
        <h3 class="table-title">Активные пользователи</h3>
    </div>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Пользователь</th>
                    <th>Кампаний</th>
                    <th>Размещений</th>
                    <th>Последний вход</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($activeUsers as $user): ?>
                <tr>
                    <td>
                        <div>
                            <div style="font-weight: 600;"><?php echo htmlspecialchars($user['username']); ?></div>
                            <div style="font-size: 12px; color: var(--text-secondary);"><?php echo htmlspecialchars($user['email']); ?></div>
                        </div>
                    </td>
                    <td><?php echo $user['campaigns_count']; ?></td>
                    <td><?php echo $user['placements_count']; ?></td>
                    <td style="font-size: 12px; color: var(--text-secondary);">
                        <?php echo $user['last_login'] ? date('d.m.Y H:i', strtotime($user['last_login'])) : 'Никогда'; ?>
                    </td>
                    <td>
                        <a href="/admin/users.php?id=<?php echo $user['id']; ?>" class="btn btn-secondary" style="padding: 4px 8px; font-size: 12px;">
                            Просмотр
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    // Анимации
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('open');
    }

    // Intersection Observer для анимаций
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
            }
        });
    }, observerOptions);

    // Наблюдаем за всеми элементами с анимацией
    document.querySelectorAll('.fade-in').forEach(el => {
        observer.observe(el);
    });

    // График (можно добавить Chart.js)
    // const chartData = <?php echo json_encode($chartData); ?>;
    // const chartLabels = <?php echo json_encode($chartLabels); ?>;
</script>

</div>
    </main>
</div>
</body>
</html>