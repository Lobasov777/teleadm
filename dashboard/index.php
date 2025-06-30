<?php
// dashboard/index.php
$pageTitle = 'Дашборд';
require_once __DIR__ . '/header.php';

// Проверяем, что пользователь авторизован
if (!isset($currentUser) || !$currentUser) {
    header('Location: /login.php');
    exit;
}

// Получаем статистику пользователя
try {
    $stmt = db()->prepare("
        SELECT 
            COUNT(DISTINCT c.id) as total_campaigns,
            COUNT(ap.id) as total_placements,
            COALESCE(SUM(ap.price), 0) as total_spent,
            COALESCE(SUM(ap.subscribers_gained), 0) as total_subscribers,
            COALESCE(AVG(ap.cpm), 0) as avg_cpm,
            COALESCE(AVG(ap.price_per_subscriber), 0) as avg_price_per_subscriber
        FROM campaigns c
        LEFT JOIN ad_placements ap ON c.id = ap.campaign_id
        WHERE c.user_id = ?
    ");
    $stmt->execute([$currentUser['id']]);
    $stats = $stmt->fetch();
} catch (PDOException $e) {
    $stats = [
        'total_campaigns' => 0,
        'total_placements' => 0,
        'total_spent' => 0,
        'total_subscribers' => 0,
        'avg_cpm' => 0,
        'avg_price_per_subscriber' => 0
    ];
}

// Получаем лимиты для бесплатного пользователя
$placementsThisMonth = 0;
if ($currentUser['role'] === 'user') {
    try {
        $stmt = db()->prepare("
            SELECT COUNT(*) as count
            FROM ad_placements ap
            JOIN campaigns c ON ap.campaign_id = c.id
            WHERE c.user_id = ? 
            AND MONTH(ap.created_at) = MONTH(CURRENT_DATE())
            AND YEAR(ap.created_at) = YEAR(CURRENT_DATE())
        ");
        $stmt->execute([$currentUser['id']]);
        $result = $stmt->fetch();
        $placementsThisMonth = $result['count'] ?? 0;
    } catch (PDOException $e) {
        $placementsThisMonth = 0;
    }
}

// Получаем последние размещения
try {
    $stmt = db()->prepare("
        SELECT 
            ap.*,
            c.campaign_name,
            c.channel_name as advertised_channel
        FROM ad_placements ap
        JOIN campaigns c ON ap.campaign_id = c.id
        WHERE c.user_id = ?
        ORDER BY ap.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$currentUser['id']]);
    $recentPlacements = $stmt->fetchAll();
} catch (PDOException $e) {
    $recentPlacements = [];
}
?>

<div class="dashboard-container">
    <!-- Компактный блок приветствия -->
    <div class="welcome-section">
        <div class="welcome-content">
            <h1 class="welcome-title">Добро пожаловать, <?php echo htmlspecialchars($currentUser['username']); ?>!</h1>
            <p class="welcome-subtitle">Отслеживайте эффективность ваших рекламных кампаний</p>
        </div>
        <div class="welcome-decoration">
            <div class="decoration-dot dot-1"></div>
            <div class="decoration-dot dot-2"></div>
            <div class="decoration-dot dot-3"></div>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-section">
        <div class="stats-grid">
            <div class="stat-card campaigns">
                <div class="stat-content">
                    <div class="stat-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                            <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                            <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value"><?php echo $stats['total_campaigns']; ?></div>
                        <div class="stat-label">Активных кампаний</div>
                    </div>
                </div>
                <div class="stat-trend positive">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M7 14L12 9L17 14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    На пути к успеху
                </div>
            </div>
            
            <div class="stat-card placements">
                <div class="stat-content">
                    <div class="stat-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2" stroke="currentColor" stroke-width="2"/>
                            <rect x="9" y="9" width="6" height="6" stroke="currentColor" stroke-width="2"/>
                        </svg>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value"><?php echo $stats['total_placements']; ?></div>
                        <div class="stat-label">Всего размещений</div>
                    </div>
                </div>
                <div class="stat-trend positive">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M7 14L12 9L17 14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Растем каждый день
                </div>
            </div>
            
            <div class="stat-card spending">
                <div class="stat-content">
                    <div class="stat-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                            <path d="M12 6V12L16 14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value">₽<?php echo number_format($stats['total_spent'], 0, ',', ' '); ?></div>
                        <div class="stat-label">Общие расходы</div>
                    </div>
                </div>
                <div class="stat-trend neutral">
                    CPM: ₽<?php echo number_format($stats['avg_cpm'], 2, ',', ' '); ?>
                </div>
            </div>
            
            <div class="stat-card subscribers">
                <div class="stat-content">
                    <div class="stat-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M17 21V19C17 17.9391 16.5786 16.9217 15.8284 16.1716C15.0783 15.4214 14.0609 15 13 15H5C3.93913 15 2.92172 15.4214 2.17157 16.1716C1.42143 16.9217 1 17.9391 1 19V21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <circle cx="9" cy="7" r="4" stroke="currentColor" stroke-width="2"/>
                            <path d="M23 21V19C23 18.1645 22.7155 17.3541 22.2094 16.7071C21.7033 16.0601 20.9999 15.6134 20.2 15.4387" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M16 3.13C16.8003 3.30448 17.5037 3.75116 18.0098 4.39818C18.5159 5.04521 18.8004 5.85562 18.8004 6.69118C18.8004 7.52674 18.5159 8.33715 18.0098 8.98418C17.5037 9.6312 16.8003 10.0779 16 10.2524" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value"><?php echo number_format($stats['total_subscribers'], 0, ',', ' '); ?></div>
                        <div class="stat-label">Новых подписчиков</div>
                    </div>
                </div>
                <div class="stat-trend neutral">
                    Цена: ₽<?php echo number_format($stats['avg_price_per_subscriber'], 2, ',', ' '); ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($currentUser['role'] === 'user'): ?>
    <!-- Usage Limits -->
    <div class="limit-section">
        <div class="limit-card">
            <div class="limit-header">
                <div class="limit-info">
                    <div class="limit-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                            <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                            <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="limit-title">Базовый тариф</h3>
                        <p class="limit-subtitle">Использовано <?php echo $placementsThisMonth; ?> из 50 размещений</p>
                    </div>
                </div>
                <button class="upgrade-btn">
                    <span>Улучшить план</span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M12 5L19 12L12 19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </div>
            <div class="progress-container">
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo min(($placementsThisMonth / 50) * 100, 100); ?>%"></div>
                </div>
                <div class="progress-text"><?php echo round(($placementsThisMonth / 50) * 100); ?>%</div>
            </div>
            <?php if ($placementsThisMonth >= 45): ?>
            <div class="limit-warning">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M10.29 3.86L1.82 18C1.64 18.37 1.64 18.82 1.82 19.19C2 19.56 2.37 19.78 2.77 19.78H21.23C21.63 19.78 22 19.56 22.18 19.19C22.36 18.82 22.36 18.37 22.18 18L13.71 3.86C13.53 3.49 13.16 3.27 12.76 3.27C12.36 3.27 11.99 3.49 11.81 3.86H10.29Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <line x1="12" y1="9" x2="12" y2="13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <line x1="12" y1="17" x2="12.01" y2="17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Приближаетесь к лимиту размещений
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="actions-section">
        <div class="section-header">
            <h2 class="section-title">Быстрые действия</h2>
            <p class="section-subtitle">Основные функции для управления кампаниями</p>
        </div>
        <div class="actions-grid">
            <a href="/dashboard/campaigns.php?action=new" class="action-card primary">
                <div class="action-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <line x1="12" y1="5" x2="12" y2="19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <line x1="5" y1="12" x2="19" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="action-content">
                    <h3 class="action-title">Создать кампанию</h3>
                    <p class="action-desc">Запустите новую рекламную кампанию</p>
                </div>
            </a>
            
            <a href="/dashboard/placements.php?action=new" class="action-card">
                <div class="action-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M21 15C21 15.5304 20.7893 16.0391 20.4142 16.4142C20.0391 16.7893 19.5304 17 19 17H7L3 21V5C3 4.46957 3.21071 3.96086 3.58579 3.58579C3.96086 3.21071 4.46957 3 5 3H19C19.5304 3 20.0391 3.21071 20.4142 3.58579C20.7893 3.96086 21 4.46957 21 5V15Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="action-content">
                    <h3 class="action-title">Добавить размещение</h3>
                    <p class="action-desc">Зафиксируйте результаты рекламы</p>
                </div>
            </a>
            
            <a href="/dashboard/analytics.php" class="action-card">
                <div class="action-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M3 3V21H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M9 9L12 6L16 10L20 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="action-content">
                    <h3 class="action-title">Аналитика</h3>
                    <p class="action-desc">Изучите детальную статистику</p>
                </div>
            </a>
            
            <?php if ($stats['total_placements'] > 0): ?>
            <a href="/dashboard/export.php" class="action-card">
                <div class="action-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M21 15V19C21 19.5304 20.7893 20.0391 20.4142 20.4142C20.0391 20.7893 19.5304 21 19 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <polyline points="7,10 12,15 17,10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <line x1="12" y1="15" x2="12" y2="3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="action-content">
                    <h3 class="action-title">Экспорт данных</h3>
                    <p class="action-desc">Скачайте отчет в Excel</p>
                </div>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Placements -->
    <?php if (count($recentPlacements) > 0): ?>
    <div class="placements-section">
        <div class="section-header">
            <h2 class="section-title">Последние размещения</h2>
            <a href="/dashboard/placements.php" class="view-all-link">
                Все размещения
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M12 5L19 12L12 19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </a>
        </div>
        <div class="table-card">
            <div class="table-container">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Канал</th>
                            <th>Кампания</th>
                            <th>Дата</th>
                            <th>Охват</th>
                            <th>Цена</th>
                            <th>Подписчики</th>
                            <th>CPM</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentPlacements as $placement): ?>
                        <tr>
                            <td>
                                <div class="channel-cell">
                                    <div class="channel-name"><?php echo htmlspecialchars($placement['channel_name']); ?></div>
                                    <?php if ($placement['admin_name']): ?>
                                        <div class="admin-name">Админ: <?php echo htmlspecialchars($placement['admin_name']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="campaign-tag"><?php echo htmlspecialchars($placement['campaign_name']); ?></span>
                            </td>
                            <td class="date-cell"><?php echo date('d.m.Y', strtotime($placement['placement_date'])); ?></td>
                            <td class="number-cell"><?php echo number_format($placement['reach_24h'], 0, ',', ' '); ?></td>
                            <td class="price-cell">₽<?php echo number_format($placement['price'], 0, ',', ' '); ?></td>
                            <td>
                                <span class="subscribers-tag"><?php echo $placement['subscribers_gained']; ?></span>
                            </td>
                            <td class="number-cell">₽<?php echo number_format($placement['cpm'], 2, ',', ' '); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="empty-section">
        <div class="empty-card">
            <div class="empty-icon">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2" stroke="currentColor" stroke-width="1.5"/>
                    <rect x="9" y="9" width="6" height="6" stroke="currentColor" stroke-width="1.5"/>
                </svg>
            </div>
            <h3 class="empty-title">Начните отслеживать рекламу</h3>
            <p class="empty-desc">Создайте первую кампанию и добавьте данные о размещении для получения детальной аналитики</p>
            <a href="/dashboard/campaigns.php?action=new" class="empty-action">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <line x1="12" y1="5" x2="12" y2="19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <line x1="5" y1="12" x2="19" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Создать первую кампанию
            </a>
        </div>
    </div>
    <?php endif; ?>
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

.dashboard-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 24px;
    background: var(--bg-secondary);
    min-height: 100vh;
}

