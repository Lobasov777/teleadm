<?php
$pageTitle = 'Мониторинг активности';
require_once 'header.php';

// Фильтры
$period = $_GET['period'] ?? '24h';
$userType = $_GET['user_type'] ?? 'all';
$campaignSearch = trim($_GET['search'] ?? '');

// Условие для периода
$dateCondition = "";
switch ($period) {
    case '1h':
        $dateCondition = "AND ap.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)";
        break;
    case '24h':
        $dateCondition = "AND ap.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        break;
    case '7d':
        $dateCondition = "AND ap.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case '30d':
        $dateCondition = "AND ap.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        break;
}

try {
    // Получаем последние размещения
    $query = "
        SELECT ap.*, c.campaign_name, c.channel_name as advertised_channel,
               u.id as user_id, u.username, u.email, u.role as user_role
        FROM ad_placements ap
        JOIN campaigns c ON ap.campaign_id = c.id
        JOIN users u ON c.user_id = u.id
        WHERE 1=1
    ";

    $params = [];
    if ($userType !== 'all' && in_array($userType, ['user', 'premium'])) {
        $query .= " AND u.role = ?";
        $params[] = $userType;
    }

    if ($campaignSearch) {
        $query .= " AND (c.campaign_name LIKE ? OR c.channel_name LIKE ? OR ap.channel_name LIKE ?)";
        $searchParam = "%$campaignSearch%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    $query .= " $dateCondition ORDER BY ap.created_at DESC LIMIT 50";

    $stmt = db()->prepare($query);
    $stmt->execute($params);
    $placements = $stmt->fetchAll();

    // Статистика за период
    $statsQuery = "
        SELECT COUNT(DISTINCT u.id) as active_users,
               COUNT(DISTINCT c.id) as active_campaigns,
               COUNT(ap.id) as total_placements,
               COALESCE(SUM(ap.price), 0) as total_spent,
               COALESCE(SUM(ap.subscribers_gained), 0) as total_subscribers,
               COALESCE(AVG(ap.cpm), 0) as avg_cpm
        FROM ad_placements ap
        JOIN campaigns c ON ap.campaign_id = c.id
        JOIN users u ON c.user_id = u.id
        WHERE 1=1 $dateCondition
    ";

    if ($userType !== 'all' && in_array($userType, ['user', 'premium'])) {
        $statsQuery .= " AND u.role = ?";
        $stmt = db()->prepare($statsQuery);
        $stmt->execute([$userType]);
    } else {
        $stmt = db()->query($statsQuery);
    }
    $stats = $stmt->fetch();

    // Активность по часам для графика
    $hoursQuery = "
        SELECT HOUR(ap.created_at) as hour, COUNT(*) as count
        FROM ad_placements ap
        JOIN campaigns c ON ap.campaign_id = c.id
        JOIN users u ON c.user_id = u.id
        WHERE ap.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ";

    if ($userType !== 'all' && in_array($userType, ['user', 'premium'])) {
        $hoursQuery .= " AND u.role = ?";
        $hoursQuery .= " GROUP BY HOUR(ap.created_at) ORDER BY hour";
        $stmt = db()->prepare($hoursQuery);
        $stmt->execute([$userType]);
    } else {
        $hoursQuery .= " GROUP BY HOUR(ap.created_at) ORDER BY hour";
        $stmt = db()->query($hoursQuery);
    }
    $hoursData = $stmt->fetchAll();

    // Подготовка данных для графика
    $chartLabels = [];
    $chartData = [];
    for ($i = 0; $i < 24; $i++) {
        $chartLabels[] = sprintf('%02d:00', $i);
        $chartData[$i] = 0;
    }

    foreach ($hoursData as $hour) {
        $chartData[$hour['hour']] = (int)$hour['count'];
    }
    $chartData = array_values($chartData);

    // Топ пользователей по активности
    $topUsersQuery = "
        SELECT u.username, u.email, u.role,
               COUNT(ap.id) as placements_count,
               COALESCE(SUM(ap.price), 0) as total_spent,
               MAX(ap.created_at) as last_activity
        FROM users u
        JOIN campaigns c ON u.id = c.user_id
        JOIN ad_placements ap ON c.id = ap.campaign_id
        WHERE 1=1 $dateCondition
    ";

    if ($userType !== 'all' && in_array($userType, ['user', 'premium'])) {
        $topUsersQuery .= " AND u.role = ?";
        $topUsersQuery .= " GROUP BY u.id ORDER BY placements_count DESC LIMIT 10";
        $stmt = db()->prepare($topUsersQuery);
        $stmt->execute([$userType]);
    } else {
        $topUsersQuery .= " GROUP BY u.id ORDER BY placements_count DESC LIMIT 10";
        $stmt = db()->query($topUsersQuery);
    }
    $topUsers = $stmt->fetchAll();

    // Статистика системы
    $systemQuery = "
        SELECT 
            (SELECT COUNT(*) FROM users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as online_users,
            (SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as new_users_today,
            (SELECT COUNT(*) FROM payments WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as payments_today,
            (SELECT COUNT(*) FROM admin_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as admin_actions_today
    ";

    $stmt = db()->query($systemQuery);
    $systemStats = $stmt->fetch();

} catch (Exception $e) {
    error_log("Ошибка в мониторинге: " . $e->getMessage());
    
    // Значения по умолчанию при ошибке
    $stats = [
        'active_users' => 0, 'active_campaigns' => 0, 'total_placements' => 0,
        'total_spent' => 0, 'total_subscribers' => 0, 'avg_cpm' => 0
    ];
    $systemStats = [
        'online_users' => 0, 'new_users_today' => 0,
        'payments_today' => 0, 'admin_actions_today' => 0
    ];
    $placements = $topUsers = [];
    $chartLabels = ['00:00', '01:00', '02:00', '03:00', '04:00', '05:00'];
    $chartData = [0, 0, 0, 0, 0, 0];
}
?>

<style>
    /* Основные стили страницы */
    .page-container {
        padding: 32px;
        max-width: 1400px;
        margin: 0 auto;
    }

    /* Статистика мониторинга */
    .monitoring-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 32px;
    }

    .monitoring-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        opacity: 0;
        transform: translateY(20px);
    }

    .monitoring-card.animate-in {
        opacity: 1;
        transform: translateY(0);
    }

    .monitoring-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--gradient);
    }

    .monitoring-card.active-users::before { --gradient: linear-gradient(135deg, #10b981, #047857); }
    .monitoring-card.campaigns::before { --gradient: linear-gradient(135deg, #3b82f6, #1e40af); }
    .monitoring-card.placements::before { --gradient: linear-gradient(135deg, #f59e0b, #d97706); }
    .monitoring-card.revenue::before { --gradient: linear-gradient(135deg, #8b5cf6, #7c3aed); }

    .monitoring-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    }

    .monitoring-card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 16px;
    }

    .monitoring-card-icon {
        width: 56px;
        height: 56px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 24px;
        background: var(--gradient);
    }

    .monitoring-card-icon.active-users { --gradient: linear-gradient(135deg, #10b981, #047857); }
    .monitoring-card-icon.campaigns { --gradient: linear-gradient(135deg, #3b82f6, #1e40af); }
    .monitoring-card-icon.placements { --gradient: linear-gradient(135deg, #f59e0b, #d97706); }
    .monitoring-card-icon.revenue { --gradient: linear-gradient(135deg, #8b5cf6, #7c3aed); }

    .monitoring-card-content {
        flex: 1;
        margin-right: 16px;
    }

    .monitoring-card-value {
        font-size: 32px;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 6px;
        line-height: 1;
        letter-spacing: -0.025em;
    }

    .monitoring-card-label {
        font-size: 15px;
        color: #64748b;
        font-weight: 600;
        margin-bottom: 12px;
    }

    .monitoring-card-trend {
        font-size: 12px;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .monitoring-card-trend.up {
        background: rgba(16, 185, 129, 0.1);
        color: #047857;
    }

    .monitoring-card-trend.neutral {
        background: #f1f5f9;
        color: #64748b;
    }

    /* Фильтры */
    .filters-section {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 24px;
        margin-bottom: 24px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        opacity: 0;
        transform: translateY(20px);
        transition: all 0.4s ease;
    }

    .filters-section.animate-in {
        opacity: 1;
        transform: translateY(0);
    }

    .filters-grid {
        display: grid;
        grid-template-columns: 1fr 1fr 2fr auto;
        gap: 16px;
        align-items: end;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .form-label {
        font-size: 14px;
        font-weight: 600;
        color: #374151;
    }

    .form-input, .form-select {
        padding: 12px 16px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 14px;
        background: white;
        transition: all 0.2s ease;
    }

    .form-input:focus, .form-select:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    /* График */
    .chart-section {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 32px;
        margin-bottom: 24px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        opacity: 0;
        transform: translateY(20px);
        transition: all 0.4s ease;
    }

    .chart-section.animate-in {
        opacity: 1;
        transform: translateY(0);
    }

    .chart-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 28px;
    }

    .chart-title {
        font-size: 22px;
        font-weight: 700;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 12px;
        letter-spacing: -0.025em;
    }

    .chart-controls {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .refresh-button {
        background: linear-gradient(135deg, #10b981, #047857);
        color: white;
        border: none;
        padding: 10px 16px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .refresh-button:hover {
        background: linear-gradient(135deg, #047857, #065f46);
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(16, 185, 129, 0.3);
    }

    .auto-refresh {
        font-size: 12px;
        color: #64748b;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .live-dot {
        width: 8px;
        height: 8px;
        background: #10b981;
        border-radius: 50%;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }

    .chart-container {
        height: 350px;
        position: relative;
        background: #fafafa;
        border-radius: 12px;
        overflow: hidden;
    }

    /* Активность и топ пользователи */
    .activity-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 24px;
    }

    .activity-table, .top-users-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        opacity: 0;
        transform: translateY(20px);
        transition: all 0.4s ease;
    }

    .activity-table.animate-in, .top-users-card.animate-in {
        opacity: 1;
        transform: translateY(0);
    }

    .activity-header, .top-users-header {
        padding: 24px 28px;
        border-bottom: 1px solid #e2e8f0;
        background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    }

    .activity-title, .top-users-title {
        font-size: 18px;
        font-weight: 700;
        color: #0f172a;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
        letter-spacing: -0.025em;
    }

    .live-indicator {
        width: 8px;
        height: 8px;
        background: #10b981;
        border-radius: 50%;
        animation: pulse 2s infinite;
    }

    /* Список активности */
    .activity-list {
        max-height: 500px;
        overflow-y: auto;
    }

    .activity-item {
        padding: 20px 28px;
        border-bottom: 1px solid #f1f5f9;
        transition: all 0.2s ease;
    }

    .activity-item:hover {
        background: #f8fafc;
    }

    .activity-item:last-child {
        border-bottom: none;
    }

    .activity-time {
        font-size: 12px;
        color: #94a3b8;
        margin-bottom: 8px;
        font-weight: 500;
    }

    .activity-content {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .activity-user {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #3b82f6, #1e40af);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 16px;
        flex-shrink: 0;
    }

    .activity-details {
        flex: 1;
        min-width: 0;
    }

    .activity-details h4 {
        font-weight: 600;
        margin: 0 0 4px 0;
        font-size: 15px;
        color: #0f172a;
    }

    .activity-details p {
        margin: 0;
        font-size: 13px;
        color: #64748b;
        word-break: break-word;
    }

    .activity-meta {
        text-align: right;
        flex-shrink: 0;
    }

    .activity-amount {
        font-weight: 700;
        color: #10b981;
        font-size: 15px;
        margin-bottom: 2px;
    }

    .activity-reach {
        font-size: 12px;
        color: #64748b;
    }

    /* Топ пользователи */
    .top-users-list {
        padding: 16px 0;
    }

    .top-user-item {
        padding: 16px 28px;
        display: flex;
        align-items: center;
        gap: 16px;
        transition: all 0.2s ease;
    }

    .top-user-item:hover {
        background: #f8fafc;
    }

    .user-rank {
        width: 28px;
        height: 28px;
        background: #f1f5f9;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 12px;
        color: #64748b;
        flex-shrink: 0;
    }

    .user-rank.top-3 {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: white;
    }

    .user-avatar-small {
        width: 36px;
        height: 36px;
        background: linear-gradient(135deg, #3b82f6, #1e40af);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 14px;
        flex-shrink: 0;
    }

    .user-info-small {
        flex: 1;
        min-width: 0;
    }

    .user-info-small h5 {
        font-weight: 600;
        margin: 0 0 4px 0;
        font-size: 14px;
        color: #0f172a;
    }

    .user-info-small p {
        margin: 0;
        font-size: 12px;
        color: #64748b;
    }

    .user-stats {
        text-align: right;
        flex-shrink: 0;
    }

    .user-placements {
        font-weight: 700;
        color: #3b82f6;
        font-size: 15px;
        margin-bottom: 2px;
    }

    .user-spent {
        font-size: 12px;
        color: #64748b;
    }

    /* Empty states */
    .empty-state {
        text-align: center;
        padding: 60px 40px;
        color: #64748b;
    }

    .empty-icon {
        font-size: 48px;
        margin-bottom: 16px;
        opacity: 0.5;
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

    /* Анимации */
    .monitoring-card:nth-child(1) { transition-delay: 0.1s; }
    .monitoring-card:nth-child(2) { transition-delay: 0.2s; }
    .monitoring-card:nth-child(3) { transition-delay: 0.3s; }
    .monitoring-card:nth-child(4) { transition-delay: 0.4s; }

    /* Responsive */
    @media (max-width: 1024px) {
        .activity-grid {
            grid-template-columns: 1fr;
        }
        
        .filters-grid {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (max-width: 768px) {
        .page-container {
            padding: 20px;
        }
        
        .monitoring-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .filters-grid {
            grid-template-columns: 1fr;
        }
        
        .chart-controls {
            flex-direction: column;
            gap: 8px;
        }
        
        .auto-refresh {
            display: none;
        }
    }

    @media (max-width: 480px) {
        .monitoring-grid {
            grid-template-columns: 1fr;
        }
        
        .activity-content {
            gap: 12px;
        }
        
        .activity-user {
            width: 32px;
            height: 32px;
            font-size: 14px;
        }
    }
</style>

<div class="page-container">
    <!-- Статистика мониторинга -->
    <div class="monitoring-grid">
        <div class="monitoring-card active-users">
            <div class="monitoring-card-header">
                <div class="monitoring-card-content">
                    <div class="monitoring-card-value"><?php echo number_format($stats['active_users'] ?? 0); ?></div>
                    <div class="monitoring-card-label">Активных пользователей</div>
                    <div class="monitoring-card-trend up">
                        <div class="live-dot"></div>
                        <?php echo $systemStats['online_users']; ?> онлайн
                    </div>
                </div>
                <div class="monitoring-card-icon active-users">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none">
                        <path d="M17 21V19C17 17.9391 16.5786 16.9217 15.8284 16.1716C15.0783 15.4214 14.0609 15 13 15H5C3.93913 15 2.92172 15.4214 2.17157 16.1716C1.42143 16.9217 1 17.9391 1 19V21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="9" cy="7" r="4" stroke="currentColor" stroke-width="2"/>
                        <path d="M23 21V19C22.9993 18.1137 22.7044 17.2528 22.1614 16.5523C21.6184 15.8519 20.8581 15.3516 20 15.13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M16 3.13C16.8604 3.35031 17.623 3.85071 18.1676 4.55232C18.7122 5.25392 19.0078 6.11683 19.0078 7.005C19.0078 7.89318 18.7122 8.75608 18.1676 9.45769C17.623 10.1593 16.8604 10.6597 16 10.88" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="monitoring-card campaigns">
            <div class="monitoring-card-header">
                <div class="monitoring-card-content">
                    <div class="monitoring-card-value"><?php echo number_format($stats['active_campaigns'] ?? 0); ?></div>
                    <div class="monitoring-card-label">Активных кампаний</div>
                    <div class="monitoring-card-trend neutral">За период</div>
                </div>
                <div class="monitoring-card-icon campaigns">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2" stroke="currentColor" stroke-width="2"/>
                        <line x1="8" y1="21" x2="16" y2="21" stroke="currentColor" stroke-width="2"/>
                        <line x1="12" y1="17" x2="12" y2="21" stroke="currentColor" stroke-width="2"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="monitoring-card placements">
            <div class="monitoring-card-header">
                <div class="monitoring-card-content">
                    <div class="monitoring-card-value"><?php echo number_format($stats['total_placements'] ?? 0); ?></div>
                    <div class="monitoring-card-label">Размещений</div>
                    <div class="monitoring-card-trend <?php echo $stats['avg_cpm'] > 0 ? 'up' : 'neutral'; ?>">
                        CPM: ₽<?php echo number_format($stats['avg_cpm'] ?? 0, 2); ?>
                    </div>
                </div>
                <div class="monitoring-card-icon placements">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none">
                        <path d="M18 20V10" stroke="currentColor" stroke-width="2"/>
                        <path d="M12 20V4" stroke="currentColor" stroke-width="2"/>
                        <path d="M6 20V14" stroke="currentColor" stroke-width="2"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="monitoring-card revenue">
            <div class="monitoring-card-header">
                <div class="monitoring-card-content">
                    <div class="monitoring-card-value">₽<?php echo number_format($stats['total_spent'] ?? 0, 0, ',', ' '); ?></div>
                    <div class="monitoring-card-label">Потрачено</div>
                    <div class="monitoring-card-trend <?php echo $stats['total_subscribers'] > 0 ? 'up' : 'neutral'; ?>">
                        +<?php echo number_format($stats['total_subscribers'] ?? 0); ?> подп.
                    </div>
                </div>
                <div class="monitoring-card-icon revenue">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none">
                        <line x1="12" y1="1" x2="12" y2="23" stroke="currentColor" stroke-width="2"/>
                        <path d="M17 5H9.5C8.57174 5 7.6815 5.36875 7.02513 6.02513C6.36875 6.6815 6 7.57174 6 8.5C6 9.42826 6.36875 10.3185 7.02513 10.9749C7.6815 11.6313 8.57174 12 9.5 12H14.5C15.4283 12 16.3185 12.3687 16.9749 13.0251C17.6313 13.6815 18 14.5717 18 15.5C18 16.4283 17.6313 17.3185 16.9749 17.9749C16.3185 18.6313 15.4283 19 14.5 19H6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Фильтры -->
    <div class="filters-section">
        <form method="GET" class="filters-grid">
            <div class="form-group">
                <label class="form-label">Период мониторинга</label>
                <select name="period" class="form-select">
                    <option value="1h" <?php echo $period === '1h' ? 'selected' : ''; ?>>Последний час</option>
                    <option value="24h" <?php echo $period === '24h' ? 'selected' : ''; ?>>24 часа</option>
                    <option value="7d" <?php echo $period === '7d' ? 'selected' : ''; ?>>7 дней</option>
                    <option value="30d" <?php echo $period === '30d' ? 'selected' : ''; ?>>30 дней</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Тип пользователей</label>
                <select name="user_type" class="form-select">
                    <option value="all" <?php echo $userType === 'all' ? 'selected' : ''; ?>>Все пользователи</option>
                    <option value="premium" <?php echo $userType === 'premium' ? 'selected' : ''; ?>>Premium</option>
                    <option value="user" <?php echo $userType === 'user' ? 'selected' : ''; ?>>Базовые</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Поиск по кампании/каналу</label>
                <input type="text" name="search" class="form-input" placeholder="Название кампании или канала" value="<?php echo htmlspecialchars($campaignSearch); ?>">
            </div>
            <button type="submit" class="btn btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                    <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/>
                    <path d="M21 21L16.65 16.65" stroke="currentColor" stroke-width="2"/>
                </svg>
                Применить
            </button>
        </form>
    </div>

    <!-- График активности -->
    <div class="chart-section">
        <div class="chart-header">
            <h3 class="chart-title">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <path d="M3 3V21H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M9 9L12 6L16 10L20 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Активность по часам (24ч)
            </h3>
            <div class="chart-controls">
                <button class="refresh-button" onclick="location.reload()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                        <path d="M3 12C3 12 3 4 12 4C18 4 21 8 21 12" stroke="currentColor" stroke-width="2"/>
                        <path d="M21 12C21 12 21 20 12 20C6 20 3 16 3 12" stroke="currentColor" stroke-width="2"/>
                        <path d="M8 4L3 4L3 9" stroke="currentColor" stroke-width="2"/>
                        <path d="M16 20L21 20L21 15" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    Обновить
                </button>
                <div class="auto-refresh">
                    <div class="live-dot"></div>
                    Автообновление каждые 30 сек
                </div>
            </div>
        </div>
        <div class="chart-container">
            <canvas id="activityChart" style="width: 100%; height: 100%;"></canvas>
        </div>
    </div>

    <!-- Активность и топ пользователи -->
    <div class="activity-grid">
        <!-- Последняя активность -->
        <div class="activity-table">
            <div class="activity-header">
                <h3 class="activity-title">
                    <div class="live-indicator"></div>
                    Последние размещения
                </h3>
            </div>
            <div class="activity-list">
                <?php if (empty($placements)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📭</div>
                        <div class="empty-title">Нет активности</div>
                        <div class="empty-desc">За выбранный период размещений не было</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($placements as $placement): ?>
                    <div class="activity-item">
                        <div class="activity-time">
                            <?php echo date('d.m.Y H:i:s', strtotime($placement['created_at'])); ?>
                        </div>
                        <div class="activity-content">
                            <div class="activity-user">
                                <?php echo strtoupper(substr($placement['username'], 0, 1)); ?>
                            </div>
                            <div class="activity-details">
                                <h4><?php echo htmlspecialchars($placement['username']); ?></h4>
                                <p><?php echo htmlspecialchars($placement['campaign_name']); ?> → <?php echo htmlspecialchars($placement['channel_name']); ?></p>
                            </div>
                            <div class="activity-meta">
                                <div class="activity-amount">₽<?php echo number_format($placement['price'], 0, ',', ' '); ?></div>
                                <div class="activity-reach"><?php echo number_format($placement['reach_24h']); ?> охват</div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Топ пользователи -->
        <div class="top-users-card">
            <div class="top-users-header">
                <h3 class="top-users-title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                        <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Топ пользователи
                </h3>
            </div>
            <div class="top-users-list">
                <?php if (empty($topUsers)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📊</div>
                        <div class="empty-title">Нет данных</div>
                        <div class="empty-desc">За выбранный период нет активности</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($topUsers as $index => $user): ?>
                    <div class="top-user-item">
                        <div class="user-rank <?php echo $index < 3 ? 'top-3' : ''; ?>">
                            <?php echo $index + 1; ?>
                        </div>
                        <div class="user-avatar-small">
                            <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                        </div>
                        <div class="user-info-small">
                            <h5><?php echo htmlspecialchars($user['username']); ?></h5>
                            <p>
                                <?php if ($user['role'] === 'premium'): ?>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" style="display: inline; color: #f59e0b;">
                                        <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" stroke="currentColor" stroke-width="2"/>
                                    </svg>
                                    Premium
                                <?php else: ?>
                                    Базовый
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="user-stats">
                            <div class="user-placements"><?php echo $user['placements_count']; ?></div>
                            <div class="user-spent">₽<?php echo number_format($user['total_spent'], 0, ',', ' '); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        document.querySelectorAll('.monitoring-card, .filters-section, .chart-section, .activity-table, .top-users-card').forEach(el => {
            observer.observe(el);
        });

        // График активности
        const ctx = document.getElementById('activityChart');
        if (ctx) {
            const chartData = <?php echo json_encode($chartData); ?>;
            const chartLabels = <?php echo json_encode($chartLabels); ?>;
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        label: 'Размещений',
                        data: chartData,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#3b82f6',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.9)',
                            titleColor: '#ffffff',
                            bodyColor: '#ffffff',
                            borderColor: '#3b82f6',
                            borderWidth: 1,
                            cornerRadius: 8,
                            displayColors: false,
                            callbacks: {
                                title: function(context) {
                                    return 'Время: ' + context[0].label;
                                },
                                label: function(context) {
                                    return 'Размещений: ' + context.parsed.y;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                color: 'rgba(226, 232, 240, 0.5)',
                                drawBorder: false
                            },
                            ticks: {
                                color: '#64748b',
                                font: {
                                    size: 12,
                                    weight: '500'
                                }
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(226, 232, 240, 0.5)',
                                drawBorder: false
                            },
                            ticks: {
                                color: '#64748b',
                                font: {
                                    size: 12,
                                    weight: '500'
                                },
                                callback: function(value) {
                                    return Number.isInteger(value) ? value : '';
                                }
                            }
                        }
                    },
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    }
                }
            });
        }

        // Автообновление каждые 30 секунд
        setInterval(function() {
            if (document.visibilityState === 'visible') {
                location.reload();
            }
        }, 30000);

        // Остановка автообновления при скрытии страницы
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'hidden') {
                // Можно добавить логику для остановки обновлений
            }
        });
    });

    // Обработка кликов по карточкам статистики
    document.querySelectorAll('.monitoring-card').forEach(card => {
        card.style.cursor = 'pointer';
        card.addEventListener('click', function() {
            // Можно добавить переходы к детальной статистике
            const cardType = this.classList[1]; // active-users, campaigns, etc.
            console.log('Клик по карточке:', cardType);
        });
    });
</script>

<?php require_once 'footer.php'; ?>