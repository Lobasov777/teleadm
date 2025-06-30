<?php
// dashboard/analytics.php
$pageTitle = 'Аналитика';
require_once 'header.php';

// Период анализа (по умолчанию - последние 30 дней)
$period = $_GET['period'] ?? '30';
$customFrom = $_GET['from'] ?? '';
$customTo = $_GET['to'] ?? '';

// Определяем даты для фильтра
if ($period === 'custom' && $customFrom && $customTo) {
    $dateFrom = $customFrom;
    $dateTo = $customTo;
} else {
    $dateTo = date('Y-m-d');
    switch ($period) {
        case '7':
            $dateFrom = date('Y-m-d', strtotime('-7 days'));
            break;
        case '30':
            $dateFrom = date('Y-m-d', strtotime('-30 days'));
            break;
        case '90':
            $dateFrom = date('Y-m-d', strtotime('-90 days'));
            break;
        case 'all':
            $dateFrom = '2000-01-01';
            break;
        default:
            $dateFrom = date('Y-m-d', strtotime('-30 days'));
    }
}

// Получаем общую статистику за период
$stmt = db()->prepare("
    SELECT 
        COUNT(DISTINCT c.id) as campaigns_count,
        COUNT(ap.id) as placements_count,
        COALESCE(SUM(ap.price), 0) as total_spent,
        COALESCE(SUM(ap.reach_24h), 0) as total_reach,
        COALESCE(SUM(ap.subscribers_gained), 0) as total_subscribers,
        COALESCE(AVG(ap.cpm), 0) as avg_cpm,
        COALESCE(AVG(ap.price_per_subscriber), 0) as avg_price_per_subscriber,
        COALESCE(AVG(ap.subscribers_gained), 0) as avg_subscribers_per_placement
    FROM campaigns c
    LEFT JOIN ad_placements ap ON c.id = ap.campaign_id 
        AND ap.placement_date BETWEEN ? AND ?
    WHERE c.user_id = ?
");
$stmt->execute([$dateFrom, $dateTo, $currentUser['id']]);
$stats = $stmt->fetch();

// Данные для графика расходов по дням
$stmt = db()->prepare("
    SELECT 
        DATE(ap.placement_date) as date,
        SUM(ap.price) as daily_spent,
        COUNT(ap.id) as placements_count
    FROM ad_placements ap
    JOIN campaigns c ON ap.campaign_id = c.id
    WHERE c.user_id = ? AND ap.placement_date BETWEEN ? AND ?
    GROUP BY DATE(ap.placement_date)
    ORDER BY date
");
$stmt->execute([$currentUser['id'], $dateFrom, $dateTo]);
$dailyStats = $stmt->fetchAll();

// Статистика по тематикам
$stmt = db()->prepare("
    SELECT 
        COALESCE(ap.theme, 'Не указано') as theme,
        COUNT(ap.id) as count,
        SUM(ap.price) as total_spent,
        SUM(ap.subscribers_gained) as total_subscribers,
        AVG(ap.cpm) as avg_cpm,
        AVG(ap.price_per_subscriber) as avg_price_per_subscriber
    FROM ad_placements ap
    JOIN campaigns c ON ap.campaign_id = c.id
    WHERE c.user_id = ? AND ap.placement_date BETWEEN ? AND ?
    GROUP BY ap.theme
    ORDER BY total_spent DESC
");
$stmt->execute([$currentUser['id'], $dateFrom, $dateTo]);
$themeStats = $stmt->fetchAll();

// Топ каналов по эффективности
$stmt = db()->prepare("
    SELECT 
        ap.channel_name,
        COUNT(ap.id) as placements_count,
        SUM(ap.price) as total_spent,
        SUM(ap.subscribers_gained) as total_subscribers,
        AVG(ap.cpm) as avg_cpm,
        AVG(ap.price_per_subscriber) as avg_price_per_subscriber
    FROM ad_placements ap
    JOIN campaigns c ON ap.campaign_id = c.id
    WHERE c.user_id = ? AND ap.placement_date BETWEEN ? AND ?
    GROUP BY ap.channel_name
    HAVING total_subscribers > 0
    ORDER BY avg_price_per_subscriber ASC
    LIMIT 10
");
$stmt->execute([$currentUser['id'], $dateFrom, $dateTo]);
$topChannels = $stmt->fetchAll();

// Статистика по кампаниям
$stmt = db()->prepare("
    SELECT 
        c.campaign_name,
        c.channel_name,
        COUNT(ap.id) as placements_count,
        COALESCE(SUM(ap.price), 0) as total_spent,
        COALESCE(SUM(ap.subscribers_gained), 0) as total_subscribers,
        COALESCE(AVG(ap.cpm), 0) as avg_cpm
    FROM campaigns c
    LEFT JOIN ad_placements ap ON c.id = ap.campaign_id 
        AND ap.placement_date BETWEEN ? AND ?
    WHERE c.user_id = ?
    GROUP BY c.id
    HAVING placements_count > 0
    ORDER BY total_spent DESC
");
$stmt->execute([$dateFrom, $dateTo, $currentUser['id']]);
$campaignStats = $stmt->fetchAll();

// Подготовка данных для графиков
$chartLabels = [];
$chartData = [];
foreach ($dailyStats as $day) {
    $chartLabels[] = date('d.m', strtotime($day['date']));
    $chartData[] = $day['daily_spent'];
}

$themeLabels = [];
$themeData = [];
foreach ($themeStats as $theme) {
    if ($theme['total_spent'] > 0) {
        $themeLabels[] = $theme['theme'];
        $themeData[] = $theme['total_spent'];
    }
}
?>

<div class="analytics-container">
    <!-- Заголовок страницы -->
    <div class="page-header">
        <div class="header-content">
            <h1 class="page-title">Аналитика рекламных кампаний</h1>
            <p class="page-subtitle">Детальная статистика и графики эффективности за выбранный период</p>
        </div>
        <div class="header-visual">
            <div class="analytics-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M3 3V21H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M9 9L12 6L16 10L20 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
        </div>
    </div>

    <!-- Фильтр периода -->
    <div class="card">
        <div class="card-header">
            <div class="card-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2" stroke="currentColor" stroke-width="2"/>
                    <line x1="16" y1="2" x2="16" y2="6" stroke="currentColor" stroke-width="2"/>
                    <line x1="8" y1="2" x2="8" y2="6" stroke="currentColor" stroke-width="2"/>
                    <line x1="3" y1="10" x2="21" y2="10" stroke="currentColor" stroke-width="2"/>
                </svg>
            </div>
            <h3 class="card-title">Период анализа</h3>
        </div>
        <div class="card-body">
            <form method="GET" action="/dashboard/analytics.php" class="period-filter">
                <div class="filter-group">
                    <div class="filter-buttons">
                        <button type="submit" name="period" value="7" 
                                class="btn btn-filter <?php echo $period == '7' ? 'active' : ''; ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                                <polyline points="12,6 12,12 16,14" stroke="currentColor" stroke-width="2"/>
                            </svg>
                            7 дней
                        </button>
                        <button type="submit" name="period" value="30" 
                                class="btn btn-filter <?php echo $period == '30' ? 'active' : ''; ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                                <polyline points="12,6 12,12 16,14" stroke="currentColor" stroke-width="2"/>
                            </svg>
                            30 дней
                        </button>
                        <button type="submit" name="period" value="90" 
                                class="btn btn-filter <?php echo $period == '90' ? 'active' : ''; ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                                <polyline points="12,6 12,12 16,14" stroke="currentColor" stroke-width="2"/>
                            </svg>
                            90 дней
                        </button>
                        <button type="submit" name="period" value="all" 
                                class="btn btn-filter <?php echo $period == 'all' ? 'active' : ''; ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                                <polyline points="12,6 12,12 16,14" stroke="currentColor" stroke-width="2"/>
                            </svg>
                            Все время
                        </button>
                    </div>
                </div>
                <div class="filter-group custom-dates <?php echo $period == 'custom' ? 'active' : ''; ?>">
                    <div class="custom-date-inputs">
                        <input type="hidden" name="period" value="custom">
                        <div class="date-input-group">
                            <label for="from">От:</label>
                            <input type="date" id="from" name="from" value="<?php echo $customFrom; ?>" 
                                   max="<?php echo date('Y-m-d'); ?>" class="form-input">
                        </div>
                        <div class="date-separator">—</div>
                        <div class="date-input-group">
                            <label for="to">До:</label>
                            <input type="date" id="to" name="to" value="<?php echo $customTo; ?>" 
                                   max="<?php echo date('Y-m-d'); ?>" class="form-input">
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/>
                                <path d="M21 21L16.65 16.65" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Применить
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Общая статистика -->
    <div class="stats-grid">
        <div class="stat-card spending">
            <div class="stat-content">
                <div class="stat-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <line x1="12" y1="1" x2="12" y2="23" stroke="currentColor" stroke-width="2"/>
                        <path d="M17 5H9.5C8.57174 5 7.6815 5.36875 7.02513 6.02513C6.36875 6.6815 6 7.57174 6 8.5C6 9.42826 6.36875 10.3185 7.02513 10.9749C7.6815 11.6313 8.57174 12 9.5 12H14.5C15.4283 12 16.3185 12.3687 16.9749 13.0251C17.6313 13.6815 18 14.5717 18 15.5C18 16.4283 17.6313 17.3185 16.9749 17.9749C16.3185 18.6313 15.4283 19 14.5 19H6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="stat-info">
                    <div class="stat-value">₽<?php echo number_format($stats['total_spent'], 0, ',', ' '); ?></div>
                    <div class="stat-label">Общие расходы</div>
                </div>
            </div>
            <div class="stat-footer">
                <span class="stat-meta">За период: <?php echo $stats['placements_count']; ?> размещений</span>
            </div>
        </div>
        
        <div class="stat-card subscribers">
            <div class="stat-content">
                <div class="stat-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M16 21V19C16 17.9391 15.5786 16.9217 14.8284 16.1716C14.0783 15.4214 13.0609 15 12 15H5C3.93913 15 2.92172 15.4214 1.17157 16.1716C0.421427 16.9217 0 17.9391 0 19V21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="8.5" cy="7" r="4" stroke="currentColor" stroke-width="2"/>
                        <line x1="20" y1="8" x2="20" y2="14" stroke="currentColor" stroke-width="2"/>
                        <line x1="23" y1="11" x2="17" y2="11" stroke="currentColor" stroke-width="2"/>
                    </svg>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?php echo number_format($stats['total_subscribers'], 0, ',', ' '); ?></div>
                    <div class="stat-label">Новых подписчиков</div>
                </div>
            </div>
            <div class="stat-footer">
                <span class="stat-meta">В среднем: <?php echo round($stats['avg_subscribers_per_placement']); ?> с размещения</span>
            </div>
        </div>
        
        <div class="stat-card cpm">
            <div class="stat-content">
                <div class="stat-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M3 3V21H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M9 9L12 6L16 10L20 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="stat-info">
                    <div class="stat-value">₽<?php echo number_format($stats['avg_cpm'], 2, ',', ' '); ?></div>
                    <div class="stat-label">Средний CPM</div>
                </div>
            </div>
            <div class="stat-footer">
                <span class="stat-meta">Охват: <?php echo number_format($stats['total_reach'], 0, ',', ' '); ?></span>
            </div>
        </div>
        
        <div class="stat-card price-per-sub">
            <div class="stat-content">
                <div class="stat-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="stat-info">
                    <div class="stat-value">₽<?php echo number_format($stats['avg_price_per_subscriber'], 2, ',', ' '); ?></div>
                    <div class="stat-label">Средняя цена подписчика</div>
                </div>
            </div>
            <div class="stat-footer">
                <span class="stat-meta <?php echo $stats['avg_price_per_subscriber'] < 50 ? 'positive' : 'negative'; ?>">
                    <?php echo $stats['avg_price_per_subscriber'] < 50 ? 'Отличный результат!' : 'Можно оптимизировать'; ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Графики -->
    <div class="charts-grid">
        <!-- График расходов по дням -->
        <div class="card chart-card">
            <div class="card-header">
                <div class="card-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M3 3V21H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M9 9L12 6L16 10L20 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <h3 class="card-title">Динамика расходов</h3>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="spendingChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Распределение по тематикам -->
        <div class="card chart-card">
            <div class="card-header">
                <div class="card-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                        <path d="M2 12H22" stroke="currentColor" stroke-width="2"/>
                        <path d="M12 2C14.5013 4.73835 15.9228 8.29203 16 12C15.9228 15.708 14.5013 19.2616 12 22C9.49872 19.2616 8.07725 15.708 8 12C8.07725 8.29203 9.49872 4.73835 12 2Z" stroke="currentColor" stroke-width="2"/>
                    </svg>
                </div>
                <h3 class="card-title">Расходы по тематикам</h3>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="themeChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Топ эффективных каналов -->
    <?php if (count($topChannels) > 0): ?>
    <div class="card">
        <div class="card-header">
            <div class="card-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h3 class="card-title">Топ-10 эффективных каналов</h3>
        </div>
        <div class="card-body table-container">
            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Место</th>
                            <th>Канал</th>
                            <th>Размещений</th>
                            <th>Потрачено</th>
                            <th>Подписчиков</th>
                            <th>Цена подписчика</th>
                            <th>CPM</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topChannels as $index => $channel): ?>
                        <tr>
                            <td>
                                <div class="rank-badge rank-<?php echo $index + 1; ?>">
                                    <?php if ($index < 3): ?>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    <?php endif; ?>
                                    <?php echo $index + 1; ?>
                                </div>
                            </td>
                            <td>
                                <div class="channel-name"><?php echo htmlspecialchars($channel['channel_name']); ?></div>
                            </td>
                            <td>
                                <span class="count-badge"><?php echo $channel['placements_count']; ?></span>
                            </td>
                            <td>
                                <div class="price-cell">₽<?php echo number_format($channel['total_spent'], 0, ',', ' '); ?></div>
                            </td>
                            <td>
                                <span class="subscribers-badge"><?php echo $channel['total_subscribers']; ?></span>
                            </td>
                            <td>
                                <div class="highlight-good">₽<?php echo number_format($channel['avg_price_per_subscriber'], 2, ',', ' '); ?></div>
                            </td>
                            <td>
                                <div class="number-cell">₽<?php echo number_format($channel['avg_cpm'], 2, ',', ' '); ?></div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Статистика по кампаниям -->
    <?php if (count($campaignStats) > 0): ?>
    <div class="card">
        <div class="card-header">
            <div class="card-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                    <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                    <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                </svg>
            </div>
            <h3 class="card-title">Эффективность кампаний</h3>
        </div>
        <div class="card-body table-container">
            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Кампания</th>
                            <th>Канал</th>
                            <th>Размещений</th>
                            <th>Расходы</th>
                            <th>Подписчиков</th>
                            <th>Средний CPM</th>
                            <th>ROI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($campaignStats as $campaign): ?>
                        <tr>
                            <td>
                                <div class="campaign-name"><?php echo htmlspecialchars($campaign['campaign_name']); ?></div>
                            </td>
                            <td>
                                <div class="channel-name"><?php echo htmlspecialchars($campaign['channel_name']); ?></div>
                            </td>
                            <td>
                                <span class="count-badge"><?php echo $campaign['placements_count']; ?></span>
                            </td>
                            <td>
                                <div class="price-cell">₽<?php echo number_format($campaign['total_spent'], 0, ',', ' '); ?></div>
                            </td>
                            <td>
                                <?php if ($campaign['total_subscribers'] > 0): ?>
                                    <span class="subscribers-badge"><?php echo $campaign['total_subscribers']; ?></span>
                                <?php else: ?>
                                    <span class="no-data">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="number-cell">₽<?php echo number_format($campaign['avg_cpm'], 2, ',', ' '); ?></div>
                            </td>
                            <td>
                                <?php 
                                $roi = $campaign['total_spent'] > 0 ? 
                                       ($campaign['total_subscribers'] * 100 / $campaign['total_spent']) : 0;
                                ?>
                                <span class="roi-indicator <?php echo $roi > 10 ? 'good' : ($roi > 5 ? 'medium' : 'bad'); ?>">
                                    <?php echo number_format($roi, 1); ?>%
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (count($topChannels) === 0 && count($campaignStats) === 0): ?>
    <!-- Пустое состояние -->
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <div class="empty-icon">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M3 3V21H21" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M9 9L12 6L16 10L20 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <h3 class="empty-title">Недостаточно данных для анализа</h3>
                <p class="empty-desc">Добавьте размещения рекламы, чтобы увидеть детальную аналитику и графики эффективности</p>
                <a href="/dashboard/placements.php?action=new" class="empty-action">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <line x1="12" y1="5" x2="12" y2="19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <line x1="5" y1="12" x2="19" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Добавить размещение
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Chart.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>

<script>
// График расходов
const spendingCtx = document.getElementById('spendingChart').getContext('2d');
new Chart(spendingCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($chartLabels); ?>,
        datasets: [{
            label: 'Расходы, ₽',
            data: <?php echo json_encode($chartData); ?>,
            borderColor: '#2563eb',
            backgroundColor: 'rgba(37, 99, 235, 0.1)',
            tension: 0.4,
            fill: true,
            pointBackgroundColor: '#2563eb',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6
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
                borderColor: '#2563eb',
                borderWidth: 1,
                cornerRadius: 8,
                displayColors: false
            }
        },
        scales: {
            x: {
                grid: {
                    color: 'rgba(226, 232, 240, 0.5)',
                    borderColor: '#e2e8f0'
                },
                ticks: {
                    color: '#64748b',
                    font: {
                        family: 'Inter'
                    }
                }
            },
            y: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(226, 232, 240, 0.5)',
                    borderColor: '#e2e8f0'
                },
                ticks: {
                    color: '#64748b',
                    font: {
                        family: 'Inter'
                    },
                    callback: function(value) {
                        return '₽' + value.toLocaleString('ru');
                    }
                }
            }
        }
    }
});

