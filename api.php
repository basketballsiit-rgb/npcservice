<?php
/**
 * Main API Router and Controller with PHP Session Security
 */

// Allow cross-origin requests for testing, and set JSON content type
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST");

// Start Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// Retrieve raw JSON input
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

if (!$input || !isset($input['action'])) {
    echo json_encode(["status" => "error", "message" => "Invalid Request"]);
    exit;
}

$action = $input['action'];
$arguments = isset($input['arguments']) ? $input['arguments'] : [];

// Helper: Check if Admin is logged in
function isAdminLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

// Router
switch ($action) {
    // ----------------------------------------------------
    // Public Endpoints
    // ----------------------------------------------------
    case 'submitRepairForm':
        $formObject = isset($arguments[0]) ? $arguments[0] : null;
        echo json_encode(submitRepairForm($formObject));
        break;

    case 'getRepairData':
        echo json_encode(getRepairData());
        break;

    case 'getFormConfig':
        echo json_encode(getFormConfig());
        break;

    case 'verifyAdminLogin':
        $username = isset($arguments[0]) ? $arguments[0] : '';
        $password = isset($arguments[1]) ? $arguments[1] : '';
        echo json_encode(verifyAdminLogin($username, $password));
        break;

    // ----------------------------------------------------
    // Admin-Only Endpoints (Protected by Session)
    // ----------------------------------------------------
    case 'updateRepairStatusAndNotes':
        if (!isAdminLoggedIn()) {
            echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
            exit;
        }
        $rowNumber = isset($arguments[0]) ? $arguments[0] : null;
        $newStatus = isset($arguments[1]) ? $arguments[1] : '';
        $newNotes = isset($arguments[2]) ? $arguments[2] : '';
        echo json_encode(updateRepairStatusAndNotes($rowNumber, $newStatus, $newNotes));
        break;

    case 'deleteRepair':
        if (!isAdminLoggedIn()) {
            echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
            exit;
        }
        $rowNumber = isset($arguments[0]) ? $arguments[0] : null;
        $fileIdsString = isset($arguments[1]) ? $arguments[1] : '';
        echo json_encode(deleteRepair($rowNumber, $fileIdsString));
        break;

    case 'getSystemSettings':
        if (!isAdminLoggedIn()) {
            echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
            exit;
        }
        echo json_encode(getSystemSettings());
        break;

    case 'updateSystemSettings':
        if (!isAdminLoggedIn()) {
            echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
            exit;
        }
        $settingsObject = isset($arguments[0]) ? $arguments[0] : null;
        echo json_encode(updateSystemSettings($settingsObject));
        break;

    case 'testLineNotification':
        if (!isAdminLoggedIn()) {
            echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
            exit;
        }
        $lineToken = isset($arguments[0]) ? trim($arguments[0]) : '';
        $lineTarget = isset($arguments[1]) ? trim($arguments[1]) : '';
        echo json_encode(testLineNotification($lineToken, $lineTarget));
        break;

    case 'changeAdminPassword':
        if (!isAdminLoggedIn()) {
            echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
            exit;
        }
        $currentPassword = isset($arguments[0]) ? $arguments[0] : '';
        $newPassword = isset($arguments[1]) ? $arguments[1] : '';
        echo json_encode(changeAdminPassword($currentPassword, $newPassword));
        break;

    case 'adminLogout':
        echo json_encode(adminLogout());
        break;

    default:
        echo json_encode(["status" => "error", "message" => "Action '$action' not found"]);
        break;
}

/**
 * 1. Submit Repair Request
 */
