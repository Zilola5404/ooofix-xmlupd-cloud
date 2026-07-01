-- Добавить MEMBER_ID в старые установки (таблица создана до появления колонки)
-- mysql -u btops_app -p btops_app < database/migrations/002_add_portal_member_id.sql

ALTER TABLE portals
    ADD COLUMN MEMBER_ID VARCHAR(64) DEFAULT NULL AFTER DOMAIN;