// График по тематикам
const themeCtx = document.getElementById('themeChart').getContext('2d');
new Chart(themeCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($themeLabels); ?>,
        datasets: [{
            data: <?php echo json_encode($themeData); ?>,
            backgroundColor: [
                '#2563eb', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
                '#06b6d4', '#84cc16', '#f97316', '#ec4899', '#6b7280'
            ],
            borderWidth: 0,
            hoverBorderWidth: 2,
            hoverBorderColor: '#ffffff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'right',
                labels: {
                    color: '#64748b',
                    font: {
                        family: 'Inter',
                        size: 12
                    },
                    padding: 16,
                    usePointStyle: true,
                    pointStyle: 'circle'
                }
            },
            tooltip: {
                backgroundColor: 'rgba(15, 23, 42, 0.9)',
                titleColor: '#ffffff',
                bodyColor: '#ffffff',
                borderColor: '#2563eb',
                borderWidth: 1,
                cornerRadius: 8,
                callbacks: {
                    label: function(context) {
                        return context.label + ': ₽' + context.parsed.toLocaleString('ru');
                    }
                }
            }
        }
    }
});

// Показать/скрыть кастомные даты
document.addEventListener('DOMContentLoaded', function() {
    const filterButtons = document.querySelectorAll('.btn-filter');
    const customDates = document.querySelector('.custom-dates');
    
    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            if (this.value !== 'custom') {
                customDates.classList.remove('active');
            }
        });
    });
    
    // Показать кастомные даты при клике на поля даты
    const dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(input => {
        input.addEventListener('focus', function() {
            customDates.classList.add('active');
            // Убираем активность с других кнопок
            filterButtons.forEach(btn => btn.classList.remove('active'));
        });
    });
});
</script>

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

