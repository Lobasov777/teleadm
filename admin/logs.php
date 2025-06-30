<?php
$pageTitle = 'Логи системы';
require_once 'header.php';

// Фильтры
$actionFilter = $_GET['action'] ?? '';
$adminFilter = $_GET['admin'] ?? '';
$period = $_GET['period'] ?? 7;
$search = $_GET['search'] ?? '';

// Условие для периода
$dateCondition = "";
if ($period) {
    $dateCondition = "AND al.created_at >= DATE_SUB(NOW(), INTERVAL $period DAY)";
}

// Получаем логи
$query = "
    SELECT al.*, u.username as admin_name, u.email as admin_email
    FROM admin_logs al
    JOIN users u ON al.admin_id = u.id
    WHERE 1=1
";

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
    $query .= " AND (al.old_value LIKE ? OR al.new_value LIKE ? OR al.target_id LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$query .= " $dateCondition ORDER BY al.created_at DESC LIMIT 200";

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
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL $period DAY)
    GROUP BY action
    ORDER BY count DESC
";
$stmt = db()->query($statsQuery);
$actionStats = $stmt->fetchAll();

// Статистика по дням
$dailyStatsQuery = "
    SELECT DATE(created_at) as date, COUNT(*) as count
    FROM admin_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date
";
$stmt = db()->query($dailyStatsQuery);
$dailyStats = $stmt->fetchAll();

// Подготовка данных для графика
$chartLabels = [];
$chartData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chartLabels[] = date('d.m', strtotime($date));
    $chartData[] = 0;
}

foreach ($dailyStats as $day) {
    $index = array_search(date('d.m', strtotime($day['date'])), $chartLabels);
    if ($index !== false) {
        $chartData[$index] = $day['count'];
    }
}

// Форматирование действий
$actionLabels = [
    'login' => ['icon' => '🔐', 'label' => 'Вход в систему', 'color' => 'info'],
    'logout' => ['icon' => '🚪', 'label' => 'Выход из системы', 'color' => 'secondary'],
    'change_role' => ['icon' => '🔄', 'label' => 'Изменение роли', 'color' => 'warning'],
    'block_user' => ['icon' => '🚫', 'label' => 'Блокировка пользователя', 'color' => 'error'],
    'unblock_user' => ['icon' => '✅', 'label' => 'Разблокировка пользователя', 'color' => 'success'],
    'delete_user' => ['icon' => '🗑️', 'label' => 'Удаление пользователя', 'color' => 'error'],
    'update_settings' => ['icon' => '⚙️', 'label' => 'Изменение настроек', 'color' => 'primary'],
    'manual_payment' => ['icon' => '💰', 'label' => 'Подтверждение платежа', 'color' => 'success'],
    'refund_payment' => ['icon' => '💸', 'label' => 'Возврат платежа', 'color' => 'warning'],
    'view_user' => ['icon' => '👁️', 'label' => 'Просмотр пользователя', 'color' => 'info'],
    'export_data' => ['icon' => '📊', 'label' => 'Экспорт данных', 'color' => 'info']
];

// Общая статистика
$totalLogs = count($logs);
$uniqueAdmins = count(array_unique(array_column($logs, 'admin_id')));
$todayLogs = count(array_filter($logs, function($log) {
    return date('Y-m-d', strtotime($log['created_at'])) === date('Y-m-d');
}));
?>

