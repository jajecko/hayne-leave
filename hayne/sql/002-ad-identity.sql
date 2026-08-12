-- HAYNE Leave stable Active Directory identity mapping.
-- Existing installations should apply this idempotent migration explicitly
-- before the first write-enabled AD sync. New MySQL volumes receive it via
-- docker-entrypoint-initdb.d.

CREATE TABLE IF NOT EXISTS `hayne_ad_identity` (
    `user_id` int NOT NULL,
    `object_guid` char(36) NOT NULL,
    `distinguished_name` varchar(1024) NOT NULL,
    `last_seen_at` datetime NOT NULL,
    `last_synced_at` datetime NOT NULL,
    `source_dc` varchar(255) NOT NULL,
    PRIMARY KEY (`user_id`),
    UNIQUE KEY `object_guid` (`object_guid`),
    KEY `distinguished_name` (`distinguished_name`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
