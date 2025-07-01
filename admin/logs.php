<?php$pageTitle = 'Логи системы';
require_once 'header.php';

// Фильтры
$actionFilter = $_GET['action'] ?? '';
$adminFilter = $_GET['admin'] ?? '';
$search = trim($_GET['search'] ?? '');
$period = $_GET['period'] ?? '7';
$sortBy = $_GET['sort'] ?? 'created_at';
$sortOrder = $_GET['order'] ?? 'DESC';
$page = (int)($_GET['page'] ?? 1);
$perPage = 50;

// Валидация сортировки
$allowedSorts = ['created_at', 'action', 'admin_id'];
$allowedOrders = ['ASC', 'DESC'];

if (!in_array($sortBy, $allowedSorts)) {
    $sortBy = 'created_at';
}
if (!in_array($sortOrder, $allowedOrders)) {
    $sortOrder = 'DESC';
}

// Условие для даты
$dateCondition = "";
if ($period !== 'all' && is_numeric($period)) {
    $dateCondition = "AND al.created_at >= DATE_SUB(NOW(), INTERVAL $period DAY)";
}

// Получаем общее количество записей
$countQuery = "
    SELECT COUNT(*) as total 
    FROM admin_logs al 
    JOIN users u ON al.admin_id = u.id 
    WHERE 1=1
";

$params = [];

if ($actionFilter) {
    $countQuery .= " AND al.action = ?";
    $params[] = $actionFilter;
}

if ($adminFilter) {
    $countQuery .= " AND al.admin_id = ?";
    $params[] = $adminFilter;
}

