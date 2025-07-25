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
 * The form used at the frubric editor page is defined here
 *
 * @package    gradingform_frubric
 * @copyright  2021 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');
require_once(__DIR__ . '/lib.php');

class gradingform_frubric_editrubric extends moodleform {

    /**
     * Defines forms elements
     */
    public function definition() {
        global  $PAGE, $CFG;

        $form = $this->_form;
        // Print.
        $form->addElement('hidden', 'areaid');
        $form->setType('areaid', PARAM_INT);

        $form->addElement('hidden', 'returnurl');
        $form->setType('returnurl', PARAM_LOCALURL);

        $form->addElement('hidden', 'regrade');
        $form->setType('regrade', PARAM_INT);
        $form->addElement('hidden', 'regradeoptionselected');
        $form->setType('regradeoptionselected', PARAM_RAW);

        $form->addElement('hidden', 'outcomesjson');
        $form->setType('outcomesjson', PARAM_RAW);

        // Name.
        $form->addElement(
            'text',
            'name',
            get_string('name', 'gradingform_frubric'),
            array('size' => 52, 'aria-required' => 'true')
        );
        $form->addRule('name', get_string('required'), 'required', null, 'client');
        $form->setType('name', PARAM_TEXT);

        // Description.
        $options = gradingform_frubric_controller::description_form_field_options($this->_customdata['context']);
        $form->addElement('editor', 'description_editor', get_string('description', 'gradingform_frubric'), null, $options);
        $form->setType('description_editor', PARAM_RAW);

        // Frubric completion status.
        $choices = array();
        $tagdraft = html_writer::tag(
            'span',
            get_string('statusdraft', 'core_grading'),
            array('class' => 'status draft')
        );
        $tagready = html_writer::tag(
            'span',
            get_string('statusready', 'core_grading'),
            array('class' => 'status ready')
        );
        $choices[gradingform_controller::DEFINITION_STATUS_DRAFT] = $tagdraft;
        $choices[gradingform_controller::DEFINITION_STATUS_READY] = $tagready;
        $form->addElement('select', 'status', get_string('frubricstatus', 'gradingform_frubric'), $choices)->freeze();

        // Helper input to pass the criteria around JS.
        $form->addElement('text', 'criteria', get_string('criteriajson', 'gradingform_frubric'));
        $form->setType('criteria', PARAM_RAW);

        $form->addElement('text', 'criteriahelper', get_string('criteriajson', 'gradingform_frubric'), ['regrade' => '']);
        $form->setType('criteriahelper', PARAM_RAW);

        if ($this->_customdata['criteriajsonhelper'] != '') {
            list($d, $criteriajson) = $this->getcriteriondata($this->_customdata['criteriajsonhelper']);
        } else {
            list($d, $criteriajson) = $this->getcriteriondata();
        }

        $form->addElement('hidden', 'forrerender');
        $form->setType('forrerender', PARAM_RAW);

        if (!empty($criteriajson)) {
            $form->setDefault('criteria', $criteriajson);
            $form->setDefault('criteriahelper', $criteriajson);
        }


        $form->addElement('hidden', 'criteriajsonhelper');
        $form->setType('criteriajsonhelper', PARAM_RAW);
        $renderer = $PAGE->get_renderer('gradingform_frubric');
        $regrademsg = $renderer->render_regregade_content();
        $form->addElement('html', $regrademsg);

        $errorwhensaving = $renderer->render_error_saving();
        $form->addElement('html', $errorwhensaving);

        // Frubric editor.
        $form->setType('frubric', PARAM_RAW);
        $outcomes = $this->_customdata['outcomes'];
        $d['outcomes'] = $outcomes;
        $d['hasoutcomes'] = count($outcomes);
        $flexrubireditorhtml = $renderer->render_template(gradingform_frubric_controller::DISPLAY_EDIT_FULL, $d);
        $form->addElement('html',  $flexrubireditorhtml);

        $options = [
            $form->createElement('advcheckbox', 'options[alwaysshowdefinition]',
            '', get_string('alwaysshowdefinition', 'gradingform_frubric'),
                array('group' => 1), array(0, 1)),
            $form->createElement('advcheckbox', 'options[showdescriptionstudent]',
            '', get_string('showdescriptionstudent', 'gradingform_frubric'),
            array('group' => 1), array(0, 1)),
            $form->createElement('advcheckbox', 'options[disablecriteriacomments]',
            '', get_string('disablecriteriacomments',
            'gradingform_frubric'), array('group' => 1), array(0, 1))
        ];

        $form->addGroup($options, 'options', get_string('frubricoptions', 'gradingform_frubric'), array('<br>'), false);

        $buttonarray = array();
        $buttonarray[] = &$form->createElement(
            'submit',
            'savefrubric',
            get_string('savefrubric', 'gradingform_frubric')
        );

        if ($this->_customdata['allowdraft']) {
            $buttonarray[] = &$form->createElement(
                'submit',
                'savefrubricdraft',
                get_string('savefrubricdraft', 'gradingform_frubric')
            );
        }

        $editbutton = &$form->createElement('submit', 'editfrubric', ' ');
        $editbutton->freeze();
        $buttonarray[] = &$editbutton;
        $buttonarray[] = &$form->createElement('cancel');
        $form->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $form->closeHeaderBefore('buttonar');

        $PAGE->requires->js(new moodle_url($CFG->wwwroot . '/grade/grading/form/frubric/js/rerendererr.js'));
    }


