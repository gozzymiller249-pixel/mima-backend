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
        // Get pending sync items
        $stmt = $db->prepare("SELECT * FROM offline_queue WHERE user_id = ? AND sync_status = 'pending' ORDER BY created_at ASC");
        $stmt->execute([$user_id]);
        $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'pending_items' => $pending, 'count' => count($pending)]);
    }
    elseif ($method === 'POST') {
        // Add item to offline queue
        $input = json_decode(file_get_contents('php://input'));
        
        if (!$input || !isset($input->operation) || !isset($input->table_name) || !isset($input->data)) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit();
        }
        
        $stmt = $db->prepare("
            INSERT INTO offline_queue (user_id, operation, table_name, data, sync_status) 
            VALUES (?, ?, ?, ?, 'pending')
        ");
        
        $result = $stmt->execute([
            $user_id,
            $input->operation,
            $input->table_name,
            json_encode($input->data)
        ]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Added to sync queue', 'queue_id' => $db->lastInsertId()]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add to queue']);
        }
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>