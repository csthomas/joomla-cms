DELIMITER |
-- The above custom delimiter informs joomla parser how to split queries

ALTER TABLE `#__session` MODIFY `session_id` varbinary(192) NOT NULL |
ALTER TABLE `#__session` MODIFY `guest` tinyint(3) unsigned NOT NULL DEFAULT 1 |
ALTER TABLE `#__session` MODIFY `time` int(10) unsigned NOT NULL DEFAULT 0 |
ALTER TABLE `#__session` MODIFY `userid` int(11) NOT NULL DEFAULT 0 |
ALTER TABLE `#__session` MODIFY `username` varchar(150) NOT NULL DEFAULT '' |

DROP FUNCTION IF EXISTS SESSION_BASE64_DECODE |

CREATE FUNCTION SESSION_BASE64_DECODE (input BLOB)
	RETURNS BLOB
	CONTAINS SQL
	DETERMINISTIC
BEGIN
	DECLARE ret BLOB DEFAULT '';
	DECLARE done TINYINT DEFAULT 0;
	DECLARE base64_data CHAR(64) DEFAULT 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-,';

	IF input IS NULL THEN
		RETURN NULL;
	END IF;

	WHILE NOT done DO BEGIN
		DECLARE accum_value BIGINT UNSIGNED DEFAULT 0;
		DECLARE in_count TINYINT DEFAULT 0;
		DECLARE out_count TINYINT DEFAULT 3;

		WHILE in_count < 4 DO BEGIN
			DECLARE first_char BINARY(1);

			IF LENGTH(input) = 0 THEN
				RETURN ret;
			END IF;

			SET first_char = SUBSTRING(input,1,1);
			SET input = SUBSTRING(input,2);

			BEGIN
				DECLARE tempval TINYINT UNSIGNED;
				DECLARE error TINYINT DEFAULT 0;
				DECLARE base64_getval CURSOR FOR SELECT c-1 FROM (SELECT LOCATE(BINARY first_char, base64_data) AS c) b WHERE c > 0;
				DECLARE CONTINUE HANDLER FOR SQLSTATE '02000' SET error = 1;

				OPEN base64_getval;
				FETCH base64_getval INTO tempval;
				CLOSE base64_getval;

				IF NOT error THEN
					SET accum_value = (accum_value << 6) + tempval;
					SET in_count = in_count + 1;
				END IF;
			END;
		END; END WHILE;

		-- We've now accumulated 24 bits; deaccumulate into bytes
		-- We have to work from the left, so use the third byte position and shift left
		WHILE out_count > 0 DO BEGIN
			SET ret = CONCAT(ret,CHAR((accum_value & 0xff0000) >> 16));
			SET out_count = out_count - 1;
			SET accum_value = (accum_value << 8) & 0xffffff;
		END; END WHILE;

	END; END WHILE;

	RETURN ret;
END |

-- First change session id characters [A-Za-z0-9-,] to set of base64 characters [A-Za-Z0-9+/].
-- Base64 has to have length % 4 == 0. Replace missing characters by 'A' and decode session_id to binary.
UPDATE `#__session`
SET `session_id` = SESSION_BASE64_DECODE(concat(`session_id`, repeat('A', (4 - (length(`session_id`) % 4)) % 4)))
WHERE length(`session_id`) > 0 AND `session_id` NOT RLIKE '[^0-9A-Za-z,-]' |

DROP FUNCTION IF EXISTS SESSION_BASE64_DECODE |
