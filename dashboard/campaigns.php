<?php
// dashboard/campaigns.php
$pageTitle = 'Мои кампании';
require_once 'header.php';

// Обработка удаления кампании
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $campaignId = (int)$_GET['delete'];
    
    // Проверяем владельца
    $stmt = db()->prepare("SELECT id FROM campaigns WHERE id = ? AND user_id = ?");
    $stmt->execute([$campaignId, $currentUser['id']]);
    
    if ($stmt->fetch()) {
        $stmt = db()->prepare("DELETE FROM campaigns WHERE id = ? AND user_id = ?");
        $stmt->execute([$campaignId, $currentUser['id']]);
        header('Location: /dashboard/campaigns.php?deleted=1');
        exit;
    }
}

// Обработка создания/редактирования кампании
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $campaignName = trim($_POST['campaign_name'] ?? '');
    $channelUrl = trim($_POST['channel_url'] ?? '');
    $channelName = trim($_POST['channel_name'] ?? '');
    $campaignId = $_POST['campaign_id'] ?? null;
    
    if (!empty($campaignName) && !empty($channelName)) {
        if ($campaignId) {
            // Редактирование
            $stmt = db()->prepare("
                UPDATE campaigns 
                SET campaign_name = ?, channel_url = ?, channel_name = ?, updated_at = NOW() 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$campaignName, $channelUrl, $channelName, $campaignId, $currentUser['id']]);
        } else {
            // Создание новой
            $stmt = db()->prepare("
                INSERT INTO campaigns (user_id, campaign_name, channel_url, channel_name) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$currentUser['id'], $campaignName, $channelUrl, $channelName]);
        }
        
        header('Location: /dashboard/campaigns.php?saved=1');
        exit;
    }
}

// Получаем кампании пользователя
$stmt = db()->prepare("
    SELECT 
        c.*,
        COUNT(ap.id) as placements_count,
        COALESCE(SUM(ap.price), 0) as total_spent,
        COALESCE(SUM(ap.subscribers_gained), 0) as total_subscribers,
        MAX(ap.placement_date) as last_placement_date
    FROM campaigns c
    LEFT JOIN ad_placements ap ON c.id = ap.campaign_id
    WHERE c.user_id = ?
    GROUP BY c.id
    ORDER BY c.created_at DESC
");
$stmt->execute([$currentUser['id']]);
$campaigns = $stmt->fetchAll();

// Если редактирование
$editCampaign = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = db()->prepare("SELECT * FROM campaigns WHERE id = ? AND user_id = ?");
    $stmt->execute([$_GET['edit'], $currentUser['id']]);
    $editCampaign = $stmt->fetch();
}

$showForm = isset($_GET['action']) && $_GET['action'] === 'new' || $editCampaign;
?>

