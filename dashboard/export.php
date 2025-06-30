<?php
// dashboard/export.php

// Сначала проверяем экспорт ДО подключения header.php
if (isset($_POST['export'])) {
    // Подключаем только необходимые файлы для работы с БД
    require_once '../includes/auth.php';
    requireAuth();
    $currentUser = getCurrentUser();
    
    try {
        $exportType = $_POST['export_type'] ?? 'all';
        $formatType = $_POST['format_type'] ?? 'csv';
        $dateFrom = $_POST['date_from'] ?? date('Y-m-01');
        $dateTo = $_POST['date_to'] ?? date('Y-m-d');
        $campaignId = $_POST['campaign_id'] ?? null;
        
        // Валидация дат
        if (!$dateFrom || !$dateTo) {
            throw new Exception('Необходимо указать период экспорта');
        }
        
        if (strtotime($dateFrom) > strtotime($dateTo)) {
            throw new Exception('Дата начала не может быть больше даты окончания');
        }
        
        // Формируем запрос
        $query = "
            SELECT 
                ap.placement_date,
                c.campaign_name,
                c.channel_name as advertised_channel,
                ap.channel_name,
                COALESCE(ap.admin_name, '') as admin_name,
                COALESCE(ap.theme, '') as theme,
                ap.reach_24h,
                ap.price,
                ap.cpm,
                ap.subscribers_gained,
                ap.price_per_subscriber,
                COALESCE(ap.creative_text, '') as creative_text
            FROM ad_placements ap
            JOIN campaigns c ON ap.campaign_id = c.id
            WHERE c.user_id = ? AND ap.placement_date BETWEEN ? AND ?
        ";
        
        $params = [$currentUser['id'], $dateFrom, $dateTo];
        
        if ($campaignId && $campaignId !== 'all') {
            $query .= " AND c.id = ?";
            $params[] = $campaignId;
        }
        
        $query .= " ORDER BY ap.placement_date DESC";
        
        $stmt = db()->prepare($query);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($data) === 0) {
            header('Location: /dashboard/export.php?error=' . urlencode('Нет данных для экспорта за выбранный период'));
            exit;
        }
        
        // Очищаем все буферы
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        if ($formatType === 'pdf') {
            // PDF экспорт
            generatePDFReport($data, $dateFrom, $dateTo, $currentUser);
        } else {
            // CSV экспорт
            generateCSVReport($data, $dateFrom, $dateTo, $currentUser);
        }
        
        exit;
        
    } catch (Exception $e) {
        header('Location: /dashboard/export.php?error=' . urlencode($e->getMessage()));
        exit;
    }
}

