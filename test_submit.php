<?php
/**
 * Diagnostic script for testing complete form submission and notification flow
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/api.php';

echo "<pre>";
echo "Starting submitRepairForm Diagnostic...\n";
echo "-----------------------------------------\n";

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

echo "Form Object:\n";
print_r($formObject);
echo "-----------------------------------------\n";

try {
    // Let's temporarily print the LINE API response inside submitRepairForm if possible,
    // or just capture the execution.
    $res = submitRepairForm($formObject);
    echo "Result of submitRepairForm:\n";
    print_r($res);
} catch (Exception $e) {
    echo "Exception occurred: " . $e->getMessage() . "\n";
}

echo "-----------------------------------------\n";
echo "Diagnostic Finished.\n";
echo "</pre>";
?>
