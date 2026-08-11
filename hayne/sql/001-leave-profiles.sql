-- HAYNE Leave persistent annual vacation settings and request metadata.
-- The official MySQL entrypoint runs init files in MYSQL_DATABASE, so this
-- script intentionally does not hardcode a database name.
-- Existing persistent installations self-upgrade through matching
-- CREATE TABLE IF NOT EXISTS guards in the application models.

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

CREATE TABLE IF NOT EXISTS `hayne_leave_request_meta` (
    `leave_id` int(11) NOT NULL,
    `on_demand` tinyint(1) NOT NULL DEFAULT 0,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`leave_id`),
    KEY `on_demand` (`on_demand`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
