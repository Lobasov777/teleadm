<?php
// dashboard/header.php
require_once '../includes/auth.php';

// Проверяем авторизацию
requireAuth();

// Получаем данные пользователя
$currentUser = getCurrentUser();

// Определяем активную страницу
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Проверяем, находится ли админ в режиме impersonate
$isImpersonating = isset($_SESSION['admin_impersonating']) && $_SESSION['admin_impersonating'] === true;

// Обработка выхода из режима impersonate
if (isset($_GET['exit_impersonate']) && $isImpersonating) {
    // Записываем в логи
    $stmt = db()->prepare("
        UPDATE admin_impersonations 
        SET ended_at = NOW() 
        WHERE admin_id = ? AND user_id = ? AND ended_at IS NULL
    ");
    $stmt->execute([$_SESSION['admin_id'], $_SESSION['user_id']]);
    
    // Восстанавливаем сессию админа
    $stmt = db()->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch();
    
    if ($admin) {
        $_SESSION['user_id'] = $admin['id'];
        $_SESSION['user_email'] = $admin['email'];
        $_SESSION['user_name'] = $admin['username'];
        $_SESSION['user_role'] = $admin['role'];
        
        unset($_SESSION['admin_id']);
        unset($_SESSION['admin_impersonating']);
    }
    
    header('Location: /admin/users.php?impersonate_ended=1');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Личный кабинет'; ?> - TeleAdm</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --primary-light: #3b82f6;
            --primary-dark: #1d4ed8;
            --success: #10b981;
            --warning: #f59e0b;
            --error: #ef4444;
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
            --sidebar-width: 280px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        body {
            color: var(--text-primary);
            background-color: var(--bg-secondary);
            line-height: 1.6;
            font-size: 14px;
            overflow-x: hidden;
        }

        /* Боковое меню */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--text-primary) 0%, #1e293b 100%);
            overflow-y: auto;
            z-index: 100;
            box-shadow: var(--shadow-lg);
        }

        .sidebar-header {
            padding: 32px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
        }

        .sidebar-logo {
            font-size: 24px;
            color: white;
            text-decoration: none;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            letter-spacing: -0.025em;
        }

        .logo-icon {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
        }

        .sidebar-menu {
            list-style: none;
            padding: 24px 0;
        }

        .sidebar-menu > li {
            margin-bottom: 4px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 24px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            font-size: 14px;
            font-weight: 500;
            border-radius: 0 24px 24px 0;
            margin-right: 12px;
        }

        .sidebar-menu a:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(4px);
        }

        .sidebar-menu a.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .sidebar-menu a.active::before {
            content: '';
            position: absolute;
            left: -12px;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 24px;
            background: white;
            border-radius: 2px;
        }

        .menu-icon {
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .menu-section {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .menu-label {
            padding: 0 24px;
            margin-bottom: 12px;
            font-size: 11px;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.5);
            letter-spacing: 1px;
            font-weight: 600;
        }

        .menu-badge {
            margin-left: auto;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            background: linear-gradient(135deg, var(--warning), #ea580c);
            color: white;
        }

        /* Основной контент */
        .main-wrapper {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            background: var(--bg-secondary);
            position: relative;
        }

        /* Уведомление о режиме impersonate */
        .impersonate-banner {
            background: linear-gradient(135deg, var(--error), #dc2626);
            color: white;
            padding: 16px 24px;
            text-align: center;
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            right: 0;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            font-size: 14px;
            font-weight: 500;
            box-shadow: var(--shadow-md);
        }

        .impersonate-banner a {
            color: white;
            text-decoration: none;
            font-weight: 600;
            padding: 6px 12px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: var(--radius-sm);
            transition: all 0.2s;
        }

        .impersonate-banner a:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-1px);
        }

        /* Верхняя панель */
        .top-bar {
            background: var(--bg-primary);
            border-bottom: 1px solid var(--border);
            padding: 20px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 50;
            <?php if ($isImpersonating): ?>
            margin-top: 56px;
            <?php endif; ?>
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: var(--text-secondary);
        }

        .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .breadcrumb a:hover {
            color: var(--primary-dark);
        }

        .breadcrumb-separator {
            color: var(--text-tertiary);
            font-size: 12px;
        }

        .user-panel {
            display: flex;
            align-items: center;
            gap: 24px;
        }

        .user-balance {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: linear-gradient(135deg, var(--bg-tertiary), var(--bg-secondary));
            border: 1px solid var(--border);
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
        }

        .balance-label {
            color: var(--text-secondary);
        }

        .balance-amount {
            font-weight: 600;
            color: var(--success);
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 16px;
            background: var(--bg-primary);
            border: 1px solid var(--border);
            border-radius: 24px;
            transition: all 0.2s;
            cursor: pointer;
        }

        .user-menu:hover {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
        }

        .user-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .user-name {
            font-weight: 600;
            font-size: 14px;
            color: var(--text-primary);
        }

        .user-role {
            font-size: 12px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .user-role.premium {
            color: var(--warning);
            font-weight: 600;
        }

        .user-role.admin {
            color: var(--primary);
            font-weight: 600;
        }

        /* Контент */
        .main-content {
            padding: 0;
            <?php if ($isImpersonating): ?>
            padding-top: 0;
            <?php endif; ?>
        }

        /* Адаптивность */
        @media (max-width: 1024px) {
            .user-panel {
                gap: 16px;
            }
            
            .user-balance {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.mobile-open {
                transform: translateX(0);
            }
            
            .main-wrapper {
                margin-left: 0;
            }
            
            .impersonate-banner {
                left: 0;
            }
            
            .top-bar {
                padding: 16px 20px;
            }
            
            .breadcrumb {
                display: none;
            }
            
            .user-info {
                display: none;
            }
            
            .mobile-menu-toggle {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 40px;
                height: 40px;
                background: var(--bg-secondary);
                border: 1px solid var(--border);
                border-radius: var(--radius-md);
                cursor: pointer;
                transition: all 0.2s;
            }
            
            .mobile-menu-toggle:hover {
                background: var(--bg-tertiary);
            }
        }

        @media (min-width: 769px) {
            .mobile-menu-toggle {
                display: none;
            }
        }

        /* Скроллбар для сайдбара */
        .sidebar::-webkit-scrollbar {
            width: 4px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 2px;
        }

        .sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.5);
        }

        /* Анимации */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .main-content > * {
            animation: slideIn 0.3s ease-out;
        }
    </style>
</head>
<body>
    <!-- Боковое меню -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="/dashboard/" class="sidebar-logo">
                <div class="logo-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M3 3V21H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M9 9L12 6L16 10L20 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                TeleAdm
            </a>
        </div>
        
        <ul class="sidebar-menu">
            <li>
                <a href="/dashboard/" class="<?php echo $currentPage === 'index' ? 'active' : ''; ?>">
                    <span class="menu-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect x="3" y="3" width="7" height="7" stroke="currentColor" stroke-width="2" rx="1"/>
                            <rect x="14" y="3" width="7" height="7" stroke="currentColor" stroke-width="2" rx="1"/>
                            <rect x="14" y="14" width="7" height="7" stroke="currentColor" stroke-width="2" rx="1"/>
                            <rect x="3" y="14" width="7" height="7" stroke="currentColor" stroke-width="2" rx="1"/>
                        </svg>
                    </span>
                    <span>Дашборд</span>
                </a>
            </li>
            <li>
                <a href="/dashboard/campaigns.php" class="<?php echo $currentPage === 'campaigns' ? 'active' : ''; ?>">
                    <span class="menu-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                            <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                            <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <span>Кампании</span>
                </a>
            </li>
            <li>
                <a href="/dashboard/placements.php" class="<?php echo $currentPage === 'placements' ? 'active' : ''; ?>">
                    <span class="menu-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2" stroke="currentColor" stroke-width="2"/>
                            <rect x="9" y="9" width="6" height="6" stroke="currentColor" stroke-width="2"/>
                        </svg>
                    </span>
                    <span>Размещения</span>
                </a>
            </li>
            <li>
                <a href="/dashboard/analytics.php" class="<?php echo $currentPage === 'analytics' ? 'active' : ''; ?>">
                    <span class="menu-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M3 3V21H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M9 9L12 6L16 10L20 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <span>Аналитика</span>
                </a>
            </li>
            
            <div class="menu-section">
                <div class="menu-label">Инструменты</div>
                <li>
                    <a href="/dashboard/export.php" class="<?php echo $currentPage === 'export' ? 'active' : ''; ?>">
                        <span class="menu-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M21 15V19C21 19.5304 20.7893 20.0391 20.4142 20.4142C20.0391 20.7893 19.5304 21 19 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <polyline points="7,10 12,15 17,10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <line x1="12" y1="15" x2="12" y2="3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <span>Экспорт данных</span>
                    </a>
                </li>
            </div>
            
            <div class="menu-section">
                <div class="menu-label">Аккаунт</div>
                <li>
                    <a href="/dashboard/profile.php" class="<?php echo $currentPage === 'profile' ? 'active' : ''; ?>">
                        <span class="menu-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
                                <path d="M19.4 15C19.2669 15.3016 19.2272 15.6362 19.286 15.9606C19.3448 16.285 19.4995 16.5843 19.73 16.82L19.79 16.88C19.976 17.0657 20.1235 17.2863 20.2241 17.5291C20.3248 17.7719 20.3766 18.0322 20.3766 18.295C20.3766 18.5578 20.3248 18.8181 20.2241 19.0609C20.1235 19.3037 19.976 19.5243 19.79 19.71C19.6043 19.896 19.3837 20.0435 19.1409 20.1441C18.8981 20.2448 18.6378 20.2966 18.375 20.2966C18.1122 20.2966 17.8519 20.2448 17.6091 20.1441C17.3663 20.0435 17.1457 19.896 16.96 19.71L16.9 19.65C16.6643 19.4195 16.365 19.2648 16.0406 19.206C15.7162 19.1472 15.3816 19.1869 15.08 19.32C14.7842 19.4468 14.532 19.6572 14.3543 19.9255C14.1766 20.1938 14.0813 20.5082 14.08 20.83V21C14.08 21.5304 13.8693 22.0391 13.4942 22.4142C13.1191 22.7893 12.6104 23 12.08 23C11.5496 23 11.0409 22.7893 10.6658 22.4142C10.2907 22.0391 10.08 21.5304 10.08 21V20.91C10.0723 20.579 9.96512 20.2573 9.77251 19.9887C9.5799 19.7201 9.31074 19.5176 9 19.41C8.69838 19.2769 8.36381 19.2372 8.03941 19.296C7.71502 19.3548 7.41568 19.5095 7.18 19.74L7.12 19.8C6.93425 19.986 6.71368 20.1335 6.47088 20.2341C6.22808 20.3348 5.96783 20.3866 5.705 20.3866C5.44217 20.3866 5.18192 20.3348 4.93912 20.2341C4.69632 20.1335 4.47575 19.986 4.29 19.8C4.10405 19.6143 3.95653 19.3937 3.85588 19.1509C3.75523 18.9081 3.70343 18.6478 3.70343 18.385C3.70343 18.1222 3.75523 17.8619 3.85588 17.6191C3.95653 17.3763 4.10405 17.1557 4.29 16.97L4.35 16.91C4.58054 16.6743 4.73519 16.375 4.794 16.0506C4.85282 15.7262 4.81312 15.3916 4.68 15.09C4.55324 14.7942 4.34276 14.542 4.07447 14.3643C3.80618 14.1866 3.49179 14.0913 3.17 14.09H3C2.46957 14.09 1.96086 13.8793 1.58579 13.5042C1.21071 13.1291 1 12.6204 1 12.09C1 11.5596 1.21071 11.0509 1.58579 10.6758C1.96086 10.3007 2.46957 10.09 3 10.09H3.09C3.42099 10.0823 3.742 9.97512 4.01062 9.78251C4.27925 9.5899 4.48167 9.32074 4.59 9.01C4.72312 8.70838 4.76282 8.37381 4.704 8.04941C4.64519 7.72502 4.49054 7.42568 4.26 7.19L4.2 7.13C4.01405 6.94425 3.86653 6.72368 3.76588 6.48088C3.66523 6.23808 3.61343 5.97783 3.61343 5.715C3.61343 5.45217 3.66523 5.19192 3.76588 4.94912C3.86653 4.70632 4.01405 4.48575 4.2 4.3C4.38575 4.11405 4.60632 3.96653 4.84912 3.86588C5.09192 3.76523 5.35217 3.71343 5.615 3.71343C5.87783 3.71343 6.13808 3.76523 6.38088 3.86588C6.62368 3.96653 6.84425 4.11405 7.03 4.3L7.09 4.36C7.32568 4.59054 7.62502 4.74519 7.94941 4.804C8.27381 4.86282 8.60838 4.82312 8.91 4.69H9C9.29577 4.56324 9.54802 4.35276 9.72569 4.08447C9.90337 3.81618 9.99872 3.50179 10 3.18V3C10 2.46957 10.2107 1.96086 10.5858 1.58579C10.9609 1.21071 11.4696 1 12 1C12.5304 1 13.0391 1.21071 13.4142 1.58579C13.7893 1.96086 14 2.46957 14 3V3.09C14.0013 3.41179 14.0966 3.72618 14.2743 3.99447C14.452 4.26276 14.7042 4.47324 15 4.6C15.3016 4.73312 15.6362 4.77282 15.9606 4.714C16.285 4.65519 16.5843 4.50054 16.82 4.27L16.88 4.21C17.0657 4.02405 17.2863 3.87653 17.5291 3.77588C17.7719 3.67523 18.0322 3.62343 18.295 3.62343C18.5578 3.62343 18.8181 3.67523 19.0609 3.77588C19.3037 3.87653 19.5243 4.02405 19.71 4.21C19.896 4.39575 20.0435 4.61632 20.1441 4.85912C20.2448 5.10192 20.2966 5.36217 20.2966 5.625C20.2966 5.88783 20.2448 6.14808 20.1441 6.39088C20.0435 6.63368 19.896 6.85425 19.71 7.04L19.65 7.1C19.4195 7.33568 19.2648 7.63502 19.206 7.95941C19.1472 8.28381 19.1869 8.61838 19.32 8.92V9C19.4468 9.29577 19.6572 9.54802 19.9255 9.72569C20.1938 9.90337 20.5082 9.99872 20.83 10H21C21.5304 10 22.0391 10.2107 22.4142 10.5858C22.7893 10.9609 23 11.4696 23 12C23 12.5304 22.7893 13.0391 22.4142 13.4142C22.0391 13.7893 21.5304 14 21 14H20.91C20.5882 14.0013 20.2738 14.0966 20.0055 14.2743C19.7372 14.452 19.5268 14.7042 19.4 15Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <span>Настройки</span>
                    </a>
                </li>
                <li>
                    <a href="/pricing.php">
                        <span class="menu-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <span>Тарифы</span>
                        <?php if ($currentUser['role'] !== 'premium' && $currentUser['role'] !== 'admin'): ?>
                        <span class="menu-badge">PRO</span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php if ($currentUser['role'] === 'admin' && !$isImpersonating): ?>
                <li>
                    <a href="/admin/">
                        <span class="menu-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 1L3 5V11C3 16.55 6.84 21.74 12 23C17.16 21.74 21 16.55 21 11V5L12 1Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <span>Админ-панель</span>
                    </a>
                </li>
                <?php endif; ?>
                <li>
                    <a href="/logout.php">
                        <span class="menu-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M9 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V5C3 4.46957 3.21071 3.96086 3.58579 3.58579C3.96086 3.21071 4.46957 3 5 3H9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <polyline points="16,17 21,12 16,7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <line x1="21" y1="12" x2="9" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <span>Выйти</span>
                    </a>
                </li>
            </div>
        </ul>
    </nav>

    <!-- Основной контент -->
    <div class="main-wrapper">
        <?php if ($isImpersonating): ?>
        <!-- Уведомление о режиме impersonate -->
        <div class="impersonate-banner">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M10.29 3.86L1.82 18C1.64 18.37 1.64 18.82 1.82 19.19C2 19.56 2.37 19.78 2.77 19.78H21.23C21.63 19.78 22 19.56 22.18 19.19C22.36 18.82 22.36 18.37 22.18 18L13.71 3.86C13.53 3.49 13.16 3.27 12.76 3.27C12.36 3.27 11.99 3.49 11.81 3.86H10.29Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <line x1="12" y1="9" x2="12" y2="13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <line x1="12" y1="17" x2="12.01" y2="17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <strong>Режим просмотра:</strong> Вы вошли как пользователь <?php echo htmlspecialchars($currentUser['username']); ?>
            <a href="?exit_impersonate=1">Вернуться в админ-панель</a>
        </div>
        <?php endif; ?>
        
        <!-- Верхняя панель -->
        <div class="top-bar">
            <div class="mobile-menu-toggle" onclick="toggleMobileMenu()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <line x1="3" y1="6" x2="21" y2="6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <line x1="3" y1="12" x2="21" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <line x1="3" y1="18" x2="21" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            
            <div class="breadcrumb">
                <a href="/dashboard/">Личный кабинет</a>
                <span class="breadcrumb-separator">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <polyline points="9,18 15,12 9,6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
                <span><?php echo $pageTitle ?? 'Дашборд'; ?></span>
            </div>
            
            <div class="user-panel">
                <?php if ($currentUser['role'] !== 'premium' && $currentUser['role'] !== 'admin'): ?>
                <div class="user-balance">
                    <span class="balance-label">Лимит:</span>
                    <span class="balance-amount">50 размещений/мес</span>
                </div>
                <?php endif; ?>
                
                <div class="user-menu">
                    <div class="user-avatar">
                        <?php echo mb_strtoupper(mb_substr($currentUser['username'], 0, 1)); ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($currentUser['username']); ?></div>
                        <div class="user-role <?php echo $currentUser['role']; ?>">
                            <?php 
                            if ($currentUser['role'] === 'admin') {
                                echo 'Администратор';
                            } elseif ($currentUser['role'] === 'premium') {
                                echo 'Premium';
                            } else {
                                echo 'Базовый';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <main class="main-content">

<script>
function toggleMobileMenu() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('mobile-open');
}

// Закрытие мобильного меню при клике вне его
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    const toggle = document.querySelector('.mobile-menu-toggle');
    
    if (window.innerWidth <= 768 && 
        !sidebar.contains(event.target) && 
        !toggle.contains(event.target) &&
        sidebar.classList.contains('mobile-open')) {
        sidebar.classList.remove('mobile-open');
    }
});

// Закрытие мобильного меню при изменении размера окна
window.addEventListener('resize', function() {
    const sidebar = document.getElementById('sidebar');
    if (window.innerWidth > 768) {
        sidebar.classList.remove('mobile-open');
    }
});
</script>
