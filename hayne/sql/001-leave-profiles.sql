-- HAYNE Leave persistent annual vacation settings.
-- The application model also runs CREATE TABLE IF NOT EXISTS so existing
-- installations with a persistent MySQL volume self-upgrade safely.

USE jorani;

CREATE TABLE IF NOT EXISTS `hayne_leave_profiles` (
    `employee_id` int(11) NOT NULL,
    `vacation_type_id` int(11) NOT NULL,
    `annual_days` int(11) NOT NULL,
    `auto_renew` tinyint(1) NOT NULL DEFAULT 1,
    `effective_from_year` smallint(4) NOT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`employee_id`),
    KEY `vacation_type_id` (`vacation_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
