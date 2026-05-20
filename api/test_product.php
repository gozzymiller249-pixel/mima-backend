<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../vendor/autoload.php';

use MIMA\Config\Database;

$user_id = 4;  // Your user ID

$method = $_SERVER['REQUEST_METHOD'];
$response = ['success' => false, 'message' => 'Unknown error'];

try {
    $db = Database::getInstance()->getConnection();
    
    if ($method === 'GET') {
        // Get all products
        $stmt = $db->prepare("SELECT id, name, category, current_stock as quantity, selling_price, cost_price, reorder_threshold, barcode FROM products WHERE user_id = ? ORDER BY id DESC");
        $stmt->execute([$user_id]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $response = ['success' => true, 'products' => $products];
    }
    elseif ($method === 'POST') {
        // Add new product
        $input = json_decode(file_get_contents('php://input'));
        
        if (!$input || !isset($input->name) || !isset($input->selling_price)) {
            $response = ['success' => false, 'message' => 'Product name and selling price are required'];
        } else {
            // Generate a unique barcode
            $barcode = 'BRC' . time() . rand(1000, 9999) . rand(100, 999);
            
            $stmt = $db->prepare("
                INSERT INTO products (user_id, name, category, current_stock, selling_price, cost_price, reorder_threshold, barcode) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $result = $stmt->execute([
                $user_id,
                $input->name,
                $input->category ?? '',
                $input->quantity ?? 0,
                $input->selling_price,
                $input->cost_price ?? 0,
                $input->reorder_level ?? 10,
                $barcode
            ]);
            
            if ($result) {
                $response = ['success' => true, 'message' => 'Product added successfully'];
            } else {
                $response = ['success' => false, 'message' => 'Database insert failed'];
            }
        }
    }
    elseif ($method === 'DELETE') {
        // Delete product
        $id = isset($_GET['id']) ? $_GET['id'] : null;
        if ($id) {
            $stmt = $db->prepare("DELETE FROM products WHERE id = ? AND user_id = ?");
            $result = $stmt->execute([$id, $user_id]);
            $response = ['success' => true, 'message' => 'Product deleted'];
        } else {
            $response = ['success' => false, 'message' => 'Product ID required'];
        }
    }
    else {
        $response = ['success' => false, 'message' => 'Method not supported'];
    }
} catch (Exception $e) {
    $response = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
}

echo json_encode($response);