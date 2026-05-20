<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../vendor/autoload.php';

use MIMA\Config\Database;

try {
    $db = Database::getInstance()->getConnection();
    echo json_encode(['success' => true, 'message' => 'Database connected!']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}