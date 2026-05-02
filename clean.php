<?php
$lines = file('dashboard.php');
// Remove lines 593 to 621 (index 592 to 620)
array_splice($lines, 592, 29);
file_put_contents('dashboard.php', implode('', $lines));
echo "Cleaned!";
?>
