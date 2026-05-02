<?php
$file = 'dashboard.php';
$content = file_get_contents($file);

$anchor = "            break;\r\n    }\r\n}\r\n\r\n// Re-fetch user after potential changes";

$insert = "            break;\r\n        case 'request_withdrawal':\r\n            \$amount = (float)(\$_POST['withdraw_amount'] ?? 0);\r\n            \$network = trim(\$_POST['momo_network'] ?? '');\r\n            \$number = trim(\$_POST['momo_number'] ?? '');\r\n            if (\$amount <= 0) { \$msg = \"Please enter a valid amount.\"; break; }\r\n            if (!\$network || !\$number) { \$msg = \"Please select a network and enter your mobile number.\"; break; }\r\n            if (!preg_match('/^[0-9]{10}\$/', \$number)) { \$msg = \"Please enter a valid 10-digit phone number.\"; break; }\r\n            if (!\in_array(\$network, ['MTN', 'Vodafone', 'AirtelTigo'], true)) { \$msg = \"Invalid network selected.\"; break; }\r\n            // Balance validation + atomic withdrawal\r\n            \$pdo->beginTransaction();\r\n            try {\r\n                \$bal = \$pdo->prepare(\"SELECT balance FROM users WHERE id = ? FOR UPDATE\");\r\n                \$bal->execute([\$user['id']]);\r\n                \$currentBalance = (float)\$bal->fetchColumn();\r\n                if (\$amount > \$currentBalance) {\r\n                    throw new Exception(\"Insufficient funds. Your balance is GHS \" . number_format(\$currentBalance, 2));\r\n                }\r\n                \$pdo->prepare(\"UPDATE users SET balance = balance - ? WHERE id = ?\")->execute([\$amount, \$user['id']]);\r\n                \$ref = 'WD-' . strtoupper(bin2hex(random_bytes(6)));\r\n                \$desc = \"Withdrawal to \$network \$number\";\r\n                \$pdo->prepare(\"INSERT INTO transactions (user_id, type, amount, status, reference, description) VALUES (?, 'withdrawal', ?, 'pending', ?, ?)\")\r\n                    ->execute([\$user['id'], \$amount, \$ref, \$desc]);\r\n                \$pdo->commit();\r\n                \$_SESSION['flash'] = \"Withdrawal of GHS \" . number_format(\$amount, 2) . \" submitted successfully (Ref: \$ref). Processing may take 24-48 hours.\";\r\n                header('Location: dashboard.php?tab=wallet');\r\n                exit;\r\n            } catch (Exception \$e) {\r\n                if (\$pdo->inTransaction()) \$pdo->rollBack();\r\n                \$msg = \$e->getMessage();\r\n            }\r\n            break;\r\n    }\r\n}\r\n\r\n// Re-fetch user after potential changes";

if (strpos($content, $anchor) !== false) {
    $content = str_replace($anchor, $insert, $content);
    file_put_contents($file, $content);
    echo "Withdrawal handler added successfully.\n";
} else {
    echo "WARN: Anchor not found.\n";
}
?>
