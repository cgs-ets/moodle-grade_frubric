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
        $requiredcats = ['FINAL', 'SEMESTER 1', 'SEMESTER 2', 'SEMESTER 3', 'CGS Effort', 'Classwork'];
        $calculatedgradeitems = [
            '' => array( //empty means insert these items at the root, rather than in a category.
                'Semester 1 Grade|S1',
                'Semester 2 Grade|S2',
                'Semester 3 Grade|S3',
            )
        ];
        $catrefs = [
            'SEMESTER 1' => 'S1',
            'SEMESTER 2' => 'S2',
            'SEMESTER 3' => 'S3',
        ];
        $effortgradeitems = [
            'CGS Effort' => array( //empty means insert these items at the root, rather than in a category.
                array('Term 1 Punctuality', 'Punctuality and Organisation includes prompt arrival to class, ensuring that you have all the correct equipment and that you respond to correspondence with staff.', 'T1P'),
                array('Term 1 Classwork', 'Effective use of class time and technology includes working constructively, engaging in class discussions, listening well, taking notes, working collaboratively and utilising a mobile device effectively.', 'T1C'),
                array('Term 1 Approach', 'Independent approach to learning emphasises self-discipline and active learning, for example, drafting work for peer/teacher feedback or persisting with tasks when concepts are challenging or reading more broadly on topics. You are responsible for your learning.', 'T1A'),
                array('Term 1 Deadines', 'Meeting deadlines includes effective time management and thorough completion of homework and assignment tasks', 'T1D'),
                
                array('Term 2 Punctuality', 'Punctuality and Organisation includes prompt arrival to class, ensuring that you have all the correct equipment and that you respond to correspondence with staff.', 'T1P'),
                array('Term 2 Classwork', 'Effective use of class time and technology includes working constructively, engaging in class discussions, listening well, taking notes, working collaboratively and utilising a mobile device effectively.', 'T1C'),
                array('Term 2 Approach', 'Independent approach to learning emphasises self-discipline and active learning, for example, drafting work for peer/teacher feedback or persisting with tasks when concepts are challenging or reading more broadly on topics. You are responsible for your learning.', 'T1A'),
                array('Term 2 Deadines', 'Meeting deadlines includes effective time management and thorough completion of homework and assignment tasks', 'T1D'),

                array('Term 3 Punctuality', 'Punctuality and Organisation includes prompt arrival to class, ensuring that you have all the correct equipment and that you respond to correspondence with staff.', 'T1P'),
                array('Term 3 Classwork', 'Effective use of class time and technology includes working constructively, engaging in class discussions, listening well, taking notes, working collaboratively and utilising a mobile device effectively.', 'T1C'),
                array('Term 3 Approach', 'Independent approach to learning emphasises self-discipline and active learning, for example, drafting work for peer/teacher feedback or persisting with tasks when concepts are challenging or reading more broadly on topics. You are responsible for your learning.', 'T1A'),
                array('Term 3 Deadines', 'Meeting deadlines includes effective time management and thorough completion of homework and assignment tasks', 'T1D'),
                
                array('Term 4 Punctuality', 'Punctuality and Organisation includes prompt arrival to class, ensuring that you have all the correct equipment and that you respond to correspondence with staff.', 'T1P'),
                array('Term 4 Classwork', 'Effective use of class time and technology includes working constructively, engaging in class discussions, listening well, taking notes, working collaboratively and utilising a mobile device effectively.', 'T1C'),
                array('Term 4 Approach', 'Independent approach to learning emphasises self-discipline and active learning, for example, drafting work for peer/teacher feedback or persisting with tasks when concepts are challenging or reading more broadly on topics. You are responsible for your learning.', 'T1A'),
                array('Term 4 Deadines', 'Meeting deadlines includes effective time management and thorough completion of homework and assignment tasks', 'T1D'),
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

            // Creating category causes new entries into the gradeitem table too.
            // Add ID numbers to the category grade items so that they can be referenced in the formulas later.
            foreach ($catrefs as $catname => $ref) {
                // Get the category.
                $cat = \grade_category::fetch(array(
                    'courseid' => $course->id, 
                    'fullname' => $catname, 
                ));
                if (!$cat) {
                    $this->log("Issue fetching cat '$catname', failed to insert idnumber reference");
                    continue;
                }
                // Now find the associated grade item.
                $gradeitem = \grade_item::fetch(array('courseid'=> $course->id, 'iteminstance'=> $cat->id));
                if (!$gradeitem) {
                    $this->log("Grade item for category not found: $catname");
                    continue;
                }
                if ($gradeitem->idnumber != $ref) {
                    $this->log("Updating idnumber (for formula reference) for grade item $gradeitem->id to $ref");
                    $gradeitem->idnumber = $ref;
                    $gradeitem->update();
                }
            }

            // Create calculated grade items.
            foreach ($calculatedgradeitems as $catname => $catgradeitems) {
                foreach ($catgradeitems as $gradeitemname) {
                    list($gradeitemname, $refidnumber) = explode('|', $gradeitemname);
                    // Get the category where to insert this grade item. Empty means root.
                    $cat = null;
                    if (empty($catname)) {
                        $cat = \grade_category::fetch(array(
                            'courseid' => $course->id, 
                            'fullname' => '?', 
                            'depth' => 1, 
                        ));
                    } else {
                        $cat = \grade_category::fetch(array(
                            'courseid' => $course->id, 
                            'fullname' => $catname, 
                        ));
                    }
                    if (!$cat) {
                        $this->log("Issue fetching cat: $catname");
                        continue;
                    }
                    
                    // Check if the grade item already exists for the course.
                    $gradeitem = \grade_item::fetch(array('courseid'=> $course->id, 'itemname'=> $gradeitemname));
                    if ($gradeitem) {
                        $this->log("Grade item already exists: $gradeitemname");
                        continue;
                    }

                    // Find the relevant category grade item, needed for the calculation.
                    $calculation = '';
                    $refgradeitem = \grade_item::fetch(array('courseid'=> $course->id, 'idnumber'=> $refidnumber, 'itemtype' => 'category'));
                    if (!$refgradeitem) {
                        $this->log("Failed to find ref grade item for: $refidnumber");
                    } else {
                        $rid = $refgradeitem->id;
                        $calculation = "=(##gi$rid##>0)+(##gi$rid##>20)+(##gi$rid##>40)+(##gi$rid##>50)+(##gi$rid##>65)+(##gi$rid##>75)+(##gi$rid##>85)";
                    }

                    $this->log("Adding grade item '$gradeitemname' to category '$cat->id'");
                    $params = array(
                        'itemtype'  => 'manual',
                        'itemname'  => $gradeitemname,
                        'gradetype' => GRADE_TYPE_VALUE,
                        'courseid'  => $course->id,
                        'categoryid' => $cat->id,
                        'gradetype' => 2,
                        'grademax' => 6,
                        'grademin' => 1,
                        'calculation' => $calculation,
                    );
                    $gradeitem = new \grade_item($params, false);
                    $gradeitemid = $gradeitem->insert();
                }
            }

            // Create effort grade items.
            foreach ($effortgradeitems as $catname => $catgradeitems) {
                foreach ($catgradeitems as $gradeitem) {
                    list($name, $info, $idnumber) = $gradeitem;
                    // Get the category where to insert this grade item. Empty means root.
                    $cat = null;
                    if (empty($catname)) {
                        $cat = \grade_category::fetch(array(
                            'courseid' => $course->id, 
                            'fullname' => '?', 
                            'depth' => 1, 
                        ));
                    } else {
                        $cat = \grade_category::fetch(array(
                            'courseid' => $course->id, 
                            'fullname' => $catname, 
                        ));
                    }
                    if (!$cat) {
                        $this->log("Issue fetching cat: $catname");
                        continue;
                    }
                    
                    // Check if the grade item already exists for the course.
                    $gradeitem = \grade_item::fetch(array('courseid'=> $course->id, 'itemname'=> $name));
                    if ($gradeitem) {
                        $this->log("Grade item already exists: $name");
                        continue;
                    }

                    // Get the effort scale.
                    $scale = $DB->get_record('scale', array('courseid' => 0, 'name' => 'Effort rubric scale'));
   
                    $this->log("Adding grade item '$name' to category '$cat->id'");
                    $params = array(
                        'itemtype'  => 'manual',
                        'iteminfo'  => $info,
                        'idnumber'  => $idnumber,
                        'itemname'  => $name,
                        'gradetype' => GRADE_TYPE_SCALE,
                        'courseid'  => $course->id,
                        'categoryid' => $cat->id,
                        'grademax' => 5,
                        'grademin' => 1,
                        'scaleid' => $scale ? $scale->id : null,
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