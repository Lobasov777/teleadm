<?php
// api/create-payment.php
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Проверяем авторизацию
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Получаем данные
$data = json_decode(file_get_contents('php://input'), true);
$amount = $data['amount'] ?? 0;
$userId = $data['user_id'] ?? $_SESSION['user_id'];

// Проверяем, что пользователь создает платеж для себя
if ($userId != $_SESSION['user_id'] && !isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

try {
    // Создаем платеж
    $stmt = db()->prepare("
        INSERT INTO payments (user_id, amount, currency, status, payment_method, created_at) 
        VALUES (?, ?, 'RUB', 'pending', 'manual', NOW())
    ");
    $stmt->execute([$userId, $amount]);
    
    $paymentId = db()->lastInsertId();
    
    // Генерируем уникальный ID транзакции
    $transactionId = 'TRX' . date('YmdHis') . $paymentId;
    
    $stmt = db()->prepare("UPDATE payments SET transaction_id = ? WHERE id = ?");
    $stmt->execute([$transactionId, $paymentId]);
    
    echo json_encode([
        'success' => true,
        'payment_id' => $paymentId,
        'transaction_id' => $transactionId,
        'message' => 'Платеж создан. Обратитесь к администратору для подтверждения оплаты.'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
?>