-- CondoCare: add Blacklist feature to an EXISTING condocare_db
-- Run this once in phpMyAdmin (SQL tab) if you have already imported condocare_db.sql.
-- A fresh import of the updated condocare_db.sql already includes this table.
USE `condocare_db`;

-- 8. Blacklist table (residents barred from booking facilities)
CREATE TABLE IF NOT EXISTS `blacklist` (
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

INSERT IGNORE INTO `blacklist` (`id`, `resident_id`, `reason`, `notes`, `banned_until`, `status`, `added_by`, `created_at`, `lifted_at`) VALUES
(1, 3, 'Facility damage', 'Damaged BBQ Pit grill and left the area uncleaned after booking.', NULL, 'Active', 6, '2026-11-02 10:15:00', NULL),
(2, 5, 'No-show / late cancellation', 'Three no-shows on Badminton Court bookings in October.', '2026-12-31', 'Active', 7, '2026-11-09 14:30:00', NULL),
(3, 2, 'Noise / misconduct', 'Noise complaints during a Function Hall event. Resolved after meeting with management.', '2026-10-31', 'Active', 6, '2026-09-20 09:00:00', NULL),
(4, 4, 'Unpaid fees', 'Outstanding Function Hall fee, since settled.', NULL, 'Lifted', 8, '2026-08-14 11:20:00', '2026-09-01 16:45:00');
