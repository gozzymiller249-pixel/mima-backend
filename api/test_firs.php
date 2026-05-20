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

// FIRS sandbox configuration
$firs_sandbox_url = "https://firs-sandbox.gov.ng/api";
$api_key = "test-firs-key-12345";

try {
    $db = Database::getInstance()->getConnection();
    
    if ($method === 'GET') {
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        
        if ($action === 'tin_validation') {
            // Validate TIN with FIRS
            $tin = isset($_GET['tin']) ? $_GET['tin'] : '';
            
            if (empty($tin)) {
                $response = ['success' => false, 'message' => 'TIN required'];
            } else {
                // Simulate TIN validation
                $is_valid = preg_match('/^\d{10,15}$/', $tin);
                $response = [
                    'success' => $is_valid,
                    'tin' => $tin,
                    'business_name' => $is_valid ? 'StockWise SSA Enterprise' : null,
                    'status' => $is_valid ? 'active' : 'invalid',
                    'message' => $is_valid ? 'TIN validated successfully' : 'Invalid TIN format'
                ];
            }
        }
        elseif ($action === 'vat_rates') {
            // Get current VAT rates
            $response = [
                'success' => true,
                'vat_rate' => 7.5,
                'effective_from' => '2020-02-01',
                'exempt_categories' => ['Basic Food Items', 'Educational Materials', 'Medical Supplies'],
                'message' => 'Current VAT rates retrieved'
            ];
        }
        elseif ($action === 'filing_status') {
            // Get filing status for a period
            $period = isset($_GET['period']) ? $_GET['period'] : date('Y-m');
            
            $stmt = $db->prepare("
                SELECT * FROM tax_ledger 
                WHERE user_id = ? AND period_start = ? 
                ORDER BY id DESC LIMIT 1
            ");
            $stmt->execute([$user_id, $period . '-01']);
            $submission = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $response = [
                'success' => true,
                'period' => $period,
                'filing_status' => $submission ? $submission['submission_status'] : 'pending',
                'submission_id' => $submission ? $submission['firs_submission_id'] : null,
                'submitted_at' => $submission ? $submission['submitted_at'] : null,
                'message' => 'Filing status retrieved'
            ];
        }
        else {
            $response = ['success' => true, 'message' => 'FIRS TaxPro-Max API is reachable', 'sandbox' => true];
        }
    }
    elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'));
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        
        if ($action === 'validate_tin') {
            // Validate TIN (POST version)
            if (!$input || !isset($input->tin)) {
                $response = ['success' => false, 'message' => 'TIN required'];
            } else {
                $is_valid = preg_match('/^\d{10,15}$/', $input->tin);
                $response = [
                    'success' => $is_valid,
                    'tin' => $input->tin,
                    'business_name' => $is_valid ? 'StockWise SSA Enterprise' : null,
                    'status' => $is_valid ? 'active' : 'invalid',
                    'message' => $is_valid ? 'TIN validated successfully' : 'Invalid TIN format'
                ];
            }
        }
        elseif ($action === 'generate_invoice') {
            // Generate FIRS-compliant e-invoice
            if (!$input || !isset($input->invoice_data)) {
                $response = ['success' => false, 'message' => 'Invoice data required'];
            } else {
                $uir = 'FIR-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -8));
                
                $response = [
                    'success' => true,
                    'message' => 'E-invoice generated successfully',
                    'uir' => $uir,
                    'digital_stamp' => base64_encode('FIRS_STAMP_' . $uir),
                    'invoice_number' => 'INV-' . date('Ymd') . '-' . rand(1000, 9999),
                    'timestamp' => date('Y-m-d H:i:s'),
                    'status' => 'valid'
                ];
            }
        }
        elseif ($action === 'submit_ledger') {
            // Submit electronic stock ledger to FIRS
            if (!$input || !isset($input->period_start) || !isset($input->period_end)) {
                $response = ['success' => false, 'message' => 'Period start and end required'];
            } else {
                // Calculate VAT from transactions
                $stmt = $db->prepare("
                    SELECT 
                        SUM(CASE WHEN t.type = 'out' THEN t.vat_amount ELSE 0 END) as output_vat,
                        SUM(CASE WHEN t.type = 'in' THEN t.vat_amount ELSE 0 END) as input_vat
                    FROM inventory_transactions t
                    WHERE t.user_id = ? 
                    AND DATE(t.transaction_date) BETWEEN ? AND ?
                ");
                $stmt->execute([$user_id, $input->period_start, $input->period_end]);
                $vat_summary = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $submission_id = 'FIRS-' . date('Ymd') . '-' . rand(10000, 99999);
                
                // Save to tax_ledger
                $stmt = $db->prepare("
                    INSERT INTO tax_ledger (user_id, period_start, period_end, taxable_value, vat_rate, vat_amount, transaction_type, firs_submission_id, submission_status) 
                    VALUES (?, ?, ?, ?, ?, ?, 'output', ?, 'submitted')
                ");
                $stmt->execute([
                    $user_id,
                    $input->period_start,
                    $input->period_end,
                    $vat_summary['output_vat'] / 0.075,
                    7.5,
                    $vat_summary['output_vat'],
                    $submission_id
                ]);
                
                $response = [
                    'success' => true,
                    'message' => 'Stock ledger submitted to FIRS successfully',
                    'submission_id' => $submission_id,
                    'submission_date' => date('Y-m-d H:i:s'),
                    'vat_summary' => [
                        'output_vat' => $vat_summary['output_vat'],
                        'input_vat' => $vat_summary['input_vat'],
                        'net_vat_payable' => $vat_summary['output_vat'] - $vat_summary['input_vat']
                    ],
                    'status' => 'accepted'
                ];
            }
        }
        elseif ($action === 'submit_vat_return') {
            // Submit monthly VAT return
            if (!$input || !isset($input->period)) {
                $response = ['success' => false, 'message' => 'Period required (YYYY-MM)'];
            } else {
                $period_start = $input->period . '-01';
                $period_end = date('Y-m-t', strtotime($period_start));
                
                $submission_id = 'VAT-' . date('Ymd') . '-' . rand(10000, 99999);
                
                $response = [
                    'success' => true,
                    'message' => 'VAT return submitted to FIRS',
                    'submission_id' => $submission_id,
                    'period' => $input->period,
                    'filing_date' => date('Y-m-d H:i:s'),
                    'status' => 'accepted',
                    'next_filing_due' => date('Y-m-d', strtotime('+1 month'))
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