<div class="campaigns-container">
    <!-- Заголовок страницы -->
    <div class="page-header">
        <div class="header-content">
            <h1 class="page-title">Мои кампании</h1>
            <p class="page-subtitle">Управляйте рекламными кампаниями и отслеживайте их эффективность</p>
        </div>
        <div class="header-stats">
            <div class="stat-badge">
                <div class="stat-number"><?php echo count($campaigns); ?></div>
                <div class="stat-label">Всего кампаний</div>
            </div>
        </div>
    </div>

    <!-- Уведомления -->
    <?php if (isset($_GET['saved'])): ?>
    <div class="alert alert-success">
        <div class="alert-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M9 12L11 14L15 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
            </svg>
        </div>
        <span>Кампания успешно сохранена!</span>
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-info">
        <div class="alert-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                <path d="M12 6V12L16 14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <span>Кампания удалена</span>
    </div>
    <?php endif; ?>

    <?php if ($showForm): ?>
    <!-- Форма создания/редактирования -->
    <div class="card">
        <div class="card-header">
            <div class="card-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <?php if ($editCampaign): ?>
                    <path d="M17 3C17.5523 3 18 3.44772 18 4V20C18 20.5523 17.5523 21 17 21H7C6.44772 21 6 20.5523 6 20V4C6 3.44772 6.44772 3 7 3H17Z" stroke="currentColor" stroke-width="2"/>
                    <path d="M9 9H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <path d="M9 13H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <path d="M9 17H13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <?php else: ?>
                    <line x1="12" y1="5" x2="12" y2="19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <line x1="5" y1="12" x2="19" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <?php endif; ?>
                </svg>
            </div>
            <h3 class="card-title">
                <?php echo $editCampaign ? 'Редактировать кампанию' : 'Новая кампания'; ?>
            </h3>
        </div>
        <div class="card-body">
            <form method="POST" action="/dashboard/campaigns.php" class="modern-form">
                <?php if ($editCampaign): ?>
                <input type="hidden" name="campaign_id" value="<?php echo $editCampaign['id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="campaign_name" class="form-label">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                            <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                            <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                        </svg>
                        Название кампании <span class="required">*</span>
                    </label>
                    <input type="text" id="campaign_name" name="campaign_name" class="form-input"
                           value="<?php echo htmlspecialchars($editCampaign['campaign_name'] ?? ''); ?>"
                           placeholder="Например: Продвижение канала о криптовалюте"
                           required>
                    <div class="form-hint">Дайте понятное название для удобства работы</div>
                </div>
                
                <div class="form-group">
                    <label for="channel_name" class="form-label">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M21 15C21 15.5304 20.7893 16.0391 20.4142 16.4142C20.0391 16.7893 19.5304 17 19 17H7L3 21V5C3 4.46957 3.21071 3.96086 3.58579 3.58579C3.96086 3.21071 4.46957 3 5 3H19C19.5304 3 20.0391 3.21071 20.4142 3.58579C20.7893 3.96086 21 4.46957 21 5V15Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Название вашего канала <span class="required">*</span>
                    </label>
                    <input type="text" id="channel_name" name="channel_name" class="form-input"
                           value="<?php echo htmlspecialchars($editCampaign['channel_name'] ?? ''); ?>"
                           placeholder="Например: Крипто Новости 24/7"
                           required>
                    <div class="form-hint">Канал, который вы продвигаете</div>
                </div>
                
                <div class="form-group">
                    <label for="channel_url" class="form-label">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M10 13C10.4295 13.5741 10.9774 14.0491 11.6066 14.3929C12.2357 14.7367 12.9315 14.9411 13.6467 14.9923C14.3618 15.0435 15.0796 14.9403 15.7513 14.6897C16.4231 14.4392 17.0331 14.047 17.54 13.54L20.54 10.54C21.4508 9.59695 21.9548 8.33394 21.9434 7.02296C21.932 5.71198 21.4061 4.45791 20.4791 3.53087C19.5521 2.60383 18.298 2.07799 16.987 2.0666C15.676 2.0552 14.413 2.55918 13.47 3.47L11.75 5.18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M14 11C13.5705 10.4259 13.0226 9.9509 12.3934 9.60712C11.7643 9.26335 11.0685 9.05888 10.3533 9.00771C9.63819 8.95654 8.92037 9.05971 8.24860 9.31026C7.57683 9.56081 6.96687 9.95301 6.46 10.46L3.46 13.46C2.54918 14.403 2.04520 15.6661 2.05660 16.977C2.06799 18.288 2.59383 19.5421 3.52087 20.4691C4.44791 21.3962 5.70198 21.922 7.01296 21.9334C8.32394 21.9448 9.58695 21.4408 10.53 20.53L12.24 18.82" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Ссылка на канал
                    </label>
                    <input type="url" id="channel_url" name="channel_url" class="form-input"
                           value="<?php echo htmlspecialchars($editCampaign['channel_url'] ?? ''); ?>"
                           placeholder="https://t.me/yourchannel">
                    <div class="form-hint">Необязательно, но поможет быстро перейти к каналу</div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M19 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V5C3 4.46957 3.21071 3.96086 3.58579 3.58579C3.96086 3.21071 4.46957 3 5 3H16L21 8V19C21 19.5304 20.7893 20.0391 20.4142 20.4142C20.0391 20.7893 19.5304 21 19 21Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <polyline points="17,21 17,13 7,13 7,21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <polyline points="7,3 7,8 15,8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <?php echo $editCampaign ? 'Сохранить изменения' : 'Создать кампанию'; ?>
                    </button>
                    <a href="/dashboard/campaigns.php" class="btn btn-secondary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <line x1="18" y1="6" x2="6" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <line x1="6" y1="6" x2="18" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Отмена
                    </a>
                </div>
            </form>
        </div>
    </div>

    <?php else: ?>
    <!-- Список кампаний -->
    <div class="card">
        <div class="card-header">
            <div class="card-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                    <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                    <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                </svg>
            </div>
            <h3 class="card-title">Все кампании (<?php echo count($campaigns); ?>)</h3>
            <a href="/dashboard/campaigns.php?action=new" class="btn btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <line x1="12" y1="5" x2="12" y2="19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <line x1="5" y1="12" x2="19" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Создать кампанию
            </a>
        </div>
        <div class="card-body table-container">
            <?php if (count($campaigns) > 0): ?>
            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Кампания</th>
                            <th>Канал</th>
                            <th>Размещений</th>
                            <th>Потрачено</th>
                            <th>Подписчиков</th>
                            <th>Последнее размещение</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($campaigns as $campaign): ?>
                        <tr>
                            <td>
                                <div class="campaign-info">
                                    <div class="campaign-name"><?php echo htmlspecialchars($campaign['campaign_name']); ?></div>
                                    <div class="campaign-date">
                                        Создана <?php echo date('d.m.Y', strtotime($campaign['created_at'])); ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="channel-info">
                                    <div class="channel-name"><?php echo htmlspecialchars($campaign['channel_name']); ?></div>
                                    <?php if ($campaign['channel_url']): ?>
                                        <a href="<?php echo htmlspecialchars($campaign['channel_url']); ?>" 
                                           target="_blank" class="channel-link">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M18 13V19C18 19.5304 17.7893 20.0391 17.4142 20.4142C17.0391 20.7893 16.5304 21 16 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V8C3 7.46957 3.21071 6.96086 3.58579 6.58579C3.96086 6.21071 4.46957 6 5 6H11" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                <path d="M15 3H21V9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                <path d="M10 14L21 3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                            Открыть канал
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="count-badge placements"><?php echo $campaign['placements_count']; ?></span>
                            </td>
                            <td>
                                <div class="price-cell">₽<?php echo number_format($campaign['total_spent'], 0, ',', ' '); ?></div>
                            </td>
                            <td>
                                <?php if ($campaign['total_subscribers'] > 0): ?>
                                    <span class="count-badge subscribers"><?php echo $campaign['total_subscribers']; ?></span>
                                <?php else: ?>
                                    <span class="no-data">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="date-cell">
                                    <?php if ($campaign['last_placement_date']): ?>
                                        <?php echo date('d.m.Y', strtotime($campaign['last_placement_date'])); ?>
                                    <?php else: ?>
                                        <span class="no-data">Нет размещений</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="/dashboard/placements.php?campaign=<?php echo $campaign['id']; ?>" 
                                       class="action-btn view" title="Размещения">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M3 3V21H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M9 9L12 6L16 10L20 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </a>
                                    <a href="/dashboard/campaigns.php?edit=<?php echo $campaign['id']; ?>" 
                                       class="action-btn edit" title="Редактировать">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M17 3C17.5523 3 18 3.44772 18 4V20C18 20.5523 17.5523 21 17 21H7C6.44772 21 6 20.5523 6 20V4C6 3.44772 6.44772 3 7 3H17Z" stroke="currentColor" stroke-width="2"/>
                                            <path d="M9 9H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                            <path d="M9 13H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                            <path d="M9 17H13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                        </svg>
                                    </a>
                                    <a href="/dashboard/campaigns.php?delete=<?php echo $campaign['id']; ?>" 
                                       class="action-btn delete" 
                                       onclick="return confirm('Удалить кампанию и все её размещения?')"
                                       title="Удалить">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <polyline points="3,6 5,6 21,6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M19 6V20C19 20.5304 18.7893 21.0391 18.4142 21.4142C18.0391 21.7893 17.5304 22 17 22H7C6.46957 22 5.96086 21.7893 5.58579 21.4142C5.21071 21.0391 5 20.5304 5 20V6M8 6V4C8 3.46957 8.21071 2.96086 8.58579 2.58579C8.96086 2.21071 9.46957 2 10 2H14C14.5304 2 15.0391 2.21071 15.4142 2.58579C15.7893 2.96086 16 3.46957 16 4V6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                        <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                        <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                    </svg>
                </div>
                <h3 class="empty-title">У вас пока нет кампаний</h3>
                <p class="empty-desc">Создайте первую кампанию для начала работы с рекламой</p>
                <a href="/dashboard/campaigns.php?action=new" class="empty-action">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <line x1="12" y1="5" x2="12" y2="19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <line x1="5" y1="12" x2="19" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Создать первую кампанию
                </a>
            </div>
            <?php endif; ?>
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

