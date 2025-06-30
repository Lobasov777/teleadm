<?php
// dashboard/placements.php
$pageTitle = 'Размещения';
require_once 'header.php';

// Проверка лимита для бесплатных пользователей
$canAddPlacement = true;
if ($currentUser['role'] === 'user') {
    $stmt = db()->prepare("
        SELECT COUNT(*) as count
        FROM ad_placements ap
        JOIN campaigns c ON ap.campaign_id = c.id
        WHERE c.user_id = ? 
        AND MONTH(ap.created_at) = MONTH(CURRENT_DATE())
        AND YEAR(ap.created_at) = YEAR(CURRENT_DATE())
    ");
    $stmt->execute([$currentUser['id']]);
    $placementsThisMonth = $stmt->fetch()['count'];
    $canAddPlacement = $placementsThisMonth < 50;
}

// Получаем кампании пользователя для выбора
$stmt = db()->prepare("SELECT id, campaign_name, channel_name FROM campaigns WHERE user_id = ? ORDER BY campaign_name");
$stmt->execute([$currentUser['id']]);
$userCampaigns = $stmt->fetchAll();

// Фильтр по кампании
$campaignFilter = isset($_GET['campaign']) ? (int)$_GET['campaign'] : null;

// Обработка удаления
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $placementId = (int)$_GET['delete'];
    
    // Проверяем владельца через кампанию
    $stmt = db()->prepare("
        DELETE ap FROM ad_placements ap
        JOIN campaigns c ON ap.campaign_id = c.id
        WHERE ap.id = ? AND c.user_id = ?
    ");
    $stmt->execute([$placementId, $currentUser['id']]);
    
    header('Location: /dashboard/placements.php?deleted=1');
    exit;
}

// Обработка создания размещения
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canAddPlacement) {
    $campaignId = $_POST['campaign_id'] ?? null;
    $channelName = trim($_POST['channel_name'] ?? '');
    $adminName = trim($_POST['admin_name'] ?? '');
    $reach24h = (int)($_POST['reach_24h'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $subscribersGained = (int)($_POST['subscribers_gained'] ?? 0);
    $placementDate = $_POST['placement_date'] ?? date('Y-m-d');
    $theme = trim($_POST['theme'] ?? '');
    $creativeText = trim($_POST['creative_text'] ?? '');
    
    if ($campaignId && $channelName && $reach24h > 0 && $price > 0) {
        // Проверяем, что кампания принадлежит пользователю
        $stmt = db()->prepare("SELECT id FROM campaigns WHERE id = ? AND user_id = ?");
        $stmt->execute([$campaignId, $currentUser['id']]);
        
        if ($stmt->fetch()) {
            $stmt = db()->prepare("
                INSERT INTO ad_placements 
                (campaign_id, channel_name, admin_name, reach_24h, price, subscribers_gained, placement_date, theme, creative_text) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $campaignId, $channelName, $adminName, $reach24h, $price, 
                $subscribersGained, $placementDate, $theme, $creativeText
            ]);
            
            header('Location: /dashboard/placements.php?saved=1');
            exit;
        }
    }
}

// Получаем размещения
$query = "
    SELECT 
        ap.*,
        c.campaign_name,
        c.channel_name as advertised_channel
    FROM ad_placements ap
    JOIN campaigns c ON ap.campaign_id = c.id
    WHERE c.user_id = ?
";
$params = [$currentUser['id']];

if ($campaignFilter) {
    $query .= " AND c.id = ?";
    $params[] = $campaignFilter;
}

$query .= " ORDER BY ap.placement_date DESC, ap.created_at DESC";

$stmt = db()->prepare($query);
$stmt->execute($params);
$placements = $stmt->fetchAll();

$showForm = isset($_GET['action']) && $_GET['action'] === 'new';
?>

<div class="placements-container">
    <!-- Заголовок страницы -->
    <div class="page-header">
        <div class="header-content">
            <h1 class="page-title">Размещения рекламы</h1>
            <p class="page-subtitle">Добавляйте и отслеживайте все ваши рекламные размещения</p>
        </div>
        <div class="header-stats">
            <div class="stat-badge">
                <div class="stat-number"><?php echo count($placements); ?></div>
                <div class="stat-label">Всего размещений</div>
            </div>
            <?php if ($currentUser['role'] === 'user'): ?>
            <div class="stat-badge limit">
                <div class="stat-number"><?php echo $placementsThisMonth; ?>/50</div>
                <div class="stat-label">В этом месяце</div>
            </div>
            <?php endif; ?>
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
        <span>Размещение успешно добавлено!</span>
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
        <span>Размещение удалено</span>
    </div>
    <?php endif; ?>

    <?php if (!$canAddPlacement && !$showForm): ?>
    <div class="alert alert-warning">
        <div class="alert-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M10.29 3.86L1.82 18C1.64 18.37 1.64 18.82 1.82 19.19C2 19.56 2.37 19.78 2.77 19.78H21.23C21.63 19.78 22 19.56 22.18 19.19C22.36 18.82 22.36 18.37 22.18 18L13.71 3.86C13.53 3.49 13.16 3.27 12.76 3.27C12.36 3.27 11.99 3.49 11.81 3.86H10.29Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <line x1="12" y1="9" x2="12" y2="13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <line x1="12" y1="17" x2="12.01" y2="17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <span>Вы достигли лимита в 50 размещений в месяц. <a href="/pricing.php">Обновитесь до Premium</a> для безлимитного доступа.</span>
    </div>
    <?php endif; ?>

    <?php if ($showForm && count($userCampaigns) > 0): ?>
    <!-- Форма добавления размещения -->
    <div class="card">
        <div class="card-header">
            <div class="card-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <line x1="12" y1="5" x2="12" y2="19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <line x1="5" y1="12" x2="19" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h3 class="card-title">Новое размещение</h3>
        </div>
        <div class="card-body">
            <?php if (!$canAddPlacement): ?>
            <div class="alert alert-warning">
                <div class="alert-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M10.29 3.86L1.82 18C1.64 18.37 1.64 18.82 1.82 19.19C2 19.56 2.37 19.78 2.77 19.78H21.23C21.63 19.78 22 19.56 22.18 19.19C22.36 18.82 22.36 18.37 22.18 18L13.71 3.86C13.53 3.49 13.16 3.27 12.76 3.27C12.36 3.27 11.99 3.49 11.81 3.86H10.29Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <line x1="12" y1="9" x2="12" y2="13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <line x1="12" y1="17" x2="12.01" y2="17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <span>Вы достигли лимита размещений. Обновитесь до Premium для продолжения.</span>
            </div>
            <?php else: ?>
            <form method="POST" action="/dashboard/placements.php" class="modern-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="campaign_id" class="form-label">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                            </svg>
                            Кампания <span class="required">*</span>
                        </label>
                        <select id="campaign_id" name="campaign_id" class="form-select" required>
                            <option value="">Выберите кампанию</option>
                            <?php foreach ($userCampaigns as $campaign): ?>
                            <option value="<?php echo $campaign['id']; ?>" <?php echo $campaignFilter == $campaign['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($campaign['campaign_name']); ?> 
                                (<?php echo htmlspecialchars($campaign['channel_name']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="placement_date" class="form-label">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2" stroke="currentColor" stroke-width="2"/>
                                <line x1="16" y1="2" x2="16" y2="6" stroke="currentColor" stroke-width="2"/>
                                <line x1="8" y1="2" x2="8" y2="6" stroke="currentColor" stroke-width="2"/>
                                <line x1="3" y1="10" x2="21" y2="10" stroke="currentColor" stroke-width="2"/>
                            </svg>
                            Дата размещения <span class="required">*</span>
                        </label>
                        <input type="date" id="placement_date" name="placement_date" class="form-input"
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="channel_name" class="form-label">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M21 15C21 15.5304 20.7893 16.0391 20.4142 16.4142C20.0391 16.7893 19.5304 17 19 17H7L3 21V5C3 4.46957 3.21071 3.96086 3.58579 3.58579C3.96086 3.21071 4.46957 3 5 3H19C19.5304 3 20.0391 3.21071 20.4142 3.58579C20.7893 3.96086 21 4.46957 21 5V15Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Канал размещения <span class="required">*</span>
                        </label>
                        <input type="text" id="channel_name" name="channel_name" class="form-input"
                               placeholder="Название канала, где размещена реклама" required>
                        <div class="form-hint">Канал, в котором была размещена ваша реклама</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_name" class="form-label">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M20 21V19C20 17.9391 19.5786 16.9217 18.8284 16.1716C18.0783 15.4214 17.0609 15 16 15H8C6.93913 15 5.92172 15.4214 5.17157 16.1716C4.42143 16.9217 4 17.9391 4 19V21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2"/>
                            </svg>
                            Админ канала
                        </label>
                        <input type="text" id="admin_name" name="admin_name" class="form-input"
                               placeholder="Имя или контакт админа">
                        <div class="form-hint">Необязательно, для связи в будущем</div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="reach_24h" class="form-label">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M1 12S5 4 12 4S23 12 23 12S19 20 12 20S1 12 1 12Z" stroke="currentColor" stroke-width="2"/>
                                <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
                            </svg>
                            Охват за 24ч <span class="required">*</span>
                        </label>
                        <input type="number" id="reach_24h" name="reach_24h" class="form-input"
                               placeholder="Например: 5000" min="1" required>
                        <div class="form-hint">Количество просмотров за сутки</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="price" class="form-label">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <line x1="12" y1="1" x2="12" y2="23" stroke="currentColor" stroke-width="2"/>
                                <path d="M17 5H9.5C8.57174 5 7.6815 5.36875 7.02513 6.02513C6.36875 6.6815 6 7.57174 6 8.5C6 9.42826 6.36875 10.3185 7.02513 10.9749C7.6815 11.6313 8.57174 12 9.5 12H14.5C15.4283 12 16.3185 12.3687 16.9749 13.0251C17.6313 13.6815 18 14.5717 18 15.5C18 16.4283 17.6313 17.3185 16.9749 17.9749C16.3185 18.6313 15.4283 19 14.5 19H6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Цена размещения, ₽ <span class="required">*</span>
                        </label>
                        <input type="number" id="price" name="price" class="form-input"
                               placeholder="Например: 1500" min="1" step="0.01" required>
                        <div class="form-hint">Стоимость размещения в рублях</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="subscribers_gained" class="form-label">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M16 21V19C16 17.9391 15.5786 16.9217 14.8284 16.1716C14.0783 15.4214 13.0609 15 12 15H5C3.93913 15 2.92172 15.4214 1.17157 16.1716C0.421427 16.9217 0 17.9391 0 19V21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <circle cx="8.5" cy="7" r="4" stroke="currentColor" stroke-width="2"/>
                                <line x1="20" y1="8" x2="20" y2="14" stroke="currentColor" stroke-width="2"/>
                                <line x1="23" y1="11" x2="17" y2="11" stroke="currentColor" stroke-width="2"/>
                            </svg>
                            Пришло подписчиков
                        </label>
                        <input type="number" id="subscribers_gained" name="subscribers_gained" class="form-input"
                               placeholder="0" min="0" value="0">
                        <div class="form-hint">Можете указать позже</div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="theme" class="form-label">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M20.24 12.24C21.3658 11.1142 21.9983 9.58722 21.9983 7.99504C21.9983 6.40285 21.3658 4.87588 20.24 3.75004C19.1142 2.62419 17.5872 1.9917 15.995 1.9917C14.4028 1.9917 12.8758 2.62419 11.75 3.75004L5 10.5V19H13.5L20.24 12.24Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M16 8L2 22" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M17.5 15H9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Тематика канала
                        </label>
                        <select id="theme" name="theme" class="form-select">
                            <option value="">Не указано</option>
                            <option value="Видеоигры">Видеоигры</option>
                            <option value="Отдых и развлечения">Отдых и развлечения</option>
                            <option value="Интернет технологии">Интернет технологии</option>
                            <option value="Новости и СМИ">Новости и СМИ</option>
                            <option value="Юмор и мемы">Юмор и мемы</option>
                            <option value="Искусство и дизайн">Искусство и дизайн</option>
                            <option value="Кино">Кино</option>
                            <option value="Бизнес">Бизнес</option>
                            <option value="Образование">Образование</option>
                            <option value="Другое">Другое</option>
                        </select>
                        <div class="form-hint">Поможет в анализе эффективности</div>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="creative_text" class="form-label">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M14 2H6C5.46957 2 4.96086 2.21071 4.58579 2.58579C4.21071 2.96086 4 3.46957 4 4V20C4 20.5304 4.21071 21.0391 4.58579 21.4142C4.96086 21.7893 5.46957 22 6 22H18C18.5304 22 19.0391 21.7893 19.4142 21.4142C19.7893 21.0391 20 20.5304 20 20V8L14 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <polyline points="14,2 14,8 20,8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <line x1="16" y1="13" x2="8" y2="13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <line x1="16" y1="17" x2="8" y2="17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <polyline points="10,9 9,9 8,9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Описание креатива
                        </label>
                        <textarea id="creative_text" name="creative_text" rows="3" class="form-textarea"
                                  placeholder="Например: Розыгрыш Premium подписки на 3 месяца"></textarea>
                        <div class="form-hint">Краткое описание рекламного материала</div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M19 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V5C3 4.46957 3.21071 3.96086 3.58579 3.58579C3.96086 3.21071 4.46957 3 5 3H16L21 8V19C21 19.5304 20.7893 20.0391 20.4142 20.4142C20.0391 20.7893 19.5304 21 19 21Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <polyline points="17,21 17,13 7,13 7,21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <polyline points="7,3 7,8 15,8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Добавить размещение
                    </button>
                    <a href="/dashboard/placements.php" class="btn btn-secondary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <line x1="18" y1="6" x2="6" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <line x1="6" y1="6" x2="18" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Отмена
                    </a>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <?php elseif ($showForm && count($userCampaigns) === 0): ?>
    <div class="alert alert-warning">
        <div class="alert-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M10.29 3.86L1.82 18C1.64 18.37 1.64 18.82 1.82 19.19C2 19.56 2.37 19.78 2.77 19.78H21.23C21.63 19.78 22 19.56 22.18 19.19C22.36 18.82 22.36 18.37 22.18 18L13.71 3.86C13.53 3.49 13.16 3.27 12.76 3.27C12.36 3.27 11.99 3.49 11.81 3.86H10.29Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <line x1="12" y1="9" x2="12" y2="13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <line x1="12" y1="17" x2="12.01" y2="17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <span>Сначала создайте кампанию, чтобы добавлять размещения.
        <a href="/dashboard/campaigns.php?action=new">Создать кампанию</a></span>
    </div>
    <?php endif; ?>

    <?php if (!$showForm): ?>
    <!-- Список размещений -->
    <div class="card">
        <div class="card-header">
            <div class="card-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2" stroke="currentColor" stroke-width="2"/>
                    <rect x="9" y="9" width="6" height="6" stroke="currentColor" stroke-width="2"/>
                </svg>
            </div>
            <h3 class="card-title">
                Все размещения (<?php echo count($placements); ?>)
                <?php if ($campaignFilter): ?>
                    <span class="filter-badge">Фильтр по кампании</span>
                <?php endif; ?>
            </h3>
            <div class="header-actions">
                <?php if ($campaignFilter): ?>
                    <a href="/dashboard/placements.php" class="btn btn-secondary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <line x1="18" y1="6" x2="6" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <line x1="6" y1="6" x2="18" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Сбросить фильтр
                    </a>
                <?php endif; ?>
                <?php if ($canAddPlacement): ?>
                    <a href="/dashboard/placements.php?action=new<?php echo $campaignFilter ? '&campaign=' . $campaignFilter : ''; ?>" 
                       class="btn btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <line x1="12" y1="5" x2="12" y2="19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <line x1="5" y1="12" x2="19" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Добавить размещение
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body table-container">
            <?php if (count($placements) > 0): ?>
            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Дата</th>
                            <th>Канал размещения</th>
                            <th>Кампания</th>
                            <th>Охват</th>
                            <th>Цена</th>
                            <th>CPM</th>
                            <th>Подписчики</th>
                            <th>Цена подп.</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($placements as $placement): ?>
                        <tr>
                            <td>
                                <div class="date-cell">
                                    <?php echo date('d.m.Y', strtotime($placement['placement_date'])); ?>
                                </div>
                            </td>
                            <td>
                                <div class="channel-info">
                                    <div class="channel-name"><?php echo htmlspecialchars($placement['channel_name']); ?></div>
                                    <?php if ($placement['admin_name']): ?>
                                        <div class="admin-name">Админ: <?php echo htmlspecialchars($placement['admin_name']); ?></div>
                                    <?php endif; ?>
                                    <?php if ($placement['theme']): ?>
                                        <span class="theme-badge"><?php echo htmlspecialchars($placement['theme']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="campaign-tag"><?php echo htmlspecialchars($placement['campaign_name']); ?></span>
                            </td>
                            <td>
                                <div class="number-cell"><?php echo number_format($placement['reach_24h'], 0, ',', ' '); ?></div>
                            </td>
                            <td>
                                <div class="price-cell">₽<?php echo number_format($placement['price'], 0, ',', ' '); ?></div>
                            </td>
                            <td>
                                <div class="number-cell">₽<?php echo number_format($placement['cpm'], 2, ',', ' '); ?></div>
                            </td>
                            <td>
                                <?php if ($placement['subscribers_gained'] > 0): ?>
                                    <span class="subscribers-badge"><?php echo $placement['subscribers_gained']; ?></span>
                                <?php else: ?>
                                    <span class="no-data">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($placement['price_per_subscriber'] > 0): ?>
                                    <div class="number-cell">₽<?php echo number_format($placement['price_per_subscriber'], 2, ',', ' '); ?></div>
                                <?php else: ?>
                                    <span class="no-data">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="/dashboard/placements.php?edit=<?php echo $placement['id']; ?>" 
                                       class="action-btn edit" title="Редактировать">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M17 3C17.5523 3 18 3.44772 18 4V20C18 20.5523 17.5523 21 17 21H7C6.44772 21 6 20.5523 6 20V4C6 3.44772 6.44772 3 7 3H17Z" stroke="currentColor" stroke-width="2"/>
                                            <path d="M9 9H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                            <path d="M9 13H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                            <path d="M9 17H13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                        </svg>
                                    </a>
                                    <a href="/dashboard/placements.php?delete=<?php echo $placement['id']; ?>" 
                                       class="action-btn delete" 
                                       onclick="return confirm('Удалить размещение?')"
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
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2" stroke="currentColor" stroke-width="1.5"/>
                        <rect x="9" y="9" width="6" height="6" stroke="currentColor" stroke-width="1.5"/>
                    </svg>
                </div>
                <h3 class="empty-title">Нет размещений</h3>
                <p class="empty-desc">Начните добавлять данные о размещениях рекламы для отслеживания эффективности</p>
                <?php if (count($userCampaigns) > 0 && $canAddPlacement): ?>
                    <a href="/dashboard/placements.php?action=new" class="empty-action">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <line x1="12" y1="5" x2="12" y2="19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <line x1="5" y1="12" x2="19" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Добавить первое размещение
                    </a>
                <?php elseif (count($userCampaigns) === 0): ?>
                    <a href="/dashboard/campaigns.php?action=new" class="empty-action">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                            <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                            <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                        </svg>
                        Создать кампанию
                    </a>
                <?php endif; ?>
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

.placements-container {
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
    gap: 16px;
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

.stat-badge.limit {
    background: rgba(245, 158, 11, 0.2);
    border-color: rgba(245, 158, 11, 0.3);
}

.stat-number {
    font-size: 20px;
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

.alert-warning {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    color: var(--warning);
    border: 1px solid #fbbf24;
}

.alert-warning a {
    color: var(--primary);
    font-weight: 600;
    text-decoration: none;
}

.alert-warning a:hover {
    text-decoration: underline;
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
    gap: 12px;
}

.filter-badge {
    background: linear-gradient(135deg, var(--info), var(--primary));
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.card-body {
    padding: 24px;
}

.table-container {
    padding: 0;
}

.header-actions {
    display: flex;
    gap: 12px;
    align-items: center;
}

/* Формы */
.modern-form {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-group.full-width {
    grid-column: 1 / -1;
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

.form-input,
.form-select,
.form-textarea {
    padding: 12px 16px;
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    font-size: 14px;
    transition: all 0.2s;
    background: var(--bg-primary);
    font-family: inherit;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.form-textarea {
    resize: vertical;
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
    position: sticky;
    top: 0;
    z-index: 10;
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

.date-cell {
    font-variant-numeric: tabular-nums;
    color: var(--text-secondary);
    font-weight: 500;
}

.channel-info {
    min-width: 180px;
}

.channel-name {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.admin-name {
    font-size: 12px;
    color: var(--text-tertiary);
    margin-bottom: 6px;
}

.theme-badge {
    background: linear-gradient(135deg, var(--bg-tertiary), var(--bg-secondary));
    color: var(--text-secondary);
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
    border: 1px solid var(--border);
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

.subscribers-badge {
    background: linear-gradient(135deg, var(--success), #059669);
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.number-cell {
    font-variant-numeric: tabular-nums;
    color: var(--text-secondary);
    font-weight: 500;
}

.price-cell {
    font-weight: 600;
    color: var(--text-primary);
    font-variant-numeric: tabular-nums;
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
    .placements-container {
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
    
    .header-stats {
        flex-direction: row;
        justify-content: center;
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
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .header-actions {
        flex-direction: column;
        gap: 8px;
        width: 100%;
    }
    
    .table-responsive {
        margin: 0 -20px;
        padding: 0 20px;
    }
    
    .modern-table {
        min-width: 800px;
    }
}

@media (max-width: 480px) {
    .header-stats {
        flex-direction: column;
        gap: 12px;
    }
    
    .stat-badge {
        width: 100%;
        max-width: 200px;
    }
    
    .modern-table th,
    .modern-table td {
        padding: 12px 8px;
        font-size: 13px;
    }
    
    .channel-info {
        min-width: auto;
    }
}
</style>

<?php require_once 'footer.php'; ?>
