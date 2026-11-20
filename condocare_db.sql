-- CondoCare Condominium Management System Database Schema
-- Rapid Application Development Assignment (DCS2102)

CREATE DATABASE IF NOT EXISTS `condocare_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `condocare_db`;

-- Drop existing tables to ensure clean setup
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `blacklist`;
DROP TABLE IF EXISTS `maintenance_requests`;
DROP TABLE IF EXISTS `bookings`;
DROP TABLE IF EXISTS `facilities`;
DROP TABLE IF EXISTS `technicians`;
DROP TABLE IF EXISTS `admins`;
DROP TABLE IF EXISTS `residents`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Users table (stores authentication and basic credentials for both residents and admins)
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(25) NOT NULL,
  `role` ENUM('resident', 'admin') NOT NULL DEFAULT 'resident',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Residents table (stores unit details, tower, move-in date)
CREATE TABLE `residents` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `unit_no` VARCHAR(20) NOT NULL,
  `tower` VARCHAR(20) NOT NULL DEFAULT 'Tower A',
  `move_in_date` DATE NULL,
  CONSTRAINT `fk_resident_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Admins table (stores staff identification for management users)
CREATE TABLE `admins` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `staff_id` VARCHAR(30) NOT NULL UNIQUE,
  CONSTRAINT `fk_admin_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Technicians table (stores maintenance technicians and their specialties)
CREATE TABLE `technicians` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(25) NOT NULL,
  `email` VARCHAR(100) NULL,
  `specialty` ENUM('Plumbing', 'Electrical', 'Air-con', 'General', 'Lift', 'Other') NOT NULL DEFAULT 'General',
  `status` ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Facilities table (stores bookable amenities and hourly fees)
CREATE TABLE `facilities` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `hourly_rate` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  `description` TEXT NULL,
  `open_hour` INT NOT NULL DEFAULT 8,
  `close_hour` INT NOT NULL DEFAULT 22,
  `status` ENUM('Available', 'Maintenance') NOT NULL DEFAULT 'Available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Bookings table (stores facility reservations made by residents)
CREATE TABLE `bookings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `booking_no` VARCHAR(25) NOT NULL UNIQUE,
  `resident_id` INT NOT NULL,
  `facility_id` INT NOT NULL,
  `booking_date` DATE NOT NULL,
  `start_hour` INT NOT NULL,
  `end_hour` INT NOT NULL,
  `total_amount` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  `remarks` TEXT NULL,
  `status` ENUM('pending', 'approved', 'cancelled', 'completed', 'rejected') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_booking_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_booking_facility` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Maintenance Requests table (stores defect reports and repair status)
CREATE TABLE `maintenance_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ticket_no` VARCHAR(25) NOT NULL UNIQUE,
  `resident_id` INT NOT NULL,
  `category` ENUM('Plumbing', 'Electrical', 'Air-con', 'Lift', 'Other') NOT NULL,
  `location_type` ENUM('My unit', 'Common area') NOT NULL DEFAULT 'My unit',
  `title` VARCHAR(150) NOT NULL,
  `description` TEXT NOT NULL,
  `urgency` ENUM('Low', 'Medium', 'High') NOT NULL DEFAULT 'Medium',
  `preferred_date` DATE NULL,
  `photo_path` VARCHAR(255) NULL,
  `technician_id` INT NULL,
  `status` ENUM('reported', 'review', 'progress', 'resolved', 'cancelled') NOT NULL DEFAULT 'reported',
  `admin_notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_request_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_request_technician` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Blacklist table (residents barred from booking facilities)
CREATE TABLE `blacklist` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `resident_id` INT NOT NULL,
  `reason` ENUM('Unpaid fees', 'Facility damage', 'No-show / late cancellation', 'Noise / misconduct', 'Other') NOT NULL DEFAULT 'Other',
  `notes` TEXT NULL,
  `banned_until` DATE NULL COMMENT 'NULL = indefinite',
  `status` ENUM('Active', 'Lifted') NOT NULL DEFAULT 'Active',
  `added_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `lifted_at` TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT `fk_blacklist_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_blacklist_admin` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================
-- SAMPLE DATA POPULATION
-- =======================================================

