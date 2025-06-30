<?php
$pageTitle = 'Управление пользователями';
require_once 'header.php';

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = $_POST['user_id'] ?? 0;
    
    if ($action === 'change_role' && $userId) {
        $newRole = $_POST['new_role'] ?? '';
        $stmt = db()->prepare("UPDATE users SET role = ? WHERE id = ? AND role != 'admin'");
        $stmt->execute([$newRole, $userId]);
        
        // Логируем действие
        $stmt = db()->prepare("
            INSERT INTO admin_logs (admin_id, action, target_type, target_id, old_value, new_value, ip_address) 
            VALUES (?, 'change_role', 'user', ?, ?, ?, ?)
        ");
        $stmt->execute([$currentUser['id'], $userId, $_POST['old_role'] ?? '', $newRole, $_SERVER['REMOTE_ADDR'] ?? '']);
        $message = 'Роль пользователя изменена';
    }
    
    if ($action === 'block' && $userId) {
        $reason = $_POST['reason'] ?? 'Нарушение правил';
        $stmt = db()->prepare("UPDATE users SET is_blocked = TRUE, blocked_at = NOW(), blocked_reason = ? WHERE id = ?");
        $stmt->execute([$reason, $userId]);
        $message = 'Пользователь заблокирован';
    }
    
    if ($action === 'unblock' && $userId) {
        $stmt = db()->prepare("UPDATE users SET is_blocked = FALSE, blocked_at = NULL, blocked_reason = NULL WHERE id = ?");
        $stmt->execute([$userId]);
        $message = 'Пользователь разблокирован';
    }
}

// Фильтры
$search = $_GET['search'] ?? '';
$roleFilter = $_GET['role'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$sortBy = $_GET['sort'] ?? 'created_at';
$sortOrder = $_GET['order'] ?? 'DESC';

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

if ($roleFilter) {
    $query .= " AND u.role = ?";
    $params[] = $roleFilter;
}

if ($statusFilter === 'blocked') {
    $query .= " AND u.is_blocked = TRUE";
} elseif ($statusFilter === 'active') {
    $query .= " AND u.is_blocked = FALSE";
}

$query .= " GROUP BY u.id ORDER BY $sortBy $sortOrder";

$stmt = db()->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Статистика
$stmt = db()->query("
    SELECT COUNT(*) as total,
           SUM(CASE WHEN role = 'premium' THEN 1 ELSE 0 END) as premium,
           SUM(CASE WHEN is_blocked = TRUE THEN 1 ELSE 0 END) as blocked
    FROM users WHERE role != 'admin'
");
$stats = $stmt->fetch();
?>

<style>
    .filters-card {
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 24px;
        margin-bottom: 24px;
        box-shadow: var(--shadow-sm);
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
        gap: 6px;
    }

    .form-label {
        font-size: 14px;
        font-weight: 600;
        color: var(--text-primary);
    }

    .form-input, .form-select {
        padding: 10px 12px;
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
        font-size: 14px;
        background: var(--bg-primary);
        transition: border-color 0.2s;
    }

    .form-input:focus, .form-select:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 24px;
    }

    .stat-card {
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 20px;
        text-align: center;
        box-shadow: var(--shadow-sm);
        transition: transform 0.2s;
    }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .stat-value {
        font-size: 32px;
        font-weight: 800;
        color: var(--primary);
        margin-bottom: 8px;
    }

    .stat-label {
        font-size: 14px;
        color: var(--text-secondary);
        font-weight: 500;
    }

    .users-table-card {
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
    }

    .table-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border);
        background: var(--bg-secondary);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .table-title {
        font-size: 18px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }

    .users-table {
        width: 100%;
        border-collapse: collapse;
    }

    .users-table th {
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

    .users-table td {
        padding: 16px;
        border-bottom: 1px solid var(--border);
        font-size: 14px;
        vertical-align: middle;
    }

    .users-table tr:hover {
        background: var(--bg-secondary);
    }

    .user-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, var(--primary), var(--primary-light));
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        font-size: 16px;
    }

    .user-details h4 {
        font-weight: 600;
        margin: 0 0 4px 0;
        color: var(--text-primary);
    }

    .user-details p {
        margin: 0;
        font-size: 12px;
        color: var(--text-secondary);
    }

    .badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .badge.premium {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .badge.user {
        background: #f3f4f6;
        color: #374151;
    }

    .badge.blocked {
        background: #fee2e2;
        color: #991b1b;
    }

    .badge.active {
        background: #dcfce7;
        color: #166534;
    }

    .actions-dropdown {
        position: relative;
        display: inline-block;
    }

    .actions-btn {
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
        padding: 8px 12px;
        cursor: pointer;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s;
    }

    .actions-btn:hover {
        border-color: var(--primary);
        background: var(--bg-tertiary);
    }

    .dropdown-menu {
        position: absolute;
        top: 100%;
        right: 0;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
        box-shadow: var(--shadow-lg);
        min-width: 160px;
        z-index: 1000;
        display: none;
    }

    .dropdown-menu.show {
        display: block;
    }

    .dropdown-item {
        display: block;
        width: 100%;
        padding: 10px 16px;
        background: none;
        border: none;
        text-align: left;
        font-size: 14px;
        color: var(--text-primary);
        cursor: pointer;
        transition: background 0.2s;
    }

    .dropdown-item:hover {
        background: var(--bg-secondary);
    }

    .dropdown-item.danger {
        color: var(--error);
    }

    .modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 2000;
    }

    .modal.show {
        display: flex;
    }

    .modal-content {
        background: var(--bg-primary);
        border-radius: var(--radius-lg);
        padding: 24px;
        max-width: 500px;
        width: 90%;
        box-shadow: var(--shadow-xl);
    }

    .modal-header {
        margin-bottom: 20px;
    }

    .modal-title {
        font-size: 20px;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
    }

    .modal-actions {
        display: flex;
        gap: 12px;
        justify-content: flex-end;
        margin-top: 24px;
    }

    @media (max-width: 768px) {
        .filters-grid {
            grid-template-columns: 1fr;
        }
        
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .users-table {
            font-size: 12px;
        }
        
        .users-table th,
        .users-table td {
            padding: 8px;
        }
    }
