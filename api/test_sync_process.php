<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../vendor/autoload.php';

use MIMA\Config\Database;

$user_id = 4;

try {
    $db = Database::getInstance()->getConnection();
    $input = json_decode(file_get_contents('php://input'));
    
    $response = ['success' => true, 'synced' => 0, 'failed' => 0, 'message' => 'No items to sync'];
    
    if ($input && isset($input->items) && count($input->items) > 0) {
        $response['message'] = 'Processing ' . count($input->items) . ' items';
        
        foreach ($input->items as $item) {
            if ($item->table_name === 'products' && $item->operation === 'create') {
                // Generate a unique barcode
                $barcode = 'BRC' . time() . rand(1000, 9999) . rand(100, 999);
                
                $stmt = $db->prepare("
                    INSERT INTO products (user_id, name, category, current_stock, selling_price, cost_price, reorder_threshold, barcode) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $result = $stmt->execute([
                    $user_id,
                    $item->data->name,
                    $item->data->category ?? '',
                    $item->data->quantity ?? 0,
                    $item->data->selling_price ?? 0,
                    $item->data->cost_price ?? 0,
                    $item->data->reorder_level ?? 10,
                    $barcode
                ]);
                
                if ($result) {
                    $response['synced']++;
                    // Update queue status
                    $stmt2 = $db->prepare("UPDATE offline_queue SET sync_status = 'synced', synced_at = NOW() WHERE id = ?");
                    $stmt2->execute([$item->queue_id]);
                } else {
                    $response['failed']++;
                    $response['errors'][] = 'Failed to insert product: ' . $item->data->name;
                }
            } else {
                $response['failed']++;
                $response['errors'][] = 'Unsupported queued operation: ' . ($item->operation ?? '?') . ' on ' . ($item->table_name ?? '?');
                if (isset($item->queue_id)) {
                    $stmt2 = $db->prepare("UPDATE offline_queue SET sync_status = 'failed' WHERE id = ?");
                    $stmt2->execute([$item->queue_id]);
                }
            }
        }
        
        $response['message'] = $response['synced'] . ' items synced, ' . $response['failed'] . ' failed';
    }
    
    echo json_encode($response);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>