    /**
     * Setup the form depending on current values. This method is called after definition(),
     * data submission and set_data().
     * All form setup that is dependent on form values should go in here.
     *
     * We remove the element status if there is no current status (i.e. rubric is only being created)
     * so the users do not get confused
     */
    public function definition_after_data() {

        parent::definition_after_data();
        $form = &$this->_form;

        $el = $form->getElement('status');
        if (!$el->getValue()) {
            $form->removeElement('status');
        } else {

            $vals = array_values($el->getValue());
            if ($vals[0] == gradingform_controller::DEFINITION_STATUS_READY) {
                $this->findbutton('savefrubric')->setValue(get_string('save', 'gradingform_frubric'));
            }
        }

        $el = $form->getElement('options');
    }



    /**
     * Return submitted data if properly submitted or returns NULL if validation fails or
     * if there is no submitted data.
     *
     * @return object submitted data; NULL if not valid or not submitted or cancelled
     */
    public function get_data() {
        $data = parent::get_data();

        if (!empty($data->savefrubric)) {
            $data->status = gradingform_controller::DEFINITION_STATUS_READY;
        } else if (!empty($data->savefrubricdraft)) {
            $data->status = gradingform_controller::DEFINITION_STATUS_DRAFT;
        }

        return $data;
    }

    /**
     * Returns a form element (submit button) with the name $elementname
     *
     * @param string $elementname
     * @return HTML_QuickForm_element
     */
    protected function &findbutton($elementname) {
        $form = $this->_form;
        $buttonar = &$form->getElement('buttonar');
        $elements = &$buttonar->getElements();
        foreach ($elements as $el) {
            if ($el->getName() == $elementname) {
                return $el;
            }
        }
        return null;
    }


    // Generate the context for the feditor template.
    private function getcriteriondata($criteriacollection = null) {
        global $DB;

        $definitionid = $this->_customdata['defid'];
        $criteriajson = '';
        $criteria = [];

        $d = new \stdClass();
        $d->editfull = '1'; // Default is DISPLAY_EDIT_FULL.
        $d->definitionid = 0; // Default when its new rubric.
        $d->id = "frubric-criteria-NEWID1";
        $d->criteriongroupid = 1;
        $d->new = $definitionid == 0;

        $data = [
            'criteria' => [$d],
            'definitionid' => $definitionid,
            'counter' => 0,
            'first' => 1,
        ];
        // To avoid multiple events attachment.
        $edit = 0;
        $mode = 'create';

        if ($definitionid != 0 && $criteriacollection == null) {
            $criteriacollection = $DB->get_records(
                'gradingform_frubric_criteria',
                ['definitionid' => $definitionid],
                'id',
                'criteriajson'
            );

            foreach ($criteriacollection as $t => $criterion) {
                $criteria[] = json_decode($criterion->criteriajson);
            }
            $criteriajson = json_encode($criteria); // I need it in the criteria json input to work on the JS.
        } else {

            $criteria = json_decode($criteriacollection);
            $criteriajson = str_replace("\\", "", $criteriacollection);
        }

        $criterioncounter = 1;
        $dummyval = false;

        if ($criteria != null && !is_string($criteria)) {
            foreach ($criteria as $i => $criterion) {
                $d = new \stdClass();
                if (!empty($criterion)) {
                    $edit = 1;
                    $mode = 'edit';

                    $d->id = $criterion->id;
                    $d->criteriongroupid = $criterion->id;
                    $d->description = $criterion->description;
                    $d->definitionid = $definitionid;
                    $d->outcomeid = isset($criterion->outcomeid) ? $criterion->outcomeid : 0;
                    $leveldbids = [];

                    if (count((array)$criterion->levels) == 0) {
                        $dummyval = true;
                        $dummylevel = new \stdClass();
                        $dummylevel->status = 'NEW';
                        $dummylevel->score = 0;
                        $dummylevel->id = $i;
                        $dummydescriptor = new stdClass();
                        $dummydescriptor->checked = false;
                        $dummydescriptor->descText = '';
                        $dummydescriptor->delete = 0;
                        $dummydescriptor->descriptorid = 0;
                        $dummylevel->descriptors = [$dummydescriptor];
                        $criterion->levels = [$dummylevel];
                    }

                    // Sort levels in descent order.
                    sortlevels($criterion->levels);

                    foreach ($criterion->levels as $l => $level) {

                        $level->dcg = $criterion->id;

                        if ($level->score == "0") {
                            $level->score = '';
                        }

                        if (count($level->descriptors) == 0) {
                            $dummydescriptor = new stdClass();
                            $dummydescriptor->checked = false;
                            $dummydescriptor->descText = '';
                            $dummydescriptor->delete = 0;
                            $dummydescriptor->descriptorid = 0;
                            array_push($level->descriptors, $dummydescriptor);
                        }

                        $d->levels[] = $level;
                        $leveldbids[] = strval($level->id);
                    }

                    $d->levelids = json_encode($leveldbids);

                    if (isset($criterion->sumscore)) {
                        $d->sumscore = $criterion->sumscore;
                    }

                    $d->totaloutof = isset($criterion->totaloutof) ? $criterion->totaloutof : '';
                    $d->titlefortotal = "Criterion $criterioncounter";
                    $criterioncounter++;
                    $data['criteria'][] = $d;
                }
            }
        }

        if (count($data['criteria']) > 1) {
            array_shift($data['criteria']);
        }

        if ($dummyval) {
            $criteriajson = json_encode($criteria);
        }

        $data['edit'] = $edit;
        $data['mode'] = $mode;

        return [$data, $criteriajson];
    }

