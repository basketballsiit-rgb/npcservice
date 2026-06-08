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
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
