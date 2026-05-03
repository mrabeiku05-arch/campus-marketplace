<?php
/**
 * Payment Routes (Paystack)
 * POST /payments/initialize - Start payment for tier upgrade
 * GET  /payments/verify/:reference - Verify payment
 * POST /payments/deposit - Wallet deposit
 */

$auth = authenticate();

function paymentDurationToExpiry(string $duration): ?string
{
    $value = strtolower(trim($duration));
    if ($value === '' || $value === '0' || $value === 'forever') {
        return null;
    }

    $expiresAt = new DateTimeImmutable('now');

    if ($value === 'weekly') {
        return $expiresAt->modify('+7 days')->format('Y-m-d H:i:s');
    }

    if (preg_match('/^(\d+)_weeks?$/', $value, $matches)) {
        return $expiresAt->modify('+' . ((int)$matches[1] * 7) . ' days')->format('Y-m-d H:i:s');
    }

    if (preg_match('/^(\d+)_months?$/', $value, $matches)) {
        return $expiresAt->modify('+' . (int)$matches[1] . ' months')->format('Y-m-d H:i:s');
    }

    if (ctype_digit($value)) {
        return $expiresAt->modify('+' . (int)$value . ' months')->format('Y-m-d H:i:s');
    }

    return null;
}

if ($method === 'POST' && $action === 'initialize') {
    $body = getJsonBody();
    $type = strtolower(trim((string)($body['type'] ?? 'premium')));
    $amount = 0;
    $description = '';
    $user = getUser($pdo, $auth['user_id']);

    if (!$user) {
        jsonError('User account not found', 404);
    }

    if ($type === 'deposit') {
        $amount = (float)($body['amount'] ?? 0);
        if ($amount < 1) {
            jsonError('Minimum deposit is GHS 1');
        }
        $description = 'Wallet deposit';
    } else {
        if (!in_array($user['role'] ?? '', ['seller', 'admin'], true)) {
            jsonError('Only seller accounts can purchase seller tiers', 403);
        }

        $tiers = getAccountTiers($pdo);
        $tier = $tiers[$type] ?? null;
        if (!$tier) {
            jsonError('Invalid tier');
        }

        $amount = (float)($tier['price'] ?? 0);
        if ($amount <= 0) {
            jsonError('This plan does not require payment');
        }

        $description = ucfirst($type) . ' tier upgrade';
    }

    $reference = generateRef('PAY');

    $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, reference, description) VALUES (?, ?, ?, 'pending', ?, ?)")
        ->execute([$auth['user_id'], $type, $amount, $reference, $description]);

    $paystackKey = env('PAYSTACK_SECRET_KEY');
    $paystackPublicKey = env('PAYSTACK_PUBLIC_KEY', '');

    if ($paystackKey) {
        $frontendUrl = rtrim((string)env('FRONTEND_URL', 'http://localhost:3000'), '/');
        $callbackUrl = $frontendUrl . '/#/?payment=verify&reference=' . rawurlencode($reference) . '&tier=' . rawurlencode($type);
        $payload = [
            'email' => $user['email'],
            'amount' => (int)round($amount * 100),
            'reference' => $reference,
            'currency' => 'GHS',
            'callback_url' => $callbackUrl,
            'metadata' => [
                'user_id' => $auth['user_id'],
                'type' => $type,
            ],
        ];

        $ch = curl_init('https://api.paystack.co/transaction/initialize');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $paystackKey,
        ]);
        $response = json_decode(curl_exec($ch), true);

        if ($response && ($response['status'] ?? false)) {
            jsonResponse([
                'success' => true,
                'authorization_url' => $response['data']['authorization_url'],
                'reference' => $reference,
                'amount' => $amount,
                'email' => $user['email'],
                'currency' => 'GHS',
                'public_key' => $paystackPublicKey,
            ]);
        }
    }

    jsonResponse([
        'success' => true,
        'reference' => $reference,
        'amount' => $amount,
        'email' => $user['email'],
        'currency' => 'GHS',
        'public_key' => $paystackPublicKey,
    ]);
} elseif ($method === 'GET' && $action === 'verify') {
    $reference = $param ?: getQueryParam('reference', '');
    if (!$reference) {
        jsonError('Reference is required');
    }

    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE reference = ?");
    $stmt->execute([$reference]);
    $tx = $stmt->fetch();

    if (!$tx) {
        jsonError('Transaction not found', 404);
    }

    if ((int)$tx['user_id'] !== (int)$auth['user_id'] && ($auth['role'] ?? '') !== 'admin') {
        jsonError('Transaction not found', 404);
    }

    if ($tx['status'] === 'completed') {
        jsonSuccess('Already verified', ['transaction' => $tx]);
    }

    $paystackKey = env('PAYSTACK_SECRET_KEY');
    $verified = false;

    if ($paystackKey) {
        $ch = curl_init("https://api.paystack.co/transaction/verify/$reference");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $paystackKey]);
        $response = json_decode(curl_exec($ch), true);
        $verified = ($response['data']['status'] ?? '') === 'success';
    }

    if (!$verified) {
        jsonError('Payment verification failed');
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE transactions SET status = 'completed' WHERE reference = ?")->execute([$reference]);
        $auditMessage = null;
        $auditTargetId = (int)($tx['id'] ?? 0);

        if ($tx['type'] !== 'deposit') {
            $tier = strtolower((string)$tx['type']);
            $tiers = getAccountTiers($pdo);
            $tierInfo = $tiers[$tier] ?? null;

            if (!$tierInfo) {
                throw new Exception('Tier configuration no longer exists');
            }

            $expectedAmount = (float)($tierInfo['price'] ?? 0);
            if (abs($expectedAmount - (float)$tx['amount']) > 0.01) {
                throw new Exception('Payment amount does not match the selected tier');
            }

            $expiresAt = paymentDurationToExpiry((string)($tierInfo['duration'] ?? ''));

            $boolF = sqlBool(false, $pdo);
            $pdo->prepare("UPDATE users SET seller_tier = ?, tier_expires_at = ?, vacation_mode = $boolF WHERE id = ?")
                ->execute([$tier, $expiresAt, $tx['user_id']]);

            // Log to tier_subscriptions for the admin ledger
            $pdo->prepare("INSERT INTO tier_subscriptions (user_id, tier_name, amount, transaction_id, expires_at) VALUES (?, ?, ?, ?, ?)")
                ->execute([$tx['user_id'], $tier, (float)$tx['amount'], $reference, $expiresAt]);

            $auditMessage = "Upgraded to $tier tier via payment $reference";
        } else {
            $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")
                ->execute([$tx['amount'], $tx['user_id']]);
        }

        $pdo->commit();
        
        // Notifications
        if ($tx['type'] !== 'deposit') {
            createNotification($pdo, (int)$tx['user_id'], 'subscription', "Your account has been upgraded to the $tier tier.", null, ['title' => 'Subscription Upgraded']);
            createNotification($pdo, (int)$tx['user_id'], 'payment', "Your payment of GHS {$tx['amount']} was confirmed for your tier upgrade.", null, ['title' => 'Payment Confirmed']);
        } else {
            createNotification($pdo, (int)$tx['user_id'], 'payment', "Your wallet deposit of GHS {$tx['amount']} was successful.", null, ['title' => 'Deposit Confirmed']);
        }
        
        if ($auditMessage !== null) {
            try {
                auditLog($pdo, (int)$tx['user_id'], $auditMessage, 'payment', $auditTargetId);
            } catch (Throwable $auditException) {
                error_log('Payment audit log failed: ' . $auditException->getMessage());
            }
        }
        jsonSuccess('Payment verified and applied');
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonError('Failed to apply payment', 500);
    }
} else {
    jsonError('Payment endpoint not found', 404);
}