-- Users (Resident demo: resident@email.com / 12345678, Admin demo: admin@email.com / admin123)
-- Hash for '12345678' is: $2y$10$IGjo8WXmQjF10774PKCBMehk74liwMPMKBGQEUD5UaBO3XSt2AdrG
-- Hash for 'admin123' is: $2y$10$KWp3mOMF1qBEm4AKvFxxEeP3tR5QpTAQJzcHwTu5iMcGyow7MWlVu
INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `phone`, `role`) VALUES
(1, 'Tan Mei Ling', 'resident@email.com', '$2y$10$IGjo8WXmQjF10774PKCBMehk74liwMPMKBGQEUD5UaBO3XSt2AdrG', '012-345 6789', 'resident'),
(2, 'Tan Wei Hong', 'weihong.t@email.com', '$2y$10$IGjo8WXmQjF10774PKCBMehk74liwMPMKBGQEUD5UaBO3XSt2AdrG', '016-221 0843', 'resident'),
(3, 'Nur Aisyah', 'aisyah@email.com', '$2y$10$IGjo8WXmQjF10774PKCBMehk74liwMPMKBGQEUD5UaBO3XSt2AdrG', '019-887 1120', 'resident'),
(4, 'Lim Jia Hui', 'jiahui.lim@email.com', '$2y$10$IGjo8WXmQjF10774PKCBMehk74liwMPMKBGQEUD5UaBO3XSt2AdrG', '011-2345 6780', 'resident'),
(5, 'Wong Kah Meng', 'kahmeng@email.com', '$2y$10$IGjo8WXmQjF10774PKCBMehk74liwMPMKBGQEUD5UaBO3XSt2AdrG', '012-998 8776', 'resident'),
(6, 'Rachel Lim', 'admin@email.com', '$2y$10$KWp3mOMF1qBEm4AKvFxxEeP3tR5QpTAQJzcHwTu5iMcGyow7MWlVu', '03-1234 5678', 'admin'),
(7, 'Daniel Tan', 'daniel.tan@email.com', '$2y$10$KWp3mOMF1qBEm4AKvFxxEeP3tR5QpTAQJzcHwTu5iMcGyow7MWlVu', '012-888 9900', 'admin'),
(8, 'Nurul Huda', 'huda@email.com', '$2y$10$KWp3mOMF1qBEm4AKvFxxEeP3tR5QpTAQJzcHwTu5iMcGyow7MWlVu', '017-555 6677', 'admin');

-- Residents
INSERT INTO `residents` (`id`, `user_id`, `unit_no`, `tower`, `move_in_date`) VALUES
(1, 1, 'A-12-03', 'Tower A', '2025-01-15'),
(2, 2, 'B-03-07', 'Tower B', '2025-03-20'),
(3, 3, 'A-05-02', 'Tower A', '2025-06-10'),
(4, 4, 'B-08-11', 'Tower B', '2025-08-01'),
(5, 5, 'A-01-05', 'Tower A', '2026-02-12');

-- Admins
INSERT INTO `admins` (`id`, `user_id`, `staff_id`) VALUES
(1, 6, 'ST-001'),
(2, 7, 'ST-002'),
(3, 8, 'ST-003');

-- Technicians
INSERT INTO `technicians` (`id`, `full_name`, `phone`, `email`, `specialty`, `status`) VALUES
(1, 'Ahmad Faiz', '012-111 2233', 'faiz@email.com', 'Plumbing', 'Active'),
(2, 'Siti Aminah', '013-222 3344', 'siti@email.com', 'General', 'Active'),
(3, 'Kumar R.', '014-333 4455', 'kumar@email.com', 'Electrical', 'Active'),
(4, 'Lee Wei Jie', '015-444 5566', 'weijie@email.com', 'Air-con', 'Inactive');

-- Facilities
INSERT INTO `facilities` (`id`, `name`, `hourly_rate`, `description`, `open_hour`, `close_hour`, `status`) VALUES
(1, 'Function Hall', 50.00, 'Multipurpose event hall suitable for gatherings and private functions with capacity of 100 pax.', 8, 22, 'Available'),
(2, 'Badminton Court', 10.00, 'Indoor tournament-grade wooden floor badminton court with professional lighting.', 8, 22, 'Available'),
(3, 'BBQ Pit', 30.00, 'Outdoor sheltered BBQ pit area beside swimming pool with sink and seating benches.', 8, 22, 'Available'),
(4, 'Gymnasium', 0.00, 'Resident-exclusive air-conditioned fitness center with cardio and strength equipment.', 8, 22, 'Available'),
(5, 'Swimming Pool', 0.00, 'Olympic-sized swimming pool and children wading pool with lifeguard on duty.', 8, 22, 'Maintenance');