function submitRepairForm($formObject) {
    if (!$formObject) {
        return ["status" => "error", "message" => "กรุณากรอกข้อมูลฟอร์ม"];
    }

    try {
        $db = getDB();
        $fileIds = [];
        $fileUrls = [];

        // Handle File Uploads (Base64)
        if (isset($formObject['fileData']) && is_array($formObject['fileData']) && count($formObject['fileData']) > 0) {
            if (!is_dir(UPLOAD_DIR)) {
                mkdir(UPLOAD_DIR, 0755, true);
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
                        $mimeToExt = [
                            'image/jpeg' => 'jpg',
                            'image/jpg'  => 'jpg',
                            'image/png'  => 'png',
                            'image/gif'  => 'gif',
                            'image/webp' => 'webp'
                        ];
                        $ext = isset($mimeToExt[$fileType]) ? $mimeToExt[$fileType] : 'bin';
                    }
                    $uniqueFileName = uniqid('file_', true) . '.' . $ext;
                    $targetPath = UPLOAD_DIR . $uniqueFileName;

                    if (file_put_contents($targetPath, $decodedData)) {
                        $fileIds[] = md5($uniqueFileName);
                        $fileUrls[] = UPLOAD_URL . $uniqueFileName;
                    }
                }
            }
        }

        $timestamp = round(microtime(true) * 1000);
        $referenceId = "BD-" . $timestamp;

        // Insert into DB
        $sql = "INSERT INTO `repairs` 
                (`reference_id`, `request_date`, `status`, `full_name`, `position`, `phone`, `contact_back`, `repair_type`, `location_building`, `location_room`, `details`, `file_ids`, `file_urls`, `notes`) 
                VALUES (?, ?, 'ยังไม่ดำเนินการ', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '-')";
        
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
            implode(', ', $fileUrls)
        ]);

        // Send LINE Notification
        try {
            $dateFormatted = date('d/m/Y H:i');
            $notifyMsg = "📣 มีรายการแจ้งซ่อมใหม่!";
            $notifyMsg .= "\n📅 " . $dateFormatted;
            $notifyMsg .= "\n👤 " . $formObject['fullName'];
            $notifyMsg .= "\n🔧 ประเภท: " . $formObject['repairType'];
            $notifyMsg .= "\n📍 " . $formObject['location_building'] . ' ' . $formObject['location_room'];
            $notifyMsg .= "\n📝 " . $formObject['details'];
            $notifyMsg .= "\n📞 " . $formObject['phone'];

            $firstImageUrl = count($fileUrls) > 0 ? $fileUrls[0] : null;
            sendLineMessageApi($notifyMsg, $firstImageUrl);
        } catch (Exception $err) {
            error_log("LINE Notify Error: " . $err->getMessage());
        }

        return ["status" => "success", "message" => "แจ้งซ่อมสำเร็จ!", "referenceId" => $referenceId];

    } catch (Exception $e) {
        return ["status" => "error", "message" => "เกิดข้อผิดพลาด: " . $e->getMessage()];
    }
}

/**
 * 2. Get Repair Data
 */
function getRepairData() {
    try {
        $db = getDB();
        $stmt = $db->query("SELECT * FROM `repairs` ORDER BY `id` DESC");
        $rows = $stmt->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            $obj = [];
            $obj['เลขที่อ้างอิง'] = $row['reference_id'];
            $obj['วันที่แจ้ง'] = date('d/m/Y', strtotime($row['request_date']));
            $obj['สถานะการดำเนินการ'] = $row['status'];
            $obj['ชื่อ-นามสกุล'] = $row['full_name'];
            $obj['ตำแหน่ง'] = $row['position'];
            $obj['เบอร์โทรศัพท์'] = $row['phone'];
            $obj['ต้องการให้ติดต่อกลับ'] = $row['contact_back'];
            $obj['อุปกรณ์หรือจุดที่แจ้งซ่อม'] = $row['location_building'] . ' ' . $row['location_room'];
            $obj['รายละเอียดที่ต้องการแจ้งซ่อม'] = "[" . $row['repair_type'] . "] " . $row['details'];
            $obj['ID หลักฐาน'] = $row['file_ids'] ? $row['file_ids'] : '';
            $obj['ลิงก์หลักฐาน'] = $row['file_urls'] ? $row['file_urls'] : '';
            $obj['หมายเหตุ'] = $row['notes'];
            $obj['rowNumber'] = (int)$row['id'];

            $result[] = $obj;
        }
        return $result;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * 3. Get Form Config (Dynamic categories and locations)
 */
function getFormConfig() {
    $types = json_decode(getSetting('repair_types', '[]'), true);
    $locations = json_decode(getSetting('repair_locations', '[]'), true);
    return [
        "status" => "success",
        "repair_types" => $types,
        "repair_locations" => $locations
    ];
}

/**
 * 4. Verify Admin Login
 */
function verifyAdminLogin($username, $password) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM `admin_users` WHERE `username` = ?");
        $stmt->execute([trim($username)]);
        $user = $stmt->fetch();

        if ($user && password_verify(trim($password), $user['password'])) {
            // Store credentials in PHP Session
            $_SESSION['admin_logged_in'] = true;
            return true;
        }
        return false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * 5. Update Repair Status and Notes
 */
function updateRepairStatusAndNotes($rowNumber, $newStatus, $newNotes) {
    try {
        $db = getDB();
        $stmt = $db->prepare("UPDATE `repairs` SET `status` = ?, `notes` = ? WHERE `id` = ?");
        $stmt->execute([$newStatus, $newNotes, $rowNumber]);
        return ["status" => "success", "message" => "อัปเดตสถานะเรียบร้อยแล้ว"];
    } catch (Exception $e) {
        return ["status" => "error", "message" => $e->getMessage()];
    }
}

/**
 * 6. Delete Repair Request
 */
function deleteRepair($rowNumber, $fileIdsString) {
    try {
        $db = getDB();
        
        $stmt = $db->prepare("SELECT `file_urls` FROM `repairs` WHERE `id` = ?");
        $stmt->execute([$rowNumber]);
        $row = $stmt->fetch();

        if ($row && !empty($row['file_urls'])) {
            $urls = explode(',', $row['file_urls']);
            foreach ($urls as $url) {
                $url = trim($url);
                $filename = basename($url);
                $filePath = UPLOAD_DIR . $filename;
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
        }

        $stmt = $db->prepare("DELETE FROM `repairs` WHERE `id` = ?");
        $stmt->execute([$rowNumber]);

        return ["status" => "success", "message" => "ลบข้อมูลเรียบร้อยแล้ว"];
    } catch (Exception $e) {
        return ["status" => "error", "message" => $e->getMessage()];
    }
}

/**
 * 7. Get System Settings
 */
function getSystemSettings() {
    $types = json_decode(getSetting('repair_types', '[]'), true);
    $locations = json_decode(getSetting('repair_locations', '[]'), true);
    return [
        "status" => "success",
        "line_channel_access_token" => getSetting('line_channel_access_token', LINE_CHANNEL_ACCESS_TOKEN),
        "line_target_id" => getSetting('line_target_id', LINE_TARGET_ID),
        "repair_types" => $types,
        "repair_locations" => $locations
    ];
}

/**
 * 8. Update System Settings
 */
function updateSystemSettings($settings) {
    if (!$settings) {
        return ["status" => "error", "message" => "ข้อมูลตั้งค่าไม่ถูกต้อง"];
    }

    try {
        $lineToken = isset($settings['line_channel_access_token']) ? trim($settings['line_channel_access_token']) : '';
        $lineTarget = isset($settings['line_target_id']) ? trim($settings['line_target_id']) : '';
        $repairTypes = isset($settings['repair_types']) ? $settings['repair_types'] : [];
        $repairLocations = isset($settings['repair_locations']) ? $settings['repair_locations'] : [];

        setSetting('line_channel_access_token', $lineToken);
        setSetting('line_target_id', $lineTarget);
        setSetting('repair_types', json_encode($repairTypes, JSON_UNESCAPED_UNICODE));
        setSetting('repair_locations', json_encode($repairLocations, JSON_UNESCAPED_UNICODE));

        return ["status" => "success", "message" => "บันทึกตั้งค่าระบบเรียบร้อยแล้ว"];
    } catch (Exception $e) {
        return ["status" => "error", "message" => "ไม่สามารถบันทึกตั้งค่าระบบได้: " . $e->getMessage()];
    }
}

/**
 * 9. Change Admin Password
 */
function changeAdminPassword($currentPassword, $newPassword) {
    if (empty($currentPassword) || empty($newPassword)) {
        return ["status" => "error", "message" => "กรุณากรอกข้อมูลให้ครบถ้วน"];
    }

    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM `admin_users` WHERE `username` = 'admin'");
        $stmt->execute();
        $admin = $stmt->fetch();

        if (!$admin || !password_verify(trim($currentPassword), $admin['password'])) {
            return ["status" => "error", "message" => "รหัสผ่านปัจจุบันไม่ถูกต้อง"];
        }

        $newHash = password_hash(trim($newPassword), PASSWORD_DEFAULT);
        $updateStmt = $db->prepare("UPDATE `admin_users` SET `password` = ? WHERE `username` = 'admin'");
        $updateStmt->execute([$newHash]);

        return ["status" => "success", "message" => "เปลี่ยนรหัสผ่านเรียบร้อยแล้ว"];
    } catch (Exception $e) {
        return ["status" => "error", "message" => "เกิดข้อผิดพลาด: " . $e->getMessage()];
    }
}

/**
 * 10. Admin Logout
 */
function adminLogout() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    return ["status" => "success", "message" => "ออกจากระบบสำเร็จ"];
}

