<?php
require_once 'includes/auth.php';
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TeleAdm - Профессиональная аналитика рекламы в Telegram</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

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
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 10px 10px -5px rgb(0 0 0 / 0.04);
            --radius-md: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--text-primary);
            background: var(--bg-primary);
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* Header */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
            transition: all 0.3s ease;
        }

        .header.scrolled {
            background: rgba(255, 255, 255, 0.98);
            box-shadow: var(--shadow-lg);
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 70px;
        }

        .logo {
            font-size: 28px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
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
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            font-size: 15px;
            transition: all 0.2s ease;
            position: relative;
        }

        .nav-link:hover {
            color: var(--primary);
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--primary);
            transition: width 0.3s ease;
        }

        .nav-link:hover::after {
            width: 100%;
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
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            white-space: nowrap;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            box-shadow: var(--shadow-md);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-secondary {
            background: var(--bg-primary);
            color: var(--primary);
            border: 2px solid var(--border);
        }

        .btn-secondary:hover {
            border-color: var(--primary);
            background: var(--bg-secondary);
        }

        /* Hero Section */
        .hero {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 50%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 255, 255, 0.08) 0%, transparent 50%),
                radial-gradient(circle at 40% 80%, rgba(255, 255, 255, 0.06) 0%, transparent 50%);
        }

        .hero-bg-elements {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            overflow: hidden;
        }

        .floating-element {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }

        .floating-element:nth-child(1) {
            width: 80px;
            height: 80px;
            top: 20%;
            left: 10%;
            animation-delay: 0s;
        }

        .floating-element:nth-child(2) {
            width: 120px;
            height: 120px;
            top: 60%;
            right: 15%;
            animation-delay: 2s;
        }

        .floating-element:nth-child(3) {
            width: 60px;
            height: 60px;
            bottom: 20%;
            left: 20%;
            animation-delay: 4s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        .hero-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
            position: relative;
            z-index: 2;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 80px;
            align-items: center;
        }

        .hero-content {
            color: white;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 24px;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .hero-title {
            font-size: 56px;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 24px;
            letter-spacing: -0.025em;
        }

        .hero-subtitle {
            font-size: 20px;
            opacity: 0.9;
            margin-bottom: 40px;
            line-height: 1.6;
        }

        .hero-buttons {
            display: flex;
            gap: 16px;
            margin-bottom: 40px;
        }

        .btn-large {
            padding: 16px 32px;
            font-size: 16px;
            font-weight: 600;
        }

        .hero-stats {
            display: flex;
            gap: 32px;
        }

        .hero-stat {
            text-align: center;
        }

        .hero-stat-number {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .hero-stat-label {
            font-size: 14px;
            opacity: 0.8;
        }

        .hero-visual {
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .hero-dashboard {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--radius-xl);
            padding: 24px;
            box-shadow: var(--shadow-xl);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transform: perspective(1000px) rotateY(-5deg) rotateX(5deg);
            transition: transform 0.3s ease;
        }

        .hero-dashboard:hover {
            transform: perspective(1000px) rotateY(0deg) rotateX(0deg);
        }

        .dashboard-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
        }

        .dashboard-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .dashboard-info h4 {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 2px;
        }

        .dashboard-info p {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .dashboard-metrics {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 20px;
        }

        .metric-card {
            background: var(--bg-secondary);
            padding: 16px;
            border-radius: var(--radius-md);
            text-align: center;
        }

        .metric-value {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 4px;
        }

        .metric-label {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .dashboard-chart {
            height: 80px;
            background: var(--bg-secondary);
            border-radius: var(--radius-md);
            position: relative;
            overflow: hidden;
        }

        .chart-line {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 60%;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            clip-path: polygon(0 100%, 0 60%, 20% 40%, 40% 50%, 60% 20%, 80% 30%, 100% 10%, 100% 100%);
            opacity: 0.8;
        }

        /* Problems Section */
        .problems {
            padding: 120px 0;
            background: var(--bg-primary);
            position: relative;
        }

        .problems::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--border), transparent);
        }

        .problems-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
            text-align: center;
        }

        .problems-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: var(--error);
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 24px;
        }

        .problems-title {
            font-size: 48px;
            font-weight: 800;
            margin-bottom: 24px;
            line-height: 1.2;
        }

        .problems-subtitle {
            font-size: 20px;
            color: var(--text-secondary);
            margin-bottom: 60px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .problems-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 40px;
            margin-bottom: 60px;
        }

        .problem-card {
            background: var(--bg-secondary);
            padding: 32px 24px;
            border-radius: var(--radius-xl);
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid var(--border);
        }

        .problem-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-xl);
        }

        .problem-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 24px;
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
        }

        .problem-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 12px;
            color: var(--text-primary);
        }

        .problem-description {
            color: var(--text-secondary);
            line-height: 1.6;
        }

        .problem-stat {
            font-size: 36px;
            font-weight: 800;
            color: var(--error);
            margin-bottom: 8px;
        }

        /* Solution Section */
        .solution {
            padding: 120px 0;
            background: linear-gradient(135deg, var(--bg-secondary), var(--bg-tertiary));
            position: relative;
        }

        .solution-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
        }

        .solution-content {
            text-align: center;
            margin-bottom: 80px;
        }

        .solution-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: var(--success);
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 24px;
        }

        .solution-title {
            font-size: 48px;
            font-weight: 800;
            margin-bottom: 24px;
            line-height: 1.2;
        }

        .solution-subtitle {
            font-size: 20px;
            color: var(--text-secondary);
            margin-bottom: 40px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 32px;
        }

        .feature-card {
            background: var(--bg-primary);
            padding: 32px 24px;
            border-radius: var(--radius-xl);
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .feature-card:hover::before {
            transform: scaleX(1);
        }

        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-xl);
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 24px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: white;
        }

        .feature-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 12px;
            color: var(--text-primary);
        }

        .feature-description {
            color: var(--text-secondary);
            line-height: 1.6;
        }

        /* Social Proof */
        .social-proof {
            padding: 120px 0;
            background: var(--bg-primary);
        }

        .social-proof-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
            text-align: center;
        }

        .social-proof-title {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 60px;
            color: var(--text-primary);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 40px;
            margin-bottom: 80px;
        }

        .stat-card {
            text-align: center;
        }

        .stat-number {
            font-size: 48px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
        }

        .stat-label {
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* CTA Section */
        .cta {
            padding: 120px 0;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .cta::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 50%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 255, 255, 0.08) 0%, transparent 50%);
        }

        .cta-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 24px;
            position: relative;
            z-index: 2;
        }

        .cta-title {
            font-size: 48px;
            font-weight: 800;
            margin-bottom: 24px;
            line-height: 1.2;
        }

        .cta-subtitle {
            font-size: 20px;
            opacity: 0.9;
            margin-bottom: 40px;
            line-height: 1.6;
        }

        .cta-buttons {
            display: flex;
            gap: 16px;
            justify-content: center;
            margin-bottom: 40px;
        }

        .btn-white {
            background: white;
            color: var(--primary);
        }

        .btn-white:hover {
            background: var(--bg-secondary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .cta-guarantee {
            font-size: 14px;
            opacity: 0.8;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        /* Footer */
        .footer {
            background: var(--text-primary);
            color: white;
            padding: 60px 0 30px;
        }

        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
        }

        .footer-content {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 60px;
            margin-bottom: 40px;
        }

        .footer-brand h3 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 16px;
        }

        .footer-brand p {
            opacity: 0.8;
            line-height: 1.6;
        }

        .footer-links h4 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 16px;
        }

        .footer-links ul {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 8px;
        }

        .footer-links a {
            color: white;
            text-decoration: none;
            opacity: 0.8;
            transition: opacity 0.2s;
        }

        .footer-links a:hover {
            opacity: 1;
        }

        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 30px;
            text-align: center;
            opacity: 0.6;
        }

        /* Mobile Menu */
        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            color: var(--text-primary);
            cursor: pointer;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .mobile-menu-toggle {
                display: block;
            }

            .nav-links {
                display: none;
            }

            .hero-container {
                grid-template-columns: 1fr;
                gap: 40px;
                text-align: center;
            }

            .hero-title {
                font-size: 36px;
            }

            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }

            .hero-stats {
                justify-content: center;
            }

            .problems-grid,
            .features-grid {
                grid-template-columns: 1fr;
                gap: 24px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 24px;
            }

            .footer-content {
                grid-template-columns: 1fr;
                gap: 40px;
            }

            .problems-title,
            .solution-title,
            .cta-title {
                font-size: 32px;
            }
        }

        /* Animations */
        .fade-in {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.6s ease;
        }

        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .scale-in {
            opacity: 0;
            transform: scale(0.8);
            transition: all 0.6s ease;
        }

        .scale-in.visible {
            opacity: 1;
            transform: scale(1);
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header" id="header">
        <div class="header-container">
            <a href="/" class="logo">TeleAdm</a>
            <nav class="nav">
                <ul class="nav-links">
                    <li><a href="#features" class="nav-link">Возможности</a></li>
                    <li><a href="/pricing.php" class="nav-link">Тарифы</a></li>
                    <?php if (isLoggedIn()): ?>
                        <?php if (isAdmin()): ?>
                            <li><a href="/admin/" class="nav-link">Админ-панель</a></li>
                        <?php else: ?>
                            <li><a href="/dashboard/" class="nav-link">Личный кабинет</a></li>
                        <?php endif; ?>
                        <li><a href="/logout.php" class="btn btn-primary">Выйти</a></li>
                    <?php else: ?>
                        <li><a href="/login.php" class="nav-link">Войти</a></li>
                        <li><a href="/register.php" class="btn btn-primary">Начать бесплатно</a></li>
                    <?php endif; ?>
                </ul>
                <button class="mobile-menu-toggle">☰</button>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-bg-elements">
            <div class="floating-element"></div>
            <div class="floating-element"></div>
            <div class="floating-element"></div>
        </div>
        <div class="hero-container">
            <div class="hero-content">
                <div class="hero-badge">
                    <span>🚀</span>
                    Революция в аналитике рекламы
                </div>
                <h1 class="hero-title">Превратите рекламу в Telegram в прибыльный бизнес</h1>
                <p class="hero-subtitle">Профессиональная аналитика, которая поможет вам экономить до 60% рекламного бюджета и увеличить ROI в 3 раза</p>
                <div class="hero-buttons">
                    <a href="/register.php" class="btn btn-white btn-large">Попробовать бесплатно</a>
                    <a href="#features" class="btn btn-secondary btn-large">Узнать больше</a>
                </div>
                <div class="hero-stats">
                    <div class="hero-stat">
                        <div class="hero-stat-number">2,847</div>
                        <div class="hero-stat-label">Довольных клиентов</div>
                    </div>
                    <div class="hero-stat">
                        <div class="hero-stat-number">₽12.5М</div>
                        <div class="hero-stat-label">Сэкономлено</div>
                    </div>
                    <div class="hero-stat">
                        <div class="hero-stat-number">3.2x</div>
                        <div class="hero-stat-label">Рост ROI</div>
                    </div>
                </div>
            </div>
            <div class="hero-visual">
                <div class="hero-dashboard">
                    <div class="dashboard-header">
                        <div class="dashboard-avatar">А</div>
                        <div class="dashboard-info">
                            <h4>Алексей Петров</h4>
                            <p>Админ канала @crypto_news</p>
                        </div>
                    </div>
                    <div class="dashboard-metrics">
                        <div class="metric-card">
                            <div class="metric-value">₽45,230</div>
                            <div class="metric-label">Потрачено</div>
                        </div>
                        <div class="metric-card">
                            <div class="metric-value">1,247</div>
                            <div class="metric-label">Подписчиков</div>
                        </div>
                        <div class="metric-card">
                            <div class="metric-value">₽36.2</div>
                            <div class="metric-label">Цена подписчика</div>
                        </div>
                        <div class="metric-card">
                            <div class="metric-value">₽127</div>
                            <div class="metric-label">CPM</div>
                        </div>
                    </div>
                    <div class="dashboard-chart">
                        <div class="chart-line"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Problems Section -->
    <section class="problems">
        <div class="problems-container">
            <div class="problems-badge">
                <span>⚠️</span>
                Проблема
            </div>
            <h2 class="problems-title fade-in">87% рекламодателей в Telegram теряют деньги</h2>
            <p class="problems-subtitle fade-in">Большинство админов не знают реальную эффективность своей рекламы и переплачивают в разы</p>
            
            <div class="problems-grid">
                <div class="problem-card fade-in">
                    <div class="problem-icon">😵</div>
                    <div class="problem-stat">₽156</div>
                    <h3 class="problem-title">Переплата за подписчика</h3>
                    <p class="problem-description">Средняя переплата из-за отсутствия аналитики и контроля эффективности</p>
                </div>
                <div class="problem-card fade-in">
                    <div class="problem-icon">📉</div>
                    <div class="problem-stat">43%</div>
                    <h3 class="problem-title">Каналов в минусе</h3>
                    <p class="problem-description">Почти половина каналов работает убыточно из-за неэффективной рекламы</p>
                </div>
                <div class="problem-card fade-in">
                    <div class="problem-icon">💸</div>
                    <div class="problem-stat">₽2.3М</div>
                    <h3 class="problem-title">Потери в месяц</h3>
                    <p class="problem-description">Общие потери рынка из-за неэффективного размещения рекламы</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Solution Section -->
    <section class="solution" id="features">
        <div class="solution-container">
            <div class="solution-content">
                <div class="solution-badge">
                    <span>✅</span>
                    Решение
                </div>
                <h2 class="solution-title fade-in">TeleAdm решает все проблемы рекламы в Telegram</h2>
                <p class="solution-subtitle fade-in">Профессиональная аналитическая платформа для максимизации ROI ваших рекламных кампаний</p>
            </div>
            
            <div class="features-grid">
                <div class="feature-card scale-in">
                    <div class="feature-icon">📊</div>
                    <h3 class="feature-title">Детальная аналитика</h3>
                    <p class="feature-description">Отслеживайте CPM, стоимость подписчика, конверсии и ROI в реальном времени с точностью до копейки</p>
                </div>
                <div class="feature-card scale-in">
                    <div class="feature-icon">💰</div>
                    <h3 class="feature-title">Контроль бюджета</h3>
                    <p class="feature-description">Управляйте расходами по кампаниям, устанавливайте лимиты и получайте уведомления о превышении</p>
                </div>
                <div class="feature-card scale-in">
                    <div class="feature-icon">🎯</div>
                    <h3 class="feature-title">Поиск лучших каналов</h3>
                    <p class="feature-description">Сравнивайте эффективность площадок и находите самые выгодные каналы для размещения</p>
                </div>
                <div class="feature-card scale-in">
                    <div class="feature-icon">📈</div>
                    <h3 class="feature-title">Прогнозирование</h3>
                    <p class="feature-description">ИИ-алгоритмы предсказывают эффективность размещений и рекомендуют оптимальные стратегии</p>
                </div>
                <div class="feature-card scale-in">
                    <div class="feature-icon">📋</div>
                    <h3 class="feature-title">Автоматические отчеты</h3>
                    <p class="feature-description">Получайте красивые отчеты в Excel и PDF с графиками, таблицами и рекомендациями</p>
                </div>
                <div class="feature-card scale-in">
                    <div class="feature-icon">🔒</div>
                    <h3 class="feature-title">Безопасность данных</h3>
                    <p class="feature-description">Ваши данные защищены банковским шифрованием и доступны только вам</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Social Proof -->
    <section class="social-proof">
        <div class="social-proof-container">
            <h2 class="social-proof-title fade-in">Результаты говорят сами за себя</h2>
            <div class="stats-grid">
                <div class="stat-card fade-in">
                    <div class="stat-number">2,847</div>
                    <div class="stat-label">Активных пользователей</div>
                </div>
                <div class="stat-card fade-in">
                    <div class="stat-number">₽12.5М</div>
                    <div class="stat-label">Сэкономлено клиентам</div>
                </div>
                <div class="stat-card fade-in">
                    <div class="stat-number">3.2x</div>
                    <div class="stat-label">Средний рост ROI</div>
                </div>
                <div class="stat-card fade-in">
                    <div class="stat-number">98%</div>
                    <div class="stat-label">Довольных клиентов</div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta">
        <div class="cta-container">
            <h2 class="cta-title fade-in">Начните экономить на рекламе уже сегодня</h2>
            <p class="cta-subtitle fade-in">Присоединяйтесь к 2,847 успешным рекламодателям, которые уже экономят с TeleAdm</p>
            <div class="cta-buttons">
                <a href="/register.php" class="btn btn-white btn-large">Начать бесплатно</a>
                <a href="/pricing.php" class="btn btn-secondary btn-large">Посмотреть тарифы</a>
            </div>
            <div class="cta-guarantee">
                <span>🛡️</span>
                14 дней бесплатно • Отмена в любой момент • Без обязательств
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-content">
                <div class="footer-brand">
                    <h3>TeleAdm</h3>
                    <p>Профессиональная аналитика рекламы в Telegram. Увеличивайте ROI и экономьте бюджет с помощью данных.</p>
                </div>
                <div class="footer-links">
                    <h4>Продукт</h4>
                    <ul>
                        <li><a href="#features">Возможности</a></li>
                        <li><a href="/pricing.php">Тарифы</a></li>
                        <li><a href="/demo.php">Демо</a></li>
                    </ul>
                </div>
                <div class="footer-links">
                    <h4>Поддержка</h4>
                    <ul>
                        <li><a href="/help.php">Помощь</a></li>
                        <li><a href="/contact.php">Контакты</a></li>
                        <li><a href="/api.php">API</a></li>
                    </ul>
                </div>
                <div class="footer-links">
                    <h4>Компания</h4>
                    <ul>
                        <li><a href="/about.php">О нас</a></li>
                        <li><a href="/privacy.php">Конфиденциальность</a></li>
                        <li><a href="/terms.php">Условия</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2025 TeleAdm. Все права защищены.</p>
            </div>
        </div>
    </footer>

    <script>
        // Header scroll effect
        window.addEventListener('scroll', () => {
            const header = document.getElementById('header');
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });

        // Smooth scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Intersection Observer for animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, observerOptions);

        // Observe all animation elements
        document.querySelectorAll('.fade-in, .scale-in').forEach(el => {
            observer.observe(el);
        });

        // Mobile menu toggle
        const mobileToggle = document.querySelector('.mobile-menu-toggle');
        const navLinks = document.querySelector('.nav-links');

        mobileToggle?.addEventListener('click', () => {
            navLinks.style.display = navLinks.style.display === 'flex' ? 'none' : 'flex';
        });
    </script>
</body>
</html>
