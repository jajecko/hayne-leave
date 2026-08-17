-- Central HAYNE Leave MVP registry. Runtime behavior remains unchanged in this PR.
CREATE TABLE IF NOT EXISTS `hayne_leave_type_registry` (
    `leave_type_id` int(11) NOT NULL,
    `policy_code` varchar(48) NOT NULL,
    `balance_mode` varchar(16) NOT NULL,
    `workflow_mode` varchar(16) NOT NULL,
    `privacy_mode` varchar(16) NOT NULL,
    `active_for_new_requests` tinyint(1) NOT NULL DEFAULT 1,
    `domain` varchar(16) NOT NULL DEFAULT 'LEAVE',
    `enabled` tinyint(1) NOT NULL DEFAULT 1,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`leave_type_id`),
    UNIQUE KEY `policy_code` (`policy_code`),
    KEY `active_new_requests` (`enabled`, `active_for_new_requests`, `domain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
