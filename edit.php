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
 * frubric editor page
 *
 * @package     gradingform_frubric
 * @copyright   2021 Veronica Bermegui
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(__DIR__ . '/../../../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/edit_form.php');
require_once($CFG->libdir.'/gradelib.php');
require_once($CFG->dirroot . '/grade/grading/lib.php');

$areaid                 = required_param('areaid', PARAM_INT);
$criteriajson           = optional_param('criteriajsonhelper', '', PARAM_RAW);
$regradecheck           = optional_param('regrade', '0', PARAM_RAW);
$regradeoptselected     = optional_param('regradeoptionselected', '0', PARAM_RAW);
$manager                = get_grading_manager($areaid);

list($context, $course, $cm) = get_context_info_array($manager->get_context()->id);

require_login($course, true, $cm);
require_capability('moodle/grade:managegradingforms', $context);

$controller = $manager->get_controller('frubric');

$PAGE->set_url(new moodle_url('/grade/grading/form/frubric/edit.php', array('areaid' => $areaid)));
$PAGE->set_title(get_string('definefrubric', 'gradingform_frubric'));
$PAGE->set_heading(get_string('definefrubric', 'gradingform_frubric'));

$definitionid = $DB->get_record('grading_definitions', array('areaid' => $areaid, 'method' => 'frubric'), 'id');
$definitionid = ($definitionid) ? $definitionid->id : 0;

$customdata =
    array(
        'areaid' => $areaid,
        'context' => $context,
        'defid' => $definitionid,
        'criteriajsonhelper' => $criteriajson,
        'allowdraft' => !$controller->has_active_instances(),
        'outcomes' => array_values($controller->get_assign_outcomes($cm->instance)), //Used to populate select on initial load.
    );
$target     = array('class' => 'gradingform_rubric_editform');
$mform      = new gradingform_frubric_editrubric(null, $customdata, 'post', '', $target);

$mform->need_confirm_regrading($controller);
$returnurl  = optional_param('returnurl', $manager->get_management_url(), PARAM_LOCALURL);
$data = $controller->get_definition_for_editing(true);
$data->returnurl = $returnurl;
$data->regrade   = 0;

$outcomes = array_values($controller->get_assign_outcomes($cm->instance));
$data->outcomesjson = json_encode(array( // Used for populating select when adding new criteria.
    'outcomes' => $outcomes,
    'hasoutcomes' => count($outcomes),
));

$mform->set_data($data);

$confirmregrading = (!$mform->need_confirm_regrading($controller) || $regradecheck == 1);

if ($mform->is_cancelled()) {
    redirect($returnurl);
} else if ($mform->is_submitted() && $mform->is_validated() && $confirmregrading) {
    // Everything ok, validated, re-grading confirmed if needed. Make changes to the rubric.
    $data = $mform->get_data();
    $data->regrade = $regradeoptselected;
    $controller->update_definition($data);
    // If we do not go back to management url and the minscore warning needs to be displayed, display it during redirection.
    $warning = null;
    redirect($returnurl, $warning, null, \core\output\notification::NOTIFY_ERROR);
}


// Try to keep the session alive on this page as it may take some time
// before significant interaction happens with the server.
\core\session\manager::keepalive();



echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