function generateCSVReport($data, $dateFrom, $dateTo, $currentUser) {
    $filename = 'TeleAdm_Report_' . date('Y-m-d_His') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // BOM для корректного отображения в Excel
    echo "\xEF\xBB\xBF";
    
    // Заголовок отчета
    echo "ОТЧЕТ TELEADM - РЕКЛАМНЫЕ РАЗМЕЩЕНИЯ\n";
    echo "Период;От " . date('d.m.Y', strtotime($dateFrom)) . " до " . date('d.m.Y', strtotime($dateTo)) . "\n";
    echo "Пользователь;" . $currentUser['username'] . "\n";
    echo "Дата создания;" . date('d.m.Y H:i') . "\n";
    echo "\n";
    
    // Сводная статистика
    $totalReach = array_sum(array_column($data, 'reach_24h'));
    $totalSpent = array_sum(array_column($data, 'price'));
    $totalSubscribers = array_sum(array_column($data, 'subscribers_gained'));
    $avgCPM = $totalReach > 0 ? ($totalSpent / $totalReach * 1000) : 0;
    $avgPricePerSub = $totalSubscribers > 0 ? ($totalSpent / $totalSubscribers) : 0;
    
    echo "СВОДНАЯ СТАТИСТИКА\n";
    echo "Показатель;Значение\n";
    echo "Общие расходы;₽" . number_format($totalSpent, 2, ',', ' ') . "\n";
    echo "Общий охват;" . number_format($totalReach, 0, ',', ' ') . "\n";
    echo "Новых подписчиков;" . number_format($totalSubscribers, 0, ',', ' ') . "\n";
    echo "Средний CPM;₽" . number_format($avgCPM, 2, ',', ' ') . "\n";
    echo "Средняя цена подписчика;₽" . number_format($avgPricePerSub, 2, ',', ' ') . "\n";
    echo "Количество размещений;" . count($data) . "\n";
    echo "\n";
    
    // Детальные данные
    echo "ДЕТАЛЬНЫЕ ДАННЫЕ ПО РАЗМЕЩЕНИЯМ\n";
    echo "Дата;Кампания;Канал размещения;Админ;Тематика;Охват 24ч;Цена ₽;CPM ₽;Подписчики;Цена подписчика ₽;Креатив\n";
    
    foreach ($data as $row) {
        echo date('d.m.Y', strtotime($row['placement_date'])) . ";" .
             '"' . str_replace('"', '""', $row['campaign_name']) . '";' .
             '"' . str_replace('"', '""', $row['channel_name']) . '";' .
             '"' . str_replace('"', '""', $row['admin_name']) . '";' .
             '"' . str_replace('"', '""', $row['theme']) . '";' .
             number_format($row['reach_24h'], 0, ',', ' ') . ";" .
             number_format($row['price'], 2, ',', ' ') . ";" .
             number_format($row['cpm'], 2, ',', ' ') . ";" .
             $row['subscribers_gained'] . ";" .
             number_format($row['price_per_subscriber'], 2, ',', ' ') . ";" .
             '"' . str_replace('"', '""', substr($row['creative_text'], 0, 100)) . '"' . "\n";
    }
    
    // Итоговая строка
    echo "\n";
    echo "ИТОГО:;;;;" . 
         number_format($totalReach, 0, ',', ' ') . ";" .
         number_format($totalSpent, 2, ',', ' ') . ";" .
         number_format($avgCPM, 2, ',', ' ') . ";" .
         number_format($totalSubscribers, 0, ',', ' ') . ";" .
         number_format($avgPricePerSub, 2, ',', ' ') . ";\n";
    
    echo "\n";
    echo "Отчет создан системой TeleAdm;" . date('d.m.Y H:i') . "\n";
}

