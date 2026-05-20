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
    
    $stmt = $db->prepare("SELECT id FROM users WHERE phone = ?");
    $stmt->execute([$data->phone]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        echo json_encode(['success' => false, 'message' => 'Phone already registered']);
        exit();
    }
    
    $password_hash = password_hash($data->password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO users (phone, business_name, password_hash) VALUES (?, ?, ?)");
    $result = $stmt->execute([$data->phone, $data->business_name, $password_hash]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'User registered successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Registration failed']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}