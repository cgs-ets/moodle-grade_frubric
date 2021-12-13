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
        global $OUTPUT;

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

    private function preview_prepare_data($criteria) {
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

        return $data;
    }

    /**
     * Displays for the student the list of instances or default content if no instances found
     *
     * @param array $instances array of objects of type gradingform_rubric_instance
     * @param string $defaultcontent default string that would be displayed without advanced grading
     * @param boolean $cangrade whether current user has capability to grade in this context
     * @return string
     */
    public function display_instances($instances, $defaultcontent, $cangrade, $maxscore) {
        $return = '';
        if (sizeof($instances)) {
            $return .= html_writer::start_tag('div', array('class' => 'advancedgrade'));
            $idx = 0;
            foreach ($instances as $instance) {
                $return .= $this->display_instance($instance, $idx++, $cangrade, $maxscore);
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
    public function display_instance(gradingform_frubric_instance $instance, $idx, $cangrade, $maxscore) {
        global $OUTPUT;

        $criteria = $instance->get_controller()->get_definition()->frubric_criteria;
        $values = $instance->get_frubric_filling(true);
        $levelscores = 0;
        $sumscores = 0;

        $data = [
            'criteria' => [],
            'preview' => 1, // doesnt display criterion controls.
            'totalscore' => $maxscore,
            'sumscores' => 0.0,

        ];

        foreach ($criteria as $i => &$criterion) {
            $value = $values['criteria'][$i];
            $sumscores += $value['levelscore'];
            $descriptorids = '';
            foreach ($criterion as $j => &$cri) {
                if ($j == 'levels') {
                    $criterionlevelids = $this->get_level_ids_per_criterion($i);
                    $leveljson = (array)json_decode($value['leveljson']);
                    foreach ($criterionlevelids as $lid) {
                        $level = (array)$leveljson[$lid->id];
                        foreach ($level['descriptors'] as $desc) { // get the score for the descript
                            if ($desc->checked) {
                                $descriptorids .= "$desc->descriptorid,";
                            }
                        }
                        $descriptorids = rtrim($descriptorids, ',');
                        $levelscores = $this->get_desc_sum_scores($descriptorids);
                        $cri[$lid->id] = $level;
                    }
                }
            }
            $criterion['levelscore'] = $levelscores;
            $criterion['feedback'] = $value['remark'];
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

    private function get_desc_sum_scores($descriptorids) {
        global $DB;
        $sql = "SELECT sum(score) as score FROM mdl_gradingform_frubric_descript WHERE id IN ($descriptorids)";
        $results = $DB->get_record_sql($sql);
        return $results->score;
    }

    private function get_level_ids_per_criterion($criterionid) {
        global $DB;
        $sql = "SELECT id  FROM mdl_gradingform_frubric_levels WHERE criterionid = $criterionid";
        $results = $DB->get_records_sql($sql);

        return $results;
    }
}