function generatePDFReport($data, $dateFrom, $dateTo, $currentUser) {
    $filename = 'TeleAdm_Analytics_' . date('Y-m-d_His') . '.html';
    
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Вычисляем статистику
    $totalReach = array_sum(array_column($data, 'reach_24h'));
    $totalSpent = array_sum(array_column($data, 'price'));
    $totalSubscribers = array_sum(array_column($data, 'subscribers_gained'));
    $avgCPM = $totalReach > 0 ? ($totalSpent / $totalReach * 1000) : 0;
    $avgPricePerSub = $totalSubscribers > 0 ? ($totalSpent / $totalSubscribers) : 0;
    
    // Топ размещений
    usort($data, function($a, $b) {
        return $b['subscribers_gained'] - $a['subscribers_gained'];
    });
    $topPlacements = array_slice($data, 0, 10);
    
    echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Аналитический отчет TeleAdm</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { background: #2563eb; color: white; padding: 20px; text-align: center; }
        .metrics { display: flex; gap: 20px; margin: 20px 0; }
        .metric { background: #f8f9fa; padding: 15px; border-radius: 8px; text-align: center; flex: 1; }
        .metric h3 { margin: 0; color: #666; font-size: 12px; }
        .metric .value { font-size: 24px; font-weight: bold; color: #333; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #f8f9fa; }
        .print-btn { background: #2563eb; color: white; padding: 10px 20px; border: none; cursor: pointer; }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">Печать PDF</button>
    
    <div class="header">
        <h1>Аналитический отчет TeleAdm</h1>
        <p>Период: ' . date('d.m.Y', strtotime($dateFrom)) . ' — ' . date('d.m.Y', strtotime($dateTo)) . '</p>
    </div>
    
    <div class="metrics">
        <div class="metric">
            <h3>Общие расходы</h3>
            <div class="value">₽' . number_format($totalSpent, 0, ',', ' ') . '</div>
        </div>
        <div class="metric">
            <h3>Общий охват</h3>
            <div class="value">' . number_format($totalReach, 0, ',', ' ') . '</div>
        </div>
        <div class="metric">
            <h3>Подписчиков</h3>
            <div class="value">' . number_format($totalSubscribers, 0, ',', ' ') . '</div>
        </div>
        <div class="metric">
            <h3>Средний CPM</h3>
            <div class="value">₽' . number_format($avgCPM, 2, ',', ' ') . '</div>
        </div>
    </div>
    
    <h2>Топ-10 размещений по подписчикам</h2>
    <table>
        <thead>
            <tr>
                <th>№</th>
                <th>Дата</th>
                <th>Канал</th>
                <th>Подписчиков</th>
                <th>Цена</th>
            </tr>
        </thead>
        <tbody>';
    
    foreach ($topPlacements as $index => $placement) {
        echo '<tr>
            <td>' . ($index + 1) . '</td>
            <td>' . date('d.m.Y', strtotime($placement['placement_date'])) . '</td>
            <td>' . htmlspecialchars($placement['channel_name']) . '</td>
            <td>' . $placement['subscribers_gained'] . '</td>
            <td>₽' . number_format($placement['price'], 0, ',', ' ') . '</td>
        </tr>';
    }
    
    echo '</tbody></table>
</body>
</html>';
}

// Теперь подключаем header.php для отображения страницы
$pageTitle = 'Экспорт данных';
require_once 'header.php';

// Получаем ошибку из URL если есть
$error = isset($_GET['error']) ? $_GET['error'] : '';

// Получаем кампании для фильтра
$stmt = db()->prepare("SELECT id, campaign_name FROM campaigns WHERE user_id = ? ORDER BY campaign_name");
$stmt->execute([$currentUser['id']]);
$campaigns = $stmt->fetchAll();

// Статистика для предпросмотра
$stmt = db()->prepare("
    SELECT 
        COUNT(DISTINCT c.id) as campaigns_count,
        COUNT(ap.id) as placements_count,
        COALESCE(SUM(ap.price), 0) as total_spent,
        COALESCE(SUM(ap.subscribers_gained), 0) as total_subscribers
    FROM campaigns c
    LEFT JOIN ad_placements ap ON c.id = ap.campaign_id
    WHERE c.user_id = ?
");
$stmt->execute([$currentUser['id']]);
$totalStats = $stmt->fetch();
?>

<div class="export-container">
    <!-- Заголовок страницы -->
    <div class="page-header">
        <div class="header-content">
            <h1 class="page-title">Экспорт данных</h1>
            <p class="page-subtitle">Создавайте профессиональные отчеты в CSV и PDF форматах</p>
        </div>
        <div class="header-visual">
            <div class="export-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M21 15V19C21 19.5304 20.7893 20.0391 20.4142 20.4142C20.0391 20.7893 19.5304 21 19 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <polyline points="7,10 12,15 17,10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <line x1="12" y1="15" x2="12" y2="3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
        </div>
    </div>

    <!-- Уведомления об ошибках -->
    <?php if ($error): ?>
    <div class="alert alert-error">
        <div class="alert-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                <line x1="15" y1="9" x2="9" y2="15" stroke="currentColor" stroke-width="2"/>
                <line x1="9" y1="9" x2="15" y2="15" stroke="currentColor" stroke-width="2"/>
            </svg>
        </div>
        <span><?php echo htmlspecialchars($error); ?></span>
    </div>
    <?php endif; ?>

    <div class="export-grid">
        <!-- Форма экспорта -->
        <div class="card">
            <div class="card-header">
                <div class="card-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M9 12L11 14L15 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                    </svg>
                </div>
                <h3 class="card-title">Параметры экспорта</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="/dashboard/export.php" class="export-form">
                    <div class="form-group">
                        <label class="form-label">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M14 2H6C5.46957 2 4.96086 2.21071 4.58579 2.58579C4.21071 2.96086 4 3.46957 4 4V20C4 20.5304 4.21071 21.0391 4.58579 21.4142C4.96086 21.7893 5.46957 22 6 22H18C18.5304 22 19.0391 21.7893 19.4142 21.4142C19.7893 21.0391 20 20.5304 20 20V8L14 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <polyline points="14,2 14,8 20,8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Формат отчета
                        </label>
                        <div class="format-types">
                            <label class="format-type-card">
                                <input type="radio" name="format_type" value="csv" checked>
                                <div class="format-type-content">
                                    <div class="format-icon csv">
                                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M14 2H6C5.46957 2 4.96086 2.21071 4.58579 2.58579C4.21071 2.96086 4 3.46957 4 4V20C4 20.5304 4.21071 21.0391 4.58579 21.4142C4.96086 21.7893 5.46957 22 6 22H18C18.5304 22 19.0391 21.7893 19.4142 21.4142C19.7893 21.0391 20 20.5304 20 20V8L14 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            <polyline points="14,2 14,8 20,8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                    <div class="format-title">CSV таблица</div>
                                    <div class="format-desc">Красивая структурированная таблица</div>
                                </div>
                            </label>
                            
                            <label class="format-type-card">
                                <input type="radio" name="format_type" value="pdf">
                                <div class="format-type-content">
                                    <div class="format-icon pdf">
                                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M3 3V21H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M9 9L12 6L16 10L20 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                    <div class="format-title">PDF с аналитикой</div>
                                    <div class="format-desc">Отчет с топ размещений</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M9 11H15V17H9V11Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                <path d="M4 4H20C21.1046 4 22 4.89543 22 6V18C22 19.1046 21.1046 20 20 20H4C2.89543 20 2 19.1046 2 18V6C2 4.89543 2.89543 4 4 4Z" stroke="currentColor" stroke-width="2"/>
                            </svg>
                            Тип данных для экспорта
                        </label>
                        <div class="export-types">
                            <label class="export-type-card">
                                <input type="radio" name="export_type" value="all" checked>
                                <div class="export-type-content">
                                    <div class="export-icon">
                                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M3 3V21H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M9 9L12 6L16 10L20 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                    <div class="export-title">Все размещения</div>
                                    <div class="export-desc">Полная выгрузка всех данных</div>
                                </div>
                            </label>
                            
                            <label class="export-type-card">
                                <input type="radio" name="export_type" value="campaign">
                                <div class="export-type-content">
                                    <div class="export-icon">
                                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                            <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                            <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                    <div class="export-title">По кампании</div>
                                    <div class="export-desc">Данные конкретной кампании</div>
                                </div>
                            </label>
                            
                            <label class="export-type-card">
                                <input type="radio" name="export_type" value="period">
                                <div class="export-type-content">
                                    <div class="export-icon">
                                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2" stroke="currentColor" stroke-width="2"/>
                                            <line x1="16" y1="2" x2="16" y2="6" stroke="currentColor" stroke-width="2"/>
                                            <line x1="8" y1="2" x2="8" y2="6" stroke="currentColor" stroke-width="2"/>
                                            <line x1="3" y1="10" x2="21" y2="10" stroke="currentColor" stroke-width="2"/>
                                        </svg>
                                    </div>
                                    <div class="export-title">За период</div>
                                    <div class="export-desc">Фильтр по датам</div>
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group campaign-filter" style="display: none;">
                        <label for="campaign_id" class="form-label">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                            </svg>
                            Выберите кампанию
                        </label>
                        <select name="campaign_id" id="campaign_id" class="form-select">
                            <option value="all">Все кампании</option>
                            <?php foreach ($campaigns as $campaign): ?>
                            <option value="<?php echo $campaign['id']; ?>">
                                <?php echo htmlspecialchars($campaign['campaign_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group period-filter">
                        <label class="form-label">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2" stroke="currentColor" stroke-width="2"/>
                                <line x1="16" y1="2" x2="16" y2="6" stroke="currentColor" stroke-width="2"/>
                                <line x1="8" y1="2" x2="8" y2="6" stroke="currentColor" stroke-width="2"/>
                                <line x1="3" y1="10" x2="21" y2="10" stroke="currentColor" stroke-width="2"/>
                            </svg>
                            Период выгрузки
                        </label>
                        <div class="date-range">
                            <div class="date-input-group">
                                <label for="date_from">От:</label>
                                <input type="date" name="date_from" id="date_from" class="form-input"
                                       value="<?php echo date('Y-m-01'); ?>" max="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="date-separator">—</div>
                            <div class="date-input-group">
                                <label for="date_to">До:</label>
                                <input type="date" name="date_to" id="date_to" class="form-input"
                                       value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="export" value="1" class="btn btn-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M21 15V19C21 19.5304 20.7893 20.0391 20.4142 20.4142C20.0391 20.7893 19.5304 21 19 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <polyline points="7,10 12,15 17,10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <line x1="12" y1="15" x2="12" y2="3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Создать отчет
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Боковая панель -->
        <div class="export-sidebar">
            <div class="card">
                <div class="card-header">
                    <div class="card-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M3 3V21H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M9 9L12 6L16 10L20 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <h3 class="card-title">Ваши данные</h3>
                </div>
                <div class="card-body">
                    <div class="data-summary">
                        <div class="summary-item">
                            <div class="summary-icon campaigns">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                    <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                    <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                </svg>
                            </div>
                            <div class="summary-content">
                                <div class="summary-value"><?php echo $totalStats['campaigns_count']; ?></div>
                                <div class="summary-label">Кампаний</div>
                            </div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-icon placements">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2" stroke="currentColor" stroke-width="2"/>
                                    <rect x="9" y="9" width="6" height="6" stroke="currentColor" stroke-width="2"/>
                                </svg>
                            </div>
                            <div class="summary-content">
                                <div class="summary-value"><?php echo $totalStats['placements_count']; ?></div>
                                <div class="summary-label">Размещений</div>
                            </div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-icon spending">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <line x1="12" y1="1" x2="12" y2="23" stroke="currentColor" stroke-width="2"/>
                                    <path d="M17 5H9.5C8.57174 5 7.6815 5.36875 7.02513 6.02513C6.36875 6.6815 6 7.57174 6 8.5C6 9.42826 6.36875 10.3185 7.02513 10.9749C7.6815 11.6313 8.57174 12 9.5 12H14.5C15.4283 12 16.3185 12.3687 16.9749 13.0251C17.6313 13.6815 18 14.5717 18 15.5C18 16.4283 17.6313 17.3185 16.9749 17.9749C16.3185 18.6313 15.4283 19 14.5 19H6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                            <div class="summary-content">
                                <div class="summary-value">₽<?php echo number_format($totalStats['total_spent'], 0, ',', ' '); ?></div>
                                <div class="summary-label">Общие расходы</div>
                            </div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-icon subscribers">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M16 21V19C16 17.9391 15.5786 16.9217 14.8284 16.1716C14.0783 15.4214 13.0609 15 12 15H5C3.93913 15 2.92172 15.4214 1.17157 16.1716C0.421427 16.9217 0 17.9391 0 19V21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <circle cx="8.5" cy="7" r="4" stroke="currentColor" stroke-width="2"/>
                                    <line x1="20" y1="8" x2="20" y2="14" stroke="currentColor" stroke-width="2"/>
                                    <line x1="23" y1="11" x2="17" y2="11" stroke="currentColor" stroke-width="2"/>
                                </svg>
                            </div>
                            <div class="summary-content">
                                <div class="summary-value"><?php echo number_format($totalStats['total_subscribers'], 0, ',', ' '); ?></div>
                                <div class="summary-label">Подписчиков</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const exportTypeRadios = document.querySelectorAll('input[name="export_type"]');
    const campaignFilter = document.querySelector('.campaign-filter');
    
    exportTypeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'campaign') {
                campaignFilter.style.display = 'block';
            } else {
                campaignFilter.style.display = 'none';
            }
        });
    });
});
</script>

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
    --bg-primary: #ffffff;
    --bg-secondary: #f8fafc;
    --border: #e2e8f0;
    --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
    --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
    --radius-md: 8px;
    --radius-lg: 12px;
}

