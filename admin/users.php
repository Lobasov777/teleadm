<?php$pageTitle = 'Управление пользователями';
require_once 'header.php';

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);
    
    if ($action === 'change_role' && $userId) {
        $newRole = $_POST['new_role'] ?? '';
        $oldRole = $_POST['old_role'] ?? '';
        
        if (in_array($newRole, ['user', 'premium']) && $userId) {
            $stmt = db()->prepare("UPDATE users SET role = ? WHERE id = ? AND role != 'admin'");
            $stmt->execute([$newRole, $userId]);
            
            // Логируем действие
            $stmt = db()->prepare("
                INSERT INTO admin_logs (admin_id, action, target_type, target_id, old_value, new_value, ip_address) 
                VALUES (?, 'change_role', 'user', ?, ?, ?, ?)
            ");
            $stmt->execute([$currentUser['id'], $userId, $oldRole, $newRole, $_SERVER['REMOTE_ADDR'] ?? '']);
            $message = 'Роль пользователя успешно изменена';
        }
    }
    
    if ($action === 'block' && $userId) {
        $reason = trim($_POST['reason'] ?? 'Нарушение правил сервиса');
        $stmt = db()->prepare("UPDATE users SET is_blocked = TRUE, blocked_at = NOW(), blocked_reason = ? WHERE id = ? AND role != 'admin'");
        $stmt->execute([$reason, $userId]);
        $message = 'Пользователь заблокирован';
    }
    
    if ($action === 'unblock' && $userId) {
        $stmt = db()->prepare("UPDATE users SET is_blocked = FALSE, blocked_at = NULL, blocked_reason = NULL WHERE id = ? AND role != 'admin'");
        $stmt->execute([$userId]);
        $message = 'Пользователь разблокирован';
    }
    
    // Редирект для предотвращения повторной отправки формы
    if (isset($message)) {
        header("Location: users.php?message=" . urlencode($message));
        exit;
    }
}

// Показываем сообщение после редиректа
$message = $_GET['message'] ?? '';

