<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../vendor/autoload.php';

use MIMA\Config\Database;

$user_id = 4; // Your user ID
$method = $_SERVER['REQUEST_METHOD'];
$response = ['success' => false, 'message' => 'Unknown error'];

try {
    $db = Database::getInstance()->getConnection();
    
    if ($method === 'GET') {
        // Get all transactions for this user
        $stmt = $db->prepare("
            SELECT t.*, p.name as product_name 
            FROM inventory_transactions t
            JOIN products p ON t.product_id = p.id
            WHERE t.user_id = ?
            ORDER BY t.transaction_date DESC
        ");
        $stmt->execute([$user_id]);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $response = ['success' => true, 'transactions' => $transactions];
    }
    elseif ($method === 'POST') {
        // Add new transaction (stock in or out)
        $input = json_decode(file_get_contents('php://input'));
        
        if (!$input || !isset($input->product_id) || !isset($input->type) || !isset($input->quantity)) {
            $response = ['success' => false, 'message' => 'Product ID, type, and quantity are required'];
        } elseif (!in_array($input->type, ['in', 'out'], true) || !is_numeric($input->quantity) || (int)$input->quantity <= 0) {
            $response = ['success' => false, 'message' => 'Type must be in or out, and quantity must be a positive whole number'];
        } else {
            // Get product current stock and price
            $stmt = $db->prepare("SELECT current_stock, selling_price, cost_price FROM products WHERE id = ? AND user_id = ?");
            $stmt->execute([$input->product_id, $user_id]);
            $product = $stmt->fetch();
            
            if (!$product) {
                $response = ['success' => false, 'message' => 'Product not found'];
            } else {
                // Calculate unit price and VAT
                if ($input->type === 'in') {
                    $unit_price = $input->unit_price ?? $product['cost_price'];
                    $new_stock = $product['current_stock'] + $input->quantity;
                    $vat_amount = $unit_price * $input->quantity * 0.075;
                } else {
                    $unit_price = $input->unit_price ?? $product['selling_price'];
                    $new_stock = $product['current_stock'] - $input->quantity;
                    $vat_amount = $unit_price * $input->quantity * 0.075;
                    
                    if ($new_stock < 0) {
                        $response = ['success' => false, 'message' => 'Insufficient stock'];
                        echo json_encode($response);
                        exit();
                    }
                }
                
                // Insert transaction
                $stmt = $db->prepare("
                    INSERT INTO inventory_transactions (user_id, product_id, type, quantity, unit_price, vat_amount) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $result = $stmt->execute([$user_id, $input->product_id, $input->type, $input->quantity, $unit_price, $vat_amount]);
                
                if ($result) {
                    // Update product stock
                    $stmt = $db->prepare("UPDATE products SET current_stock = ? WHERE id = ? AND user_id = ?");
                    $stmt->execute([$new_stock, $input->product_id, $user_id]);
                    
                    $response = [
                        'success' => true, 
                        'message' => 'Transaction recorded successfully',
                        'new_stock' => $new_stock,
                        'vat_amount' => $vat_amount
                    ];
                } else {
                    $response = ['success' => false, 'message' => 'Failed to record transaction'];
                }
            }
        }
    }
} catch (Exception $e) {
    $response = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
}

echo json_encode($response);