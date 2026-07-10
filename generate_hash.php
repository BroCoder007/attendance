<?php
// Run from CLI: php generate_hash.php 1234
// Or visit in browser: generate_hash.php?pin=1234 (remove this file before going live)
$pin = $argv[1] ?? ($_GET['pin'] ?? null);
if (!$pin) {
    echo "Usage: php generate_hash.php <pin>\n";
    exit(1);
}
echo password_hash($pin, PASSWORD_DEFAULT) . "\n";
