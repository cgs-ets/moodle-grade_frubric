-- ================================================
-- Template generated from Template Explorer using:
-- Create Procedure (New Menu).SQL
--
-- Use the Specify Values for Template Parameters
-- command (Ctrl-Shift-M) to fill in the parameter
-- values below.
--
-- This block of comments will not be included in
-- the definition of the procedure.
-- ================================================
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
-- =============================================
-- Author:		Veronica Bermegui
-- Create date: 22/05/23
-- Description:	Flexrubrics that were created before the option to enable/disable a criterion, needs to be updated to have
-- the attribute visible true to display all the criterions.
-- =============================================
CREATE PROCEDURE update_frubric_criteriajson

AS
BEGIN
	-- SET NOCOUNT ON added to prevent extra result sets from
	-- interfering with SELECT statements.
	SET NOCOUNT ON;
	DROP TABLE IF EXISTS  #tmpfcriteria
	SELECT id
		INTO #tmpfcriteria
		FROM  [dbo].[mdl_gradingform_frubric_criteria]
		WHERE JSON_VALUE(criteriajson , '$.visibility') IS NULL;

	UPDATE[dbo].[mdl_gradingform_frubric_criteria]
	SET criteriajson = JSON_MODIFY(criteriajson, '$.visibility' , 'true')
	WHERE
	 id IN (SELECT id FROM #tmpfcriteria);
END
GO