-- Bookings (Matching mockups BK-0231, BK-0228, BK-0219, BK-0210, etc.)
INSERT INTO `bookings` (`id`, `booking_no`, `resident_id`, `facility_id`, `booking_date`, `start_hour`, `end_hour`, `total_amount`, `remarks`, `status`) VALUES
(1, 'BK-0231', 1, 1, '2026-11-12', 18, 22, 200.00, 'Family birthday gathering', 'pending'),
(2, 'BK-0233', 4, 1, '2026-11-15', 14, 18, 200.00, 'Resident club meeting', 'pending'),
(3, 'BK-0228', 1, 2, '2026-11-10', 20, 22, 20.00, 'Weekly badminton session', 'approved'),
(4, 'BK-0219', 1, 3, '2026-10-02', 19, 22, 90.00, 'Barbecue with neighbors', 'completed'),
(5, 'BK-0210', 1, 2, '2026-09-28', 10, 11, 10.00, 'Morning badminton game', 'cancelled');

-- Maintenance Requests (Matching mockups MT-0149, MT-0145, MT-0139, MT-0142, MT-0131, MT-0120)
INSERT INTO `maintenance_requests` (`id`, `ticket_no`, `resident_id`, `category`, `location_type`, `title`, `description`, `urgency`, `preferred_date`, `photo_path`, `technician_id`, `status`, `admin_notes`) VALUES
(1, 'MT-0149', 5, 'Other', 'Common area', 'Lobby door closer broken', 'Ground floor main lobby glass entrance door is slamming shut loudly and does not soft-close.', 'High', '2026-11-05', NULL, NULL, 'reported', NULL),
(2, 'MT-0145', 1, 'Lift', 'Common area', 'Lift button not working', 'The 12th floor button on Lift B inside Tower A is unresponsive when pressed.', 'High', '2026-11-02', NULL, NULL, 'reported', NULL),
(3, 'MT-0139', 1, 'Air-con', 'My unit', 'Bedroom air-con not cooling', 'Master bedroom unit blowing warm air even when temperature set to 18C and fan high.', 'Medium', '2026-10-20', NULL, 4, 'review', 'Inspected on 21/10, awaiting compressor refrigerant part.'),
(4, 'MT-0142', 1, 'Plumbing', 'My unit', 'Leaking kitchen sink pipe', 'Under-sink PVC drainage elbow pipe has a small crack and water drips onto cabinet bottom.', 'Medium', '2026-10-24', NULL, 1, 'progress', 'Assigned to Ahmad Faiz, pipe replacement in progress.'),
(5, 'MT-0131', 1, 'Electrical', 'Common area', 'Corridor light flickering', 'Ceiling fluorescent light outside Unit A-12-04 is constantly flashing and needs tube change.', 'Low', '2026-09-12', NULL, 3, 'resolved', 'Tube replaced and ballast tested. Completed.'),
(6, 'MT-0120', 1, 'Other', 'Common area', 'Cockroaches in bin room', 'Pest sighting noticed near 12th floor rubbish chute room.', 'Low', '2026-09-01', NULL, NULL, 'cancelled', 'Fumigation vendor already scheduled for building pest control.');

-- Blacklist (residents barred from booking facilities)
INSERT INTO `blacklist` (`id`, `resident_id`, `reason`, `notes`, `banned_until`, `status`, `added_by`, `created_at`, `lifted_at`) VALUES
(1, 3, 'Facility damage', 'Damaged BBQ Pit grill and left the area uncleaned after booking.', NULL, 'Active', 6, '2026-11-02 10:15:00', NULL),
(2, 5, 'No-show / late cancellation', 'Three no-shows on Badminton Court bookings in October.', '2026-12-31', 'Active', 7, '2026-11-09 14:30:00', NULL),
(3, 2, 'Noise / misconduct', 'Noise complaints during a Function Hall event. Resolved after meeting with management.', '2026-10-31', 'Active', 6, '2026-09-20 09:00:00', NULL),
(4, 4, 'Unpaid fees', 'Outstanding Function Hall fee, since settled.', NULL, 'Lifted', 8, '2026-08-14 11:20:00', '2026-09-01 16:45:00');
