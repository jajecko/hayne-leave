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

CREATE TABLE IF NOT EXISTS `hayne_statutory_leave_policies` (
    `policy_code` varchar(32) NOT NULL,
    `leave_type_id` int(11) NOT NULL,
    `enabled` tinyint(1) NOT NULL DEFAULT 1,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`policy_code`),
    KEY `leave_type_id` (`leave_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hayne_caregiver_request_meta` (
    `leave_id` int(11) NOT NULL,
    `person_name` varchar(190) NOT NULL,
    `relation_code` varchar(32) NOT NULL,
    `household_address` varchar(255) DEFAULT NULL,
    `care_reason` text NOT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`leave_id`),
    KEY `relation_code` (`relation_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hayne_force_majeure_request_meta` (
    `leave_id` int(11) NOT NULL,
    `event_code` varchar(16) NOT NULL,
    `immediate_presence` tinyint(1) NOT NULL DEFAULT 1,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`leave_id`),
    KEY `event_code` (`event_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hayne_childcare_year_allocations` (
    `employee_id` int(11) NOT NULL,
    `year` smallint(4) NOT NULL,
    `granted_days` tinyint(1) NOT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`employee_id`, `year`),
    KEY `year` (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
