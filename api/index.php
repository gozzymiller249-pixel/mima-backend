<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../vendor/autoload.php';

use MIMA\Config\Database;
use MIMA\Controllers\AuthController;

// Get the route from query string
$route = isset($_GET['route']) ? $_GET['route'] : '';

$response = ['success' => false, 'message' => 'Invalid endpoint'];

// Get JSON input for POST requests
$input = json_decode(file_get_contents('php://input'));

try {
    // Test endpoint
    if ($route === 'test') {
        $db = Database::getInstance()->getConnection();
        $response = ['success' => true, 'message' => 'API is working! Database connected.'];
    }
    // Register endpoint
    elseif ($route === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $auth = new AuthController();
        $response = $auth->register($input);
    }
    // Login endpoint
    elseif ($route === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $auth = new AuthController();
        $response = $auth->login($input);
    }
    else {
        $response = ['success' => false, 'message' => 'Endpoint not found: ' . $route];
    }
} catch (Exception $e) {
    $response = ['success' => false, 'message' => 'Server error: ' . $e->getMessage()];
}

echo json_encode($response);