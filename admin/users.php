<?php
$pageTitle = 'Управление пользователями';
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
$stats = $stmt->fetch();
?>

<style>
    /* Основные стили страницы */
    .page-container {
        padding: 32px;
        max-width: 1400px;
        margin: 0 auto;
    }

    /* Сообщения */
    .alert {
        padding: 16px 20px;
        border-radius: 12px;
        margin-bottom: 24px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .alert-success {
        background: rgba(34, 197, 94, 0.1);
        color: #16a34a;
        border: 1px solid rgba(34, 197, 94, 0.2);
    }

    /* Статистика */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 32px;
    }

    .stat-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 24px;
        text-align: center;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        opacity: 0;
        transform: translateY(20px);
    }

    .stat-card.animate-in {
        opacity: 1;
        transform: translateY(0);
    }

    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        margin: 0 auto 16px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
    }

    .stat-icon.total { background: linear-gradient(135deg, #3b82f6, #1e40af); }
    .stat-icon.premium { background: linear-gradient(135deg, #f59e0b, #d97706); }
    .stat-icon.blocked { background: linear-gradient(135deg, #ef4444, #dc2626); }
    .stat-icon.today { background: linear-gradient(135deg, #10b981, #047857); }

    .stat-value {
        font-size: 32px;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 8px;
        letter-spacing: -0.025em;
    }

    .stat-label {
        font-size: 14px;
        color: #64748b;
        font-weight: 600;
    }

    /* Фильтры */
    .filters-card {
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

    .filters-card.animate-in {
        opacity: 1;
        transform: translateY(0);
    }

    .filters-grid {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1fr auto;
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

    /* Таблица пользователей */
    .users-table-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        opacity: 0;
        transform: translateY(20px);
        transition: all 0.4s ease;
    }

    .users-table-card.animate-in {
        opacity: 1;
        transform: translateY(0);
    }

    .table-header {
        padding: 24px 32px;
        border-bottom: 1px solid #e2e8f0;
        background: linear-gradient(135deg, #f8fafc, #f1f5f9);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .table-title {
        font-size: 20px;
        font-weight: 700;
        color: #0f172a;
        margin: 0;
        letter-spacing: -0.025em;
    }

    .table-container {
        overflow-x: auto;
    }

    .users-table {
        width: 100%;
        border-collapse: collapse;
    }

    .users-table th {
        background: #f8fafc;
        padding: 16px 20px;
        text-align: left;
        font-weight: 700;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #475569;
        border-bottom: 1px solid #e2e8f0;
        white-space: nowrap;
    }

    .users-table td {
        padding: 20px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 14px;
        vertical-align: middle;
    }

    .users-table tr:hover {
        background: #f8fafc;
    }

    .users-table tr:last-child td {
        border-bottom: none;
    }

    .user-info {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 200px;
    }

    .user-avatar {
        width: 44px;
        height: 44px;
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

    .user-details h4 {
        font-weight: 600;
        margin: 0 0 4px 0;
        color: #0f172a;
        font-size: 15px;
    }

    .user-details p {
        margin: 0;
        font-size: 13px;
        color: #64748b;
        word-break: break-word;
    }

    .user-id {
        font-family: 'Monaco', 'Consolas', monospace;
        font-size: 12px;
        color: #64748b;
        background: #f1f5f9;
        padding: 4px 8px;
        border-radius: 6px;
        font-weight: 600;
    }

    /* Бейджи */
    .badge {
        display: inline-flex;
        align-items: center;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        white-space: nowrap;
    }

    .badge.premium {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: white;
    }

    .badge.user {
        background: #f1f5f9;
        color: #64748b;
    }

    .badge.blocked {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
    }

    .badge.active {
        background: linear-gradient(135deg, #10b981, #047857);
        color: white;
    }

    /* Статистика пользователя */
    .user-stats {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .stat-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
    }

    .stat-number {
        font-weight: 700;
        color: #3b82f6;
    }

    /* Dropdown действия */
    .actions-dropdown {
        position: relative;
        display: inline-block;
    }

    .actions-btn {
        background: white;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        padding: 8px 12px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s ease;
        color: #374151;
    }

    .actions-btn:hover {
        border-color: #3b82f6;
        background: #f8fafc;
        color: #3b82f6;
    }

    .dropdown-menu {
        position: absolute;
        top: calc(100% + 4px);
        right: 0;
        background: white;
        border: 1px solid #d1d5db;
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        min-width: 180px;
        z-index: 1000;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: all 0.2s ease;
    }

    .dropdown-menu.show {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .dropdown-item {
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
        padding: 12px 16px;
        background: none;
        border: none;
        text-align: left;
        font-size: 14px;
        color: #374151;
        cursor: pointer;
        transition: all 0.2s ease;
        font-weight: 500;
    }

    .dropdown-item:first-child {
        border-radius: 12px 12px 0 0;
    }

    .dropdown-item:last-child {
        border-radius: 0 0 12px 12px;
    }

    .dropdown-item:hover {
        background: #f8fafc;
        color: #3b82f6;
    }

    .dropdown-item.danger {
        color: #ef4444;
    }

    .dropdown-item.danger:hover {
        background: #fef2f2;
        color: #dc2626;
    }

    /* Модальные окна */
    .modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.6);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
        backdrop-filter: blur(4px);
    }

    .modal.show {
        opacity: 1;
        visibility: visible;
    }

    .modal-content {
        background: white;
        border-radius: 16px;
        padding: 32px;
        max-width: 500px;
        width: 90%;
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
        transform: scale(0.9);
        transition: all 0.3s ease;
    }

    .modal.show .modal-content {
        transform: scale(1);
    }

    .modal-header {
        margin-bottom: 24px;
        text-align: center;
    }

    .modal-title {
        font-size: 22px;
        font-weight: 700;
        color: #0f172a;
        margin: 0 0 8px 0;
        letter-spacing: -0.025em;
    }

    .modal-subtitle {
        font-size: 14px;
        color: #64748b;
    }

    .modal-actions {
        display: flex;
        gap: 12px;
        justify-content: center;
        margin-top: 32px;
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

    .btn-secondary {
        background: white;
        color: #64748b;
        border: 1px solid #d1d5db;
    }

    .btn-secondary:hover {
        border-color: #9ca3af;
        color: #374151;
    }

    .btn-danger {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
        box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3);
    }

    .btn-danger:hover {
        background: linear-gradient(135deg, #dc2626, #b91c1c);
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(239, 68, 68, 0.4);
    }

    /* Empty state */
    .empty-state {
        text-align: center;
        padding: 60px 40px;
        color: #64748b;
    }

    .empty-icon {
        width: 64px;
        height: 64px;
        margin: 0 auto 20px;
        opacity: 0.4;
        color: #94a3b8;
    }

    .empty-title {
        font-size: 18px;
        font-weight: 600;
        color: #475569;
        margin-bottom: 8px;
    }

    .empty-desc {
        font-size: 14px;
        color: #64748b;
    }

    /* Анимации */
    .stat-card:nth-child(1) { transition-delay: 0.1s; }
    .stat-card:nth-child(2) { transition-delay: 0.2s; }
    .stat-card:nth-child(3) { transition-delay: 0.3s; }
    .stat-card:nth-child(4) { transition-delay: 0.4s; }

    /* Responsive */
    @media (max-width: 1024px) {
        .filters-grid {
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        
        .users-table th,
        .users-table td {
            padding: 12px 16px;
        }
    }

    @media (max-width: 768px) {
        .page-container {
            padding: 20px;
        }
        
        .filters-grid {
            grid-template-columns: 1fr;
        }
        
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        .users-table {
            min-width: 800px;
        }
        
        .users-table th,
        .users-table td {
            padding: 8px 12px;
            font-size: 12px;
        }
        
        .user-info {
            min-width: 150px;
        }
        
        .user-avatar {
            width: 36px;
            height: 36px;
            font-size: 14px;
        }
    }

    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .modal-content {
            padding: 24px;
            margin: 20px;
        }
    }
</style>

<div class="page-container">
    <!-- Сообщение об успехе -->
    <?php if ($message): ?>
        <div class="alert alert-success">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                <path d="M9 12L11 14L15 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
            </svg>
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

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
            <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
            <div class="stat-label">Всего пользователей</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon premium">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <div class="stat-value"><?php echo number_format($stats['premium']); ?></div>
            <div class="stat-label">Premium подписки</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon blocked">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                    <line x1="4.93" y1="4.93" x2="19.07" y2="19.07" stroke="currentColor" stroke-width="2"/>
                </svg>
            </div>
            <div class="stat-value"><?php echo number_format($stats['blocked']); ?></div>
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
            <div class="stat-value"><?php echo number_format($stats['today']); ?></div>
            <div class="stat-label">Новых сегодня</div>
        </div>
    </div>

    <!-- Фильтры -->
    <div class="filters-card">
        <form method="GET" class="filters-grid">
            <div class="form-group">
                <label class="form-label">Поиск по имени или email</label>
                <input type="text" name="search" class="form-input" placeholder="Введите имя пользователя или email" value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Роль пользователя</label>
                <select name="role" class="form-select">
                    <option value="">Все роли</option>
                    <option value="user" <?php echo $roleFilter === 'user' ? 'selected' : ''; ?>>Базовый</option>
                    <option value="premium" <?php echo $roleFilter === 'premium' ? 'selected' : ''; ?>>Premium</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Статус аккаунта</label>
                <select name="status" class="form-select">
                    <option value="">Все статусы</option>
                    <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Активные</option>
                    <option value="blocked" <?php echo $statusFilter === 'blocked' ? 'selected' : ''; ?>>Заблокированные</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Сортировка</label>
                <select name="sort" class="form-select">
                    <option value="created_at" <?php echo $sortBy === 'created_at' ? 'selected' : ''; ?>>По дате регистрации</option>
                    <option value="last_login" <?php echo $sortBy === 'last_login' ? 'selected' : ''; ?>>По последнему входу</option>
                    <option value="username" <?php echo $sortBy === 'username' ? 'selected' : ''; ?>>По имени</option>
                    <option value="email" <?php echo $sortBy === 'email' ? 'selected' : ''; ?>>По email</option>
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
            <h3 class="table-title">Пользователи системы (<?php echo count($users); ?>)</h3>
            <div style="display: flex; gap: 12px;">
                <a href="?sort=<?php echo $sortBy; ?>&order=<?php echo $sortOrder === 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $roleFilter; ?>&status=<?php echo $statusFilter; ?>" class="btn btn-secondary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                        <path d="M3 6H21" stroke="currentColor" stroke-width="2"/>
                        <path d="M7 12H17" stroke="currentColor" stroke-width="2"/>
                        <path d="M10 18H14" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    Сортировка: <?php echo $sortOrder === 'ASC' ? '↑' : '↓'; ?>
                </a>
            </div>
        </div>
        
        <div class="table-container">
            <?php if (empty($users)): ?>
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
            <?php else: ?>
                <table class="users-table">
                    <thead>
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
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <span class="user-id">#<?php echo $user['id']; ?></span>
                            </td>
                            <td>
                                <div class="user-info">
                                    <div class="user-avatar">
                                        <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                    </div>
                                    <div class="user-details">
                                        <h4><?php echo htmlspecialchars($user['username']); ?></h4>
                                        <p><?php echo htmlspecialchars($user['email']); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?php echo $user['role']; ?>">
                                    <?php echo $user['role'] === 'premium' ? 'Premium' : 'Базовый'; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?php echo $user['is_blocked'] ? 'blocked' : 'active'; ?>">
                                    <?php echo $user['is_blocked'] ? 'Заблокирован' : 'Активен'; ?>
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
                                        <span class="stat-number"><?php echo $user['campaigns_count']; ?></span> кампаний
                                    </div>
                                    <div class="stat-item">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                                            <path d="M18 20V10" stroke="currentColor" stroke-width="2"/>
                                            <path d="M12 20V4" stroke="currentColor" stroke-width="2"/>
                                            <path d="M6 20V14" stroke="currentColor" stroke-width="2"/>
                                        </svg>
                                        <span class="stat-number"><?php echo $user['placements_count']; ?></span> размещений
                                    </div>
                                    <div class="stat-item">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                                            <line x1="12" y1="1" x2="12" y2="23" stroke="currentColor" stroke-width="2"/>
                                            <path d="M17 5H9.5C8.57174 5 7.6815 5.36875 7.02513 6.02513C6.36875 6.6815 6 7.57174 6 8.5C6 9.42826 6.36875 10.3185 7.02513 10.9749C7.6815 11.6313 8.57174 12 9.5 12H14.5C15.4283 12 16.3185 12.3687 16.9749 13.0251C17.6313 13.6815 18 14.5717 18 15.5C18 16.4283 17.6313 17.3185 16.9749 17.9749C16.3185 18.6313 15.4283 19 14.5 19H6" stroke="currentColor" stroke-width="2"/>
                                        </svg>
                                        <span class="stat-number">₽<?php echo number_format($user['total_spent'], 0, ',', ' '); ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="font-size: 13px; color: #64748b;">
                                    <?php echo date('d.m.Y', strtotime($user['created_at'])); ?>
                                    <div style="font-size: 11px; color: #94a3b8;">
                                        <?php echo date('H:i', strtotime($user['created_at'])); ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if ($user['last_login']): ?>
                                    <div style="font-size: 13px; color: #64748b;">
                                        <?php echo date('d.m.Y', strtotime($user['last_login'])); ?>
                                        <div style="font-size: 11px; color: #94a3b8;">
                                            <?php echo date('H:i', strtotime($user['last_login'])); ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #94a3b8; font-size: 13px;">Никогда</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="actions-dropdown">
                                    <button class="actions-btn" onclick="toggleDropdown(<?php echo $user['id']; ?>)">
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
                                    <div class="dropdown-menu" id="dropdown-<?php echo $user['id']; ?>">
                                        <button class="dropdown-item" onclick="changeRole(<?php echo $user['id']; ?>, '<?php echo $user['role']; ?>', '<?php echo htmlspecialchars($user['username']); ?>')">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                                <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" stroke="currentColor" stroke-width="2"/>
                                            </svg>
                                            Изменить роль
                                        </button>
                                        <?php if ($user['is_blocked']): ?>
                                            <button class="dropdown-item" onclick="unblockUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                                    <path d="M18 6L6 18" stroke="currentColor" stroke-width="2"/>
                                                    <path d="M6 6L18 18" stroke="currentColor" stroke-width="2"/>
                                                </svg>
                                                Разблокировать
                                            </button>
                                        <?php else: ?>
                                            <button class="dropdown-item danger" onclick="blockUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                                                    <line x1="4.93" y1="4.93" x2="19.07" y2="19.07" stroke="currentColor" stroke-width="2"/>
                                                </svg>
                                                Заблокировать
                                            </button>
                                        <?php endif; ?>
                                        <button class="dropdown-item" onclick="loginAsUser(<?php echo $user['id']; ?>)">
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
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
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

<script>
    // Dropdown меню
    function toggleDropdown(userId) {
        // Закрываем все открытые dropdown
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            if (menu.id !== `dropdown-${userId}`) {
                menu.classList.remove('show');
            }
        });
        
        // Переключаем текущий
        const dropdown = document.getElementById(`dropdown-${userId}`);
        dropdown.classList.toggle('show');
    }

    // Изменение роли
    function changeRole(userId, currentRole, username) {
        document.getElementById('roleUserId').value = userId;
        document.getElementById('oldRole').value = currentRole;
        document.getElementById('roleUserName').textContent = username;
        
        // Устанавливаем противоположную роль по умолчанию
        const newRoleSelect = document.getElementById('newRole');
        newRoleSelect.value = currentRole === 'premium' ? 'user' : 'premium';
        
        document.getElementById('roleModal').classList.add('show');
        closeAllDropdowns();
    }

    // Блокировка пользователя
    function blockUser(userId, username) {
        document.getElementById('blockUserId').value = userId;
        document.getElementById('blockUserName').textContent = username;
        document.getElementById('blockModal').classList.add('show');
        closeAllDropdowns();
    }

    // Разблокировка пользователя
    function unblockUser(userId, username) {
        document.getElementById('unblockUserId').value = userId;
        document.getElementById('unblockUserName').textContent = username;
        document.getElementById('unblockModal').classList.add('show');
        closeAllDropdowns();
    }

    // Вход как пользователь (функция для будущей реализации)
    function loginAsUser(userId) {
        if (confirm('Войти от имени этого пользователя? Вы будете перенаправлены в его кабинет.')) {
            // Здесь можно реализовать функционал входа под пользователем
            window.open(`../dashboard/?impersonate=${userId}`, '_blank');
        }
        closeAllDropdowns();
    }

    // Закрытие модальных окон
    function closeModal(modalId) {
        document.getElementById(modalId).classList.remove('show');
    }

    // Закрытие всех dropdown меню
    function closeAllDropdowns() {
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            menu.classList.remove('show');
        });
    }

    // События
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
        document.querySelectorAll('.stat-card, .filters-card, .users-table-card').forEach(el => {
            observer.observe(el);
        });

        // Закрытие dropdown при клике вне
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.actions-dropdown')) {
                closeAllDropdowns();
            }
        });

        // Закрытие модальных окон при клике вне или по Escape
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.classList.remove('show');
                }
            });
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal.show').forEach(modal => {
                    modal.classList.remove('show');
                });
                closeAllDropdowns();
            }
        });

        // Автофокус на первое поле в модальных окнах
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('transitionend', function() {
                if (modal.classList.contains('show')) {
                    const firstInput = modal.querySelector('input, select, textarea');
                    if (firstInput) {
                        firstInput.focus();
                    }
                }
            });
        });
    });

    // Автосохранение фильтров в localStorage
    function saveFilters() {
        const filters = {
            search: document.querySelector('input[name="search"]').value,
            role: document.querySelector('select[name="role"]').value,
            status: document.querySelector('select[name="status"]').value,
            sort: document.querySelector('select[name="sort"]').value
        };
        localStorage.setItem('userFilters', JSON.stringify(filters));
    }

    // Восстановление фильтров
    function restoreFilters() {
        const saved = localStorage.getItem('userFilters');
        if (saved) {
            const filters = JSON.parse(saved);
            Object.keys(filters).forEach(key => {
                const element = document.querySelector(`[name="${key}"]`);
                if (element && filters[key]) {
                    element.value = filters[key];
                }
            });
        }
    }

    // Восстанавливаем фильтры при загрузке
    document.addEventListener('DOMContentLoaded', restoreFilters);
</script>

<?php require_once 'footer.php'; ?>