<?php
$pageTitle = 'Управление платежами';
require_once 'header.php';

// Фильтры
$statusFilter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$periodFilter = $_GET['period'] ?? 30;

// Условие для периода
$dateCondition = "";
if ($periodFilter) {
    $dateCondition = "AND p.created_at >= DATE_SUB(NOW(), INTERVAL $periodFilter DAY)";
}

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $paymentId = $_POST['payment_id'] ?? 0;
    
    if ($action === 'mark_completed' && $paymentId) {
        $stmt = db()->prepare("UPDATE payments SET status = 'completed', paid_at = NOW() WHERE id = ? AND status = 'pending'");
        $stmt->execute([$paymentId]);
        
        // Активируем Premium для пользователя
        $stmt = db()->prepare("SELECT user_id, amount FROM payments WHERE id = ?");
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch();
        
        if ($payment) {
            $stmt = db()->prepare("UPDATE users SET role = 'premium' WHERE id = ?");
            $stmt->execute([$payment['user_id']]);
            
            $stmt = db()->prepare("
                INSERT INTO subscriptions (user_id, type, start_date, end_date, is_active) 
                VALUES (?, 'premium', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), TRUE)
                ON DUPLICATE KEY UPDATE end_date = DATE_ADD(end_date, INTERVAL 30 DAY), is_active = TRUE
            ");
            $stmt->execute([$payment['user_id']]);
        }
        
        header('Location: /admin/payments.php?success=1');
        exit;
    }
    
    if ($action === 'refund' && $paymentId) {
        $stmt = db()->prepare("UPDATE payments SET status = 'refunded' WHERE id = ?");
        $stmt->execute([$paymentId]);
        header('Location: /admin/payments.php?refunded=1');
        exit;
    }
}

// Получаем платежи
$query = "
    SELECT p.*, u.username, u.email, u.role as current_role
    FROM payments p
    JOIN users u ON p.user_id = u.id
    WHERE 1=1
";

$params = [];
if ($statusFilter) {
    $query .= " AND p.status = ?";
    $params[] = $statusFilter;
}