</style>

<!-- Статистика -->
<div class="stats-grid fade-in">
    <div class="stat-card">
        <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
        <div class="stat-label">Всего пользователей</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo number_format($stats['premium']); ?></div>
        <div class="stat-label">Premium пользователей</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo number_format($stats['blocked']); ?></div>
        <div class="stat-label">Заблокировано</div>
    </div>
</div>

<!-- Фильтры -->
<div class="filters-card fade-in">
    <form method="GET" class="filters-grid">
        <div class="form-group">
            <label class="form-label">Поиск</label>
            <input type="text" name="search" class="form-input" placeholder="Имя пользователя или email" value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Роль</label>
            <select name="role" class="form-select">
                <option value="">Все роли</option>
                <option value="user" <?php echo $roleFilter === 'user' ? 'selected' : ''; ?>>Базовый</option>
                <option value="premium" <?php echo $roleFilter === 'premium' ? 'selected' : ''; ?>>Premium</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Статус</label>
            <select name="status" class="form-select">
                <option value="">Все</option>
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
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Применить</button>
    </form>
</div>

<!-- Таблица пользователей -->
<div class="users-table-card fade-in">
    <div class="table-header">
        <h3 class="table-title">Пользователи (<?php echo count($users); ?>)</h3>
    </div>
    
    <div class="table-container">
        <table class="users-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Пользователь</th>
                    <th>Роль</th>
                    <th>Статус</th>
                    <th>Кампаний</th>
                    <th>Размещений</th>
                    <th>Потрачено</th>
                    <th>Регистрация</th>
                    <th>Последний вход</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td>#<?php echo $user['id']; ?></td>
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
                    <td><?php echo $user['campaigns_count']; ?></td>
                    <td><?php echo $user['placements_count']; ?></td>
                    <td>₽<?php echo number_format($user['total_spent'], 0, ',', ' '); ?></td>
                    <td><?php echo date('d.m.Y', strtotime($user['created_at'])); ?></td>
                    <td>
                        <?php if ($user['last_login']): ?>
                            <?php echo date('d.m.Y H:i', strtotime($user['last_login'])); ?>
                        <?php else: ?>
                            <span style="color: var(--text-tertiary);">Никогда</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="actions-dropdown">
                            <button class="actions-btn" onclick="toggleDropdown(<?php echo $user['id']; ?>)">
                                Действия
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M6 9L12 15L18 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </button>
                            <div class="dropdown-menu" id="dropdown-<?php echo $user['id']; ?>">
                                <button class="dropdown-item" onclick="changeRole(<?php echo $user['id']; ?>, '<?php echo $user['role']; ?>')">
                                    Изменить роль
                                </button>
                                <?php if ($user['is_blocked']): ?>
                                    <button class="dropdown-item" onclick="unblockUser(<?php echo $user['id']; ?>)">
                                        Разблокировать
                                    </button>
                                <?php else: ?>
                                    <button class="dropdown-item danger" onclick="blockUser(<?php echo $user['id']; ?>)">
                                        Заблокировать
                                    </button>
                                <?php endif; ?>
                                <button class="dropdown-item" onclick="viewProfile(<?php echo $user['id']; ?>)">
                                    Профиль
                                </button>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($users)): ?>
                <tr>
                    <td colspan="10" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                        Пользователи не найдены
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Модальные окна -->
<div class="modal" id="roleModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Изменить роль пользователя</h3>
        </div>
        <form method="POST" id="roleForm">
            <input type="hidden" name="action" value="change_role">
            <input type="hidden" name="user_id" id="roleUserId">
            <input type="hidden" name="old_role" id="oldRole">
            
            <div class="form-group">
                <label class="form-label">Новая роль</label>
                <select name="new_role" class="form-select" id="newRole">
                    <option value="user">Базовый</option>
                    <option value="premium">Premium</option>
                </select>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('roleModal')">Отмена</button>
                <button type="submit" class="btn btn-primary">Изменить</button>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="blockModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Заблокировать пользователя</h3>
        </div>
        <form method="POST" id="blockForm">
            <input type="hidden" name="action" value="block">
            <input type="hidden" name="user_id" id="blockUserId">
            
            <div class="form-group">
                <label class="form-label">Причина блокировки</label>
                <textarea name="reason" class="form-input" rows="3" placeholder="Укажите причину блокировки"></textarea>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('blockModal')">Отмена</button>
                <button type="submit" class="btn btn-primary" style="background: var(--error);">Заблокировать</button>
            </div>
        </form>
    </div>
</div>

<script>
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

    function changeRole(userId, currentRole) {
        document.getElementById('roleUserId').value = userId;
        document.getElementById('oldRole').value = currentRole;
        document.getElementById('newRole').value = currentRole === 'premium' ? 'user' : 'premium';
        document.getElementById('roleModal').classList.add('show');
        closeAllDropdowns();
    }

    function blockUser(userId) {
        document.getElementById('blockUserId').value = userId;
        document.getElementById('blockModal').classList.add('show');
        closeAllDropdowns();
    }

    function unblockUser(userId) {
        if (confirm('Разблокировать пользователя?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="unblock">
                <input type="hidden" name="user_id" value="${userId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
        closeAllDropdowns();
    }

    function viewProfile(userId) {
        window.open(`/dashboard/?user_id=${userId}`, '_blank');
        closeAllDropdowns();
    }

    function closeModal(modalId) {
        document.getElementById(modalId).classList.remove('show');
    }

    function closeAllDropdowns() {
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            menu.classList.remove('show');
        });
    }

    // Закрываем dropdown при клике вне
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.actions-dropdown')) {
            closeAllDropdowns();
        }
    });

    // Закрываем модальные окна при клике вне
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.classList.remove('show');
            }
        });
    });

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