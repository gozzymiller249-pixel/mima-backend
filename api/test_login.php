<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS Headers
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../vendor/autoload.php';

use MIMA\Config\Database;

$data = json_decode(file_get_contents('php://input'));

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'No data received']);
    exit();
}

try {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("SELECT * FROM users WHERE phone = ?");
    $stmt->execute([$data->phone]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }
    
    if (!password_verify($data->password, $user['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid password']);
        exit();
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user' => [
            'id' => $user['id'],
            'phone' => $user['phone'],
            'business_name' => $user['business_name']
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}