<?php
// register.php
require_once 'includes/auth.php';

// Если пользователь уже авторизован
if (isLoggedIn()) {
    header('Location: /dashboard/');
    exit;
}

$error = '';
$success = false;

// Обработка формы регистрации
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $username = trim($_POST['username'] ?? '');
    
    // Валидация
    if (empty($email) || empty($password) || empty($username)) {
        $error = 'Заполните все поля';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Введите корректный email';
    } elseif (strlen($password) < 8) {
        $error = 'Пароль должен быть не менее 8 символов';
    } elseif ($password !== $password_confirm) {
        $error = 'Пароли не совпадают';
    } else {
        $result = registerUser($email, $password, $username);
        
        if ($result['success']) {
            $success = true;
            // Автоматический вход после регистрации
            loginUser($email, $password);
            header('Location: /dashboard/');
            exit;
        } else {
            $error = $result['error'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация - TeleAdm</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #0088cc;
            --primary-hover: #0077b3;
            --text-color: #000000;
            --text-secondary: #707579;
            --bg-color: #ffffff;
            --bg-secondary: #f4f4f5;
            --border-color: #e3e4e8;
            --error-color: #e53935;
            --success-color: #4caf50;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            color: var(--text-color);
            background-color: var(--bg-secondary);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-image: 
                radial-gradient(circle at 20% 50%, rgba(0, 136, 204, 0.03) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(0, 136, 204, 0.02) 0%, transparent 50%);
        }

        .auth-container {
            background: var(--bg-color);
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 400px;
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo a {
            font-size: 32px;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }

        h1 {
            font-size: 24px;
            font-weight: 400;
            text-align: center;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-size: 14px;
            color: var(--text-secondary);
        }

        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.2s;
        }

        input:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .btn-primary {
            width: 100%;
            background-color: var(--primary-color);
            color: white;
            padding: 14px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-error {
            background-color: #ffebee;
            color: var(--error-color);
            border: 1px solid #ffcdd2;
        }

        .alert-success {
            background-color: #e8f5e9;
            color: var(--success-color);
            border: 1px solid #c8e6c9;
        }

        .form-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: var(--text-secondary);
        }

        .form-footer a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .form-footer a:hover {
            text-decoration: underline;
        }

        .benefits {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid var(--border-color);
        }

        .benefits h3 {
            font-size: 16px;
            margin-bottom: 12px;
            text-align: center;
        }

        .benefit-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            font-size: 14px;
            color: var(--text-secondary);
        }

        .benefit-item::before {
            content: '✓';
            color: var(--success-color);
            margin-right: 8px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="logo">
            <a href="/">TeleAdm</a>
        </div>
        
        <h1>Создайте аккаунт</h1>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Имя пользователя</label>
                <input type="text" id="username" name="username" 
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                       required autofocus>
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" 
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                       required>
            </div>
            
            <div class="form-group">
                <label for="password">Пароль</label>
                <input type="password" id="password" name="password" 
                       placeholder="Минимум 8 символов" required>
            </div>
            
            <div class="form-group">
                <label for="password_confirm">Подтвердите пароль</label>
                <input type="password" id="password_confirm" name="password_confirm" required>
            </div>
            
            <button type="submit" class="btn-primary">Зарегистрироваться</button>
        </form>
        
        <div class="form-footer">
            Уже есть аккаунт? <a href="/login.php">Войдите</a>
        </div>
        
        <div class="benefits">
            <h3>Что вы получите:</h3>
            <div class="benefit-item">50 бесплатных размещений в месяц</div>
            <div class="benefit-item">Детальная аналитика рекламы</div>
            <div class="benefit-item">Расчет CPM и стоимости подписчика</div>
            <div class="benefit-item">Экспорт отчетов в Excel</div>
        </div>
    </div>
</body>
</html>