/**
 * Helper: Send LINE Push Notifications
 */
function sendLineMessageApi($messageText, $imageUrl = null) {
    try {
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

        // LINE Messaging API requires secure HTTPS URLs for images.
        // If testing on localhost (HTTP), we fallback to sending only the text message.
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

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("LINE API returned HTTP Code $httpCode. Response: $response");
            return false;
        }

        return true;
    } catch (Exception $e) {
        error_log("LINE API Error: " . $e->getMessage());
        return false;
    }
}

/**
 * 11. Test LINE Notification configuration
 */
function testLineNotification($token, $target) {
    if (empty($token) || empty($target)) {
        return ["status" => "error", "message" => "กรุณากรอกข้อมูล Token และ Target ID ก่อนการทดสอบ"];
    }
    
    try {
        $url = "https://api.line.me/v2/bot/message/push";
        $headers = [
            "Content-Type: application/json",
            "Authorization: Bearer " . $token
        ];
        $messages = [
            [
                "type" => "text",
                "text" => "🔔 [ระบบแจ้งซ่อม งานอาคารสถานที่]\nทดสอบการส่งข้อความแจ้งเตือนผ่าน LINE Messaging API สำเร็จแล้ว!"
            ]
        ];
        $payload = [
            "to" => $target,
            "messages" => $messages
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return ["status" => "error", "message" => "LINE API returned HTTP Code $httpCode. Response: $response"];
        }
        return ["status" => "success", "message" => "ส่งข้อความทดสอบเข้ากลุ่มไลน์สำเร็จแล้ว!"];
    } catch (Exception $e) {
        return ["status" => "error", "message" => $e->getMessage()];
    }
}
?>
