<?php
// login.php
require_once 'includes/auth.php';

// Если пользователь уже авторизован
if (isLoggedIn()) {
    header('Location: /dashboard/');
    exit;
}

$error = '';

// Обработка формы входа
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Введите email и пароль';
    } else {
        $result = loginUser($email, $password);
        
        if ($result['success']) {
            // Перенаправляем в зависимости от роли
            if ($_SESSION['user_role'] === 'admin') {
                header('Location: /admin/');
            } else {
                header('Location: /dashboard/');
            }
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
    <title>Вход - TeleAdm</title>
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

        .demo-credentials {
            background-color: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 8px;
            padding: 16px;
            margin-top: 20px;
            font-size: 14px;
        }

        .demo-credentials h4 {
            margin-bottom: 8px;
            font-size: 14px;
        }

        .demo-credentials code {
            background-color: rgba(0, 0, 0, 0.05);
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="logo">
            <a href="/">TeleAdm</a>
        </div>
        
        <h1>Вход в систему</h1>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" 
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                       required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">Пароль</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn-primary">Войти</button>
        </form>
        
        <div class="form-footer">
            Нет аккаунта? <a href="/register.php">Зарегистрируйтесь</a>
        </div>
        
        <!-- Временно для тестирования -->
        <div class="demo-credentials">
            <h4>🔐 Тестовые данные:</h4>
            <p>Админ: <code>admin@teleadm.ru</code></p>
            <p>Пароль нужно будет создать через phpMyAdmin</p>
        </div>
    </div>
</body>
</html>