<?php
/**
 * Temporary script to delete test repair records starting with "นายนิพนธ์ ทดสอบ"
 */

require_once __DIR__ . '/db.php';

echo "<pre>";
echo "Starting Database Cleanup...\n";
echo "-----------------------------------------\n";

try {
    $db = getDB();
    $sql = "DELETE FROM `repairs` WHERE `full_name` LIKE 'นายนิพนธ์ ทดสอบ%'";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    
    $deletedCount = $stmt->rowCount();
    echo "Deleted count: $deletedCount records\n";
    echo "Database cleanup completed successfully!\n";
} catch (Exception $e) {
    echo "Error executing cleanup: " . $e->getMessage() . "\n";
}

echo "-----------------------------------------\n";
echo "Cleanup script finished.\n";
echo "</pre>";
?>
