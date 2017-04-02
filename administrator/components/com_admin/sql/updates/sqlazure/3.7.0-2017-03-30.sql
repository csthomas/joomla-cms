sp_rename "#__session", "#__session_old";

-- First change session id characters [A-Za-z0-9-,] to set of base64 characters [A-Za-Z0-9+/].
-- Base64 has to have length % 4 == 0. Replace missing characters by 'A' and decode session_id to binary.

SELECT CAST('' AS xml).value('xs:base64Binary(sql:column("s2.newId"))', 'varbinary(192)') AS "session_id",
	"client_id", "guest", cast("time" AS bigint) AS "time", "data", "userid", "username"
INTO "#__session"
FROM "#__session_old" s
INNER JOIN (
	SELECT "session_id", replace(replace("session_id", '-', '+'), ',', '/') + replicate('A', (4 - (len("session_id") % 4)) % 4) AS newId
	FROM "#__session_old"
) s2 ON s."session_id" = s2."session_id"
WHERE len(s."session_id") > 0 AND s."session_id" NOT LIKE '%[^0-9A-Za-z,-]%';

DROP TABLE "#__session_old";

ALTER TABLE "#__session" ALTER COLUMN "session_id" varbinary(192) NOT NULL;
ALTER TABLE "#__session" ADD CONSTRAINT "PK_#__session_session_id" PRIMARY KEY ("session_id") ON [PRIMARY];
ALTER TABLE "#__session" ADD DEFAULT (NULL) FOR "client_id";
ALTER TABLE "#__session" ALTER COLUMN "guest" tinyint NOT NULL;
ALTER TABLE "#__session" ADD DEFAULT (1) FOR "guest";
ALTER TABLE "#__session" ALTER COLUMN "time" bigint NOT NULL;
ALTER TABLE "#__session" ADD DEFAULT (0) FOR "time";
ALTER TABLE "#__session" ALTER COLUMN "userid" int NOT NULL;
ALTER TABLE "#__session" ADD DEFAULT (0) FOR "userid";
ALTER TABLE "#__session" ALTER COLUMN "username" nvarchar(150) NOT NULL;
ALTER TABLE "#__session" ADD DEFAULT ('') FOR "username";

CREATE INDEX "time" ON "#__session" ("time");
CREATE INDEX "userid" ON "#__session" ("userid");
