<?php
$content = file_get_contents('dashboard.php');
if (strpos($content, 'audit log') !== false || strpos($content, 'UNION ALL') !== false) {
    echo "Found audit log query";
} else {
    echo "No audit log query found.";
}
?>
