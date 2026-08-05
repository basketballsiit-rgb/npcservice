<?php
/**
 * Standalone diagnostic script to test submission with image attachments
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

echo "<pre>";
echo "Starting Image-Enabled submitRepairForm Diagnostic...\n";
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

    echo "Image URL provided: " . ($imageUrl ? $imageUrl : "None") . "\n";

    if ($imageUrl && stripos($imageUrl, 'https://') === 0) {
        echo "Image URL starts with https. Adding image message to payload...\n";
        $messages[] = [
            "type" => "image",
            "originalContentUrl" => $imageUrl,
            "previewImageUrl" => $imageUrl
        ];
    } else if ($imageUrl) {
        echo "Image URL does not start with https (starts with http). Skipping image payload as per LINE requirements.\n";
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

    echo "\nPayload being sent to LINE:\n";
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
    echo "LINE API Response Code: $httpCode\n";
    echo "LINE API Response Body: $response\n";
    return $httpCode === 200;
}

function submitRepairFormDiagnostic($formObject) {
    try {
        $db = getDB();
        $fileIds = [];
        $fileUrls = [];

        // Handle File Uploads (Base64)
        if (isset($formObject['fileData']) && is_array($formObject['fileData']) && count($formObject['fileData']) > 0) {
            if (!is_dir(UPLOAD_DIR)) {
                if (!mkdir(UPLOAD_DIR, 0755, true)) {
                    throw new Exception("ไม่สามารถสร้างโฟลเดอร์ uploads ได้");
                }
            }
            if (!is_writable(UPLOAD_DIR)) {
                throw new Exception("โฟลเดอร์ uploads ไม่มีสิทธิ์เขียนไฟล์ (Permission denied)");
            }

            foreach ($formObject['fileData'] as $fileInfo) {
                $fileName = $fileInfo['name'];
                $fileType = $fileInfo['type'];
                $fileData = $fileInfo['data'];

                $parts = explode(',', $fileData);
                if (count($parts) > 1) {
                    $base64Data = $parts[1];
                    $decodedData = base64_decode($base64Data);

                    $ext = pathinfo($fileName, PATHINFO_EXTENSION);
                    if (empty($ext)) {
                        $ext = 'png';
                    }
                    $uniqueFileName = uniqid('file_', true) . '.' . $ext;
                    $targetPath = UPLOAD_DIR . $uniqueFileName;

                    if (file_put_contents($targetPath, $decodedData)) {
                        $fileIds[] = md5($uniqueFileName);
                        $fileUrls[] = UPLOAD_URL . $uniqueFileName;
                        echo "Uploaded file saved to: $targetPath\n";
                    } else {
                        throw new Exception("ไม่สามารถเขียนไฟล์ลงในโฟลเดอร์ uploads ได้");
                    }
                }
            }
        }

        $timestamp = round(microtime(true) * 1000);
        $referenceId = "BD-" . $timestamp;

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
        $notifyMsg = "📣 มีรายการแจ้งซ่อมใหม่ (ทดสอบวินิจฉัยภาพ)!";
        $notifyMsg .= "\n📅 " . $dateFormatted;
        $notifyMsg .= "\n👤 " . $formObject['fullName'];
        $notifyMsg .= "\n🔧 ประเภท: " . $formObject['repairType'];
        $notifyMsg .= "\n📍 " . $formObject['location_building'] . ' ' . $formObject['location_room'];
        $notifyMsg .= "\n📝 " . $formObject['details'];
        $notifyMsg .= "\n📞 " . $formObject['phone'];

        $firstImageUrl = count($fileUrls) > 0 ? $fileUrls[0] : null;
        $lineSuccess = sendLineMessageApiDiagnostic($notifyMsg, $firstImageUrl);
        
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

// 1x1 Pixel Dummy PNG
$dummyBase64 = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==";

$formObject = [
    'requestDate' => date('Y-m-d'),
    'fullName' => 'นายนิพนธ์ ทดสอบแนบภาพ',
    'position' => 'เจ้าหน้าที่ทดสอบภาพ',
    'phone' => '0999999999',
    'contactBack' => 'ต้องการ',
    'repairType' => 'ระบบไฟฟ้า/เครื่องปรับอากาศ',
    'location_building' => 'อาคาร 1 สามัญสัมพันธ์',
    'location_room' => 'ห้องทดสอบระบบภาพ',
    'details' => 'ทดสอบระบบแจ้งซ่อมแบบมีภาพแนบและเช็คความเข้ากันได้ของ LINE API',
    'fileData' => [
        [
            'name' => 'diagnostic_test.png',
            'type' => 'image/png',
            'data' => $dummyBase64
        ]
    ]
];

$res = submitRepairFormDiagnostic($formObject);
echo "\nFinal Result:\n";
print_r($res);

echo "-----------------------------------------\n";
echo "Diagnostic Finished.\n";
echo "</pre>";
?>
