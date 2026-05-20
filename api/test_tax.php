<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../vendor/autoload.php';

use MIMA\Config\Database;

$user_id = 4;
$method = $_SERVER['REQUEST_METHOD'];
$response = ['success' => false, 'message' => 'Unknown error'];

try {
    $db = Database::getInstance()->getConnection();
    
    if ($method === 'GET') {
        // Get period from query string (default to current month)
        $period = isset($_GET['period']) ? $_GET['period'] : date('Y-m');
        $start_date = $period . '-01';
        $end_date = date('Y-m-t', strtotime($start_date));
        
        // Get transactions for the period
        $stmt = $db->prepare("
            SELECT t.*, p.name as product_name, p.selling_price, p.cost_price
            FROM inventory_transactions t
            JOIN products p ON t.product_id = p.id
            WHERE t.user_id = ? AND DATE(t.transaction_date) BETWEEN ? AND ?
        ");
        $stmt->execute([$user_id, $start_date, $end_date]);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate VAT totals
        $output_vat = 0; // VAT on sales (stock out)
        $input_vat = 0;  // VAT on purchases (stock in)
        
        foreach ($transactions as $t) {
            if ($t['type'] === 'out') {
                $output_vat += $t['vat_amount'];
            } else {
                $input_vat += $t['vat_amount'];
            }
        }
        
        $net_vat = $output_vat - $input_vat;
        
        $response = [
            'success' => true,
            'period' => $period,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'transactions' => $transactions,
            'summary' => [
                'output_vat' => $output_vat,
                'input_vat' => $input_vat,
                'net_vat' => $net_vat,
                'total_sales' => $output_vat / 0.075,
                'total_purchases' => $input_vat / 0.075
            ]
        ];
    }
} catch (Exception $e) {
    $response = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
}

echo json_encode($response);