if ($search) {
    $query .= " AND (u.username LIKE ? OR u.email LIKE ? OR p.transaction_id LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$query .= " $dateCondition ORDER BY p.created_at DESC";

$stmt = db()->prepare($query);
$stmt->execute($params);
$payments = $stmt->fetchAll();

// Статистика платежей
$statsQuery = "
    SELECT COUNT(*) as total_payments,
           SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_payments,
           SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_payments,
           SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_payments,
           SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_revenue,
           AVG(CASE WHEN status = 'completed' THEN amount ELSE NULL END) as avg_payment
    FROM payments WHERE 1=1 $dateCondition
";

$stmt = db()->query($statsQuery);
$stats = $stmt->fetch();
?>

<style>
    .payments-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 24px;
    }

    .payment-stat-card {
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 20px;
        text-align: center;
        box-shadow: var(--shadow-sm);
        position: relative;
        overflow: hidden;
    }

    .payment-stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
    }

    .payment-stat-card.revenue::before {
        background: linear-gradient(135deg, var(--success), #059669);
    }

    .payment-stat-card.pending::before {
        background: linear-gradient(135deg, var(--warning), #d97706);
    }

    .payment-stat-card.completed::before {
        background: linear-gradient(135deg, var(--primary), var(--primary-light));
    }

    .payment-stat-card.failed::before {
        background: linear-gradient(135deg, var(--error), #dc2626);
    }

    .payment-stat-value {
        font-size: 28px;
        font-weight: 800;
        margin-bottom: 8px;
    }

    .payment-stat-value.revenue {
        color: var(--success);
    }

    .payment-stat-value.pending {
        color: var(--warning);
    }

    .payment-stat-value.completed {
        color: var(--primary);
    }

    .payment-stat-value.failed {
        color: var(--error);
    }

    .payment-stat-label {
        font-size: 14px;
        color: var(--text-secondary);
        font-weight: 500;
    }

    .payment-status {
        display: inline-flex;
        align-items: center;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .payment-status.completed {
        background: #dcfce7;
        color: #166534;
    }

    .payment-status.pending {
        background: #fef3c7;
        color: #92400e;
    }

    .payment-status.failed {
        background: #fee2e2;
        color: #991b1b;
    }

    .payment-status.refunded {
        background: #f3f4f6;
        color: #374151;
    }

    .payment-actions {
        display: flex;
        gap: 8px;
    }

    .btn-small {
        padding: 6px 12px;
        font-size: 12px;
        border-radius: 6px;
    }

    .btn-success {
        background: var(--success);
        color: white;
        border: none;
    }

    .btn-success:hover {
        background: #059669;
    }

    .btn-warning {
        background: var(--warning);
        color: white;
        border: none;
    }

    .btn-warning:hover {
        background: #d97706;
    }

    .transaction-id {
        font-family: 'Monaco', 'Menlo', monospace;
        font-size: 12px;
        background: var(--bg-secondary);
        padding: 4px 8px;
        border-radius: 4px;
        color: var(--text-secondary);
    }
</style>

<!-- Статистика платежей -->
<div class="payments-stats fade-in">
    <div class="payment-stat-card revenue">
        <div class="payment-stat-value revenue">₽<?php echo number_format($stats['total_revenue'], 0, ',', ' '); ?></div>
        <div class="payment-stat-label">Общая выручка</div>
    </div>
    <div class="payment-stat-card completed">
        <div class="payment-stat-value completed"><?php echo $stats['completed_payments']; ?></div>
        <div class="payment-stat-label">Завершенных платежей</div>
    </div>
    <div class="payment-stat-card pending">
        <div class="payment-stat-value pending"><?php echo $stats['pending_payments']; ?></div>
        <div class="payment-stat-label">Ожидают подтверждения</div>
    </div>
    <div class="payment-stat-card failed">
        <div class="payment-stat-value failed"><?php echo $stats['failed_payments']; ?></div>
        <div class="payment-stat-label">Неудачных платежей</div>
    </div>
</div>

<!-- Фильтры -->
<div class="filters-card fade-in">
    <form method="GET" class="filters-grid">
        <div class="form-group">
            <label class="form-label">Поиск</label>
            <input type="text" name="search" class="form-input" placeholder="Пользователь, email или ID транзакции" value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Статус</label>
            <select name="status" class="form-select">
                <option value="">Все статусы</option>
                <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Завершен</option>
                <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Ожидает</option>
                <option value="failed" <?php echo $statusFilter === 'failed' ? 'selected' : ''; ?>>Неудачный</option>
                <option value="refunded" <?php echo $statusFilter === 'refunded' ? 'selected' : ''; ?>>Возвращен</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Период</label>
            <select name="period" class="form-select">
                <option value="7" <?php echo $periodFilter == 7 ? 'selected' : ''; ?>>7 дней</option>
                <option value="30" <?php echo $periodFilter == 30 ? 'selected' : ''; ?>>30 дней</option>
                <option value="90" <?php echo $periodFilter == 90 ? 'selected' : ''; ?>>90 дней</option>
                <option value="" <?php echo $periodFilter == '' ? 'selected' : ''; ?>>Все время</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Применить</button>
    </form>
</div>

<!-- Таблица платежей -->
<div class="users-table-card fade-in">
    <div class="table-header">
        <h3 class="table-title">История платежей (<?php echo count($payments); ?>)</h3>
    </div>
    
    <div class="table-container">
        <table class="users-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Дата</th>
                    <th>Пользователь</th>
                    <th>Сумма</th>
                    <th>Способ оплаты</th>
                    <th>ID транзакции</th>
                    <th>Статус</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $payment): ?>
                <tr>
                    <td>#<?php echo $payment['id']; ?></td>
                    <td>
                        <?php if ($payment['paid_at']): ?>
                            <?php echo date('d.m.Y H:i', strtotime($payment['paid_at'])); ?>
                        <?php else: ?>
                            <?php echo date('d.m.Y H:i', strtotime($payment['created_at'])); ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="user-info">
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($payment['username'], 0, 1)); ?>
                            </div>
                            <div class="user-details">
                                <h4><?php echo htmlspecialchars($payment['username']); ?></h4>
                                <p><?php echo htmlspecialchars($payment['email']); ?></p>
                            </div>
                        </div>
                    </td>
                    <td>
                        <strong>₽<?php echo number_format($payment['amount'], 2, ',', ' '); ?></strong>
                    </td>
                    <td><?php echo htmlspecialchars($payment['payment_method'] ?? '—'); ?></td>
                    <td>
                        <?php if ($payment['transaction_id']): ?>
                            <span class="transaction-id"><?php echo htmlspecialchars($payment['transaction_id']); ?></span>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $statusBadge = [
                            'completed' => 'Завершен',
                            'pending' => 'Ожидает',
                            'failed' => 'Неудачный',
                            'refunded' => 'Возвращен'
                        ];
                        ?>
                        <span class="payment-status <?php echo $payment['status']; ?>">
                            <?php echo $statusBadge[$payment['status']] ?? 'Неизвестно'; ?>
                        </span>
                    </td>
                    <td>
                        <div class="payment-actions">
                            <?php if ($payment['status'] === 'pending'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="mark_completed">
                                    <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                    <button type="submit" class="btn btn-success btn-small" onclick="return confirm('Подтвердить платеж?')">
                                        Подтвердить
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <?php if ($payment['status'] === 'completed'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="refund">
                                    <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                    <button type="submit" class="btn btn-warning btn-small" onclick="return confirm('Вернуть платеж?')">
                                        Вернуть
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <a href="/admin/users.php?search=<?php echo urlencode($payment['email']); ?>" class="btn btn-secondary btn-small">
                                👤 Профиль
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($payments)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                        💸 Платежей не найдено
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
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