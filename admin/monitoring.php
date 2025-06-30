<?php
$pageTitle = 'Мониторинг системы';
require_once 'header.php';

// Фильтры
$period = $_GET['period'] ?? '24h';
$userType = $_GET['user_type'] ?? 'all';
$campaignSearch = $_GET['search'] ?? '';

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
if ($userType !== 'all') {
    $query .= " AND u.role = ?";
    $params[] = $userType;
}

if ($campaignSearch) {
    $query .= " AND (c.campaign_name LIKE ? OR c.channel_name LIKE ?)";
    $searchParam = "%$campaignSearch%";
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
           SUM(ap.price) as total_spent,
           SUM(ap.subscribers_gained) as total_subscribers,
           AVG(ap.cpm) as avg_cpm
    FROM ad_placements ap
    JOIN campaigns c ON ap.campaign_id = c.id
    JOIN users u ON c.user_id = u.id
    WHERE 1=1 $dateCondition
";

if ($userType !== 'all') {
    $statsQuery .= " AND u.role = '$userType'";
}

$stmt = db()->query($statsQuery);
$stats = $stmt->fetch();

// Активность по часам для графика
$hoursQuery = "
    SELECT HOUR(ap.created_at) as hour, COUNT(*) as count
    FROM ad_placements ap
    JOIN campaigns c ON ap.campaign_id = c.id
    JOIN users u ON c.user_id = u.id
    WHERE ap.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
";

if ($userType !== 'all') {
    $hoursQuery .= " AND u.role = '$userType'";
}

$hoursQuery .= " GROUP BY HOUR(ap.created_at) ORDER BY hour";

$stmt = db()->query($hoursQuery);
$hoursData = $stmt->fetchAll();

// Подготовка данных для графика
$chartLabels = [];
$chartData = [];
for ($i = 0; $i < 24; $i++) {
    $chartLabels[] = sprintf('%02d:00', $i);
    $chartData[$i] = 0;
}

foreach ($hoursData as $hour) {
    $chartData[$hour['hour']] = $hour['count'];
}
$chartData = array_values($chartData);

// Топ пользователей по активности
$topUsersQuery = "
    SELECT u.username, u.email, u.role,
           COUNT(ap.id) as placements_count,
           SUM(ap.price) as total_spent,
           MAX(ap.created_at) as last_activity
    FROM users u
    JOIN campaigns c ON u.id = c.user_id
    JOIN ad_placements ap ON c.id = ap.campaign_id
    WHERE 1=1 $dateCondition
    GROUP BY u.id
    ORDER BY placements_count DESC
    LIMIT 10
";

$stmt = db()->query($topUsersQuery);
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
?>

<style>
    .monitoring-grid {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr 1fr;
        gap: 20px;
        margin-bottom: 24px;
    }

    .monitoring-card {
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 20px;
        box-shadow: var(--shadow-sm);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .monitoring-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
    }

    .monitoring-card.active-users::before {
        background: linear-gradient(135deg, var(--success), #059669);
    }

    .monitoring-card.campaigns::before {
        background: linear-gradient(135deg, var(--primary), var(--primary-light));
    }

    .monitoring-card.placements::before {
        background: linear-gradient(135deg, var(--warning), #d97706);
    }

    .monitoring-card.revenue::before {
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    }

    .monitoring-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-lg);
    }

    .monitoring-card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 12px;
    }

    .monitoring-card-icon {
        width: 48px;
        height: 48px;
        border-radius: var(--radius-lg);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 20px;
    }

    .monitoring-card-icon.active-users {
        background: linear-gradient(135deg, var(--success), #059669);
    }

    .monitoring-card-icon.campaigns {
        background: linear-gradient(135deg, var(--primary), var(--primary-light));
    }

    .monitoring-card-icon.placements {
        background: linear-gradient(135deg, var(--warning), #d97706);
    }

    .monitoring-card-icon.revenue {
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    }

    .monitoring-card-value {
        font-size: 28px;
        font-weight: 800;
        color: var(--text-primary);
        margin-bottom: 4px;
        line-height: 1;
    }

    .monitoring-card-label {
        font-size: 14px;
        color: var(--text-secondary);
        font-weight: 500;
    }

    .monitoring-card-trend {
        font-size: 12px;
        font-weight: 600;
        padding: 4px 8px;
        border-radius: 12px;
        margin-top: 8px;
        display: inline-block;
    }

    .monitoring-card-trend.up {
        background: #dcfce7;
        color: #166534;
    }

    .monitoring-card-trend.down {
        background: #fee2e2;
        color: #991b1b;
    }

    .filters-section {
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 20px;
        margin-bottom: 24px;
        box-shadow: var(--shadow-sm);
    }

    .filters-grid {
        display: grid;
        grid-template-columns: 1fr 1fr 2fr auto;
        gap: 16px;
        align-items: end;
    }

    .chart-section {
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 24px;
        margin-bottom: 24px;
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
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .chart-container {
        height: 300px;
        background: var(--bg-secondary);
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-secondary);
        position: relative;
    }

    .activity-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 24px;
    }

    .activity-table {
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
    }

    .activity-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border);
        background: var(--bg-secondary);
        display: flex;
        justify-content: between;
        align-items: center;
    }

    .activity-title {
        font-size: 18px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .live-indicator {
        width: 8px;
        height: 8px;
        background: var(--success);
        border-radius: 50%;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% { opacity: 1; }
        50% { opacity: 0.5; }
        100% { opacity: 1; }
    }

    .activity-list {
        max-height: 400px;
        overflow-y: auto;
    }

    .activity-item {
        padding: 16px 24px;
        border-bottom: 1px solid var(--border);
        transition: background 0.2s;
    }

    .activity-item:hover {
        background: var(--bg-secondary);
    }

    .activity-item:last-child {
        border-bottom: none;
    }

    .activity-time {
        font-size: 12px;
        color: var(--text-tertiary);
        margin-bottom: 4px;
    }

    .activity-content {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .activity-user {
        width: 32px;
        height: 32px;
        background: linear-gradient(135deg, var(--primary), var(--primary-light));
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        font-size: 12px;
    }

    .activity-details h4 {
        font-weight: 600;
        margin: 0 0 2px 0;
        font-size: 14px;
        color: var(--text-primary);
    }

    .activity-details p {
        margin: 0;
        font-size: 12px;
        color: var(--text-secondary);
    }

    .activity-meta {
        margin-left: auto;
        text-align: right;
    }

    .activity-amount {
        font-weight: 600;
        color: var(--success);
        font-size: 14px;
    }

    .activity-reach {
        font-size: 12px;
        color: var(--text-secondary);
    }

    .top-users-card {
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
    }

    .top-users-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border);
        background: var(--bg-secondary);
    }

    .top-users-title {
        font-size: 18px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .top-users-list {
        padding: 16px 0;
    }

    .top-user-item {
        padding: 12px 24px;
        display: flex;
        align-items: center;
        gap: 12px;
        transition: background 0.2s;
    }

    .top-user-item:hover {
        background: var(--bg-secondary);
    }

    .user-rank {
        width: 24px;
        height: 24px;
        background: var(--bg-tertiary);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 12px;
        color: var(--text-secondary);
    }

    .user-rank.top-3 {
        background: linear-gradient(135deg, var(--warning), #d97706);
        color: white;
    }

    .user-avatar-small {
        width: 32px;
        height: 32px;
        background: linear-gradient(135deg, var(--primary), var(--primary-light));
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        font-size: 12px;
    }

    .user-info-small h5 {
        font-weight: 600;
        margin: 0 0 2px 0;
        font-size: 14px;
        color: var(--text-primary);
    }

    .user-info-small p {
        margin: 0;
        font-size: 12px;
        color: var(--text-secondary);
    }

    .user-stats {
        margin-left: auto;
        text-align: right;
    }

    .user-placements {
        font-weight: 600;
        color: var(--primary);
        font-size: 14px;
    }

    .user-spent {
        font-size: 12px;
        color: var(--text-secondary);
    }

    .refresh-button {
        background: var(--success);
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: var(--radius-md);
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .refresh-button:hover {
        background: #059669;
        transform: translateY(-1px);
    }

    .auto-refresh {
        font-size: 12px;
        color: var(--text-secondary);
        margin-left: 12px;
    }

    @media (max-width: 768px) {
        .monitoring-grid {
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        
        .filters-grid {
            grid-template-columns: 1fr;
        }
        
        .activity-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 480px) {
        .monitoring-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<!-- Статистика мониторинга -->
<div class="monitoring-grid fade-in">
    <div class="monitoring-card active-users">
        <div class="monitoring-card-header">
            <div>
                <div class="monitoring-card-value"><?php echo number_format($stats['active_users'] ?? 0); ?></div>
                <div class="monitoring-card-label">Активных пользователей</div>
                <div class="monitoring-card-trend up">+<?php echo $systemStats['online_users']; ?> онлайн</div>
            </div>
            <div class="monitoring-card-icon active-users">👥</div>
        </div>
    </div>

    <div class="monitoring-card campaigns">
        <div class="monitoring-card-header">
            <div>
                <div class="monitoring-card-value"><?php echo number_format($stats['active_campaigns'] ?? 0); ?></div>
                <div class="monitoring-card-label">Активных кампаний</div>
                <div class="monitoring-card-trend up">За период</div>
            </div>
            <div class="monitoring-card-icon campaigns">📊</div>
        </div>
    </div>

    <div class="monitoring-card placements">
        <div class="monitoring-card-header">
            <div>
                <div class="monitoring-card-value"><?php echo number_format($stats['total_placements'] ?? 0); ?></div>
                <div class="monitoring-card-label">Размещений</div>
                <div class="monitoring-card-trend up">CPM: ₽<?php echo number_format($stats['avg_cpm'] ?? 0, 2); ?></div>
            </div>
            <div class="monitoring-card-icon placements">📈</div>
        </div>
    </div>

    <div class="monitoring-card revenue">
        <div class="monitoring-card-header">
            <div>
                <div class="monitoring-card-value">₽<?php echo number_format($stats['total_spent'] ?? 0, 0, ',', ' '); ?></div>
                <div class="monitoring-card-label">Потрачено</div>
                <div class="monitoring-card-trend up">+<?php echo $stats['total_subscribers'] ?? 0; ?> подп.</div>
            </div>
            <div class="monitoring-card-icon revenue">💰</div>
        </div>
    </div>
</div>

<!-- Фильтры -->
<div class="filters-section fade-in">
    <form method="GET" class="filters-grid">
        <div class="form-group">
            <label class="form-label">Период</label>
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
                <option value="all" <?php echo $userType === 'all' ? 'selected' : ''; ?>>Все</option>
                <option value="premium" <?php echo $userType === 'premium' ? 'selected' : ''; ?>>Premium</option>
                <option value="user" <?php echo $userType === 'user' ? 'selected' : ''; ?>>Базовые</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Поиск по кампании/каналу</label>
            <input type="text" name="search" class="form-input" placeholder="Название кампании или канала" value="<?php echo htmlspecialchars($campaignSearch); ?>">
        </div>
        <button type="submit" class="btn btn-primary">Применить</button>
    </form>
</div>

<!-- График активности -->
<div class="chart-section fade-in">
    <div class="chart-header">
        <h3 class="chart-title">
            📊 Активность по часам (24ч)
        </h3>
        <div style="display: flex; align-items: center;">
            <button class="refresh-button" onclick="location.reload()">
                🔄 Обновить
            </button>
            <span class="auto-refresh">Автообновление каждые 30 сек</span>
        </div>
    </div>
    <div class="chart-container">
        <canvas id="activityChart" width="800" height="300"></canvas>
    </div>
</div>

<!-- Активность и топ пользователи -->
<div class="activity-grid fade-in">
    <!-- Последняя активность -->
    <div class="activity-table">
        <div class="activity-header">
            <h3 class="activity-title">
                <div class="live-indicator"></div>
                Последние размещения
            </h3>
        </div>
        <div class="activity-list">
            <?php foreach ($placements as $placement): ?>
            <div class="activity-item">
                <div class="activity-time">
                    <?php echo date('H:i:s', strtotime($placement['created_at'])); ?>
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
            
            <?php if (empty($placements)): ?>
            <div class="activity-item">
                <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
                    📭 Нет активности за выбранный период
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Топ пользователи -->
    <div class="top-users-card">
        <div class="top-users-header">
            <h3 class="top-users-title">
                🏆 Топ пользователи
            </h3>
        </div>
        <div class="top-users-list">
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
                    <p><?php echo $user['role'] === 'premium' ? '⭐ Premium' : '👤 Базовый'; ?></p>
                </div>
                <div class="user-stats">
                    <div class="user-placements"><?php echo $user['placements_count']; ?></div>
                    <div class="user-spent">₽<?php echo number_format($user['total_spent'], 0, ',', ' '); ?></div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if (empty($topUsers)): ?>
            <div class="top-user-item">
                <div style="text-align: center; padding: 20px; color: var(--text-secondary);">
                    📊 Нет данных за период
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // График активности
    const ctx = document.getElementById('activityChart').getContext('2d');
    const chartData = <?php echo json_encode($chartData); ?>;
    const chartLabels = <?php echo json_encode($chartLabels); ?>;
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'Размещений',
                data: chartData,
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37, 99, 235, 0.1)',
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#2563eb',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                x: {
                    grid: {
                        color: 'rgba(226, 232, 240, 0.5)'
                    },
                    ticks: {
                        color: '#64748b'
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(226, 232, 240, 0.5)'
                    },
                    ticks: {
                        color: '#64748b'
                    }
                }
            }
        }
    });

    // Автообновление каждые 30 секунд
    setInterval(function() {
        location.reload();
    }, 30000);

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
</script>

</div>
    </main>
</div>
</body>
</html>