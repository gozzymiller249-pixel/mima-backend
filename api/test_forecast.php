<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
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

try {
    $db = Database::getInstance()->getConnection();
    
    if ($method === 'GET') {
        // Get demand forecast for all products
        $stmt = $db->prepare("
            SELECT p.id, p.name, p.category, p.current_stock, p.reorder_threshold,
                   COALESCE(SUM(CASE WHEN t.type = 'out' THEN t.quantity ELSE 0 END), 0) as total_sales_last_30_days,
                   COALESCE(SUM(CASE WHEN t.type = 'out' AND t.transaction_date >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN t.quantity ELSE 0 END), 0) as weekly_sales
            FROM products p
            LEFT JOIN inventory_transactions t ON p.id = t.product_id AND t.user_id = p.user_id
            WHERE p.user_id = ?
            GROUP BY p.id
        ");
        $stmt->execute([$user_id]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $forecasts = [];
        foreach ($products as $product) {
            // Calculate daily average sales (last 30 days)
            $daily_avg = $product['total_sales_last_30_days'] / 30;
            
            // Calculate moving average (last 7 days)
            $weekly_avg = $product['weekly_sales'] / 7;
            
            // Use weighted average (70% weekly, 30% monthly)
            $predicted_daily_demand = ($weekly_avg * 0.7) + ($daily_avg * 0.3);
            
            // Predict demand for next 7 days
            $predicted_weekly_demand = round($predicted_daily_demand * 7);
            
            // Determine risk level
            $risk_level = 'LOW';
            $days_remaining = 999;
            
            if ($predicted_weekly_demand > 0 && $product['current_stock'] > 0) {
                $days_remaining = floor($product['current_stock'] / $predicted_daily_demand);
                if ($days_remaining <= 7) {
                    $risk_level = 'CRITICAL';
                } elseif ($days_remaining <= 14) {
                    $risk_level = 'HIGH';
                } elseif ($days_remaining <= 30) {
                    $risk_level = 'MEDIUM';
                } else {
                    $risk_level = 'LOW';
                }
            }
            
            // Suggested order quantity (if needed)
            $suggested_order = 0;
            if ($days_remaining < $product['reorder_threshold']) {
                $suggested_order = ($predicted_weekly_demand * 2) - $product['current_stock'];
                if ($suggested_order < 0) $suggested_order = 0;
            }
            
            $forecasts[] = [
                'product_id' => $product['id'],
                'product_name' => $product['name'],
                'category' => $product['category'],
                'current_stock' => (int)$product['current_stock'],
                'reorder_threshold' => (int)$product['reorder_threshold'],
                'weekly_sales' => (int)$product['weekly_sales'],
                'predicted_weekly_demand' => $predicted_weekly_demand,
                'days_remaining' => $days_remaining,
                'risk_level' => $risk_level,
                'suggested_order' => $suggested_order,
                'confidence_score' => $weekly_avg > 0 ? min(95, round(($weekly_avg / ($weekly_avg + 1)) * 100)) : 50
            ];
        }
        
        // Sort by risk level (CRITICAL first)
        usort($forecasts, function($a, $b) {
            $risk_order = ['CRITICAL' => 1, 'HIGH' => 2, 'MEDIUM' => 3, 'LOW' => 4];
            return $risk_order[$a['risk_level']] - $risk_order[$b['risk_level']];
        });
        
        echo json_encode(['success' => true, 'forecasts' => $forecasts]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>