    /**
     * Form validation.
     * If there are errors return array of errors ("fieldname"=>"error message"),
     * otherwise true if ok.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *               or an empty array if everything is OK (true allowed for backwards compatibility too).
     */
    public function validation($data, $files) {
        $err = parent::validation($data, $files);
        $err = array();

        $hasoutcomes = (json_decode($data['outcomesjson']))->hasoutcomes;
        error_log(print_r($hasoutcomes, true));
        if (isset($data['savefrubric']) && $data['savefrubric']) {
            $frubricel = json_decode($data['criteria']);

            foreach ($frubricel as $criterion) {
                if ($criterion->status == 'DELETE' && count($frubricel) > 1) {
                    continue;
                } else {

                    if ($criterion->description == '') {
                        $err['criteria'] = get_string('err_nocriteria', 'gradingform_frubric');
                    }

                    if (count($criterion->levels) == 0) {
                        $err['criteria'] = get_string('err_levels', 'gradingform_frubric');
                    }

                    foreach ($criterion->levels as $i => $level) {
                        if ($level->status == 'DELETE') {
                            continue;
                        }

                        // if ($level->score == 0 && count($criterion->levels) == $i - 1) { // The last level can have a zero val.
                        //     $err['criteria'] = get_string('err_noscore', 'gradingform_frubric');
                        // }

                        // if ($level->score == "0-0" && count($criterion->levels) == 1) { // The last level can have a zero val.
                        //     $err['criteria'] = get_string('err_noscore', 'gradingform_frubric');
                        // }

                        if (count($level->descriptors) == 0) {
                            $err['criteria'] = get_string('err_nocriteria', 'gradingform_frubric');
                        } else {
                            foreach ($level->descriptors as $descriptor) {
                                if ($descriptor->descText == '') {
                                    $err['criteria'] = get_string('err_nodescriptiondef', 'gradingform_frubric');
                                }
                            }
                        }

                        // Check if the assignment has to be mapped to an outcome
                       if ($hasoutcomes) {
                        error_log(print_r($criterion, true));
                             if($criterion->outcomeid == 0) {
                                    $err['criteria'] = 'An outcome must be selected';
                             }
                        // exit;

                       }
                    }
                }
            }
        }

        // echo "<pre>"; var_export($err); exit;


        return $err;
    }

    /**
     * Check if there are changes in the rubric and it is needed to ask user whether to
     * mark the current grades for re-grading. User may confirm re-grading and continue,
     * return to editing or cancel the changes
     *
     * @param gradingform_frubric_controller $controller
     */
    public function need_confirm_regrading($controller) {
        global $PAGE, $CFG;
        $data = $this->get_data();

        if (!isset($data->savefrubric) || !$data->savefrubric) {
            // We only need confirmation when button 'Save frubric' is pressed.
            return false;
        }

        if (!$controller->has_active_instances()) {
            // Nothing to re-grade, confirmation not needed.
            return false;
        }

        if ($this->continue_change() == 1) {
            // We have already displayed the confirmation on the previous step.
            return false;
        }

        $PAGE->requires->js(new moodle_url($CFG->wwwroot . '/grade/grading/form/frubric/js/regrade.js'));
        $this->findbutton('savefrubric')->setValue(get_string('continue'));

        return true;
    }


    public function continue_change() {
        $form = $this->_form;
        $el = &$form->getElement('regrade');
        $val = $form->getSubmitValue($el->getName());
        return $val;
    }
}
