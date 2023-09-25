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
        $sql = "SELECT DISTINCT gi.*, gg.usermodified
                FROM {grading_areas} ga
                INNER JOIN {grade_items} gi ON gi.iteminstance = ga.id
                INNER JOIN {grade_grades} gg on gg.itemid = gi.id
                INNER JOIN {grade_outcomes_courses} oc ON oc.courseid = gi.courseid
                WHERE ga.activemethod = 'frubric'
                AND gi.itemmodule = 'assign'
                AND gi.outcomeid IS NULL
                AND oc.outcomeid > 0
                AND gg.timemodified >= $lastrun";
        $fgraded = $DB->get_records_sql($sql);

        foreach ($fgraded as $fgrade) {
            $this->log("Processing outcome grades related to grade item $fgrade->id - $fgrade->itemname", 1);
            // Get users that have been graded for this assignment
            $sql = "SELECT * 
                    FROM {assign_grades}
                    WHERE assignment = $fgrade->iteminstance";
            $assigngrades = $DB->get_records_sql($sql);
            // Save outcomes for each user.
            foreach($assigngrades as $assigngrade) {
                $this->log("Looking for grading instance/fillings for user $assigngrade->userid since last run", 2);
                // Get the latest filling for this user.
                $sql = "SELECT i.* 
                        FROM {grading_instances} i
                        INNER JOIN {grading_definitions} d on d.id = i.definitionid
                        WHERE i.itemid = $assigngrade->id
                        AND d.areaid = $fgrade->iteminstance
                        AND i.timemodified >= $lastrun
                        ORDER BY i.timemodified desc";
                $ginstance = $DB->get_record_sql($sql, [], IGNORE_MULTIPLE);
                if (!$ginstance) {
                    $this->log("User $assigngrade->userid was not graded since last run - no grading instances found.", 3);
                    return;
                }
                $sql = "SELECT * 
                        FROM {gradingform_frubric_fillings}
                        WHERE instanceid = $ginstance->id";
                $fillings = $DB->get_records_sql($sql);
                $this->log("User $assigngrade->userid was graded for this assignment since last run. Found the criterion fillings: " . json_encode(array_column($fillings, 'id')), 3);

                foreach ($fillings as $filling) {
                    // Get the criterions outcome.
                    $outcomeid = $DB->get_field('gradingform_frubric_criteria', 'outcomeid', array('id' => $filling->criterionid));
                    $this->log("Criterion $filling->criterionid is mapped to outcome $outcomeid", 4);
                    
                    if ($outcomeid) {
                        // Get the grade item for the outcome that needs to be filled.
                        $outcomeitem = $DB->get_record('grade_items', array(
                            'iteminstance' => $fgrade->iteminstance,
                            'outcomeid' => $outcomeid,
                        ));
                        $this->log("Grade item $outcomeitem->id is used for outcome $outcomeid", 4);
                        if ($outcomeitem) {
                            // Look for existing outcome grade for the user.
                            $outcomegrade = $DB->get_record('grade_grades', array(
                                'itemid' => $outcomeitem->id,
                                'userid' => $assigngrade->userid,
                            ));
                            // Use $criterion->levelscore to set the outcome level for the user.
                            if ($outcomegrade) {
                                // Update the outcome grade.
                                $this->log("Updating existing grade_grades row with new score of $filling->levelscore", 4);
                                $outcomegrade->timemodified = time();
                                $outcomegrade->rawgrade = $filling->levelscore;
                                $outcomegrade->finalgrade = $filling->levelscore;
                                $outcomegrade->usermodified = $fgrade->usermodified; // Same user as the main grade.
                                $DB->update_record('grade_grades', $outcomegrade);
                            } else {
                                // Insert the outcome grade.
                                $this->log("Inserting a new grade_grades row with score of $filling->levelscore", 4);
                                $data = array(
                                    'itemid' => $outcomeitem->id,
                                    'userid' => $assigngrade->userid,
                                    'rawgrade' => $filling->levelscore,
                                    'usermodified' => $fgrade->usermodified, // Same user as the main grade.
                                    'finalgrade' => $filling->levelscore,
                                    'timemodified' => time(),
                                );
                                $DB->insert_record('grade_grades', $data);
                            }
                        }
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