/* Компактный блок приветствия */
.welcome-section {
    position: relative;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    border-radius: var(--radius-lg);
    padding: 24px 32px;
    margin-bottom: 24px;
    overflow: hidden;
    color: white;
}

.welcome-content {
    position: relative;
    z-index: 2;
}

.welcome-title {
    font-size: 24px;
    font-weight: 700;
    margin: 0 0 4px 0;
    letter-spacing: -0.025em;
}

.welcome-subtitle {
    font-size: 14px;
    opacity: 0.9;
    margin: 0;
    font-weight: 400;
}

.welcome-decoration {
    position: absolute;
    top: 0;
    right: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
}

.decoration-dot {
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
}

.dot-1 {
    width: 60px;
    height: 60px;
    top: -30px;
    right: -30px;
    animation: float1 4s ease-in-out infinite;
}

.dot-2 {
    width: 40px;
    height: 40px;
    top: 10px;
    right: 80px;
    background: rgba(255, 255, 255, 0.05);
    animation: float2 3s ease-in-out infinite;
}

.dot-3 {
    width: 30px;
    height: 30px;
    bottom: -15px;
    right: 20px;
    background: rgba(255, 255, 255, 0.08);
    animation: float3 3.5s ease-in-out infinite;
}

@keyframes float1 {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
}

