<?php
require_once __DIR__ . '/vendor/autoload.php';

use MIMA\Config\Database;

try {
    $db = Database::getInstance()->getConnection();
    echo "✅ Database connected successfully!";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>