:root {
    --primary: #2563eb;
    --primary-light: #3b82f6;
    --primary-dark: #1d4ed8;
    --success: #10b981;
    --warning: #f59e0b;
    --error: #ef4444;
    --info: #3b82f6;
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

.analytics-container {
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

.header-visual {
    display: flex;
    align-items: center;
}

.analytics-icon {
    width: 80px;
    height: 80px;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    animation: pulse 3s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
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
    background: var(--bg-secondary);
}

.card-header .card-icon {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    margin-right: 12px;
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

.table-container {
    padding: 0;
}

/* Фильтр периода */
.period-filter {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 12px;
}

.filter-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.btn-filter {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    background: var(--bg-primary);
    color: var(--text-secondary);
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-filter:hover {
    border-color: var(--primary);
    color: var(--primary);
}

.btn-filter.active {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border-color: var(--primary);
}

.custom-dates {
    display: none;
    padding: 20px;
    background: var(--bg-tertiary);
    border-radius: var(--radius-md);
    border: 1px solid var(--border);
}

.custom-dates.active {
    display: block;
}

.custom-date-inputs {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}

.date-input-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.date-input-group label {
    font-size: 12px;
    font-weight: 500;
    color: var(--text-secondary);
}

.form-input {
    padding: 8px 12px;
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    font-size: 14px;
    transition: all 0.2s;
    background: var(--bg-primary);
}

.form-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.date-separator {
    color: var(--text-secondary);
    font-weight: 500;
    margin-top: 20px;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 16px;
    border: none;
    border-radius: var(--radius-md);
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    white-space: nowrap;
    margin-top: 20px;
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

/* Статистика */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 32px;
}

.stat-card {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 24px;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    transition: transform 0.3s ease;
    transform: scaleX(0);
}

.stat-card.spending::before { background: var(--warning); }
.stat-card.subscribers::before { background: var(--success); }
.stat-card.cpm::before { background: var(--info); }
.stat-card.price-per-sub::before { background: #8b5cf6; }

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
}

.stat-card:hover::before {
    transform: scaleX(1);
}

.stat-content {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 16px;
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

.stat-card.spending .stat-icon {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    color: var(--warning);
}

.stat-card.subscribers .stat-icon {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    color: var(--success);
}

.stat-card.cpm .stat-icon {
    background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
    color: var(--info);
}

.stat-card.price-per-sub .stat-icon {
    background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
    color: #8b5cf6;
}

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 4px;
    letter-spacing: -0.025em;
}

.stat-label {
    color: var(--text-secondary);
    font-size: 14px;
    font-weight: 500;
}

.stat-footer {
    border-top: 1px solid var(--border-light);
    padding-top: 12px;
}

.stat-meta {
    font-size: 13px;
    color: var(--text-secondary);
}

.stat-meta.positive {
    color: var(--success);
    font-weight: 500;
}

.stat-meta.negative {
    color: var(--warning);
    font-weight: 500;
}

/* Графики */
.charts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
}

.chart-card {
    min-height: 400px;
}

.chart-container {
    position: relative;
    height: 300px;
}

/* Таблицы */
.table-responsive {
    overflow-x: auto;
}

.modern-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.modern-table th {
    background: var(--bg-tertiary);
    color: var(--text-secondary);
    font-weight: 600;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 16px;
    text-align: left;
    border-bottom: 1px solid var(--border);
    white-space: nowrap;
}

.modern-table td {
    padding: 16px;
    border-bottom: 1px solid var(--border-light);
    font-size: 14px;
    vertical-align: middle;
}

.modern-table tbody tr {
    transition: background-color 0.2s;
}

.modern-table tbody tr:hover {
    background: var(--bg-secondary);
}

.rank-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    min-width: 40px;
}

.rank-badge.rank-1 {
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
    color: white;
}

.rank-badge.rank-2 {
    background: linear-gradient(135deg, #9ca3af, #6b7280);
    color: white;
}

.rank-badge.rank-3 {
    background: linear-gradient(135deg, #f97316, #ea580c);
    color: white;
}

.rank-badge:not(.rank-1):not(.rank-2):not(.rank-3) {
    background: var(--bg-tertiary);
    color: var(--text-secondary);
    border: 1px solid var(--border);
}

.channel-name,
.campaign-name {
    font-weight: 600;
    color: var(--text-primary);
}

.count-badge {
    background: linear-gradient(135deg, var(--info), var(--primary));
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.subscribers-badge {
    background: linear-gradient(135deg, var(--success), #059669);
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.price-cell {
    font-weight: 600;
    color: var(--text-primary);
    font-variant-numeric: tabular-nums;
}

.number-cell {
    font-variant-numeric: tabular-nums;
    color: var(--text-secondary);
}

.highlight-good {
    color: var(--success);
    font-weight: 600;
}

.no-data {
    color: var(--text-tertiary);
    font-style: italic;
}

.roi-indicator {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.roi-indicator.good {
    background: linear-gradient(135deg, #d1fae5, #a7f3d0);
    color: var(--success);
}

.roi-indicator.medium {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    color: var(--warning);
}

.roi-indicator.bad {
    background: linear-gradient(135deg, #fee2e2, #fecaca);
    color: var(--error);
}

/* Пустое состояние */
.empty-state {
    text-align: center;
    padding: 80px 40px;
}

.empty-icon {
    width: 120px;
    height: 120px;
    margin: 0 auto 24px;
    background: var(--bg-secondary);
    border: 2px solid var(--border);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-tertiary);
}

.empty-title {
    font-size: 20px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 12px 0;
}

.empty-desc {
    color: var(--text-secondary);
    margin: 0 0 32px 0;
    max-width: 400px;
    margin-left: auto;
    margin-right: auto;
    line-height: 1.6;
}

.empty-action {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--primary);
    color: white;
    padding: 12px 24px;
    border-radius: var(--radius-md);
    text-decoration: none;
    font-weight: 500;
    transition: all 0.2s;
}

.empty-action:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

/* Адаптивность */
@media (max-width: 768px) {
    .analytics-container {
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
    
    .analytics-icon {
        width: 60px;
        height: 60px;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
        gap: 16px;
    }
    
    .charts-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .custom-date-inputs {
        flex-direction: column;
        align-items: stretch;
    }
    
    .date-separator {
        margin: 0;
        text-align: center;
    }
    
    .btn {
        margin-top: 16px;
    }
    
    .table-responsive {
        margin: 0 -16px;
        padding: 0 16px;
    }
}

@media (max-width: 480px) {
    .filter-buttons {
        flex-direction: column;
        width: 100%;
    }
    
    .btn-filter {
        width: 100%;
        justify-content: center;
    }
    
    .modern-table th,
    .modern-table td {
        padding: 12px 8px;
        font-size: 13px;
    }
}
</style>

<?php require_once 'footer.php'; ?>
