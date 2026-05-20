<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../vendor/autoload.php';

use MIMA\Config\Database;

$user_id = 4;
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM suppliers WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'suppliers' => $suppliers]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
elseif ($method === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'));
        
        if (!$input || !isset($input->name)) {
            echo json_encode(['success' => false, 'message' => 'Name is required']);
            exit();
        }
        
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            INSERT INTO suppliers (user_id, name, contact_person, phone, email, location, lead_time_days) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $result = $stmt->execute([
            $user_id,
            $input->name,
            $input->contact_person ?? null,
            $input->phone ?? null,
            $input->email ?? null,
            $input->location ?? null,
            $input->lead_time_days ?? 3
        ]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Supplier added']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Insert failed']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>