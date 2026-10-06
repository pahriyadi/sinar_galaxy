<?php
// Simple latency endpoint: returns current timestamp in ms
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
echo json_encode([ 'ts' => round(microtime(true) * 1000) ]);
?>

