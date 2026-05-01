<?php
require_once __DIR__ . '/includes/functions.php';

$payload   = (string)file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '';
$secret    = get_setting('paymongo_webhook_secret', '');

// Verify HMAC signature when webhook secret is configured
if ($secret !== '' && $sigHeader !== '') {
    $parts     = [];
    foreach (explode(',', $sigHeader) as $part) {
        [$k, $v]    = array_pad(explode('=', $part, 2), 2, '');
        $parts[$k]  = $v;
    }
    $timestamp = $parts['t'] ?? '';
    $li        = $parts['li'] ?? '';
    $computed  = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    if (!hash_equals($computed, $li)) {
        http_response_code(400);
        exit('Invalid signature');
    }
} elseif ($secret !== '') {
    // Secret configured but no signature header — reject
    http_response_code(400);
    exit('Missing signature');
}

$event = json_decode($payload, true);
if (!$event) {
    http_response_code(400);
    exit('Invalid JSON');
}

$type = $event['data']['attributes']['type'] ?? '';

if ($type === 'checkout_session.payment.paid') {
    $attrs     = $event['data']['attributes']['data']['attributes'] ?? [];
    $reference = $attrs['reference_number'] ?? '';
    $orderId   = (int)$reference;

    if ($orderId > 0) {
        $updated = db()->prepare(
            "UPDATE orders SET payment_status='Paid', status='Processing' WHERE id=? AND payment_status='Pending'"
        );
        $updated->execute([$orderId]);

        if ($updated->rowCount() > 0) {
            audit_log('guest', null, 'paymongo-webhook', 'PAYMENT_CONFIRMED', "Order #{$orderId} marked Paid via PayMongo webhook");
        }
    }
}

http_response_code(200);
echo 'ok';
