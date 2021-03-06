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
 * Contains renderer used for displaying frubric
 *
 * @package    gradingform_frubric
 * @copyright  2021 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Grading method plugin renderer
 *
 * @package    gradingform_frubric
 * @copyright   2021 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradingform_frubric_renderer extends plugin_renderer_base {


    public function render_template($mode, $data) {
        global $OUTPUT, $PAGE, $CFG;

        switch ($mode) {
            case gradingform_frubric_controller::DISPLAY_PREVIEW:
                $data = $this->preview_prepare_data($data);
                return $OUTPUT->render_from_template('gradingform_frubric/editor_preview', $data);
                break;
            case gradingform_frubric_controller::DISPLAY_PREVIEW_GRADED:
                $data = $this->preview_prepare_data($data);
                return  $OUTPUT->render_from_template('gradingform_frubric/editor_preview_graded', $data);
                break;
            case gradingform_frubric_controller::DISPLAY_EVAL:
                return  $OUTPUT->render_from_template('gradingform_frubric/editor_evaluate', $data);
                break;
            case gradingform_frubric_controller::DISPLAY_EDIT_FULL:
                return $OUTPUT->render_from_template('gradingform_frubric/frubriceditor', $data);
                break;
        }
    }

    public function display_preview_graded($criteria) {
        global $OUTPUT;
        $criteria = array_values($criteria);

        foreach ($criteria as $i => &$criterion) {
            foreach ($criterion as $j => &$crit) {
                if ($j == 'levels') {

                    foreach ($crit as $q => $c) {
                        $criterion[$j]['level'][] = $c;
                        unset($crit[$q]);
                    }
                }
            }
        }

        $data = [
            'criteria' => $criteria,
        ];

        return  $OUTPUT->render_from_template('gradingform_frubric/editor_preview_graded', $data);
    }

    private function preview_prepare_data($criteria, $hide = null) {
        ksort($criteria); // When deleting all descriptors from  a level that is already in the DB. When adding new descriptors to this level. the order changes. to latest to earliest.

        $criteria = array_values($criteria);
        $counter = 1;
        foreach ($criteria as $i => &$criterion) {
            foreach ($criterion as $j => &$crit) {

                if ($j == 'levels') {
                    foreach ($crit as $q => $c) {
                        $c = $this->preview_score_check($c);
                        $criterion[$j]['level'][] = $c;
                        unset($crit[$q]);
                    }
                }
            }
            $criterion['criterionlabel'] = "Criterion $counter";
            $counter++;
        }

        $data = [
            'criteria' => $criteria,
            'hide' => $hide
        ];


        error_log(print_r($data, true));

        return $data;
    }

    /**
     * When saving draft and no score is given to the level. Zero is saved
     * Hide when previewing it.
     */
    private function preview_score_check($levels) {

        foreach ($levels as $i => &$level) {
            if ($i == 'score') {
                if ($level == "0") {
                    $level = "";
                }
            }
        }

        return $levels;
    }

    /**
     * Displays for the student the list of instances or default content if no instances found
     *
     * @param array $instances array of objects of type gradingform_rubric_instance
     * @param string $defaultcontent default string that would be displayed without advanced grading
     * @param int $assigngradeid id from mdl_assign_grades
     * @return string
     */
    public function display_instances($instances, $defaultcontent,  $maxscore, $assigngradeid) {
        global $PAGE, $CFG, $USER, $DB;
        $return = '';
        if (sizeof($instances)) {

            // hide criteria from submission status. 
            $sql =   "SELECT workflowstate FROM mdl_assign_user_flags 
                      WHERE userid = :userid AND assignment = (SELECT assignment  
                                                               FROM mdl_assign_grades 
                                                               WHERE id = :assigngradeid AND userid = :userid2)";
            $params = ['userid' => $USER->id, 'assigngradeid' => $assigngradeid, 'userid2' => $USER->id];
            $workflow = $DB->get_record_sql($sql, $params);

            if ($workflow->workflowstate == 'released' || $workflow->workflowstate == '') {
                $PAGE->requires->js(new moodle_url($CFG->wwwroot . '/grade/grading/form/frubric/js/hidegradingcriteria.js'));
            }

            $return .= html_writer::start_tag('div', array('class' => 'advancedgrade'));
            foreach ($instances as $instance) {
                $return .= $this->display_instance($instance,  $maxscore);
            }
            $return .= html_writer::end_tag('div');
        }

        return $return . $defaultcontent;
    }

    /**
     * Displays one grading instance
     *
     * @param gradingform_frubric_instance $instance
     * @param int $idx unique number of instance on page
     * @param bool $cangrade whether current user has capability to grade in this context
     */
    public function display_instance(gradingform_frubric_instance $instance,  $maxscore) {
        global $OUTPUT;

        $definition = $instance->get_controller()->get_definition();
        $criteria = $definition->frubric_criteria;
        $options = json_decode($definition->options);

        $values = $instance->get_frubric_filling(true);
        $sumscores = 0;

        $data = [
            'criteria' => [],
            'preview' => 1, // doesnt display criterion controls.
            'totalscore' => $maxscore,
            'sumscores' => 0.0,
        ];

        $counter = 0;

        if (isset($values)) {

            foreach ($criteria as $i => &$criterion) {
                if (isset($values['criteria'][$i])) {

                    $value = $values['criteria'][$i];
                }
                $sumscores += $value['levelscore'];
                $counter++;
                $descriptorids = '';
                foreach ($criterion as $j => &$cri) {

                    if (!isset($criterion['descriptiontotal'])) {
                        $criterion['descriptiontotal'] = "Criterion $counter";
                    }
                    if ($j == 'levels') {
                        $criterionlevelids = $this->get_level_ids_per_criterion($i);
                        $leveljson = (array)json_decode($value['leveljson']);
                        foreach ($criterionlevelids as $index => $lid) {

                            if (isset($leveljson[$lid->id])) {
                                $level = (array)$leveljson[$lid->id];
                                foreach ($level['descriptors'] as $desc) { // get the score for the descript

                                    if ($desc->checked) {
                                        $descriptorids .= "$desc->descriptorid,";
                                    }
                                }

                                $cri[$lid->id] = $level;
                            }
                        }
                    }
                }

                $criterion['levelscore'] = (int)$value['levelscore'];
                $criterion['feedback'] = $value['remark'];
                $criterion['disablecomment'] = $options->disablecriteriacomments;
            }
        }

        $data['sumscores'] = $sumscores;
        $data['criteria'] = array_values($criteria);
        $this->format_criteria_array($data['criteria']);

        return $OUTPUT->render_from_template('gradingform_frubric/editor_evaluated', $data);
    }

    private function format_criteria_array(&$criteria) {
        foreach ($criteria as &$criterion) {
            foreach ($criterion as $i => $cr) {
                $level['level'] = [];
                if ($i == 'levels') {
                    foreach ($cr as $c) {
                        $level['level'][]  = $c;
                    }
                    $criterion['levels'] = $level;
                }
            }
        }
    }

    public function get_level_ids_per_criterion($criterionid) {
        global $DB;
        $sql = "SELECT id  FROM mdl_gradingform_frubric_levels WHERE criterionid = $criterionid";
        $results = $DB->get_records_sql($sql);

        return $results;
    }

}
