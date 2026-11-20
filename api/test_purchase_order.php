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
        // Get all purchase orders
        $stmt = $db->prepare("
            SELECT po.*, s.name as supplier_name 
            FROM purchase_orders po
            LEFT JOIN suppliers s ON po.supplier_id = s.id
            WHERE po.user_id = ?
            ORDER BY po.created_at DESC
        ");
        $stmt->execute([$user_id]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decode items JSON for each order
        foreach ($orders as &$order) {
            $order['items'] = json_decode($order['items'], true);
        }
        
        $response = ['success' => true, 'purchase_orders' => $orders];
    }
    elseif ($method === 'POST') {
        // Create new purchase order
        $input = json_decode(file_get_contents('php://input'));
        
        if (!$input || !isset($input->supplier_id) || !isset($input->items)) {
            $response = ['success' => false, 'message' => 'Supplier and items are required'];
        } else {
            // Generate PO number
            $po_number = 'PO-' . date('Ymd') . '-' . rand(1000, 9999);
            
            // Calculate total amount
            $total_amount = 0;
            foreach ($input->items as $item) {
                $total_amount += $item->quantity * ($item->unit_price ?? $item->unit_cost ?? 0);
            }
            
            $items_json = json_encode($input->items);
            $status = $input->status ?? 'draft';
            
            $stmt = $db->prepare("
                INSERT INTO purchase_orders (user_id, po_number, supplier_id, items, status, total_amount, submitted_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $submitted_at = ($status === 'submitted') ? date('Y-m-d H:i:s') : null;
            
            $result = $stmt->execute([
                $user_id,
                $po_number,
                $input->supplier_id,
                $items_json,
                $status,
                $total_amount,
                $submitted_at
            ]);
            
            if ($result) {
                $new_id = $db->lastInsertId();
                $response = [
                    'success' => true, 
                    'message' => 'Purchase order created successfully',
                    'purchase_order' => [
                        'id' => $new_id,
                        'po_number' => $po_number
                    ]
                ];
            } else {
                $response = ['success' => false, 'message' => 'Failed to create purchase order'];
            }
        }
    }
    elseif ($method === 'PUT') {
        // Update purchase order status
        $id = isset($_GET['id']) ? $_GET['id'] : null;
        $input = json_decode(file_get_contents('php://input'));
        
        if (!$id) {
            $response = ['success' => false, 'message' => 'Purchase order ID required'];
        } else {
            $status = $input->status ?? null;
            $allowed = ['draft', 'submitted', 'consolidated', 'in_transit', 'delivered', 'closed'];
            if (!in_array($status, $allowed, true)) {
                echo json_encode(['success' => false, 'message' => 'Invalid status']);
                exit();
            }

            $stmt = $db->prepare("SELECT status, items FROM purchase_orders WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);
            $order = $stmt->fetch();
            if (!$order) {
                echo json_encode(['success' => false, 'message' => 'Purchase order not found']);
                exit();
            }

            $db->beginTransaction();
            try {
                $stmt = $db->prepare("
                    UPDATE purchase_orders
                    SET status = ?, delivered_at = CASE WHEN ? = 'delivered' AND delivered_at IS NULL THEN NOW() ELSE delivered_at END
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([$status, $status, $id, $user_id]);

                // Add stock once, when the order first becomes delivered
                if ($status === 'delivered' && $order['status'] !== 'delivered') {
                    $items = json_decode($order['items']) ?: [];
                    foreach ($items as $item) {
                        $qty = (int)($item->quantity ?? 0);
                        $price = (float)($item->unit_price ?? $item->unit_cost ?? 0);
                        if (!isset($item->product_id) || $qty <= 0) {
                            continue;
                        }
                        $stmt = $db->prepare("UPDATE products SET current_stock = current_stock + ? WHERE id = ? AND user_id = ?");
                        $stmt->execute([$qty, $item->product_id, $user_id]);

                        $stmt = $db->prepare("
                            INSERT INTO inventory_transactions (user_id, product_id, type, quantity, unit_price, vat_amount, sync_status)
                            VALUES (?, ?, 'in', ?, ?, ?, 'synced')
                        ");
                        $stmt->execute([$user_id, $item->product_id, $qty, $price, $price * $qty * 0.075]);
                    }
                }
                $db->commit();
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }

            $response = ['success' => true, 'message' => 'Purchase order updated successfully'];
        }
    }
    elseif ($method === 'DELETE') {
        // Delete purchase order (only if draft)
        $id = isset($_GET['id']) ? $_GET['id'] : null;
        if (!$id) {
            $response = ['success' => false, 'message' => 'Purchase order ID required'];
        } else {
            $stmt = $db->prepare("DELETE FROM purchase_orders WHERE id = ? AND user_id = ? AND status = 'draft'");
            $result = $stmt->execute([$id, $user_id]);
            
            if ($result && $stmt->rowCount() > 0) {
                $response = ['success' => true, 'message' => 'Purchase order deleted successfully'];
            } else {
                $response = ['success' => false, 'message' => 'Cannot delete: order already submitted or not found'];
            }
        }
    }
} catch (Exception $e) {
    $response = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
}

echo json_encode($response);
?>