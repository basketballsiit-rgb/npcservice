<?php
/**
 * Diagnostic script for testing actual LINE push notification payloads
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

echo "<pre>";
echo "Starting LINE Notification Diagnostic...\n";
echo "-----------------------------------------\n";

$url = "https://api.line.me/v2/bot/message/push";
$token = getSetting('line_channel_access_token', LINE_CHANNEL_ACCESS_TOKEN);
$target = getSetting('line_target_id', LINE_TARGET_ID);

echo "Target Endpoint: $url\n";
echo "Token Prefix: " . substr($token, 0, 15) . "...\n";
echo "Target ID: $target\n";
echo "Base URL: " . BASE_URL . "\n";

$messageText = "📣 [แจ้งเตือน] ทดสอบการแจ้งซ่อมใหม่จากสคริปต์วินิจฉัย";

$headers = [
    "Content-Type: application/json",
    "Authorization: Bearer " . $token
];

$messages = [
    [
        "type" => "text",
        "text" => $messageText
    ],
    [
        "type" => "flex",
        "altText" => "เข้าสู่ระบบจัดการ",
        "contents" => [
            "type" => "bubble",
            "body" => [
                "type" => "box",
                "layout" => "vertical",
                "contents" => [
                    [
                        "type" => "button",
                        "action" => [
                            "type" => "uri",
                            "label" => "🔐 เข้าสู่ระบบ Admin",
                            "uri" => BASE_URL
                        ],
                        "style" => "primary",
                        "color" => "#06c755",
                        "height" => "sm"
                    ]
                ],
                "paddingAll" => "md"
            ]
        ]
    ]
];

$payload = [
    "to" => $target,
    "messages" => $messages
];

echo "\nPayload to send:\n";
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
echo "-----------------------------------------\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "cURL Error: " . ($error ? $error : "None") . "\n";
echo "HTTP Response Code: $httpCode\n";
echo "Response Body:\n";
echo htmlspecialchars($response) . "\n";
echo "-----------------------------------------\n";
echo "Diagnostic Finished.\n";
echo "</pre>";
?>
