<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
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

// Payment gateway configuration
$public_key = "pk_test_1234567890abcdef";
$secret_key = "sk_test_1234567890abcdef";

try {
    $db = Database::getInstance()->getConnection();
    
    if ($method === 'GET') {
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        
        if ($action === 'banks') {
            // Get list of banks for transfers
            $banks = [
                ['code' => '001', 'name' => 'Access Bank'],
                ['code' => '002', 'name' => 'First Bank of Nigeria'],
                ['code' => '003', 'name' => 'GTBank'],
                ['code' => '004', 'name' => 'UBA'],
                ['code' => '005', 'name' => 'Zenith Bank'],
                ['code' => '006', 'name' => 'Union Bank'],
                ['code' => '007', 'name' => 'Fidelity Bank'],
                ['code' => '008', 'name' => 'Stanbic IBTC'],
                ['code' => '009', 'name' => 'Polaris Bank'],
                ['code' => '010', 'name' => 'Wema Bank']
            ];
            $response = ['success' => true, 'banks' => $banks];
        }
        elseif ($action === 'transactions') {
            // Get payment transactions
            $stmt = $db->prepare("
                SELECT * FROM payment_transactions 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT 20
            ");
            $stmt->execute([$user_id]);
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response = ['success' => true, 'transactions' => $transactions];
        }
        else {
            $response = ['success' => true, 'message' => 'Payment gateway is reachable', 'public_key' => $public_key];
        }
    }
    elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'));
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        
        if ($action === 'initialize') {
            // Initialize a payment transaction
            if (!$input || !isset($input->amount) || !isset($input->email)) {
                $response = ['success' => false, 'message' => 'Amount and email are required'];
            } else {
                $reference = 'PAY-' . date('Ymd') . '-' . strtoupper(uniqid());
                $amount = $input->amount;
                $email = $input->email;
                $currency = $input->currency ?? 'NGN';
                
                // Store transaction in database
                $stmt = $db->prepare("
                    INSERT INTO payment_transactions (user_id, reference, amount, currency, email, status, metadata) 
                    VALUES (?, ?, ?, ?, ?, 'pending', ?)
                ");
                $stmt->execute([
                    $user_id,
                    $reference,
                    $amount,
                    $currency,
                    $email,
                    json_encode($input->metadata ?? [])
                ]);
                
                // Simulate payment URL
                $authorization_url = "http://localhost/mima/api/test_payment.php?action=verify&reference=" . $reference;
                
                $response = [
                    'success' => true,
                    'message' => 'Payment initialized',
                    'reference' => $reference,
                    'authorization_url' => $authorization_url,
                    'access_code' => 'ACC_' . substr(uniqid(), -8),
                    'amount' => $amount,
                    'currency' => $currency
                ];
            }
        }
        elseif ($action === 'verify') {
            // Verify a payment
            $reference = isset($_GET['reference']) ? $_GET['reference'] : ($input->reference ?? null);
            
            if (!$reference) {
                $response = ['success' => false, 'message' => 'Reference required'];
            } else {
                // Simulate verification
                $statuses = ['success', 'success', 'failed'];
                $random_status = $statuses[array_rand($statuses)];
                
                if ($random_status === 'success') {
                    $status = 'success';
                    $message = 'Payment verified successfully';
                } else {
                    $status = 'failed';
                    $message = 'Payment verification failed';
                }
                
                // Update transaction status
                $stmt = $db->prepare("
                    UPDATE payment_transactions 
                    SET status = ?, verified_at = NOW() 
                    WHERE reference = ? AND user_id = ?
                ");
                $stmt->execute([$status, $reference, $user_id]);
                
                $response = [
                    'success' => ($status === 'success'),
                    'message' => $message,
                    'reference' => $reference,
                    'status' => $status,
                    'amount' => 25000,
                    'fees' => 500
                ];
            }
        }
        elseif ($action === 'transfer') {
            // Initiate a bank transfer to supplier
            if (!$input || !isset($input->bank_code) || !isset($input->account_number) || !isset($input->amount)) {
                $response = ['success' => false, 'message' => 'Bank code, account number, and amount required'];
            } else {
                $transfer_reference = 'TRF-' . date('Ymd') . '-' . strtoupper(uniqid());
                
                // Simulate transfer processing
                $statuses = ['pending', 'processing', 'success', 'failed'];
                $random_status = $statuses[array_rand($statuses)];
                
                $response = [
                    'success' => true,
                    'message' => 'Transfer initiated',
                    'transfer_reference' => $transfer_reference,
                    'status' => $random_status,
                    'amount' => $input->amount,
                    'bank_code' => $input->bank_code,
                    'account_number' => substr($input->account_number, -4),
                    'estimated_completion' => date('Y-m-d H:i:s', strtotime('+5 minutes')),
                    'fee' => 50
                ];
            }
        }
        elseif ($action === 'webhook') {
            // Webhook for payment notifications
            // Verify signature
            $signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
            
            // For stub, accept any signature
            $payload = file_get_contents('php://input');
            
            // Process webhook
            $response = [
                'success' => true,
                'message' => 'Webhook received',
                'event' => $input->event ?? 'unknown'
            ];
            
            http_response_code(200);
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