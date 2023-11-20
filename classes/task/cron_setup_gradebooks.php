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

require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->libdir.'/gradelib.php');
require_once($CFG->libdir . '/grade/grade_category.php');
require_once($CFG->libdir . '/grade/grade_item.php');

class cron_setup_gradebooks extends \core\task\scheduled_task {

    // Use the logging trait to get some nice, juicy, logging.
    use \core\task\logging_trait;

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('cron_setup_gradebooks', 'gradingform_frubric');
    }

    /**
     * Execute the scheduled task.
     */
    public function execute() {
        global $DB;
        $requiredcats = ['CGS Reports', 'CGS Feedback', 'CGS Effort', 'Classwork'];
        $requiredgradeitems = [
            'CGS Reports' => array(
                'Semester 1',
                'Semester 2'
            )
        ];

        // Get courses under Senior Academic
        $cat = $DB->get_record('course_categories', array('idnumber' => 'SEN-ACADEMIC'));
        if (!$cat) {
            $this->log("Category 'SEN-ACADEMIC' not found");
            return;
        }
        $cat = \core_course_category::get($cat->id);
        $courses = $cat->get_courses(['recursive'=>true]);
        foreach ($courses as $course) {
            $this->log("Processing $course->id: $course->fullname");
            // Check start, end and visible.
            $now = time();
            if ($course->startdate > $now) {
                $this->log("Skipping: $course->startdate > $now", 1);
                continue;
            }
            if ($course->enddate && $course->enddate < $now) {
                $this->log("Skipping: $course->enddate < $now", 1);
                continue;
            }
            if (!$course->visible) {
                $this->log("Skipping: Not visible", 1);
                continue;
            }
            $cats = \grade_category::fetch_all(array('courseid' => $course->id));
            $catnames = [];
            if (!empty($cats)) {
                $catnames = array_column($cats, 'fullname');
            }
            $missingcats = array_diff($requiredcats, $catnames);
            foreach ($missingcats as $missingcat) {
                // Create new gradecategory item.
                $this->log("Adding category: $missingcat");
                $gradecategory = new \grade_category(['courseid' => $course->id], false);
                $gradecategory->apply_default_settings();
                $gradecategory->apply_forced_settings();
                $gradecategory->fullname = $missingcat;
                $gradecategory->insert();
            }
            // Refetch the cats, now that they've all be checked and created.
            // $cats = \grade_category::fetch_all(array('courseid' => $course->id));
            // Create a grade items.
            foreach ($requiredgradeitems as $catname => $catgradeitems) {
                foreach ($catgradeitems as $gradeitemname) {
                    $cat = \grade_category::fetch(array(
                        'courseid' => $course->id, 
                        'fullname' => $catname, 
                    ));
                    if (!$cat) {
                        $this->log("Issue fetching cat: $catname");
                        continue;
                    }
                    // Check if the grade item already exists under the grade category.
                    $gradeitem = \grade_item::fetch_all(array('courseid'=>$course->id, 'itemname'=>$gradeitemname));
                    if ($gradeitem) {
                        $this->log("Grade item already exists: $gradeitemname");
                        continue;
                    }
                    $this->log("Adding grade item '$gradeitemname' to category '$catname'");
                    $params = array(
                        'itemtype'  => 'manual',
                        'itemname'  => $gradeitemname,
                        'gradetype' => GRADE_TYPE_VALUE,
                        'courseid'  => $course->id,
                        'categoryid' => $cat->id,
                    );
                    $gradeitem = new \grade_item($params, false);
                    $gradeitemid = $gradeitem->insert();
                }
            }
        }
        return 1;
    }

    public function can_run(): bool {
        return true;
    }

}