@keyframes float2 {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-8px); }
}

@keyframes float3 {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-6px); }
}

/* Stats Section */
.stats-section {
    margin-bottom: 32px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
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

.stat-card.campaigns::before { background: var(--primary); }
.stat-card.placements::before { background: var(--success); }
.stat-card.spending::before { background: var(--warning); }
.stat-card.subscribers::before { background: #8b5cf6; }

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
    border-color: var(--border);
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

.stat-card.campaigns .stat-icon {
    background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
    color: var(--primary);
}

.stat-card.placements .stat-icon {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    color: var(--success);
}

.stat-card.spending .stat-icon {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    color: var(--warning);
}

.stat-card.subscribers .stat-icon {
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

.stat-trend {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    font-weight: 500;
}

.stat-trend.positive { color: var(--success); }
.stat-trend.neutral { color: var(--text-secondary); }

/* Limit Section */
.limit-section {
    margin-bottom: 32px;
}

.limit-card {
    background: linear-gradient(135deg, #fef7ed 0%, #fed7aa 100%);
    border: 1px solid #fdba74;
    border-radius: var(--radius-lg);
    padding: 24px;
}

.limit-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.limit-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.limit-icon {
    width: 40px;
    height: 40px;
    background: rgba(251, 146, 60, 0.2);
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ea580c;
}

.limit-title {
    font-size: 16px;
    font-weight: 600;
    color: #ea580c;
    margin: 0 0 2px 0;
}

.limit-subtitle {
    font-size: 14px;
    color: #9a3412;
    margin: 0;
}

.upgrade-btn {
    background: var(--primary);
    color: white;
    border: none;
    padding: 10px 16px;
    border-radius: var(--radius-md);
    font-size: 14px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    transition: all 0.2s;
}

.upgrade-btn:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
}

.progress-container {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}

.progress-bar {
    flex: 1;
    height: 8px;
    background: rgba(255, 255, 255, 0.7);
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #f59e0b 0%, #ea580c 100%);
    border-radius: 4px;
    transition: width 0.3s ease;
}

.progress-text {
    font-size: 14px;
    font-weight: 600;
    color: #ea580c;
    min-width: 40px;
}

.limit-warning {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--error);
    background: rgba(239, 68, 68, 0.1);
    padding: 12px 16px;
    border-radius: var(--radius-md);
    font-size: 14px;
    font-weight: 500;
    margin-top: 16px;
}

/* Section Headers */
.section-header {
    margin-bottom: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.section-title {
    font-size: 24px;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
    letter-spacing: -0.025em;
}

.section-subtitle {
    color: var(--text-secondary);
    font-size: 16px;
    margin: 4px 0 0 0;
}

.view-all-link {
    display: flex;
    align-items: center;
    gap: 6px;
    color: var(--primary);
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s;
}

.view-all-link:hover {
    color: var(--primary-dark);
    transform: translateX(2px);
}

/* Actions Section */
.actions-section {
    margin-bottom: 32px;
}

.actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
}

.action-card {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 24px;
    text-decoration: none;
    color: var(--text-primary);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: flex-start;
    gap: 16px;
    position: relative;
    overflow: hidden;
}

.action-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: var(--primary);
    transform: scaleX(0);
    transition: transform 0.3s ease;
}

.action-card.primary::before {
    background: var(--primary);
}

.action-card:hover {
    background: var(--bg-secondary);
    border-color: var(--primary);
    transform: translateY(-4px);
    box-shadow: var(--shadow-md);
}

.action-card:hover::before {
    transform: scaleX(1);
}

.action-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    border-radius: var(--radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    flex-shrink: 0;
}

.action-title {
    font-size: 16px;
    font-weight: 600;
    margin: 0 0 6px 0;
    color: var(--text-primary);
}

.action-desc {
    font-size: 14px;
    color: var(--text-secondary);
    margin: 0;
    line-height: 1.4;
}

/* Table Section */
.placements-section {
    margin-bottom: 32px;
}

.table-card {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
}

.table-container {
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

.channel-cell {
    min-width: 150px;
}

.channel-name {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 2px;
}

.admin-name {
    font-size: 12px;
    color: var(--text-tertiary);
}

.campaign-tag {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    white-space: nowrap;
}

.subscribers-tag {
    background: linear-gradient(135deg, var(--success), #059669);
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.date-cell, .number-cell {
    font-variant-numeric: tabular-nums;
    color: var(--text-secondary);
}

.price-cell {
    font-weight: 600;
    color: var(--text-primary);
    font-variant-numeric: tabular-nums;
}

/* Empty State */
.empty-section {
    margin-bottom: 32px;
}

.empty-card {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 80px 40px;
    text-align: center;
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

/* Responsive Design */
@media (max-width: 768px) {
    .dashboard-container {
        padding: 16px;
    }
    
    .welcome-section {
        padding: 20px 24px;
    }
    
    .welcome-title {
        font-size: 20px;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
        gap: 16px;
    }
    
    .actions-grid {
        grid-template-columns: 1fr;
        gap: 16px;
    }
    
    .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
    
    .limit-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
    }
    
    .action-card {
        flex-direction: column;
        text-align: center;
        gap: 16px;
    }
    
    .table-container {
        margin: 0 -16px;
        padding: 0 16px;
    }
}
</style>

<?php require_once 'footer.php'; ?>