* {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
}

.export-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 24px;
    background: var(--bg-secondary);
    min-height: 100vh;
}

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
}

.header-content p {
    font-size: 16px;
    opacity: 0.9;
    margin: 0;
}

.export-icon {
    width: 80px;
    height: 80px;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

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

.alert-error {
    background: #fee2e2;
    color: var(--error);
    border: 1px solid #f87171;
}

.export-grid {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 24px;
}

.card {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    margin-bottom: 24px;
    box-shadow: var(--shadow-sm);
}

.card-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    background: var(--bg-secondary);
}

.card-icon {
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
}

.card-body {
    padding: 24px;
}

.export-form {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.form-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
}

.format-types {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.export-types {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
}

.format-type-card, .export-type-card {
    position: relative;
    cursor: pointer;
}

.format-type-card input, .export-type-card input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.format-type-content, .export-type-content {
    padding: 20px;
    border: 2px solid var(--border);
    border-radius: var(--radius-lg);
    text-align: center;
    transition: all 0.2s;
    background: var(--bg-primary);
}

.format-type-card input:checked + .format-type-content,
.export-type-card input:checked + .export-type-content {
    border-color: var(--primary);
    background: rgba(37, 99, 235, 0.05);
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.format-icon, .export-icon {
    margin-bottom: 12px;
    color: var(--primary);
}

.format-icon.csv {
    color: var(--success);
}

.format-icon.pdf {
    color: var(--error);
}

.format-title, .export-title {
    font-weight: 600;
    margin-bottom: 6px;
    color: var(--text-primary);
    font-size: 14px;
}

.format-desc, .export-desc {
    font-size: 12px;
    color: var(--text-secondary);
}

.form-select, .form-input {
    padding: 12px 16px;
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    font-size: 14px;
    background: var(--bg-primary);
    font-family: inherit;
}

.form-select:focus, .form-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.date-range {
    display: flex;
    align-items: center;
    gap: 16px;
}

.date-input-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
    flex: 1;
}

.date-input-group label {
    font-size: 12px;
    font-weight: 500;
    color: var(--text-secondary);
}

.date-separator {
    color: var(--text-secondary);
    font-weight: 500;
    margin-top: 20px;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 24px;
    border: none;
    border-radius: var(--radius-md);
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
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

.data-summary {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.summary-item {
    display: flex;
    align-items: center;
    gap: 12px;
}

.summary-icon {
    width: 40px;
    height: 40px;
    border-radius: var(--radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.summary-icon.campaigns {
    background: #dbeafe;
    color: var(--primary);
}

.summary-icon.placements {
    background: #d1fae5;
    color: var(--success);
}

.summary-icon.spending {
    background: #fef3c7;
    color: var(--warning);
}

.summary-icon.subscribers {
    background: #e0e7ff;
    color: #8b5cf6;
}

.summary-value {
    font-size: 20px;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 2px;
}

.summary-label {
    font-size: 12px;
    color: var(--text-secondary);
    font-weight: 500;
}

@media (max-width: 768px) {
    .export-grid {
        grid-template-columns: 1fr;
    }
    
    .format-types, .export-types {
        grid-template-columns: 1fr;
    }
    
    .date-range {
        flex-direction: column;
        align-items: stretch;
    }
    
    .data-summary {
        grid-template-columns: 1fr;
    }
}
</style>

<?php require_once 'footer.php'; ?>
