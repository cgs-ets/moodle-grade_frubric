<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
/**
 * Grade outcomes based on frubric criteria.
 *
 * @package   gradingform_frubric
 * @copyright 2023 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace gradingform_frubric\task;
defined('MOODLE_INTERNAL') || die();

class cron_grade_outcomes extends \core\task\scheduled_task {

    // Use the logging trait to get some nice, juicy, logging.
    use \core\task\logging_trait;

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('cron_grade_outcomes', 'gradingform_frubric');
    }

    /**
     * Execute the scheduled task.
     */
    public function execute() {
        global $CFG, $DB, $USER;

        // Get last run.
        $lastrun = $DB->get_field('config', 'value', ['name' => 'frubric_gradeoutcomes_lastrun']);
        if ($lastrun === false) {
            // First run ever.
            $DB->insert_record('config', ['name' => 'frubric_gradeoutcomes_lastrun', 'value' => time()]);
            $lastrun = time();
        }

        // Immediately update last run time.
        $DB->execute("UPDATE {config} SET value = ? WHERE name = 'frubric_gradeoutcomes_lastrun'", [time()]);

        // Find frubric grades that have changed since last run.
        $this->log("Looking for frubric grades since last run: $lastrun");

        $startofmonth = strtotime(date('Y-m-01 00:00:00'));

        // $sql = "SELECT DISTINCT
        //     CONCAT(gi.id, '-', gg.userid) AS gradeiduserid,
        //     gi.*,
        //     gg.userid,
        //     gg.usermodified
        //     FROM {grade_items} gi
        //     INNER JOIN {grade_grades} gg
        //         ON gg.itemid = gi.id
        //     INNER JOIN {grade_outcomes_courses} oc
        //         ON oc.courseid = gi.courseid
        //     INNER JOIN {modules} m
        //         ON m.name = gi.itemmodule
        //     INNER JOIN {course_modules} cm
        //         ON cm.instance = gi.iteminstance
        //         AND cm.course = gi.courseid
        //         AND cm.module = m.id
        //     INNER JOIN {context} c
        //         ON c.instanceid = cm.id
        //     INNER JOIN {grading_areas} ga
        //         ON ga.contextid = c.id
        //     WHERE ga.activemethod = 'frubric'
        //     AND gi.itemmodule = 'assign'
        //     AND gi.outcomeid IS NULL
        //     AND gg.usermodified IS NOT NULL
        //     AND oc.outcomeid > 0
        //     AND (
        //             gg.timemodified >= :lastrun1
        //             OR gi.timemodified >= :lastrun2
        //             OR EXISTS (
        //                 SELECT 1
        //                 FROM {grading_instances} gri
        //                 INNER JOIN {assign_grades} ag ON ag.id = gri.itemid
        //                 WHERE ag.assignment = cm.instance
        //                 AND ag.userid = gg.userid
        //                 AND gri.timemodified >= :lastrun3
        //                 AND gri.timemodified >= :startofmonth
        //             )
        //   )"; // Check the grade_instances timemodified. Now it covers all cases --> all graded at once, or graded differently. If not, if you graded a student and then try to grade another in another time, the job wont run unless you update the definition of the assessment.

        //  $fgraded = $DB->get_records_sql($sql, ['lastrun1' => $lastrun,
        //     'lastrun2' => $lastrun,
        //     'lastrun3' => $lastrun,
        // 'startofmonth' => $startofmonth]);

            // TODO: This queyr should be replaced by the one commented out but needs to be fixed so it doesnt pick up all the work done so far. It takes too long to execute
          $sql = "SELECT DISTINCT 
                    CONCAT(gi.id, '-', gg.userid) AS gradeiduserid, 
                    gi.*, 
                    gg.userid, 
                    gg.usermodified
                FROM {grade_items} gi
                INNER JOIN {grade_grades} gg 
                    ON gg.itemid = gi.id
                INNER JOIN {grade_outcomes_courses} oc 
                    ON oc.courseid = gi.courseid
                INNER JOIN {modules} m 
                    ON m.name = gi.itemmodule
                INNER JOIN {course_modules} cm 
                    ON cm.instance = gi.iteminstance 
                    AND cm.course = gi.courseid 
                    AND cm.module = m.id
                INNER JOIN {context} c 
                    ON c.instanceid = cm.id
                INNER JOIN {grading_areas} ga 
                    ON ga.contextid = c.id 
                WHERE ga.activemethod = 'frubric'
                AND gi.itemmodule = 'assign'
                AND gi.outcomeid IS NULL
                AND gg.usermodified IS NOT NULL
                AND oc.outcomeid > 0
                AND (gg.timemodified >= $lastrun 
                    OR gi.timemodified >= $lastrun)";
        $fgraded = $DB->get_records_sql($sql);


        foreach ($fgraded as $fgrade) {
            $this->log("Processing outcome grades related to grade item $fgrade->id - $fgrade->itemname for userid $fgrade->userid", 1);
            // Get the assign_grades row for this user.
            $sql = "SELECT *
                    FROM {assign_grades}
                    WHERE assignment = $fgrade->iteminstance
                    AND userid = $fgrade->userid";
            $assigngrade = $DB->get_record_sql($sql);
            // Save outcomes.
            if ($assigngrade) { //foreach($assigngrades as $assigngrade) {
                $this->log("Found assign grade $assigngrade->id", 2);
                $this->log("Looking for grading instance/fillings for user $fgrade->userid in $fgrade->iteminstance since last run", 2);
                // Get the latest filling for this user.
                $sql = "SELECT i.*
                        FROM {grading_instances} i
                        INNER JOIN {grading_definitions} d on d.id = i.definitionid
                        WHERE i.itemid = $assigngrade->id
                        ORDER BY i.timemodified desc";
                $ginstance = $DB->get_record_sql($sql, [], IGNORE_MULTIPLE);
                if (!$ginstance) {
                    $this->log("User $fgrade->userid was not graded since last run - no grading instances found.", 3);
                    continue;
                }
                $sql = "SELECT *
                        FROM {gradingform_frubric_fillings}
                        WHERE instanceid = $ginstance->id";
                $fillings = $DB->get_records_sql($sql);
                $this->log("User $assigngrade->userid was graded for this assignment since last run. Found the criterion fillings: " . json_encode(array_column($fillings, 'id')), 3);

                // Process the grades first.
                $gradesbyoutcome = array();
                foreach ($fillings as $filling) {
                    $outcomeid = $DB->get_field('gradingform_frubric_criteria', 'outcomeid', array('id' => $filling->criterionid));
                    if (!$outcomeid) {
                        continue;
                    }
                    if (!isset( $gradesbyoutcome[$outcomeid] )) {
                        $gradesbyoutcome[$outcomeid] = array();
                    }
                    // Add the fractional grade (grade / max score in filling)
                    //$sql = "SELECT MAX(maxscore) FROM {gradingform_frubric_descript} where criterionid = $filling->criterionid";
                    $sql = "SELECT MAX(score) FROM mdl_gradingform_frubric_levels where criterionid = $filling->criterionid";
                    $maxscore = $DB->get_field_sql($sql);
                    if (!$maxscore) {
                        $maxscore = 0;
                    }
                    if (strpos($maxscore, '-') !== false) {
                        // Format MIN-MAX.
                        $maxscore = explode('-', $maxscore)[1];
                    } else {
                        // Format MIN/MAX.
                        $maxscore = explode('/', $maxscore)[1];
                    }
                    if ($maxscore == 0) {
                        $this->log("Fraction grade for criterion $filling->criterionid could not be calculated because maxscore was 0. Skipping this outcome grade.", 4);
                        continue;
                    }
                    $filling->fractiongrade = $filling->levelscore / $maxscore;
                    $this->log("Fraction grade for criterion $filling->criterionid (contributing to outcome $outcomeid) is => $filling->levelscore (levelscore) / $maxscore (maxscore) = $filling->fractiongrade (fractiongrade)", 4);
                    $gradesbyoutcome[$outcomeid][] = $filling;
                }

                foreach ($gradesbyoutcome as $outcomeid => &$outcomegrades) {
                    // Get the grade item for the outcome that needs to be filled.
                    $outcomeitem = $DB->get_record('grade_items', array(
                        'iteminstance' => $fgrade->iteminstance,
                        'outcomeid' => $outcomeid,
                    ));
                    if (!$outcomeitem) {
                        continue;
                    }
                    $this->log("Grade item $outcomeitem->id is used for outcome $outcomeid", 4);

                    // Get the scale length for this outcome.
                    $scaleid = $DB->get_field('grade_outcomes', 'scaleid', array('id' => $outcomeid));
                    $scale = $DB->get_record('scale', array('id' => $scaleid));
                    $scale = explode(',', $scale->scale);
                    $scalelength = count($scale);

                    // If the scale has 0, need to -1 to account.
                    if ($scalelength && $scale[0] == '0') {
                        $scalelength = $scalelength - 1;
                    }

                    $outcomeslength = count($outcomegrades);
                    $fractiongradesum = array_sum(array_column($outcomegrades, 'fractiongrade'));
                    //if ($fractiongradesum == 0) {
                    //    continue;
                    //}
                    $exactscore = $scalelength * $fractiongradesum / $outcomeslength;
                    $roundedscore = round($exactscore);
                    $this->log("Grade for outcome $outcomeid is => $scalelength (scalelength) * $fractiongradesum (fractiongradesum) / $outcomeslength (outcomeslength) = $exactscore (exactscore) = $roundedscore (roundedscore) out of $scalelength", 5);

                    // Look for existing outcome grade for the user.
                    $outcomegrade = $DB->get_record('grade_grades', array(
                        'itemid' => $outcomeitem->id,
                        'userid' => $assigngrade->userid,
                    ));
                    // Use $criterion->levelscore to set the outcome level for the user.
                    $withNullScore = $roundedscore ? $roundedscore : null;
                    if ($outcomegrade) {
                        // Update the outcome grade.
                        $this->log("Updating existing grade_grades row with new score of $withNullScore", 5);
                        $outcomegrade->timemodified = time();
                        $outcomegrade->rawgrade = $withNullScore;
                        $outcomegrade->finalgrade = $withNullScore;
                        $outcomegrade->usermodified = $fgrade->usermodified; // Same user as the main grade.
                        $DB->update_record('grade_grades', $outcomegrade);
                    } else {
                        // Insert the outcome grade.
                        $this->log("Inserting a new grade_grades row with score of $withNullScore", 5);
                        $data = array(
                            'itemid' => $outcomeitem->id,
                            'userid' => $assigngrade->userid,
                            'rawgrade' => $withNullScore,
                            'finalgrade' => $withNullScore,
                            'usermodified' => $fgrade->usermodified, // Same user as the main grade.
                            'timemodified' => time(),
                        );
                        $DB->insert_record('grade_grades', $data);
                    }
                }

            }
        }

        return 1;
    }

    public function can_run(): bool {
        return true;
    }

}