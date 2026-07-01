-- Добавить недостающие колонки в portals (старые БД на хостинге)
-- Выполняйте только те ALTER, которые не падают с "Duplicate column name"
-- mysql -u btops_app -p btops_app < database/migrations/003_upgrade_portals.sql

ALTER TABLE portals ADD COLUMN MEMBER_ID VARCHAR(64) DEFAULT NULL AFTER DOMAIN;
ALTER TABLE portals ADD COLUMN ACCESS_TOKEN TEXT AFTER MEMBER_ID;
ALTER TABLE portals ADD COLUMN REFRESH_TOKEN TEXT AFTER ACCESS_TOKEN;
ALTER TABLE portals ADD COLUMN EXPIRES_AT DATETIME DEFAULT NULL AFTER REFRESH_TOKEN;
ALTER TABLE portals ADD COLUMN INSTALLED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER EXPIRES_AT;
