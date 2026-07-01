-- Синхронизация токенов: если есть и access_token, и ACCESS_TOKEN
-- mysql -u btops_app -p btops_app < database/migrations/004_sync_portal_tokens.sql

UPDATE portals
SET ACCESS_TOKEN = access_token
WHERE (ACCESS_TOKEN IS NULL OR ACCESS_TOKEN = '')
  AND access_token IS NOT NULL
  AND access_token <> '';

UPDATE portals
SET REFRESH_TOKEN = refresh_token
WHERE (REFRESH_TOKEN IS NULL OR REFRESH_TOKEN = '')
  AND refresh_token IS NOT NULL
  AND refresh_token <> '';

UPDATE portals
SET EXPIRES_AT = expires_at
WHERE EXPIRES_AT IS NULL
  AND expires_at IS NOT NULL;

UPDATE portals
SET MEMBER_ID = member_id
WHERE (MEMBER_ID IS NULL OR MEMBER_ID = '')
  AND member_id IS NOT NULL
  AND member_id <> '';
