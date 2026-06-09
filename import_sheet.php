<?php
/**
 * One-time migration script to import historical Google Sheet data into MySQL
 */
require_once __DIR__ . '/db.php';

// Set display errors for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/html; charset=utf-8');

echo "<h2>NPC SERVICE - ระบบนำเข้าข้อมูล Google Sheet</h2>";

// 1. Fetch CSV Content from Google Sheets
$sheetId = "1kM8Z-2fEAtHH_vf9W5dJ59iVelKRUJs9rKx3PvhO42I";
$csvUrl = "https://docs.google.com/spreadsheets/d/" . $sheetId . "/export?format=csv";

echo "กำลังโหลดข้อมูลจาก Google Sheet...<br>";
$csvContent = @file_get_contents($csvUrl);
if ($csvContent === false) {
    die("<font color='red'>ข้อผิดพลาด: ไม่สามารถดาวน์โหลดข้อมูลจาก Google Sheet ได้</font>");
}

// 2. Parse CSV
$lines = str_getcsv($csvContent, "\n");
if (count($lines) <= 1) {
    die("<font color='red'>ข้อผิดพลาด: ไฟล์ CSV ไม่มีข้อมูล</font>");
}

// Get headers
$headers = str_getcsv($lines[0], ",");
echo "พบคอลัมน์หลัก: " . implode(", ", $headers) . "<br><br>";

$db = getDB();

// Helper function to calculate semester and academic year
function getSemesterAndYearFromDate($dateStr) {
    $time = strtotime($dateStr);
    $y = (int)date('Y', $time);
    $m = (int)date('n', $time);
    
    // Convert to Buddhist Era (BE)
    $beYear = $y + 543;
    
    if ($m >= 5 && $m <= 9) {
        return ['semester' => '1', 'year' => (string)$beYear];
    } elseif ($m >= 10 || $m <= 3) {
        $academicYear = ($m <= 3) ? ($beYear - 1) : $beYear;
        return ['semester' => '2', 'year' => (string)$academicYear];
    } else {
        // April/Summer
        return ['semester' => 'ฤดูร้อน', 'year' => (string)($beYear - 1)];
    }
}

$successCount = 0;
$skipCount = 0;
$errorCount = 0;

for ($i = 1; $i < count($lines); $i++) {
    if (trim($lines[$i]) === '') continue;
    
    $row = str_getcsv($lines[$i], ",");
    
    // If row size is too small or columns mismatch, skip or log
    if (count($row) < 12) {
        $errorCount++;
        echo "<font color='orange'>แถวที่ " . ($i + 1) . " รูปแบบข้อมูลไม่ครบ ข้ามรายการ</font><br>";
        continue;
    }
    
    $refId = trim($row[0]);
    $rawDate = trim($row[1]);
    $status = trim($row[2]);
    $fullName = trim($row[3]);
    $position = trim($row[4]);
    $phone = trim($row[5]);
    $contactBack = trim($row[6]);
    $locationStr = trim($row[7]);
    $detailStr = trim($row[8]);
    $fileIds = trim($row[9]);
    $fileUrls = trim($row[10]);
    $notes = trim($row[11]);
    
    // Check if duplicate refId exists
    $checkStmt = $db->prepare("SELECT COUNT(*) FROM `repairs` WHERE `reference_id` = ?");
    $checkStmt->execute([$refId]);
    if ($checkStmt->fetchColumn() > 0) {
        $skipCount++;
        continue;
    }
    
    // 1. Format date (from "27/11/2025, 7:00:00" to "2025-11-27")
    $datePart = explode(',', $rawDate)[0];
    $dateParts = explode('/', trim($datePart));
    if (count($dateParts) === 3) {
        $formattedDate = $dateParts[2] . '-' . $dateParts[1] . '-' . $dateParts[0];
    } else {
        // Fallback
        $formattedDate = date('Y-m-d');
    }
    
    // 2. Parse repair_type and details from detailStr
    // e.g. "[การซ่อมบำรุงทั่วไป (งานอาคาร)] ก๊อกอ่างล้างมือรั่ว"
    $repairType = "การซ่อมบำรุงทั่วไป (งานอาคาร)";
    $details = $detailStr;
    if (preg_match('/^\[(.*?)\]\s*(.*)$/u', $detailStr, $matches)) {
        $repairType = trim($matches[1]);
        $details = trim($matches[2]);
    }
    
    // 3. Map semester and year
    $semYearInfo = getSemesterAndYearFromDate($formattedDate);
    $semester = $semYearInfo['semester'];
    $academicYear = $semYearInfo['year'];
    
    // 4. Insert into database
    try {
        $sql = "INSERT INTO `repairs` 
                (`reference_id`, `request_date`, `status`, `full_name`, `position`, `phone`, `contact_back`, `repair_type`, `location_building`, `location_room`, `details`, `file_ids`, `file_urls`, `notes`, `semester`, `academic_year`) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $refId,
            $formattedDate,
            $status,
            $fullName,
            $position,
            $phone,
            $contactBack,
            $repairType,
            $locationStr, // map to building
            '-',          // map to room as default
            $details,
            $fileIds,
            $fileUrls,
            $notes,
            $semester,
            $academicYear
        ]);
        
        $successCount++;
    } catch (Exception $e) {
        $errorCount++;
        echo "<font color='red'>แถวที่ " . ($i + 1) . " ผิดพลาด: " . $e->getMessage() . "</font><br>";
    }
}

echo "<br><b>สรุปการทำงาน:</b><br>";
echo "✅ นำเข้าสำเร็จ: " . $successCount . " รายการ<br>";
echo "⚠️ ข้าม (ข้อมูลซ้ำ): " . $skipCount . " รายการ<br>";
echo "❌ ข้อผิดพลาด: " . $errorCount . " รายการ<br>";
echo "<br><font color='green'><b>การนำเข้าข้อมูลเสร็จสิ้นแล้ว!</b></font><br>";
?>