if ($search) {
    $countQuery .= " AND (al.old_value LIKE ? OR al.new_value LIKE ? OR al.target_id LIKE ? OR u.username LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$countQuery .= " $dateCondition";

$stmt = db()->prepare($countQuery);
$stmt->execute($params);
$totalLogs = $stmt->fetch()['total'];

// Рассчитываем пагинацию
$totalPages = ceil($totalLogs / $perPage);
$page = max(1, min($page, $totalPages));
$offset = ($page - 1) * $perPage;

// Получаем логи с пагинацией
$query = "
    SELECT al.*, u.username as admin_name, u.email as admin_email 
    FROM admin_logs al 
    JOIN users u ON al.admin_id = u.id 
    WHERE 1=1
";

// Восстанавливаем параметры для основного запроса
$params = [];

if ($actionFilter) {
    $query .= " AND al.action = ?";
    $params[] = $actionFilter;
}

if ($adminFilter) {
    $query .= " AND al.admin_id = ?";
    $params[] = $adminFilter;
}

if ($search) {
    $query .= " AND (al.old_value LIKE ? OR al.new_value LIKE ? OR al.target_id LIKE ? OR u.username LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$query .= " $dateCondition ORDER BY al.$sortBy $sortOrder LIMIT $perPage OFFSET $offset";

$stmt = db()->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Получаем список админов для фильтра
$stmt = db()->query("SELECT id, username FROM users WHERE role = 'admin' ORDER BY username");
$admins = $stmt->fetchAll();

// Статистика действий за период
$statsQuery = "
    SELECT action, COUNT(*) as count 
    FROM admin_logs 
    WHERE 1=1
";

if ($period !== 'all' && is_numeric($period)) {
    $statsQuery .= " AND created_at >= DATE_SUB(NOW(), INTERVAL $period DAY)";
}

$statsQuery .= " GROUP BY action ORDER BY count DESC LIMIT 8";

$stmt = db()->query($statsQuery);
$actionStats = $stmt->fetchAll();

// Статистика по администраторам
$adminStatsQuery = "
    SELECT u.username, COUNT(*) as actions_count 
    FROM admin_logs al 
    JOIN users u ON al.admin_id = u.id 
    WHERE 1=1
";

if ($period !== 'all' && is_numeric($period)) {
    $adminStatsQuery .= " AND al.created_at >= DATE_SUB(NOW(), INTERVAL $period DAY)";
}

$adminStatsQuery .= " GROUP BY al.admin_id ORDER BY actions_count DESC LIMIT 5";

$stmt = db()->query($adminStatsQuery);
$adminStats = $stmt->fetchAll();

// Форматирование действий
$actionLabels = [
    'login' => ['icon' => '🔐', 'label' => 'Вход в систему', 'color' => 'blue', 'description' => 'Администратор вошел в панель управления'],
    'logout' => ['icon' => '🚪', 'label' => 'Выход из системы', 'color' => 'gray', 'description' => 'Администратор вышел из системы'],
    'change_role' => ['icon' => '👤', 'label' => 'Изменение роли', 'color' => 'yellow', 'description' => 'Изменена роль пользователя'],
    'block_user' => ['icon' => '🚫', 'label' => 'Блокировка', 'color' => 'red', 'description' => 'Пользователь заблокирован'],
    'unblock_user' => ['icon' => '✅', 'label' => 'Разблокировка', 'color' => 'green', 'description' => 'Пользователь разблокирован'],
    'delete_user' => ['icon' => '🗑️', 'label' => 'Удаление', 'color' => 'red', 'description' => 'Пользователь удален из системы'],
    'update_settings' => ['icon' => '⚙️', 'label' => 'Настройки', 'color' => 'purple', 'description' => 'Изменены настройки системы'],
    'manual_payment' => ['icon' => '💰', 'label' => 'Платеж', 'color' => 'green', 'description' => 'Подтвержден платеж вручную'],
    'refund_payment' => ['icon' => '💸', 'label' => 'Возврат', 'color' => 'orange', 'description' => 'Выполнен возврат платежа'],
    'view_user' => ['icon' => '👁️', 'label' => 'Просмотр', 'color' => 'blue', 'description' => 'Просмотрен профиль пользователя'],
    'export_data' => ['icon' => '📊', 'label' => 'Экспорт', 'color' => 'indigo', 'description' => 'Экспортированы данные'],
    'approve_campaign' => ['icon' => '✔️', 'label' => 'Одобрение', 'color' => 'green', 'description' => 'Кампания одобрена'],
    'reject_campaign' => ['icon' => '❌', 'label' => 'Отклонение', 'color' => 'red', 'description' => 'Кампания отклонена']
];

// Общая статистика
$statsTimeQuery = "
    SELECT 
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today,
        SUM(CASE WHEN DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN 1 ELSE 0 END) as yesterday,
        SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as week,
        COUNT(*) as total
    FROM admin_logs
";
$stmt = db()->query($statsTimeQuery);
$timeStats = $stmt->fetch();

// Показываем сообщение после редиректа
$message = $_GET['message'] ?? '';?>



<div class="page-container">
    <?php if ($message):?>
        <div class="alert alert-success">
            <span>✅</span>
            <?= htmlspecialchars($message)?>
        </div>
    <?php endif;?>

    <!-- Статистика -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon total">📊</div>
            <div class="stat-value"><?= number_format($timeStats['total'])?></div>
            <div class="stat-label">Всего записей</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon today">📈</div>
            <div class="stat-value"><?= number_format($timeStats['today'])?></div>
            <div class="stat-label">Сегодня</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon yesterday">📅</div>
            <div class="stat-value"><?= number_format($timeStats['yesterday'])?></div>
            <div class="stat-label">Вчера</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon week">📆</div>
            <div class="stat-value"><?= number_format($timeStats['week'])?></div>
            <div class="stat-label">За неделю</div>
        </div>
    </div>

    <!-- Графики активности -->
    <div class="activity-section">
        <div class="activity-card">
            <h3 class="activity-title">Популярные действия</h3>
            <div class="action-list">
                <?php if (empty($actionStats)):?>
                    <div class="empty-state">
                        <div class="empty-icon">📊</div>
                        <div class="empty-title">Нет данных</div>
                        <div class="empty-desc">За выбранный период действий не найдено</div>
                    </div>
                <?php else:?>
                    <?php foreach ($actionStats as $stat):?>
                        <?php 
                        $actionInfo = $actionLabels[$stat['action']] ?? ['icon' => '❓', 'label' => $stat['action'], 'color' => 'gray'];?>
                        <div class="action-item">
                            <div class="action-info">
                                <div class="action-icon <?= $actionInfo['color']?>">
                                    <?= $actionInfo['icon']?>
                                </div>
                                <div class="action-name"><?= $actionInfo['label']?></div>
                            </div>
                            <div class="action-count"><?= number_format($stat['count'])?></div>
                        </div>
                    <?php endforeach;?>
                <?php endif;?>
            </div>
        </div>

        <div class="activity-card">
            <h3 class="activity-title">Активные администраторы</h3>
            <div class="admin-list">
                <?php if (empty($adminStats)):?>
                    <div class="empty-state">
                        <div class="empty-icon">👥</div>
                        <div class="empty-title">Нет данных</div>
                        <div class="empty-desc">За выбранный период активности не найдено</div>
                    </div>
                <?php else:?>
                    <?php foreach ($adminStats as $stat):?>
                        <div class="admin-item">
                            <div class="admin-info">
                                <div class="admin-avatar">
                                    <?= strtoupper(substr($stat['username'], 0, 2))?>
                                </div>
                                <div class="admin-name"><?= htmlspecialchars($stat['username'])?></div>
                            </div>
                            <div class="admin-actions"><?= number_format($stat['actions_count'])?></div>
                        </div>
                    <?php endforeach;?>
                <?php endif;?>
            </div>
        </div>
    </div>

    <!-- Фильтры -->
    <div class="filters-card">
        <form method="GET" action="">
            <div class="filters-grid">
                <div class="form-group">
                    <label class="form-label">Поиск</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search)?>" 
                           placeholder="Поиск по значениям, ID или администратору..." class="form-input">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Действие</label>
                    <select name="action" class="form-select">
                        <option value="">Все действия</option>
                        <?php foreach ($actionLabels as $action => $info):?>
                            <option value="<?= $action?>" <?= $actionFilter === $action ? 'selected' : ''?>>
                                <?= $info['icon']?> <?= $info['label']?>
                            </option>
                        <?php endforeach;?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Администратор</label>
                    <select name="admin" class="form-select">
                        <option value="">Все администраторы</option>
                        <?php foreach ($admins as $admin):?>
                            <option value="<?= $admin['id']?>" <?= $adminFilter == $admin['id'] ? 'selected' : ''?>>
                                <?= htmlspecialchars($admin['username'])?>
                            </option>
                        <?php endforeach;?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Период</label>
                    <select name="period" class="form-select">
                        <option value="1" <?= $period === '1' ? 'selected' : ''?>>Сегодня</option>
                        <option value="7" <?= $period === '7' ? 'selected' : ''?>>7 дней</option>
                        <option value="30" <?= $period === '30' ? 'selected' : ''?>>30 дней</option>
                        <option value="90" <?= $period === '90' ? 'selected' : ''?>>90 дней</option>
                        <option value="all" <?= $period === 'all' ? 'selected' : ''?>>Все время</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    🔍 Фильтровать
                </button>
            </div>
        </form>
    </div>

    <!-- Таблица логов -->
    <div class="logs-table-card">
        <div class="table-header">
            <div>
                <h2 class="table-title">Журнал действий</h2>
                <div class="table-subtitle">Найдено записей: <?= number_format($totalLogs)?></div>
            </div>
        </div>
        
        <div class="table-container">
            <?php if (empty($logs)):?>
                <div class="empty-state">
                    <div class="empty-icon">📋</div>
                    <div class="empty-title">Логи не найдены</div>
                    <div class="empty-desc">Попробуйте изменить параметры фильтрации</div>
                </div>
            <?php else:?>
                <table class="logs-table">
                    <thead>
                        <tr>
                            <th class="sortable <?= $sortBy === 'created_at' ? 'sorted-' . strtolower($sortOrder) : ''?>">
                                <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'created_at', 'order' => $sortBy === 'created_at' && $sortOrder === 'DESC' ? 'ASC' : 'DESC']))?>" style="color: inherit; text-decoration: none;">
                                    Время
                                </a>
                            </th>
                            <th class="sortable <?= $sortBy === 'admin_id' ? 'sorted-' . strtolower($sortOrder) : ''?>">
                                <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'admin_id', 'order' => $sortBy === 'admin_id' && $sortOrder === 'DESC' ? 'ASC' : 'DESC']))?>" style="color: inherit; text-decoration: none;">
                                    Администратор
                                </a>
                            </th>
                            <th class="sortable <?= $sortBy === 'action' ? 'sorted-' . strtolower($sortOrder) : ''?>">
                                <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'action', 'order' => $sortBy === 'action' && $sortOrder === 'DESC' ? 'ASC' : 'DESC']))?>" style="color: inherit; text-decoration: none;">
                                    Действие
                                </a>
                            </th>
                            <th>Детали</th>
                            <th>IP адрес</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log):?>
                            <?php 
                            $actionInfo = $actionLabels[$log['action']] ?? ['icon' => '❓', 'label' => $log['action'], 'color' => 'gray'];
                            $logDate = new DateTime($log['created_at']);?>
                            <tr>
                                <td>
                                    <div class="time-info">
                                        <div class="time-date"><?= $logDate->format('d.m.Y')?></div>
                                        <div class="time-clock"><?= $logDate->format('H:i:s')?></div>
                                    </div>
                                </td>
                                
                                <td>
                                    <div class="admin-info">
                                        <div class="admin-avatar">
                                            <?= strtoupper(substr($log['admin_name'], 0, 2))?>
                                        </div>
                                        <div class="admin-details">
                                            <h4><?= htmlspecialchars($log['admin_name'])?></h4>
                                            <p><?= htmlspecialchars($log['admin_email'])?></p>
                                        </div>
                                    </div>
                                </td>
                                
                                <td>
                                    <div class="action-badge <?= $actionInfo['color']?>">
                                        <span><?= $actionInfo['icon']?></span>
                                        <?= $actionInfo['label']?>
                                    </div>
                                </td>
                                
                                <td>
                                    <div class="action-details">
                                        <?php if ($log['target_type'] && $log['target_id']):?>
                                            <div class="action-target">
                                                <?= htmlspecialchars($log['target_type'])?> #<?= htmlspecialchars($log['target_id'])?>
                                            </div>
                                        <?php endif;?>
                                        
                                        <?php if ($log['old_value'] && $log['new_value']):?>
                                            <div class="action-changes">
                                                <span class="old-value"><?= htmlspecialchars($log['old_value'])?></span>
                                                →
                                                <span class="new-value"><?= htmlspecialchars($log['new_value'])?></span>
                                            </div>
                                        <?php elseif ($log['new_value']):?>
                                            <div class="action-changes">
                                                <span class="new-value"><?= htmlspecialchars($log['new_value'])?></span>
                                            </div>
                                        <?php endif;?>
                                    </div>
                                </td>
                                
                                <td>
                                    <div class="ip-address"><?= htmlspecialchars($log['ip_address'])?></div>
                                </td>
                            </tr>
                        <?php endforeach;?>
                    </tbody>
                </table>
            <?php endif;?>
        </div>
        
        <!-- Пагинация -->
        <?php if ($totalPages > 1):?>
            <div class="pagination">
                <?php if ($page > 1):?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1]))?>">‹</a>
                <?php else:?>
                    <span class="disabled">‹</span>
                <?php endif;?>
                
                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                
                if ($startPage > 1):?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1]))?>">1</a>
                    <?php if ($startPage > 2):?>
                        <span>...</span>
                    <?php endif;?>
                <?php endif;?>
                
                <?php for ($i = $startPage; $i <= $endPage; $i++):?>
                    <?php if ($i == $page):?>
                        <span class="current"><?= $i?></span>
                    <?php else:?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i]))?>"><?= $i?></a>
                    <?php endif;?>
                <?php endfor;?>
                
                <?php if ($endPage < $totalPages):?>
                    <?php if ($endPage < $totalPages - 1):?>
                        <span>...</span>
                    <?php endif;?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $totalPages]))?>"><?= $totalPages?></a>
                <?php endif;?>
                
                <?php if ($page < $totalPages):?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1]))?>">›</a>
                <?php else:?>
                    <span class="disabled">›</span>
                <?php endif;?>
            </div>
        <?php endif;?>
    </div>
</div>



<?php require_once 'footer.php';?>