// Фильтры
$search = trim($_GET['search'] ?? '');
$roleFilter = $_GET['role'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$sortBy = $_GET['sort'] ?? 'created_at';
$sortOrder = $_GET['order'] ?? 'DESC';

// Валидация сортировки
$allowedSorts = ['created_at', 'last_login', 'username', 'email'];
$allowedOrders = ['ASC', 'DESC'];

if (!in_array($sortBy, $allowedSorts)) {
    $sortBy = 'created_at';
}
if (!in_array($sortOrder, $allowedOrders)) {
    $sortOrder = 'DESC';
}

// Формируем запрос
$query = "
    SELECT u.*, 
           COUNT(DISTINCT c.id) as campaigns_count,
           COUNT(DISTINCT ap.id) as placements_count,
           COALESCE(SUM(ap.price), 0) as total_spent
    FROM users u
    LEFT JOIN campaigns c ON u.id = c.user_id
    LEFT JOIN ad_placements ap ON c.id = ap.campaign_id
    WHERE u.role != 'admin'
";

$params = [];
if ($search) {
    $query .= " AND (u.username LIKE ? OR u.email LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if ($roleFilter && in_array($roleFilter, ['user', 'premium'])) {
    $query .= " AND u.role = ?";
    $params[] = $roleFilter;
}

if ($statusFilter === 'blocked') {
    $query .= " AND u.is_blocked = TRUE";
} elseif ($statusFilter === 'active') {
    $query .= " AND u.is_blocked = FALSE";
}

$query .= " GROUP BY u.id ORDER BY u.$sortBy $sortOrder";

$stmt = db()->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Статистика
$stmt = db()->query("
    SELECT COUNT(*) as total,
           SUM(CASE WHEN role = 'premium' THEN 1 ELSE 0 END) as premium,
           SUM(CASE WHEN is_blocked = TRUE THEN 1 ELSE 0 END) as blocked,
           SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today
    FROM users WHERE role != 'admin'
");
$stats = $stmt->fetch();?>



<div class="page-container">
    <!-- Сообщение об успехе -->
    <?php if ($message):?>
        <div class="alert alert-success">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                <path d="M9 12L11 14L15 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
            </svg>
            <?php echo htmlspecialchars($message);?>
        </div>
    <?php endif;?>

    <!-- Статистика -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon total">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <path d="M17 21V19C17 17.9391 16.5786 16.9217 15.8284 16.1716C15.0783 15.4214 14.0609 15 13 15H5C3.93913 15 2.92172 15.4214 2.17157 16.1716C1.42143 16.9217 1 17.9391 1 19V21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="9" cy="7" r="4" stroke="currentColor" stroke-width="2"/>
                    <path d="M23 21V19C22.9993 18.1137 22.7044 17.2528 22.1614 16.5523C21.6184 15.8519 20.8581 15.3516 20 15.13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M16 3.13C16.8604 3.35031 17.623 3.85071 18.1676 4.55232C18.7122 5.25392 19.0078 6.11683 19.0078 7.005C19.0078 7.89318 18.7122 8.75608 18.1676 9.45769C17.623 10.1593 16.8604 10.6597 16 10.88" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <div class="stat-value"><?php echo number_format($stats['total']);?></div>
            <div class="stat-label">Всего пользователей</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon premium">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <div class="stat-value"><?php echo number_format($stats['premium']);?></div>
            <div class="stat-label">Premium подписки</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon blocked">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                    <line x1="4.93" y1="4.93" x2="19.07" y2="19.07" stroke="currentColor" stroke-width="2"/>
                </svg>
            </div>
            <div class="stat-value"><?php echo number_format($stats['blocked']);?></div>
            <div class="stat-label">Заблокировано</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon today">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2" stroke="currentColor" stroke-width="2"/>
                    <line x1="16" y1="2" x2="16" y2="6" stroke="currentColor" stroke-width="2"/>
                    <line x1="8" y1="2" x2="8" y2="6" stroke="currentColor" stroke-width="2"/>
                    <line x1="3" y1="10" x2="21" y2="10" stroke="currentColor" stroke-width="2"/>
                    <path d="M8 14H12V18H8V14Z" fill="currentColor"/>
                </svg>
            </div>
            <div class="stat-value"><?php echo number_format($stats['today']);?></div>
            <div class="stat-label">Новых сегодня</div>
        </div>
    </div>

    <!-- Фильтры -->
    <div class="filters-card">
        <form method="GET" class="filters-grid">
            <div class="form-group">
                <label class="form-label">Поиск по имени или email</label>
                <input type="text" name="search" class="form-input" placeholder="Введите имя пользователя или email" value="<?php echo htmlspecialchars($search);?>">
            </div>
            <div class="form-group">
                <label class="form-label">Роль пользователя</label>
                <select name="role" class="form-select">
                    <option value="">Все роли</option>
                    <option value="user" <?php echo $roleFilter === 'user' ? 'selected' : '';?>>Базовый</option>
                    <option value="premium" <?php echo $roleFilter === 'premium' ? 'selected' : '';?>>Premium</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Статус аккаунта</label>
                <select name="status" class="form-select">
                    <option value="">Все статусы</option>
                    <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : '';?>>Активные</option>
                    <option value="blocked" <?php echo $statusFilter === 'blocked' ? 'selected' : '';?>>Заблокированные</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Сортировка</label>
                <select name="sort" class="form-select">
                    <option value="created_at" <?php echo $sortBy === 'created_at' ? 'selected' : '';?>>По дате регистрации</option>
                    <option value="last_login" <?php echo $sortBy === 'last_login' ? 'selected' : '';?>>По последнему входу</option>
                    <option value="username" <?php echo $sortBy === 'username' ? 'selected' : '';?>>По имени</option>
                    <option value="email" <?php echo $sortBy === 'email' ? 'selected' : '';?>>По email</option>
                </select>
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

    <!-- Таблица пользователей -->
    <div class="users-table-card">
        <div class="table-header">
            <h3 class="table-title">Пользователи системы (<?php echo count($users);?>)</h3>
            <div style="display: flex; gap: 12px;">
                <a href="?sort=<?php echo $sortBy;?>&order=<?php echo $sortOrder === 'ASC' ? 'DESC' : 'ASC';?>&search=<?php echo urlencode($search);?>&role=<?php echo $roleFilter;?>&status=<?php echo $statusFilter;?>" class="btn btn-secondary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                        <path d="M3 6H21" stroke="currentColor" stroke-width="2"/>
                        <path d="M7 12H17" stroke="currentColor" stroke-width="2"/>
                        <path d="M10 18H14" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    Сортировка: <?php echo $sortOrder === 'ASC' ? '↑' : '↓';?>
                </a>
            </div>
        </div>
        
        <div class="table-container">
            <?php if (empty($users)):?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none">
                            <path d="M20 21V19C20 17.9391 19.5786 16.9217 18.8284 16.1716C18.0783 15.4214 17.0609 15 16 15H8C6.93913 15 5.92172 15.4214 5.17157 16.1716C4.42143 16.9217 4 17.9391 4 19V21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2"/>
                        </svg>
                    </div>
                    <div class="empty-title">Пользователи не найдены</div>
                    <div class="empty-desc">Попробуйте изменить параметры поиска или очистить фильтры</div>
                </div>
            <?php else:?>
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Пользователь</th>
                            <th>Роль</th>
                            <th>Статус</th>
                            <th>Активность</th>
                            <th>Регистрация</th>
                            <th>Последний вход</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user):?>
                        <tr>
                            <td>
                                <span class="user-id">#<?php echo $user['id'];?></span>
                            </td>
                            <td>
                                <div class="user-info">
                                    <div class="user-avatar">
                                        <?php echo strtoupper(substr($user['username'], 0, 1));?>
                                    </div>
                                    <div class="user-details">
                                        <h4><?php echo htmlspecialchars($user['username']);?></h4>
                                        <p><?php echo htmlspecialchars($user['email']);?></p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?php echo $user['role'];?>">
                                    <?php echo $user['role'] === 'premium' ? 'Premium' : 'Базовый';?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?php echo $user['is_blocked'] ? 'blocked' : 'active';?>">
                                    <?php echo $user['is_blocked'] ? 'Заблокирован' : 'Активен';?>
                                </span>
                            </td>
                            <td>
                                <div class="user-stats">
                                    <div class="stat-item">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                                            <rect x="2" y="3" width="20" height="14" rx="2" ry="2" stroke="currentColor" stroke-width="2"/>
                                            <line x1="8" y1="21" x2="16" y2="21" stroke="currentColor" stroke-width="2"/>
                                            <line x1="12" y1="17" x2="12" y2="21" stroke="currentColor" stroke-width="2"/>
                                        </svg>
                                        <span class="stat-number"><?php echo $user['campaigns_count'];?></span> кампаний
                                    </div>
                                    <div class="stat-item">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                                            <path d="M18 20V10" stroke="currentColor" stroke-width="2"/>
                                            <path d="M12 20V4" stroke="currentColor" stroke-width="2"/>
                                            <path d="M6 20V14" stroke="currentColor" stroke-width="2"/>
                                        </svg>
                                        <span class="stat-number"><?php echo $user['placements_count'];?></span> размещений
                                    </div>
                                    <div class="stat-item">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                                            <line x1="12" y1="1" x2="12" y2="23" stroke="currentColor" stroke-width="2"/>
                                            <path d="M17 5H9.5C8.57174 5 7.6815 5.36875 7.02513 6.02513C6.36875 6.6815 6 7.57174 6 8.5C6 9.42826 6.36875 10.3185 7.02513 10.9749C7.6815 11.6313 8.57174 12 9.5 12H14.5C15.4283 12 16.3185 12.3687 16.9749 13.0251C17.6313 13.6815 18 14.5717 18 15.5C18 16.4283 17.6313 17.3185 16.9749 17.9749C16.3185 18.6313 15.4283 19 14.5 19H6" stroke="currentColor" stroke-width="2"/>
                                        </svg>
                                        <span class="stat-number">₽<?php echo number_format($user['total_spent'], 0, ',', ' ');?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="font-size: 13px; color: #64748b;">
                                    <?php echo date('d.m.Y', strtotime($user['created_at']));?>
                                    <div style="font-size: 11px; color: #94a3b8;">
                                        <?php echo date('H:i', strtotime($user['created_at']));?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if ($user['last_login']):?>
                                    <div style="font-size: 13px; color: #64748b;">
                                        <?php echo date('d.m.Y', strtotime($user['last_login']));?>
                                        <div style="font-size: 11px; color: #94a3b8;">
                                            <?php echo date('H:i', strtotime($user['last_login']));?>
                                        </div>
                                    </div>
                                <?php else:?>
                                    <span style="color: #94a3b8; font-size: 13px;">Никогда</span>
                                <?php endif;?>
                            </td>
                            <td>
                                <div class="actions-dropdown">
                                    <button class="actions-btn" onclick="toggleDropdown(<?php echo $user['id'];?>)">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                            <circle cx="12" cy="12" r="1" stroke="currentColor" stroke-width="2"/>
                                            <circle cx="19" cy="12" r="1" stroke="currentColor" stroke-width="2"/>
                                            <circle cx="5" cy="12" r="1" stroke="currentColor" stroke-width="2"/>
                                        </svg>
                                        Действия
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none">
                                            <path d="M6 9L12 15L18 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </button>
                                    <div class="dropdown-menu" id="dropdown-<?php echo $user['id'];?>">
                                        <button class="dropdown-item" onclick="changeRole(<?php echo $user['id'];?>, '<?php echo $user['role'];?>', '<?php echo htmlspecialchars($user['username']);?>')">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                                <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" stroke="currentColor" stroke-width="2"/>
                                            </svg>
                                            Изменить роль
                                        </button>
                                        <?php if ($user['is_blocked']):?>
                                            <button class="dropdown-item" onclick="unblockUser(<?php echo $user['id'];?>, '<?php echo htmlspecialchars($user['username']);?>')">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                                    <path d="M18 6L6 18" stroke="currentColor" stroke-width="2"/>
                                                    <path d="M6 6L18 18" stroke="currentColor" stroke-width="2"/>
                                                </svg>
                                                Разблокировать
                                            </button>
                                        <?php else:?>
                                            <button class="dropdown-item danger" onclick="blockUser(<?php echo $user['id'];?>, '<?php echo htmlspecialchars($user['username']);?>')">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                                                    <line x1="4.93" y1="4.93" x2="19.07" y2="19.07" stroke="currentColor" stroke-width="2"/>
                                                </svg>
                                                Заблокировать
                                            </button>
                                        <?php endif;?>
                                        <button class="dropdown-item" onclick="loginAsUser(<?php echo $user['id'];?>)">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                                <path d="M15 3H19C19.5304 3 20.0391 3.21071 20.4142 3.58579C20.7893 3.96086 21 4.46957 21 5V19C21 19.5304 20.7893 20.0391 20.4142 20.4142C20.0391 20.7893 19.5304 21 19 21H15" stroke="currentColor" stroke-width="2"/>
                                                <polyline points="10,17 15,12 10,7" stroke="currentColor" stroke-width="2"/>
                                                <line x1="15" y1="12" x2="3" y2="12" stroke="currentColor" stroke-width="2"/>
                                            </svg>
                                            Войти как пользователь
                                        </button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach;?>
                    </tbody>
                </table>
            <?php endif;?>
        </div>
    </div>
</div>

<!-- Модальные окна -->
<div class="modal" id="roleModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Изменить роль пользователя</h3>
            <div class="modal-subtitle">Выберите новую роль для пользователя <strong id="roleUserName"></strong></div>
        </div>
        <form method="POST" id="roleForm">
            <input type="hidden" name="action" value="change_role">
            <input type="hidden" name="user_id" id="roleUserId">
            <input type="hidden" name="old_role" id="oldRole">
            
            <div class="form-group">
                <label class="form-label">Новая роль</label>
                <select name="new_role" class="form-select" id="newRole">
                    <option value="user">Базовый пользователь</option>
                    <option value="premium">Premium пользователь</option>
                </select>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('roleModal')">Отмена</button>
                <button type="submit" class="btn btn-primary">Изменить роль</button>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="blockModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Заблокировать пользователя</h3>
            <div class="modal-subtitle">Укажите причину блокировки пользователя <strong id="blockUserName"></strong></div>
        </div>
        <form method="POST" id="blockForm">
            <input type="hidden" name="action" value="block">
            <input type="hidden" name="user_id" id="blockUserId">
            
            <div class="form-group">
                <label class="form-label">Причина блокировки</label>
                <textarea name="reason" class="form-input" rows="4" placeholder="Укажите подробную причину блокировки пользователя..." required></textarea>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('blockModal')">Отмена</button>
                <button type="submit" class="btn btn-danger">Заблокировать пользователя</button>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="unblockModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Разблокировать пользователя</h3>
            <div class="modal-subtitle">Вы уверены, что хотите разблокировать пользователя <strong id="unblockUserName"></strong>?</div>
        </div>
        <form method="POST" id="unblockForm">
            <input type="hidden" name="action" value="unblock">
            <input type="hidden" name="user_id" id="unblockUserId">
            
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('unblockModal')">Отмена</button>
                <button type="submit" class="btn btn-primary">Разблокировать</button>
            </div>
        </form>
    </div>
</div>



<?php require_once 'footer.php';?>