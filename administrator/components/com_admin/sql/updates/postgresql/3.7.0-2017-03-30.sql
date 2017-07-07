ALTER TABLE "#__session" ALTER COLUMN "session_id" DROP DEFAULT;
ALTER TABLE "#__session" ALTER COLUMN "session_id" TYPE bytea USING "session_id"::bytea;
ALTER TABLE "#__session" ALTER COLUMN "session_id" SET NOT NULL;
ALTER TABLE "#__session" ALTER COLUMN "guest" SET NOT NULL;
ALTER TABLE "#__session" ALTER COLUMN "time" DROP DEFAULT;
ALTER TABLE "#__session" ALTER COLUMN "time" TYPE bigint USING "time"::bigint;
ALTER TABLE "#__session" ALTER COLUMN "time" SET NOT NULL;
ALTER TABLE "#__session" ALTER COLUMN "time" SET DEFAULT 0;
ALTER TABLE "#__session" ALTER COLUMN "userid" SET NOT NULL;
ALTER TABLE "#__session" ALTER COLUMN "username" SET NOT NULL;

-- First change session id characters [A-Za-z0-9-,] to set of base64 characters [A-Za-Z0-9+/].
-- Base64 has to have length % 4 == 0. Replace missing characters by 'A' and decode session_id to binary.
UPDATE "#__session"
SET "session_id" = decode(replace(replace(encode("session_id", 'escape'), '-', '+'), ',', '/')
	|| repeat('A', (4 - (length("session_id") % 4)) % 4), 'base64')
WHERE length("session_id") > 0 AND encode("session_id", 'escape') !~ '[^A-Za-z0-9,-]';