.campaigns-container {
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

.header-stats {
    display: flex;
    align-items: center;
}

.stat-badge {
    text-align: center;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: var(--radius-lg);
    padding: 16px 20px;
}

.stat-number {
    font-size: 24px;
    font-weight: 700;
    margin-bottom: 4px;
}

.stat-label {
    font-size: 12px;
    opacity: 0.8;
    font-weight: 500;
}

/* Уведомления */
.alert {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 20px;
    border-radius: var(--radius-md);
    margin-bottom: 24px;
    font-size: 14px;
    font-weight: 500;
}

.alert-success {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    color: var(--success);
    border: 1px solid #6ee7b7;
}

.alert-info {
    background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
    color: var(--info);
    border: 1px solid #93c5fd;
}

.alert-icon {
    flex-shrink: 0;
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
    justify-content: space-between;
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
    display: flex;
    align-items: center;
}

.card-body {
    padding: 24px;
}

.table-container {
    padding: 0;
}

/* Формы */
.modern-form {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
}

.required {
    color: var(--error);
}

.form-input {
    padding: 12px 16px;
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    font-size: 14px;
    transition: all 0.2s;
    background: var(--bg-primary);
}

.form-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.form-hint {
    font-size: 12px;
    color: var(--text-secondary);
}

.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 8px;
}

