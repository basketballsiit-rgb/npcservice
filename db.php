<?php
/**
 * Database Connection and Setup Helper
 */

require_once __DIR__ . '/config.php';

try {
    // 1. Connect to MySQL Server (Without specifying DB first, in case it doesn't exist)
    $dsn = "mysql:host=" . DB_HOST . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

    // 2. Create database if not exists
    $dbName = DB_NAME;
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // 3. Connect to the specific database
    $pdo->exec("USE `$dbName`");

    // 4. Create admin_users table
    $createAdminTable = "CREATE TABLE IF NOT EXISTS `admin_users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `username` VARCHAR(50) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $pdo->exec($createAdminTable);

    // 5. Create repairs table
    $createRepairsTable = "CREATE TABLE IF NOT EXISTS `repairs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `reference_id` VARCHAR(50) NOT NULL UNIQUE,
        `request_date` DATE NOT NULL,
        `status` VARCHAR(50) NOT NULL DEFAULT 'ยังไม่ดำเนินการ',
        `full_name` VARCHAR(100) NOT NULL,
        `position` VARCHAR(100) NOT NULL,
        `phone` VARCHAR(20) NOT NULL,
        `contact_back` VARCHAR(20) NOT NULL DEFAULT 'ต้องการ',
        `repair_type` VARCHAR(100) NOT NULL,
        `location_building` VARCHAR(100) NOT NULL,
        `location_room` VARCHAR(100) NOT NULL,
        `details` TEXT NOT NULL,
        `file_ids` TEXT DEFAULT NULL,
        `file_urls` TEXT DEFAULT NULL,
        `notes` TEXT DEFAULT NULL,
        `semester` VARCHAR(20) DEFAULT NULL,
        `academic_year` VARCHAR(20) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $pdo->exec($createRepairsTable);

    // Alter table to add semester and academic_year to existing tables
    try {
        $pdo->exec("ALTER TABLE `repairs` ADD COLUMN `semester` VARCHAR(20) DEFAULT NULL AFTER `notes`");
    } catch (Exception $e) {
        // ignore if exists
    }
    try {
        $pdo->exec("ALTER TABLE `repairs` ADD COLUMN `academic_year` VARCHAR(20) DEFAULT NULL AFTER `semester`");
    } catch (Exception $e) {
        // ignore if exists
    }

    // 6. Create settings table
    $createSettingsTable = "CREATE TABLE IF NOT EXISTS `settings` (
        `setting_key` VARCHAR(50) PRIMARY KEY,
        `setting_value` TEXT NULL,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $pdo->exec($createSettingsTable);

    // 7. Seed Default Admin User if table is empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM `admin_users`");
    $adminCount = $stmt->fetchColumn();
    if ($adminCount == 0) {
        $defaultUser = 'admin';
        $defaultHash = password_hash('1234', PASSWORD_DEFAULT);
        $insertAdmin = $pdo->prepare("INSERT INTO `admin_users` (`username`, `password`) VALUES (?, ?)");
        $insertAdmin->execute([$defaultUser, $defaultHash]);
    }

    // 8. Seed Default Settings if table is empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM `settings`");
    $settingsCount = $stmt->fetchColumn();
    if ($settingsCount == 0) {
        $defaultSettings = [
            'line_channel_access_token' => LINE_CHANNEL_ACCESS_TOKEN,
            'line_target_id' => LINE_TARGET_ID,
            'repair_types' => json_encode([
                "ระบบไฟฟ้า/เครื่องปรับอากาศ",
                "การซ่อมบำรุงทั่วไป (งานอาคาร)"
            ], JSON_UNESCAPED_UNICODE),
            'repair_locations' => json_encode([
                "อาคาร 1 สามัญสัมพันธ์",
                "อาคาร 2 ระยะสั้นเสริมสวย",
                "อาคาร 3 เทคโนโลยีสารสนเทศ",
                "อาคาร 4 ระยะสั้นคหกรรม",
                "อาคาร 5 ห้องเรียนทฤษฎี",
                "อาคาร 6 ตึก 60 ปีอาชีวะ",
                "อาคาร 7 อาคารวิทยบริการ",
                "อาคาร 8 ช่างอุตสาหกรรม",
                "อาคารลานสายน้ำผึ้ง",
                "อาคารหลังคาเอนกประสงค์ (โดม)",
                "อาคารโรงอาหาร",
                "อาคารศูนย์บ่มเพาะ"
            ], JSON_UNESCAPED_UNICODE)
        ];

        $insertSetting = $pdo->prepare("INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES (?, ?)");
        foreach ($defaultSettings as $key => $value) {
            $insertSetting->execute([$key, $value]);
        }
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

/**
 * Helper function to retrieve the DB connection instance
 */
function getDB() {
    global $pdo;
    return $pdo;
}

/**
 * Get setting value from database
 */
function getSetting($key, $default = null) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT `setting_value` FROM `settings` WHERE `setting_key` = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return ($val !== false) ? $val : $default;
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Set setting value in database
 */
function setSetting($key, $value) {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES (?, ?) 
                              ON DUPLICATE KEY UPDATE `setting_value` = ?");
        $stmt->execute([$key, $value, $value]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>