<style>
    .logs-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 24px;
    }

    .logs-stat-card {
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 20px;
        text-align: center;
        box-shadow: var(--shadow-sm);
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .logs-stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
    }

    .logs-stat-card.total::before {
        background: linear-gradient(135deg, var(--primary), var(--primary-light));
    }

    .logs-stat-card.admins::before {
        background: linear-gradient(135deg, var(--success), #059669);
    }

    .logs-stat-card.today::before {
        background: linear-gradient(135deg, var(--warning), #d97706);
    }

    .logs-stat-card.actions::before {
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    }

    .logs-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .logs-stat-value {
        font-size: 28px;
        font-weight: 800;
        margin-bottom: 8px;
    }

    .logs-stat-value.total {
        color: var(--primary);
    }

    .logs-stat-value.admins {
        color: var(--success);
    }

    .logs-stat-value.today {
        color: var(--warning);
    }

    .logs-stat-value.actions {
        color: #8b5cf6;
    }

    .logs-stat-label {
        font-size: 14px;
        color: var(--text-secondary);
        font-weight: 500;
    }

    .logs-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 24px;
        margin-bottom: 24px;
    }

    .chart-card {
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 24px;
        box-shadow: var(--shadow-sm);
    }

    .chart-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
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
        height: 250px;
        position: relative;
    }

    .actions-stats-card {
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
    }

    .actions-stats-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border);
        background: var(--bg-secondary);
    }

    .actions-stats-title {
        font-size: 18px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .actions-stats-list {
        padding: 16px 0;
        max-height: 300px;
        overflow-y: auto;
    }

    .action-stat-item {
        padding: 12px 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: background 0.2s;
    }

    .action-stat-item:hover {
        background: var(--bg-secondary);
    }

    .action-stat-info {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .action-stat-count {
        font-weight: 600;
        color: var(--primary);
        font-size: 16px;
    }

    .logs-table-card {
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
    }

    .logs-table-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border);
        background: var(--bg-secondary);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .logs-table-title {
        font-size: 18px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .logs-table {
        width: 100%;
        border-collapse: collapse;
    }

    .logs-table th {
        background: var(--bg-tertiary);
        padding: 16px;
        text-align: left;
        font-weight: 600;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-secondary);
        border-bottom: 1px solid var(--border);
    }

    .logs-table td {
        padding: 16px;
        border-bottom: 1px solid var(--border);
        font-size: 14px;
        vertical-align: top;
    }

    .logs-table tr:hover {
        background: var(--bg-secondary);
    }

    .log-time {
        font-size: 12px;
        color: var(--text-secondary);
        white-space: nowrap;
    }

    .log-admin {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .admin-avatar {
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

    .admin-info h5 {
        font-weight: 600;
        margin: 0 0 2px 0;
        font-size: 14px;
        color: var(--text-primary);
    }

    .admin-info p {
        margin: 0;
        font-size: 12px;
        color: var(--text-secondary);
    }

    .log-action {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        white-space: nowrap;
    }

    .log-action.info {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .log-action.success {
        background: #dcfce7;
        color: #166534;
    }

    .log-action.warning {
        background: #fef3c7;
        color: #92400e;
    }

    .log-action.error {
        background: #fee2e2;
        color: #991b1b;
    }

    .log-action.primary {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .log-action.secondary {
        background: #f3f4f6;
        color: #374151;
    }

    .log-details {
        font-size: 12px;
        color: var(--text-secondary);
        max-width: 200px;
        word-wrap: break-word;
    }

    .log-ip {
        font-family: 'Monaco', 'Menlo', monospace;
        font-size: 12px;
        background: var(--bg-secondary);
        padding: 4px 8px;
        border-radius: 4px;
        color: var(--text-secondary);
    }

    .export-logs-btn {
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

    .export-logs-btn:hover {
        background: #059669;
        transform: translateY(-1px);
    }

    @media (max-width: 768px) {
        .logs-grid {
            grid-template-columns: 1fr;
        }
        
        .logs-stats {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .logs-table {
            font-size: 12px;
        }
        
        .logs-table th,
        .logs-table td {
            padding: 8px;
        }
    }
</style>

<!-- Статистика логов -->
<div class="logs-stats fade-in">
    <div class="logs-stat-card total">
        <div class="logs-stat-value total"><?php echo number_format($totalLogs); ?></div>
        <div class="logs-stat-label">Всего записей</div>
    </div>
    <div class="logs-stat-card admins">
        <div class="logs-stat-value admins"><?php echo $uniqueAdmins; ?></div>
        <div class="logs-stat-label">Активных админов</div>
    </div>
    <div class="logs-stat-card today">
        <div class="logs-stat-value today"><?php echo $todayLogs; ?></div>
        <div class="logs-stat-label">Действий сегодня</div>
    </div>
    <div class="logs-stat-card actions">
        <div class="logs-stat-value actions"><?php echo count($actionStats); ?></div>
        <div class="logs-stat-label">Типов действий</div>
    </div>
</div>

<!-- Фильтры -->
<div class="filters-card fade-in">
    <form method="GET" class="filters-grid">
        <div class="form-group">
            <label class="form-label">Период</label>
            <select name="period" class="form-select">
                <option value="1" <?php echo $period == 1 ? 'selected' : ''; ?>>Сегодня</option>
                <option value="7" <?php echo $period == 7 ? 'selected' : ''; ?>>7 дней</option>
                <option value="30" <?php echo $period == 30 ? 'selected' : ''; ?>>30 дней</option>
                <option value="90" <?php echo $period == 90 ? 'selected' : ''; ?>>90 дней</option>
                <option value="" <?php echo $period == '' ? 'selected' : ''; ?>>Все время</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Действие</label>
            <select name="action" class="form-select">
                <option value="">Все действия</option>
                <?php foreach ($actionLabels as $action => $info): ?>
                    <option value="<?php echo $action; ?>" <?php echo $actionFilter === $action ? 'selected' : ''; ?>>
                        <?php echo $info['icon'] . ' ' . $info['label']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Администратор</label>
            <select name="admin" class="form-select">
                <option value="">Все админы</option>
                <?php foreach ($admins as $admin): ?>
                    <option value="<?php echo $admin['id']; ?>" <?php echo $adminFilter == $admin['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($admin['username']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Поиск</label>
            <input type="text" name="search" class="form-input" placeholder="Поиск в деталях..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <button type="submit" class="btn btn-primary">Применить</button>
    </form>
</div>

<!-- График и статистика действий -->
<div class="logs-grid fade-in">
    <div class="chart-card">
        <div class="chart-header">
            <h3 class="chart-title">
                📈 Активность за 7 дней
            </h3>
        </div>
        <div class="chart-container">
            <canvas id="logsChart"></canvas>
        </div>
    </div>

    <div class="actions-stats-card">
        <div class="actions-stats-header">
            <h3 class="actions-stats-title">
                🎯 Популярные действия
            </h3>
        </div>
        <div class="actions-stats-list">
            <?php foreach ($actionStats as $stat): ?>
            <div class="action-stat-item">
                <div class="action-stat-info">
                    <span><?php echo $actionLabels[$stat['action']]['icon'] ?? '📝'; ?></span>
                    <span><?php echo $actionLabels[$stat['action']]['label'] ?? $stat['action']; ?></span>
                </div>
                <div class="action-stat-count"><?php echo $stat['count']; ?></div>
            </div>
            <?php endforeach; ?>
            
            <?php if (empty($actionStats)): ?>
            <div class="action-stat-item">
                <div style="text-align: center; padding: 20px; color: var(--text-secondary);">
                    📊 Нет данных за период
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Таблица логов -->
<div class="logs-table-card fade-in">
    <div class="logs-table-header">
        <h3 class="logs-table-title">
            📝 История действий (<?php echo count($logs); ?>)
        </h3>
        <button class="export-logs-btn" onclick="exportLogs()">
            📊 Экспорт
        </button>
    </div>
    
    <div class="table-container">
        <table class="logs-table">
            <thead>
                <tr>
                    <th>Время</th>
                    <th>Администратор</th>
                    <th>Действие</th>
                    <th>Детали</th>
                    <th>IP адрес</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td>
                        <div class="log-time">
                            <?php echo date('d.m.Y', strtotime($log['created_at'])); ?><br>
                            <?php echo date('H:i:s', strtotime($log['created_at'])); ?>
                        </div>
                    </td>
                    <td>
                        <div class="log-admin">
                            <div class="admin-avatar">
                                <?php echo strtoupper(substr($log['admin_name'], 0, 1)); ?>
                            </div>
                            <div class="admin-info">
                                <h5><?php echo htmlspecialchars($log['admin_name']); ?></h5>
                                <p><?php echo htmlspecialchars($log['admin_email']); ?></p>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php 
                        $actionInfo = $actionLabels[$log['action']] ?? ['icon' => '📝', 'label' => $log['action'], 'color' => 'secondary'];
                        ?>
                        <div class="log-action <?php echo $actionInfo['color']; ?>">
                            <span><?php echo $actionInfo['icon']; ?></span>
                            <span><?php echo $actionInfo['label']; ?></span>
                        </div>
                    </td>
                    <td>
                        <div class="log-details">
                            <?php if ($log['target_type'] && $log['target_id']): ?>
                                <strong><?php echo ucfirst($log['target_type']); ?> #<?php echo $log['target_id']; ?></strong><br>
                            <?php endif; ?>
                            
                            <?php if ($log['old_value'] && $log['new_value']): ?>
                                <span style="color: var(--error);">Было:</span> <?php echo htmlspecialchars($log['old_value']); ?><br>
                                <span style="color: var(--success);">Стало:</span> <?php echo htmlspecialchars($log['new_value']); ?>
                            <?php elseif ($log['old_value']): ?>
                                <?php echo htmlspecialchars($log['old_value']); ?>
                            <?php elseif ($log['new_value']): ?>
                                <?php echo htmlspecialchars($log['new_value']); ?>
                            <?php else: ?>
                                <span style="color: var(--text-tertiary);">—</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <span class="log-ip"><?php echo htmlspecialchars($log['ip_address'] ?? '—'); ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                        📝 Логов не найдено
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // График активности
    const ctx = document.getElementById('logsChart').getContext('2d');
    const chartData = <?php echo json_encode($chartData); ?>;
    const chartLabels = <?php echo json_encode($chartLabels); ?>;
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'Действий',
                data: chartData,
                backgroundColor: 'rgba(37, 99, 235, 0.8)',
                borderColor: '#2563eb',
                borderWidth: 1,
                borderRadius: 4
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
                        color: '#64748b',
                        stepSize: 1
                    }
                }
            }
        }
    });

    function exportLogs() {
        const params = new URLSearchParams(window.location.search);
        params.set('export', '1');
        window.location.href = '?' + params.toString();
    }

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