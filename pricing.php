<?php
require_once 'includes/auth.php';
$currentUser = isLoggedIn() ? getCurrentUser() : null;
$premiumPrice = 299;
$hasActiveSubscription = false;
$subscriptionEndDate = null;
if ($currentUser && $currentUser['role'] === 'premium') {
    $stmt = db()->prepare("SELECT end_date FROM subscriptions WHERE user_id = ? AND is_active = TRUE AND type = 'premium' ORDER BY end_date DESC LIMIT 1");
    $stmt->execute([$currentUser['id']]);
    $subscription = $stmt->fetch();
    if ($subscription) {
        $hasActiveSubscription = true;
        $subscriptionEndDate = $subscription['end_date'];
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Тарифы — TeleAdm</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap');
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #eaf1fb 0%, #dbeafe 100%);
            color: #1e293b;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }
        /* Анимированный фон всей страницы */
        body::before {
            content: "";
            position: fixed;
            left: 0; top: 0; width: 100vw; height: 100vh;
            z-index: 0;
            background: radial-gradient(circle at 80% 10%, #2563eb22 0, transparent 60%),
                        radial-gradient(circle at 20% 80%, #3b82f622 0, transparent 60%),
                        linear-gradient(135deg, #eaf1fb 0%, #dbeafe 100%);
            background-size: 200% 200%;
            animation: gradientBG 16s ease-in-out infinite alternate;
            opacity: .7;
            pointer-events: none;
        }
        @keyframes gradientBG {
            0% {background-position: 0% 50%;}
            50% {background-position: 100% 50%;}
            100% {background-position: 0% 50%;}
        }
        /* Header */
        .header {
            position: sticky;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background: rgba(255,255,255,0.97);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid #e2e8f0;
            height: 70px;
            display: flex;
            align-items: center;
        }
        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 70px;
        }
        .logo {
            font-size: 28px;
            font-weight: 800;
            background: linear-gradient(135deg, #2563eb, #3b82f6 80%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-decoration: none;
            letter-spacing: -0.025em;
        }
        .nav {
            display: flex;
            align-items: center;
            gap: 32px;
        }
        .nav-links {
            display: flex;
            list-style: none;
            gap: 32px;
            align-items: center;
        }
        .nav-link {
            color: #64748b;
            text-decoration: none !important;
            font-weight: 500;
            font-size: 15px;
            transition: color 0.2s;
            position: relative;
        }
        .nav-link:hover { color: #2563eb; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 22px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: background 0.2s, color 0.2s, box-shadow 0.2s;
            text-decoration: none !important;
        }
        .btn-primary {
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: #fff;
            box-shadow: 0 2px 8px 0 rgb(59 130 246 / 0.08);
        }
        .btn-primary:hover {
            background: #1d4ed8;
            box-shadow: 0 8px 24px 0 rgb(59 130 246 / 0.14);
        }
        /* Блок "Тарифы" с анимацией и волной */
        .pricing-hero {
            position: relative;
            z-index: 1;
            text-align: center;
            padding: 90px 16px 60px;
            color: #fff;
            background: linear-gradient(120deg, #2563eb 0%, #3b82f6 80%, #6366f1 100%);
            overflow: hidden;
        }
        .pricing-hero h1 {
            font-size: 2.4rem;
            font-weight: 800;
            margin-bottom: 12px;
            letter-spacing: -0.02em;
            text-shadow: 0 2px 8px rgba(37,99,235,0.13);
            animation: fadeInUp 1.2s cubic-bezier(.4,0,.2,1);
        }
        .pricing-hero p {
            font-size: 1.15rem;
            opacity: 0.96;
            margin-bottom: 0;
            animation: fadeInUp 1.5s cubic-bezier(.4,0,.2,1);
        }
        .wave {
            position: absolute;
            left: 0; right: 0; bottom: -1px;
            width: 100%; height: 80px;
            z-index: 2;
            pointer-events: none;
            animation: waveAnim 8s linear infinite alternate;
        }
        @keyframes waveAnim {
            0% {transform: translateX(0);}
            100% {transform: translateX(-40px);}
        }
        /* Тарифы */
        .tariffs {
            display: flex;
            gap: 36px;
            justify-content: center;
            margin: 40px auto 0;
            max-width: 800px;
            flex-wrap: nowrap;
            position: relative;
            z-index: 2;
        }
        .tariff-card {
            background: #fff;
            border: 1.5px solid #e2e8f0;
            border-radius: 20px;
            padding: 38px 28px 30px;
            box-shadow: 0 8px 32px 0 rgb(37 99 235 / 0.12);
            width: 340px;
            min-width: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            transition: box-shadow 0.3s, border 0.3s, transform 0.3s, background 0.3s;
            opacity: 0;
            transform: translateY(40px) scale(0.97);
            animation: cardAppear 1.1s cubic-bezier(.4,0,.2,1) forwards;
        }
        .tariff-card:nth-child(2) { animation-delay: 0.25s; }
        @keyframes cardAppear {
            0% { opacity: 0; transform: translateY(40px) scale(0.97);}
            80% { opacity: 1; transform: translateY(-8px) scale(1.03);}
            100% { opacity: 1; transform: none;}
        }
        .tariff-card.premium {
            border: 2px solid #2563eb;
            background: linear-gradient(135deg, #f1f5f9 60%, #dbeafe 100%);
            box-shadow: 0 12px 48px 0 rgb(37 99 235 / 0.18);
        }
        .tariff-card:hover {
            box-shadow: 0 16px 48px 0 rgb(37 99 235 / 0.22);
            transform: translateY(-6px) scale(1.04);
            border-color: #3b82f6;
        }
        .tariff-badge {
            position: absolute;
            top: -18px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: #fff;
            padding: 6px 28px;
            border-radius: 16px;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 8px 0 rgb(37 99 235 / 0.12);
        }
        .tariff-title {
            font-size: 23px;
            font-weight: 700;
            margin-bottom: 10px;
            letter-spacing: -0.01em;
        }
        .tariff-price {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 8px;
            color: #2563eb;
        }
        .tariff-period {
            font-size: 15px;
            color: #64748b;
            margin-bottom: 18px;
        }
        .tariff-features {
            list-style: none;
            margin: 0 0 28px 0;
            padding: 0;
            width: 100%;
        }
        .tariff-features li {
            font-size: 16px;
            color: #1e293b;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .tariff-features li.disabled {
            color: #b6bcc7;
            text-decoration: line-through;
        }
        .tariff-features li::before {
            content: '✓';
            color: #10b981;
            font-weight: bold;
            font-size: 17px;
        }
        .tariff-features li.disabled::before {
            content: '—';
            color: #b6bcc7;
        }
        .tariff-btn {
            width: 100%;
            margin-top: auto;
            font-size: 18px;
            font-weight: 700;
            padding: 17px 0;
            border-radius: 12px;
            border: none;
            background: linear-gradient(135deg, #2563eb, #3b82f6 70%);
            color: #fff;
            box-shadow: 0 2px 8px 0 rgb(59 130 246 / 0.12);
            cursor: pointer;
            transition: background 0.2s, box-shadow 0.2s, transform 0.2s;
            letter-spacing: 0.01em;
            position: relative;
            overflow: hidden;
            font-family: inherit;
            text-decoration: none !important;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
        }
        .tariff-btn:after {
            content: '';
            position: absolute;
            left: 0; top: 0; right: 0; bottom: 0;
            background: linear-gradient(90deg,rgba(255,255,255,0.18) 0%,rgba(255,255,255,0.04) 100%);
            opacity: 0;
            transition: opacity 0.2s;
        }
        .tariff-btn:hover:after { opacity: 1; }
        .tariff-btn:hover {
            background: linear-gradient(135deg, #1d4ed8, #2563eb 70%);
            transform: translateY(-2px) scale(1.04);
            box-shadow: 0 8px 24px 0 rgb(59 130 246 / 0.14);
            text-decoration: none !important;
        }
        .tariff-btn.current {
            background: #10b981;
            color: #fff;
            cursor: default;
            box-shadow: none;
        }
        .tariff-btn:visited, .tariff-btn:active, .tariff-btn:focus {
            text-decoration: none !important;
        }
        .premium-active {
            max-width:600px;
            margin:32px auto 0;
            background:linear-gradient(90deg,#10b981,#059669);
            color:#fff;
            padding:14px 24px;
            border-radius:14px;
            text-align:center;
            font-size:16px;
            font-weight:600;
            box-shadow: 0 2px 8px 0 rgb(16 185 129 / 0.10);
        }
        .footer {
            background: #0f172a;
            color: #fff;
            text-align: center;
            padding: 36px 0 18px;
            margin-top: 64px;
            font-size: 15px;
        }
        .footer a { color: #fff; opacity: 0.7; text-decoration: none; }
        .footer a:hover { opacity: 1; }
        @media (max-width: 900px) {
            .tariffs { flex-direction: column; align-items: center; gap: 28px; }
            .tariff-card { width: 100%; max-width: 400px; }
        }
        @media (max-width:600px) {
            .header-container { padding: 0 10px; }
            .pricing-hero { padding: 56px 8px 22px; }
            .tariffs { gap: 14px; }
            .tariff-card { padding: 20px 8px 14px; }
        }
    </style>
</head>
<body>
    <header class="header" id="header">
        <div class="header-container">
            <a href="/" class="logo">TeleAdm</a>
            <nav class="nav">
                <ul class="nav-links">
                    <li><a href="/" class="nav-link">Главная</a></li>
                    <li><a href="/pricing.php" class="nav-link">Тарифы</a></li>
                    <?php if (isLoggedIn()): ?>
                        <li><a href="/dashboard/" class="nav-link">Кабинет</a></li>
                        <li><a href="/logout.php" class="btn btn-primary">Выйти</a></li>
                    <?php else: ?>
                        <li><a href="/login.php" class="nav-link">Войти</a></li>
                        <li><a href="/register.php" class="btn btn-primary">Регистрация</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <section class="pricing-hero" id="pricing-title">
        <h1>Тарифы для роста вашего Telegram</h1>
        <p>Бесплатно для старта. Премиум — для максимальных возможностей.<br>Выберите свой путь к эффективной рекламе!</p>
        <svg class="wave" viewBox="0 0 1440 80" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path fill="#f8fafc" fill-opacity="1" d="M0,48 C480,120 960,0 1440,48 L1440,80 L0,80 Z"></path>
        </svg>
    </section>
    <?php if ($hasActiveSubscription): ?>
    <div class="premium-active">
        🌟 У вас активна Premium подписка до <?php echo date('d.m.Y', strtotime($subscriptionEndDate)); ?>
    </div>
    <?php endif; ?>
    <div class="tariffs">
        <div class="tariff-card" style="animation-delay:0.05s;">
            <div class="tariff-title">Базовый</div>
            <div class="tariff-price">0 ₽</div>
            <div class="tariff-period">Навсегда</div>
            <ul class="tariff-features">
                <li>3 кампании</li>
                <li>50 размещений/мес</li>
                <li>Базовая аналитика</li>
                <li>CPM и цена подписчика</li>
                <li class="disabled">Экспорт в Excel/PDF</li>
                <li class="disabled">Графики и сравнения</li>
                <li class="disabled">API и поддержка 24/7</li>
            </ul>
            <?php if ($currentUser && $currentUser['role'] === 'user'): ?>
                <button class="tariff-btn current">Ваш тариф</button>
            <?php elseif (!$currentUser): ?>
                <a href="/register.php" class="tariff-btn">Начать бесплатно</a>
            <?php endif; ?>
        </div>
        <div class="tariff-card premium" style="animation-delay:0.28s;">
            <div class="tariff-badge">Рекомендуем</div>
            <div class="tariff-title">Premium</div>
            <div class="tariff-price"><?php echo $premiumPrice; ?> ₽</div>
            <div class="tariff-period">в месяц</div>
            <ul class="tariff-features">
                <li>Безлимит кампаний</li>
                <li>Безлимит размещений</li>
                <li>Расширенная аналитика</li>
                <li>Экспорт в Excel и PDF</li>
                <li>Графики, сравнения, ROI</li>
                <li>API и поддержка 24/7</li>
            </ul>
            <?php if ($currentUser && $currentUser['role'] === 'premium'): ?>
                <button class="tariff-btn current">Ваш тариф</button>
            <?php elseif ($currentUser): ?>
                <a href="/dashboard/upgrade.php" class="tariff-btn">Обновить</a>
            <?php else: ?>
                <a href="/register.php" class="tariff-btn">Попробовать Premium</a>
            <?php endif; ?>
        </div>
    </div>
    <footer class="footer">
        <div style="font-weight:600;font-size:18px;margin-bottom:7px;">TeleAdm</div>
        <div style="margin-bottom:8px;opacity:0.8;">Аналитика рекламы в Telegram. Экономьте и растите быстрее.</div>
        <div style="font-size:13px;opacity:0.6;">&copy; 2025 TeleAdm</div>
    </footer>
    <script>
        // Анимация карточек тарифов
        window.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.tariff-card').forEach(function(card, i){
                setTimeout(function(){ card.style.opacity = '1'; card.style.transform = 'none'; }, 200 + i*120);
            });
        });
    </script>
</body>
</html>
