<?php
/**
 * Diagnostic script for testing complete form submission and notification flow (Standalone)
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

echo "<pre>";
echo "Starting Standalone submitRepairForm Diagnostic...\n";
echo "-----------------------------------------\n";

function sendLineMessageApiDiagnostic($messageText, $imageUrl = null) {
    $url = "https://api.line.me/v2/bot/message/push";
    
    $token = getSetting('line_channel_access_token', LINE_CHANNEL_ACCESS_TOKEN);
    $target = getSetting('line_target_id', LINE_TARGET_ID);

    $headers = [
        "Content-Type: application/json",
        "Authorization: Bearer " . $token
    ];

    $messages = [
        [
            "type" => "text",
            "text" => $messageText
        ]
    ];

    if ($imageUrl && stripos($imageUrl, 'https://') === 0) {
        $messages[] = [
            "type" => "image",
            "originalContentUrl" => $imageUrl,
            "previewImageUrl" => $imageUrl
        ];
    }

    $messages[] = [
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
    ];

    $payload = [
        "to" => $target,
        "messages" => $messages
    ];

    echo "Sending payload to LINE API...\n";
    
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
    echo "LINE API Response Code: $httpCode\n";
    echo "LINE API Response Body: $response\n";
    return $httpCode === 200;
}

function submitRepairFormDiagnostic($formObject) {
    try {
        $db = getDB();
        $fileIds = [];
        $fileUrls = [];

        $timestamp = round(microtime(true) * 1000);
        $referenceId = "BD-" . $timestamp;

        // Fetch current semester and academic year
        $currentSemester = getSetting('current_semester', '1');
        $currentYear = getSetting('current_academic_year', '2569');

        echo "Inserting diagnostic record into database...\n";
        
        $sql = "INSERT INTO `repairs` 
                (`reference_id`, `request_date`, `status`, `full_name`, `position`, `phone`, `contact_back`, `repair_type`, `location_building`, `location_room`, `details`, `file_ids`, `file_urls`, `notes`, `semester`, `academic_year`) 
                VALUES (?, ?, 'ยังไม่ดำเนินการ', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '-', ?, ?)";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $referenceId,
            $formObject['requestDate'],
            $formObject['fullName'],
            $formObject['position'],
            $formObject['phone'],
            $formObject['contactBack'],
            $formObject['repairType'],
            $formObject['location_building'],
            $formObject['location_room'],
            $formObject['details'],
            implode(', ', $fileIds),
            implode(', ', $fileUrls),
            $currentSemester,
            $currentYear
        ]);

        echo "Database insertion successful. Reference ID: $referenceId\n";

        $dateFormatted = date('d/m/Y H:i');
        $notifyMsg = "📣 มีรายการแจ้งซ่อมใหม่ (ทดสอบวินิจฉัย)!";
        $notifyMsg .= "\n📅 " . $dateFormatted;
        $notifyMsg .= "\n👤 " . $formObject['fullName'];
        $notifyMsg .= "\n🔧 ประเภท: " . $formObject['repairType'];
        $notifyMsg .= "\n📍 " . $formObject['location_building'] . ' ' . $formObject['location_room'];
        $notifyMsg .= "\n📝 " . $formObject['details'];
        $notifyMsg .= "\n📞 " . $formObject['phone'];

        $lineSuccess = sendLineMessageApiDiagnostic($notifyMsg, null);
        
        return [
            "status" => "success",
            "message" => "แจ้งซ่อมและประมวลผลไลน์สำเร็จ",
            "referenceId" => $referenceId,
            "line_notification_success" => $lineSuccess
        ];

    } catch (Exception $e) {
        return ["status" => "error", "message" => "เกิดข้อผิดพลาด: " . $e->getMessage()];
    }
}

$formObject = [
    'requestDate' => date('Y-m-d'),
    'fullName' => 'นายนิพนธ์ ทดสอบระบบ',
    'position' => 'เจ้าหน้าที่ทดสอบ',
    'phone' => '0999999999',
    'contactBack' => 'ต้องการ',
    'repairType' => 'ระบบไฟฟ้า/เครื่องปรับอากาศ',
    'location_building' => 'อาคาร 1 สามัญสัมพันธ์',
    'location_room' => 'ห้องทดสอบระบบ',
    'details' => 'ทดสอบระบบแจ้งซ่อมและแจ้งเตือนไลน์ผ่านสคริปต์อัตโนมัติ',
    'fileData' => []
];

$res = submitRepairFormDiagnostic($formObject);
echo "\nFinal Result:\n";
print_r($res);

echo "-----------------------------------------\n";
echo "Diagnostic Finished.\n";
echo "</pre>";
?>
