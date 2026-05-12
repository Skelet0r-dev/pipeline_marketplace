<?php
// process_square_payment.php - Backend handler for Square Payments API
header('Content-Type: application/json');

require_once __DIR__ . '/square_config.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['sourceId']) || !isset($input['amount'])) {
    echo json_encode(['success' => false, 'message' => 'Missing payment data']);
    exit;
}

$sourceId = $input['sourceId'];
$amount   = (int)($input['amount'] * 100); // Square expects amounts in smallest unit (e.g., cents/centavos)
$currency = 'PHP';
$idempotencyKey = uniqid('pay_', true);

$payload = [
    'source_id' => $sourceId,
    'idempotency_key' => $idempotencyKey,
    'amount_money' => [
        'amount' => $amount,
        'currency' => $currency
    ],
    'location_id' => SQUARE_LOCATION_ID
];

$url = (SQUARE_ENVIRONMENT === 'sandbox') 
    ? 'https://connect.squareupsandbox.com/v2/payments' 
    : 'https://connect.squareup.com/v2/payments';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Square-Version: 2023-06-08',
    'Authorization: Bearer ' . SQUARE_ACCESS_TOKEN,
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$responseData = json_decode($response, true);

if ($httpCode === 200 || $httpCode === 201) {
    echo json_encode(['success' => true]);
} else {
    $errorMsg = $responseData['errors'][0]['detail'] ?? 'Payment failed at Square';
    echo json_encode(['success' => false, 'message' => $errorMsg]);
}
?>