/* Кнопки */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 20px;
    border: none;
    border-radius: var(--radius-md);
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    white-space: nowrap;
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

.btn-secondary {
    background: var(--bg-tertiary);
    color: var(--text-secondary);
    border: 1px solid var(--border);
}

.btn-secondary:hover {
    background: var(--bg-secondary);
    color: var(--text-primary);
    border-color: var(--border);
}

/* Таблица */
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

.campaign-info {
    min-width: 200px;
}

.campaign-name {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.campaign-date {
    font-size: 12px;
    color: var(--text-tertiary);
}

.channel-info {
    min-width: 150px;
}

.channel-name {
    font-weight: 500;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.channel-link {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    color: var(--primary);
    text-decoration: none;
    transition: color 0.2s;
}

.channel-link:hover {
    color: var(--primary-dark);
}

.count-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}

.count-badge.placements {
    background: linear-gradient(135deg, var(--info), #2563eb);
    color: white;
}

.count-badge.subscribers {
    background: linear-gradient(135deg, var(--success), #059669);
    color: white;
}

.price-cell {
    font-weight: 600;
    color: var(--text-primary);
    font-variant-numeric: tabular-nums;
}

.date-cell {
    font-variant-numeric: tabular-nums;
    color: var(--text-secondary);
}

.no-data {
    color: var(--text-tertiary);
    font-style: italic;
}

.action-buttons {
    display: flex;
    gap: 8px;
}

.action-btn {
    width: 32px;
    height: 32px;
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: all 0.2s;
    border: 1px solid var(--border);
}

.action-btn.view {
    background: var(--bg-secondary);
    color: var(--info);
}

.action-btn.view:hover {
    background: var(--info);
    color: white;
    border-color: var(--info);
}

.action-btn.edit {
    background: var(--bg-secondary);
    color: var(--warning);
}

.action-btn.edit:hover {
    background: var(--warning);
    color: white;
    border-color: var(--warning);
}

.action-btn.delete {
    background: var(--bg-secondary);
    color: var(--error);
}

.action-btn.delete:hover {
    background: var(--error);
    color: white;
    border-color: var(--error);
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
    .campaigns-container {
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
    
    .card-body {
        padding: 20px;
    }
    
    .card-header {
        padding: 16px 20px;
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .action-buttons {
        flex-direction: column;
        gap: 4px;
    }
    
    .table-responsive {
        margin: 0 -20px;
        padding: 0 20px;
    }
}

@media (max-width: 480px) {
    .stat-badge {
        display: none;
    }
    
    .modern-table th,
    .modern-table td {
        padding: 12px 8px;
        font-size: 13px;
    }
    
    .campaign-info,
    .channel-info {
        min-width: auto;
    }
}
</style>

<?php require_once 'footer.php'; ?>
