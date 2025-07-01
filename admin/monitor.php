<?php$pageTitle = 'Мониторинг активности';
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
}?>



<div class="page-container">
    <!-- Статистика мониторинга -->
    <div class="monitoring-grid">
        <div class="monitoring-card active-users">
            <div class="monitoring-card-header">
                <div class="monitoring-card-content">
                    <div class="monitoring-card-value"><?php echo number_format($stats['active_users'] ?? 0);?></div>
                    <div class="monitoring-card-label">Активных пользователей</div>
                    <div class="monitoring-card-trend up">
                        <div class="live-dot"></div>
                        <?php echo $systemStats['online_users'];?> онлайн
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
                    <div class="monitoring-card-value"><?php echo number_format($stats['active_campaigns'] ?? 0);?></div>
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
                    <div class="monitoring-card-value"><?php echo number_format($stats['total_placements'] ?? 0);?></div>
                    <div class="monitoring-card-label">Размещений</div>
                    <div class="monitoring-card-trend <?php echo $stats['avg_cpm'] > 0 ? 'up' : 'neutral';?>">
                        CPM: ₽<?php echo number_format($stats['avg_cpm'] ?? 0, 2);?>
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
                    <div class="monitoring-card-value">₽<?php echo number_format($stats['total_spent'] ?? 0, 0, ',', ' ');?></div>
                    <div class="monitoring-card-label">Потрачено</div>
                    <div class="monitoring-card-trend <?php echo $stats['total_subscribers'] > 0 ? 'up' : 'neutral';?>">
                        +<?php echo number_format($stats['total_subscribers'] ?? 0);?> подп.
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
                    <option value="1h" <?php echo $period === '1h' ? 'selected' : '';?>>Последний час</option>
                    <option value="24h" <?php echo $period === '24h' ? 'selected' : '';?>>24 часа</option>
                    <option value="7d" <?php echo $period === '7d' ? 'selected' : '';?>>7 дней</option>
                    <option value="30d" <?php echo $period === '30d' ? 'selected' : '';?>>30 дней</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Тип пользователей</label>
                <select name="user_type" class="form-select">
                    <option value="all" <?php echo $userType === 'all' ? 'selected' : '';?>>Все пользователи</option>
                    <option value="premium" <?php echo $userType === 'premium' ? 'selected' : '';?>>Premium</option>
                    <option value="user" <?php echo $userType === 'user' ? 'selected' : '';?>>Базовые</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Поиск по кампании/каналу</label>
                <input type="text" name="search" class="form-input" placeholder="Название кампании или канала" value="<?php echo htmlspecialchars($campaignSearch);?>">
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
                <?php if (empty($placements)):?>
                    <div class="empty-state">
                        <div class="empty-icon">📭</div>
                        <div class="empty-title">Нет активности</div>
                        <div class="empty-desc">За выбранный период размещений не было</div>
                    </div>
                <?php else:?>
                    <?php foreach ($placements as $placement):?>
                    <div class="activity-item">
                        <div class="activity-time">
                            <?php echo date('d.m.Y H:i:s', strtotime($placement['created_at']));?>
                        </div>
                        <div class="activity-content">
                            <div class="activity-user">
                                <?php echo strtoupper(substr($placement['username'], 0, 1));?>
                            </div>
                            <div class="activity-details">
                                <h4><?php echo htmlspecialchars($placement['username']);?></h4>
                                <p><?php echo htmlspecialchars($placement['campaign_name']);?> → <?php echo htmlspecialchars($placement['channel_name']);?></p>
                            </div>
                            <div class="activity-meta">
                                <div class="activity-amount">₽<?php echo number_format($placement['price'], 0, ',', ' ');?></div>
                                <div class="activity-reach"><?php echo number_format($placement['reach_24h']);?> охват</div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach;?>
                <?php endif;?>
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
                <?php if (empty($topUsers)):?>
                    <div class="empty-state">
                        <div class="empty-icon">📊</div>
                        <div class="empty-title">Нет данных</div>
                        <div class="empty-desc">За выбранный период нет активности</div>
                    </div>
                <?php else:?>
                    <?php foreach ($topUsers as $index => $user):?>
                    <div class="top-user-item">
                        <div class="user-rank <?php echo $index < 3 ? 'top-3' : '';?>">
                            <?php echo $index + 1;?>
                        </div>
                        <div class="user-avatar-small">
                            <?php echo strtoupper(substr($user['username'], 0, 1));?>
                        </div>
                        <div class="user-info-small">
                            <h5><?php echo htmlspecialchars($user['username']);?></h5>
                            <p>
                                <?php if ($user['role'] === 'premium'):?>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" style="display: inline; color: #f59e0b;">
                                        <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" stroke="currentColor" stroke-width="2"/>
                                    </svg>
                                    Premium
                                <?php else:?>
                                    Базовый
                                <?php endif;?>
                            </p>
                        </div>
                        <div class="user-stats">
                            <div class="user-placements"><?php echo $user['placements_count'];?></div>
                            <div class="user-spent">₽<?php echo number_format($user['total_spent'], 0, ',', ' ');?></div>
                        </div>
                    </div>
                    <?php endforeach;?>
                <?php endif;?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


<?php require_once 'footer.php';?>