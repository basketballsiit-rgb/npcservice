<?php
/**
 * GitHub Webhook Auto-Deployment Script
 */

// Define security secret (Must match secret in GitHub Webhook)
define('GITHUB_WEBHOOK_SECRET', 'NpcServiceSecretKey2026');
define('LOG_FILE', __DIR__ . '/deploy_log.txt');

// Helper to log messages
function log_msg($msg) {
    $date = date('Y-m-d H:i:s');
    file_put_contents(LOG_FILE, "[$date] $msg\n", FILE_APPEND);
}

// Verify request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    log_msg("Error: Method not allowed: " . $_SERVER['REQUEST_METHOD']);
    die('Only POST requests allowed');
}

// Verify GitHub Signature
$signature = isset($_SERVER['HTTP_X_HUB_SIGNATURE_256']) ? $_SERVER['HTTP_X_HUB_SIGNATURE_256'] : '';

if (!$signature) {
    header('HTTP/1.1 403 Forbidden');
    log_msg("Error: GitHub signature missing");
    die('Signature missing');
}

$payload = file_get_contents('php://input');
$expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, GITHUB_WEBHOOK_SECRET);

if (!hash_equals($expectedSignature, $signature)) {
    header('HTTP/1.1 403 Forbidden');
    log_msg("Error: Signature verification failed");
    die('Invalid signature');
}

// Run git pull
log_msg("Starting git pull...");
$output = [];
$return_var = 0;
exec('git pull 2>&1', $output, $return_var);

$output_str = implode("\n", $output);
log_msg("Git pull output (Exit code: $return_var):\n$output_str");

echo "Deployment completed successfully. Exit code: $return_var\n";
echo $output_str;
?>
