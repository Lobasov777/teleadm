<?php
$pageTitle = 'Логи системы';
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
$message = $_GET['message'] ?? '';
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
        font-size: 20px;
    }

    .stat-icon.total { background: linear-gradient(135deg, #3b82f6, #1e40af); }
    .stat-icon.today { background: linear-gradient(135deg, #10b981, #047857); }
    .stat-icon.week { background: linear-gradient(135deg, #f59e0b, #d97706); }
    .stat-icon.yesterday { background: linear-gradient(135deg, #8b5cf6, #6d28d9); }

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

    /* Графики активности */
    .activity-section {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
        margin-bottom: 32px;
    }

    .activity-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        opacity: 0;
        transform: translateY(20px);
        transition: all 0.4s ease;
    }

    .activity-card.animate-in {
        opacity: 1;
        transform: translateY(0);
    }

    .activity-title {
        font-size: 18px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 20px;
        letter-spacing: -0.025em;
    }

    .action-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .action-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 16px;
        background: #f8fafc;
        border-radius: 10px;
        transition: all 0.2s ease;
    }

    .action-item:hover {
        background: #f1f5f9;
        transform: translateX(4px);
    }

    .action-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .action-icon {
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        font-size: 18px;
    }

    .action-icon.blue { background: rgba(59, 130, 246, 0.1); }
    .action-icon.green { background: rgba(16, 185, 129, 0.1); }
    .action-icon.red { background: rgba(239, 68, 68, 0.1); }
    .action-icon.yellow { background: rgba(245, 158, 11, 0.1); }
    .action-icon.purple { background: rgba(139, 92, 246, 0.1); }
    .action-icon.gray { background: rgba(107, 114, 128, 0.1); }
    .action-icon.orange { background: rgba(251, 146, 60, 0.1); }
    .action-icon.indigo { background: rgba(99, 102, 241, 0.1); }

    .action-name {
        font-size: 14px;
        font-weight: 600;
        color: #374151;
    }

    .action-count {
        font-size: 16px;
        font-weight: 700;
        color: #0f172a;
    }

    .admin-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .admin-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 16px;
        background: #f8fafc;
        border-radius: 10px;
        transition: all 0.2s ease;
    }

    .admin-item:hover {
        background: #f1f5f9;
        transform: translateX(4px);
    }

    .admin-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .admin-avatar {
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
    }

    .admin-name {
        font-size: 14px;
        font-weight: 600;
        color: #374151;
    }

    .admin-actions {
        font-size: 16px;
        font-weight: 700;
        color: #0f172a;
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

    /* Таблица логов */
    .logs-table-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        opacity: 0;
        transform: translateY(20px);
        transition: all 0.4s ease;
    }

    .logs-table-card.animate-in {
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

    .table-subtitle {
        font-size: 14px;
        color: #64748b;
        margin-top: 4px;
    }

    .table-container {
        overflow-x: auto;
    }

    .logs-table {
        width: 100%;
        border-collapse: collapse;
    }

    .logs-table th {
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
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .logs-table th:hover {
        background: #f1f5f9;
        color: #3b82f6;
    }

    .logs-table th.sortable::after {
        content: ' ↕';
        opacity: 0.5;
        font-size: 10px;
    }

    .logs-table th.sorted-asc::after {
        content: ' ↑';
        opacity: 1;
        color: #3b82f6;
    }

    .logs-table th.sorted-desc::after {
        content: ' ↓';
        opacity: 1;
        color: #3b82f6;
    }

    .logs-table td {
        padding: 20px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 14px;
        vertical-align: middle;
    }

    .logs-table tr:hover {
        background: #f8fafc;
    }

    .logs-table tr:last-child td {
        border-bottom: none;
    }

    .time-info {
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 120px;
    }

    .time-date {
        font-weight: 600;
        color: #0f172a;
        font-size: 14px;
    }

    .time-clock {
        font-size: 12px;
        color: #64748b;
        font-family: 'Monaco', 'Consolas', monospace;
    }

    .admin-info {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 180px;
    }

    .admin-avatar {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #3b82f6, #1e40af);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 16px;
        flex-shrink: 0;
    }

    .admin-details h4 {
        font-weight: 600;
        margin: 0 0 4px 0;
        color: #0f172a;
        font-size: 14px;
    }

    .admin-details p {
        margin: 0;
        font-size: 12px;
        color: #64748b;
        word-break: break-word;
    }

    /* Бейджи действий */
    .action-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        white-space: nowrap;
    }

    .action-badge.blue {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        color: white;
    }

    .action-badge.green {
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
    }

    .action-badge.red {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
    }

    .action-badge.yellow {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: white;
    }

    .action-badge.purple {
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        color: white;
    }

    .action-badge.gray {
        background: #6b7280;
        color: white;
    }

    .action-badge.orange {
        background: linear-gradient(135deg, #fb923c, #ea580c);
        color: white;
    }

    .action-badge.indigo {
        background: linear-gradient(135deg, #6366f1, #4f46e5);
        color: white;
    }

    /* Детали действия */
    .action-details {
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 200px;
    }

    .action-target {
        font-size: 12px;
        color: #64748b;
        font-family: 'Monaco', 'Consolas', monospace;
    }

    .action-changes {
        display: flex;
        gap: 8px;
        align-items: center;
        font-size: 12px;
    }

    .old-value {
        background: rgba(239, 68, 68, 0.1);
        color: #dc2626;
        padding: 2px 6px;
        border-radius: 4px;
        font-family: 'Monaco', 'Consolas', monospace;
    }

    .new-value {
        background: rgba(16, 185, 129, 0.1);
        color: #059669;
        padding: 2px 6px;
        border-radius: 4px;
        font-family: 'Monaco', 'Consolas', monospace;
    }

    .ip-address {
        font-family: 'Monaco', 'Consolas', monospace;
        font-size: 12px;
        color: #64748b;
        background: #f1f5f9;
        padding: 4px 8px;
        border-radius: 6px;
        font-weight: 600;
    }

    /* Пагинация */
    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
        margin-top: 32px;
        padding: 24px;
    }

    .pagination a, .pagination span {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.2s ease;
    }

    .pagination a {
        background: white;
        border: 1px solid #d1d5db;
        color: #374151;
    }

    .pagination a:hover {
        border-color: #3b82f6;
        background: #f8fafc;
        color: #3b82f6;
    }

    .pagination .current {
        background: linear-gradient(135deg, #3b82f6, #1e40af);
        color: white;
        border: 1px solid #3b82f6;
    }

    .pagination .disabled {
        background: #f9fafb;
        color: #9ca3af;
        border: 1px solid #e5e7eb;
        cursor: not-allowed;
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
        font-size: 64px;
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

    /* Кнопки */
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

    /* Анимации */
    .stat-card:nth-child(1) { transition-delay: 0.1s; }
    .stat-card:nth-child(2) { transition-delay: 0.2s; }
    .stat-card:nth-child(3) { transition-delay: 0.3s; }
    .stat-card:nth-child(4) { transition-delay: 0.4s; }

    .activity-card:nth-child(1) { transition-delay: 0.5s; }
    .activity-card:nth-child(2) { transition-delay: 0.6s; }

    .filters-card { transition-delay: 0.7s; }
    .logs-table-card { transition-delay: 0.8s; }

    /* Responsive */
    @media (max-width: 1024px) {
        .filters-grid {
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        
        .activity-section {
            grid-template-columns: 1fr;
        }
        
        .logs-table th,
        .logs-table td {
            padding: 12px 16px;
        }
    }

    @media (max-width: 768px) {
        .page-container {
            padding: 16px;
        }
        
        .stats-grid {
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        
        .filters-grid {
            grid-template-columns: 1fr;
        }
        
        .table-header {
            padding: 16px 20px;
        }
        
        .logs-table th,
        .logs-table td {
            padding: 8px 12px;
            font-size: 12px;
        }
        
        .admin-info {
            min-width: auto;
        }
        
        .admin-details h4 {
            font-size: 12px;
        }
        
        .admin-details p {
            font-size: 11px;
        }
    }
</style>

<div class="page-container">
    <?php if ($message): ?>
        <div class="alert alert-success">
            <span>✅</span>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Статистика -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon total">📊</div>
            <div class="stat-value"><?= number_format($timeStats['total']) ?></div>
            <div class="stat-label">Всего записей</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon today">📈</div>
            <div class="stat-value"><?= number_format($timeStats['today']) ?></div>
            <div class="stat-label">Сегодня</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon yesterday">📅</div>
            <div class="stat-value"><?= number_format($timeStats['yesterday']) ?></div>
            <div class="stat-label">Вчера</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon week">📆</div>
            <div class="stat-value"><?= number_format($timeStats['week']) ?></div>
            <div class="stat-label">За неделю</div>
        </div>
    </div>

    <!-- Графики активности -->
    <div class="activity-section">
        <div class="activity-card">
            <h3 class="activity-title">Популярные действия</h3>
            <div class="action-list">
                <?php if (empty($actionStats)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📊</div>
                        <div class="empty-title">Нет данных</div>
                        <div class="empty-desc">За выбранный период действий не найдено</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($actionStats as $stat): ?>
                        <?php 
                        $actionInfo = $actionLabels[$stat['action']] ?? ['icon' => '❓', 'label' => $stat['action'], 'color' => 'gray'];
                        ?>
                        <div class="action-item">
                            <div class="action-info">
                                <div class="action-icon <?= $actionInfo['color'] ?>">
                                    <?= $actionInfo['icon'] ?>
                                </div>
                                <div class="action-name"><?= $actionInfo['label'] ?></div>
                            </div>
                            <div class="action-count"><?= number_format($stat['count']) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="activity-card">
            <h3 class="activity-title">Активные администраторы</h3>
            <div class="admin-list">
                <?php if (empty($adminStats)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">👥</div>
                        <div class="empty-title">Нет данных</div>
                        <div class="empty-desc">За выбранный период активности не найдено</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($adminStats as $stat): ?>
                        <div class="admin-item">
                            <div class="admin-info">
                                <div class="admin-avatar">
                                    <?= strtoupper(substr($stat['username'], 0, 2)) ?>
                                </div>
                                <div class="admin-name"><?= htmlspecialchars($stat['username']) ?></div>
                            </div>
                            <div class="admin-actions"><?= number_format($stat['actions_count']) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Фильтры -->
    <div class="filters-card">
        <form method="GET" action="">
            <div class="filters-grid">
                <div class="form-group">
                    <label class="form-label">Поиск</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Поиск по значениям, ID или администратору..." class="form-input">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Действие</label>
                    <select name="action" class="form-select">
                        <option value="">Все действия</option>
                        <?php foreach ($actionLabels as $action => $info): ?>
                            <option value="<?= $action ?>" <?= $actionFilter === $action ? 'selected' : '' ?>>
                                <?= $info['icon'] ?> <?= $info['label'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Администратор</label>
                    <select name="admin" class="form-select">
                        <option value="">Все администраторы</option>
                        <?php foreach ($admins as $admin): ?>
                            <option value="<?= $admin['id'] ?>" <?= $adminFilter == $admin['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($admin['username']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Период</label>
                    <select name="period" class="form-select">
                        <option value="1" <?= $period === '1' ? 'selected' : '' ?>>Сегодня</option>
                        <option value="7" <?= $period === '7' ? 'selected' : '' ?>>7 дней</option>
                        <option value="30" <?= $period === '30' ? 'selected' : '' ?>>30 дней</option>
                        <option value="90" <?= $period === '90' ? 'selected' : '' ?>>90 дней</option>
                        <option value="all" <?= $period === 'all' ? 'selected' : '' ?>>Все время</option>
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
                <div class="table-subtitle">Найдено записей: <?= number_format($totalLogs) ?></div>
            </div>
        </div>
        
        <div class="table-container">
            <?php if (empty($logs)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📋</div>
                    <div class="empty-title">Логи не найдены</div>
                    <div class="empty-desc">Попробуйте изменить параметры фильтрации</div>
                </div>
            <?php else: ?>
                <table class="logs-table">
                    <thead>
                        <tr>
                            <th class="sortable <?= $sortBy === 'created_at' ? 'sorted-' . strtolower($sortOrder) : '' ?>">
                                <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'created_at', 'order' => $sortBy === 'created_at' && $sortOrder === 'DESC' ? 'ASC' : 'DESC'])) ?>" style="color: inherit; text-decoration: none;">
                                    Время
                                </a>
                            </th>
                            <th class="sortable <?= $sortBy === 'admin_id' ? 'sorted-' . strtolower($sortOrder) : '' ?>">
                                <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'admin_id', 'order' => $sortBy === 'admin_id' && $sortOrder === 'DESC' ? 'ASC' : 'DESC'])) ?>" style="color: inherit; text-decoration: none;">
                                    Администратор
                                </a>
                            </th>
                            <th class="sortable <?= $sortBy === 'action' ? 'sorted-' . strtolower($sortOrder) : '' ?>">
                                <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'action', 'order' => $sortBy === 'action' && $sortOrder === 'DESC' ? 'ASC' : 'DESC'])) ?>" style="color: inherit; text-decoration: none;">
                                    Действие
                                </a>
                            </th>
                            <th>Детали</th>
                            <th>IP адрес</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <?php 
                            $actionInfo = $actionLabels[$log['action']] ?? ['icon' => '❓', 'label' => $log['action'], 'color' => 'gray'];
                            $logDate = new DateTime($log['created_at']);
                            ?>
                            <tr>
                                <td>
                                    <div class="time-info">
                                        <div class="time-date"><?= $logDate->format('d.m.Y') ?></div>
                                        <div class="time-clock"><?= $logDate->format('H:i:s') ?></div>
                                    </div>
                                </td>
                                
                                <td>
                                    <div class="admin-info">
                                        <div class="admin-avatar">
                                            <?= strtoupper(substr($log['admin_name'], 0, 2)) ?>
                                        </div>
                                        <div class="admin-details">
                                            <h4><?= htmlspecialchars($log['admin_name']) ?></h4>
                                            <p><?= htmlspecialchars($log['admin_email']) ?></p>
                                        </div>
                                    </div>
                                </td>
                                
                                <td>
                                    <div class="action-badge <?= $actionInfo['color'] ?>">
                                        <span><?= $actionInfo['icon'] ?></span>
                                        <?= $actionInfo['label'] ?>
                                    </div>
                                </td>
                                
                                <td>
                                    <div class="action-details">
                                        <?php if ($log['target_type'] && $log['target_id']): ?>
                                            <div class="action-target">
                                                <?= htmlspecialchars($log['target_type']) ?> #<?= htmlspecialchars($log['target_id']) ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($log['old_value'] && $log['new_value']): ?>
                                            <div class="action-changes">
                                                <span class="old-value"><?= htmlspecialchars($log['old_value']) ?></span>
                                                →
                                                <span class="new-value"><?= htmlspecialchars($log['new_value']) ?></span>
                                            </div>
                                        <?php elseif ($log['new_value']): ?>
                                            <div class="action-changes">
                                                <span class="new-value"><?= htmlspecialchars($log['new_value']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                
                                <td>
                                    <div class="ip-address"><?= htmlspecialchars($log['ip_address']) ?></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- Пагинация -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">‹</a>
                <?php else: ?>
                    <span class="disabled">‹</span>
                <?php endif; ?>
                
                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                
                if ($startPage > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">1</a>
                    <?php if ($startPage > 2): ?>
                        <span>...</span>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?>
                        <span>...</span>
                    <?php endif; ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $totalPages])) ?>"><?= $totalPages ?></a>
                <?php endif; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">›</a>
                <?php else: ?>
                    <span class="disabled">›</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Анимация появления элементов
    const animateElements = () => {
        const elements = document.querySelectorAll('.stat-card, .activity-card, .filters-card, .logs-table-card');
        elements.forEach(element => {
            element.classList.add('animate-in');
        });
    };
    
    // Запускаем анимацию с небольшой задержкой
    setTimeout(animateElements, 100);
    
    // Автоматическая отправка формы при изменении фильтров
    const filterSelects = document.querySelectorAll('.form-select');
    filterSelects.forEach(select => {
        select.addEventListener('change', function() {
            this.form.submit();
        });
    });
    
    // Подсветка строк таблицы при наведении
    const tableRows = document.querySelectorAll('.logs-table tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.01)';
            this.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.1)';
        });
        
        row.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
            this.style.boxShadow = 'none';
        });
    });
});
</script>

<?php require_once 'footer.php'; ?>

