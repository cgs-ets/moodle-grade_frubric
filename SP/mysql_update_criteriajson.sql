CREATE DEFINER=`root`@`localhost` PROCEDURE `update_frubric_criteriajson`()
BEGIN
-- Get all the ids of criterion that don't have the visibility attribute in their criteriajson
DROP TEMPORARY TABLE IF EXISTS tmpassetlist; 
CREATE TEMPORARY TABLE tmpassetlist AS
(SELECT id
				   FROM	mdl_gradingform_frubric_criteria
                   WHERE JSON_EXTRACT(criteriajson , '$.visibility') IS NULL
);

-- Add the visibility attribute to the JSON
UPDATE mdl_gradingform_frubric_criteria 
SET 
    `criteriajson` = JSON_INSERT(`criteriajson`, '$.visibility', TRUE)
WHERE
    id IN (SELECT 
            id
        FROM
            tmpassetlist)
	AND id <> 0; -- If this is not set, it throws an Error code 1175.
END