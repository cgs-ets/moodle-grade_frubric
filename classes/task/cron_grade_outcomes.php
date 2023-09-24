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
        global $CFG, $DB;

        // Get last run.
        $lastrun = $DB->get_field('config', 'value', ['name' => 'frubric_gradeoutcomes_lastrun']);
        if ($lastrun === false) {
            $DB->insert_record('config', ['name' => 'frubric_gradeoutcomes_lastrun', 'value' => 0]);
            $lastrun = 0;
        }
        
        // Find frubric grades since last run.
        $sql = "SELECT DISTINCT gi.* 
                FROM {grading_areas} ga
                INNER JOIN {grade_items} gi ON gi.iteminstance = ga.id
                INNER JOIN {grade_grades} gg on gg.itemid = gi.id
                INNER JOIN {grade_outcomes_courses} oc ON oc.courseid = gi.courseid
                WHERE ga.activemethod = 'frubric'
                AND gi.outcomeid IS NULL
                AND oc.outcomeid > 0
                AND gg.timemodified >= $lastrun
        ";
        $fgraded = $DB->get_record_sql($sql);

        foreach ($fgraded as $fgrade) {
            $sql = "SELECT DISTINCT gi.* 
                FROM {grading_areas} ga
                INNER JOIN {grade_items} gi ON gi.iteminstance = ga.id
                INNER JOIN {grade_grades} gg on gg.itemid = gi.id
                INNER JOIN {grade_outcomes_courses} oc ON oc.courseid = gi.courseid
                WHERE ga.activemethod = 'frubric'
                AND gi.outcomeid IS NULL
                AND oc.outcomeid > 0
                AND gg.timemodified >= $lastrun
            ";
            $filling = $DB->get_record_sql($sql);

            /*
            $grade = $this->get_frubric_filling();
            foreach ($grade['criteria'] as $filling) {
                $outcomeid = $DB->get_field('gradingform_frubric_criteria', 'outcomeid', array('id' => $filling['criterionid']));
                if ($outcomeid) {
                    // Save $grade->levelscore.
                    $outcomeitem = $DB->get_record('grade_items', array(
                        'iteminstance' => $this->get_controller()->get_areaid(),
                        'outcomeid' => $outcomeid,
                    ));
                    if ($outcomeitem) {
                        // Look for existing outcome grade.
                        $outcomegrade = $DB->get_record('grade_grades', array(
                            'itemid' => $outcomeitem->id,
                            'userid' => $outcomeid,
                        ));
                    }
                }
            }
            */

        }
    
        return 1;
    }

    public function can_run(): bool {
        return true;
    }

}