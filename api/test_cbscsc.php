<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../vendor/autoload.php';

use MIMA\Config\Database;

$user_id = 4;
$method = $_SERVER['REQUEST_METHOD'];
$response = ['success' => false, 'message' => 'Unknown error'];

// Simulated Smart City API responses
$smart_city_nodes = [
    ['node_id' => 'SCN-LAG-001', 'name' => 'Ikeja Smart Hub', 'location' => 'Ikeja, Lagos', 'status' => 'active'],
    ['node_id' => 'SCN-LAG-002', 'name' => 'Apapa Logistics Center', 'location' => 'Apapa, Lagos', 'status' => 'active'],
    ['node_id' => 'SCN-LAG-003', 'name' => 'Victoria Island Depot', 'location' => 'Victoria Island, Lagos', 'status' => 'active'],
    ['node_id' => 'SCN-LAG-004', 'name' => 'Maryland Fulfillment', 'location' => 'Maryland, Lagos', 'status' => 'maintenance']
];

$demand_signals = [
    ['signal_id' => 'DEM-001', 'category' => 'Electronics', 'forecast' => 'HIGH DEMAND', 'multiplier' => 1.5, 'message' => 'Surge expected for electronics next week'],
    ['signal_id' => 'DEM-002', 'category' => 'Beauty', 'forecast' => 'LOW DEMAND', 'multiplier' => 0.7, 'message' => 'Demand cooling for beauty products'],
    ['signal_id' => 'DEM-003', 'category' => 'Personal care', 'forecast' => 'MODERATE', 'multiplier' => 1.0, 'message' => 'Stable demand expected']
];

try {
    $db = Database::getInstance()->getConnection();
    
    if ($method === 'GET') {
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        
        if ($action === 'nodes') {
            $response = ['success' => true, 'nodes' => $smart_city_nodes];
        }
        elseif ($action === 'demand_signals') {
            $response = ['success' => true, 'demand_signals' => $demand_signals];
        }
        elseif ($action === 'restock_requests') {
            $stmt = $db->prepare("
                SELECT po.*, s.name as supplier_name 
                FROM purchase_orders po
                LEFT JOIN suppliers s ON po.supplier_id = s.id
                WHERE po.user_id = ? AND po.status IN ('submitted', 'in_transit')
            ");
            $stmt->execute([$user_id]);
            $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response = ['success' => true, 'restock_requests' => $requests];
        }
        elseif ($action === 'auto_broadcast') {
            $stmt = $db->prepare("
                SELECT id, name, category, current_stock, reorder_threshold 
                FROM products 
                WHERE user_id = ?
            ");
            $stmt->execute([$user_id]);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $low_stock = array_filter($products, function($p) {
                return $p['current_stock'] <= $p['reorder_threshold'];
            });
            
            $response = [
                'success' => true,
                'message' => 'Auto-broadcast completed',
                'timestamp' => date('Y-m-d H:i:s'),
                'products_broadcast' => count($products),
                'low_stock_alerts' => count($low_stock),
                'broadcast_id' => 'BC-' . time()
            ];
        }
        else {
            $response = ['success' => true, 'message' => 'CBSCSC API is reachable', 'status' => 'connected', 'city' => 'Lagos Smart City Network'];
        }
    }
    elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'));
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        
        if ($action === 'restock_request') {
            if (!$input || !isset($input->product_id) || !isset($input->quantity)) {
                $response = ['success' => false, 'message' => 'Product ID and quantity required'];
            } else {
                $sc_ref = 'SC-' . date('Ymd') . '-' . rand(1000, 9999);
                $response = [
                    'success' => true,
                    'message' => 'Restock request submitted to Smart City network',
                    'supply_chain_ref' => $sc_ref,
                    'status' => 'submitted',
                    'estimated_delivery' => rand(2, 5) . ' days',
                    'source' => $smart_city_nodes[array_rand($smart_city_nodes)]['name']
                ];
            }
        }
        elseif ($action === 'broadcast_stock') {
            $stmt = $db->prepare("
                SELECT name, current_stock, selling_price 
                FROM products 
                WHERE user_id = ? AND current_stock < reorder_threshold
            ");
            $stmt->execute([$user_id]);
            $low_stock_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response = [
                'success' => true,
                'message' => 'Stock data broadcast to Smart City network',
                'broadcast_id' => 'BC-' . time(),
                'low_stock_alerts' => count($low_stock_products),
                'products_at_risk' => $low_stock_products
            ];
        }
        elseif ($action === 'auto_restock') {
            $stmt = $db->prepare("
                SELECT p.id, p.name, p.current_stock, p.reorder_threshold, p.selling_price
                FROM products p
                WHERE p.user_id = ? AND p.current_stock <= p.reorder_threshold
            ");
            $stmt->execute([$user_id]);
            $low_stock_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($low_stock_products) > 0) {
                $restock_orders = [];
                foreach ($low_stock_products as $product) {
                    $suggested_qty = ($product['reorder_threshold'] * 2) - $product['current_stock'];
                    if ($suggested_qty > 0) {
                        $sc_ref = 'AUTO-' . date('Ymd') . '-' . rand(1000, 9999);
                        
                        $stmt2 = $db->prepare("
                            INSERT INTO purchase_orders (user_id, po_number, items, status, total_amount) 
                            VALUES (?, ?, ?, 'submitted', ?)
                        ");
                        $items = json_encode([['product_id' => $product['id'], 'quantity' => $suggested_qty, 'unit_price' => $product['selling_price']]]);
                        $total = $suggested_qty * $product['selling_price'];
                        $stmt2->execute([$user_id, 'PO-AUTO-' . time(), $items, $total]);
                        
                        $restock_orders[] = [
                            'product' => $product['name'],
                            'quantity' => $suggested_qty,
                            'supply_chain_ref' => $sc_ref
                        ];
                    }
                }
                
                $response = [
                    'success' => true,
                    'message' => 'Auto-restock triggered for ' . count($restock_orders) . ' products',
                    'auto_restock_orders' => $restock_orders
                ];
            } else {
                $response = ['success' => true, 'message' => 'No auto-restock needed', 'auto_restock_orders' => []];
            }
        }
        else {
            $response = ['success' => false, 'message' => 'Unknown action'];
        }
    }
    elseif ($method === 'PUT') {
        $input = json_decode(file_get_contents('php://input'));
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        
        if ($action === 'delivery_status') {
            if (!$input || !isset($input->supply_chain_ref)) {
                $response = ['success' => false, 'message' => 'Supply chain reference required'];
            } else {
                $statuses = ['in_transit', 'arriving_soon', 'delivered'];
                $random_status = $statuses[array_rand($statuses)];
                
                $response = [
                    'success' => true,
                    'supply_chain_ref' => $input->supply_chain_ref,
                    'status' => $random_status,
                    'location' => $smart_city_nodes[array_rand($smart_city_nodes)]['location'],
                    'estimated_arrival' => rand(1, 3) . ' days'
                ];
            }
        }
        else {
            $response = ['success' => false, 'message' => 'Unknown action'];
        }
    }
} catch (Exception $e) {
    $response = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
}

echo json_encode($response);
?>