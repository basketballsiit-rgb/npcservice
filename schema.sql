-- SQL Schema for NPC Repair Notification and Management System

CREATE DATABASE IF NOT EXISTS `npcservice_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `npcservice_db`;

-- 1. Table structure for table `admin_users`
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default admin user: Username 'admin', Password '1234' (hashed)
INSERT INTO `admin_users` (`id`, `username`, `password`) 
VALUES (1, 'admin', '$2y$10$tZ9c22zQy8h3u0x7L5b/uOzYyvW7m9P0Jv0K2K2vU7y8H8M8y8m9q')
ON DUPLICATE KEY UPDATE `username`='admin';

-- 2. Table structure for table `repairs`
CREATE TABLE IF NOT EXISTS `repairs` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Table structure for table `settings`
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key` VARCHAR(50) PRIMARY KEY,
  `setting_value` TEXT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default settings configuration
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('line_channel_access_token', 'cabR6U86yHWSaQcYKaFzuR5b4+YJ5ZHlie9IlAfj+3Dg2jzKTdNd6+u8PVVkaX28xU1asucvn6WzzVBUUJ7Ro6QyHPiSQA8I7h+af1UiUBs2KLSZVFGNalnuaoxKh1NffMjtOj/qno5AbgjKpvmkFQdB04t89/1O/w1cDnyilFU='),
('line_target_id', 'C6e6890ddf619793273e9f4df4fc0ef18'),
('repair_types', '[\"ระบบไฟฟ้า/เครื่องปรับอากาศ\",\"การซ่อมบำรุงทั่วไป (งานอาคาร)\"]'),
('repair_locations', '[\"อาคาร 1 สามัญสัมพันธ์\",\"อาคาร 2 ระยะสั้นเสริมสวย\",\"อาคาร 3 เทคโนโลยีสารสนเทศ\",\"อาคาร 4 ระยะสั้นคหกรรม\",\"อาคาร 5 ห้องเรียนทฤษฎี\",\"อาคาร 6 ตึก 60 ปีอาชีวะ\",\"อาคาร 7 อาคารวิทยบริการ\",\"อาคาร 8 ช่างอุตสาหกรรม\",\"อาคารลานสายน้ำผึ้ง\",\"อาคารหลังคาเอนกประสงค์ (โดม)\",\"อาคารโรงอาหาร\",\"อาคารศูนย์บ่มเพาะ\"]'),
('semesters', '[\"1\",\"2\"]'),
('academic_years', '[\"2568\",\"2569\",\"2570\"]'),
('current_semester', '1'),
('current_academic_year', '2569')
ON DUPLICATE KEY UPDATE `setting_key` = VALUES(`setting_key`);
