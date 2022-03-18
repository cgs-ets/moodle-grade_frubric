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

class gradingform_frubric_editrubric extends moodleform {

    /**
     * Defines forms elements
     */
    public  function definition() {
        global  $PAGE;

        $form = $this->_form;
        //print
        $form->addElement('hidden', 'areaid');
        $form->setType('areaid', PARAM_INT);

        $form->addElement('hidden', 'returnurl');
        $form->setType('returnurl', PARAM_LOCALURL);

        $form->addElement('hidden', 'regrade');
        $form->setType('regrade', PARAM_INT);

          // name
        $form->addElement('text', 'name', get_string('name', 'gradingform_frubric'), array('size' => 52, 'aria-required' => 'true'));
        $form->addRule('name', get_string('required'), 'required', null, 'client');
        $form->setType('name', PARAM_TEXT);

        // description
        $options = gradingform_frubric_controller::description_form_field_options($this->_customdata['context']);
        $form->addElement('editor', 'description_editor', get_string('description', 'gradingform_frubric'), null, $options);
        $form->setType('description_editor', PARAM_RAW);
        
        // frubric completion status
        $choices = array();
        $choices[gradingform_controller::DEFINITION_STATUS_DRAFT]    = html_writer::tag('span', get_string('statusdraft', 'core_grading'), array('class' => 'status draft'));
        $choices[gradingform_controller::DEFINITION_STATUS_READY]    = html_writer::tag('span', get_string('statusready', 'core_grading'), array('class' => 'status ready'));
        $form->addElement('select', 'status', get_string('frubricstatus', 'gradingform_frubric'), $choices)->freeze();

        // Helper input to pass the criteria around JS
        $form->addElement('text', 'criteria', get_string('criteriajson', 'gradingform_frubric'));
        $form->setType('criteria', PARAM_RAW);

        $form->addElement('text', 'criteriahelper', get_string('criteriajson', 'gradingform_frubric'), ['regrade' => '']);
        $form->setType('criteriahelper', PARAM_RAW);

        list($d, $criteriajson) = $this->getCriterionData();

        $form->addElement('hidden', 'forrerender');
        $form->setType('forrerender', PARAM_RAW);

        if (!empty($criteriajson)) {
            $form->setDefault('criteria', $criteriajson);
            $form->setDefault('criteriahelper', $criteriajson); // 
        }

        // Frubric editor.
      
        $form->setType('frubric', PARAM_RAW);
        $renderer = $PAGE->get_renderer('gradingform_frubric');
        $flexrubireditorhtml = $renderer->render_template(gradingform_frubric_controller::DISPLAY_EDIT_FULL, $d);
        $form->addElement('html',  $flexrubireditorhtml);

        $buttonarray = array();
        $buttonarray[] = &$form->createElement('submit', 'savefrubric', get_string('savefrubric', 'gradingform_frubric'));

        if ($this->_customdata['allowdraft']) {
            $buttonarray[] = &$form->createElement('submit', 'savefrubricdraft', get_string('savefrubricdraft', 'gradingform_frubric'));
        }

        $editbutton = &$form->createElement('submit', 'editfrubric', ' ');
        $editbutton->freeze();
        $buttonarray[] = &$editbutton;
        $buttonarray[] = &$form->createElement('cancel');
        $form->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $form->closeHeaderBefore('buttonar');
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
                $this->findButton('savefrubric')->setValue(get_string('save', 'gradingform_frubric'));
            }
        }
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
    protected function &findButton($elementname) {
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
    private function getCriterionData() {
        global $DB;

        $definitionid = $this->_customdata['defid'];
        $criteriajson = '';
        $criteria = [];

        $d = new \stdClass();
        $d->editfull = '1'; // Default is DISPLAY_EDIT_FULL
        $d->definitionid = 0; // Default when its new rubric
        $d->id = "frubric-criteria-NEWID1";
        $d->criteriongroupid = 1;
        $d->new = 1;

        $data = [
            'criteria' => [$d],
            'definitionid' => $definitionid,
            'counter' => 0,
            'first' => 1
        ];
        // To avoid multiple events attachment.
        $edit =  0;
        $mode = 'create';
        $fromrr = 0;


        if ($definitionid != 0) {
            $criteariacollection =  $DB->get_records('gradingform_frubric_criteria', ['definitionid' => $definitionid], 'id', 'criteriajson'); //'id',
            foreach ($criteariacollection as  $criterion) {
                $criteria[] = json_decode($criterion->criteriajson);
            }

            $criteriajson = json_encode($criteria); // I need it in the criteria json input to work on the JS.
            $criterioncounter = 1;
            foreach ($criteria as $i => $criterion) {

                $d = new \stdClass();
                if (!empty($criterion)) {
                    $edit = 1;
                    $mode = 'edit';

                    $d->id = $criterion->id;
                    $d->criteriongroupid = $criterion->id;
                    $d->description = $criterion->description;
                    $d->definitionid = $definitionid;
                    $leveldbids = [];

                    foreach ($criterion->levels as $l => $level) {
                        $level->dcg = $criterion->id;
                      
                        if ($level->score == "0") {
                            $level->score = '';
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
                    $data['criteria'][] =  $d;
                }
            }
            if (count($data['criteria']) > 1) {
                array_shift($data['criteria']);
            }
        }


        $data['edit'] = $edit;
        $data['mode'] = $mode;
        $data['fromrr'] = $fromrr;
       
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

        if (isset($data['savefrubric']) && $data['savefrubric']) {
            $frubricel = json_decode($data['criteria']);
            foreach ($frubricel as $criterion) {
                if ($criterion->status == 'DELETE' || $criterion->status == "NEW" ) { // TODO: Check how to rerender with error
                    continue;
                } else {

                    if ($criterion->description == '') {
                        $err['criteria'] = get_string('err_nocriteria', 'gradingform_frubric');
                    }
    
                    if (count($criterion->levels) == 0) {
                        $err['criteria'] = get_string('err_levels', 'gradingform_frubric');
                    }
    
                    foreach ($criterion->levels as $level) {
    
                        if ($level->status == 'DELETE') {
                            continue;
                        } 
                        if ($level->score === '') {
                            $err['criteria'] = get_string('err_noscore', 'gradingform_frubric');
                        }
                        if (count($level->descriptors) == 0) {
                            $err['criteria'] = get_string('err_nocriteria', 'gradingform_frubric');
                        } else {
                            foreach ($level->descriptors as $descriptor) {
                                if ($descriptor->descText == '') {
                                    $err['criteria'] = get_string('err_nodescriptiondef', 'gradingform_frubric');
                                }
                            }
                        }
                    }
                }
            }
        }
        
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


        // $this->check_for_changes($data); 
        if (!isset($data->savefrubric) || !$data->savefrubric) {
            // we only need confirmation when button 'Save frubric' is pressed
            return false;
        }

        if (!$controller->has_active_instances()) {
            // nothing to re-grade, confirmation not needed
            return false;
        }

        $changelevel = $controller->update_or_check_frubric($data);

        if ($changelevel == 0) {
            // no changes in the rubric, no confirmation needed
            return false;
        }


        $form = $this->_form;

        if ($this->continue_change() == -1) {
            // we have already displayed the confirmation on the previous step
            return false;
        }
        // freeze form elements and pass the values in hidden fields
        // TODO MDL-29421 description_editor does not freeze the normal way, uncomment below when fixed
        foreach (array('criteria', 'name',/*, 'description_editor'*/) as $fieldname) {
            $el = &$form->getElement($fieldname);

            $el->freeze();
            $el->setPersistantFreeze(true);
            if ($fieldname == 'criteria') {

                if ($this->check_for_changes($data)) {
                    $PAGE->requires->js_call_amd('gradingform_frubric/rerender_control', 'init', ['mode' => 'regrade']);
                    $helper = &$form->getElement('criteriahelper');
                    $helper->_attributes['regrade'] = 1;
                }
            }
        }

        $this->findButton('savefrubric')->setValue(get_string('continue'));

        return true;
    }

    private function check_for_changes($data) {
        $currentcriteria = json_decode($data->criteriahelper);
        $newdefinition = json_decode($data->criteria);
        if (count($currentcriteria) > count($newdefinition) || count($currentcriteria) < count($newdefinition)) {  // Criterion to delete or new one added
            return true;
        }


        foreach ($currentcriteria as $i => $criterion) {
            if (
                count($criterion->levels) > count($newdefinition[$i]->levels)
                || count($criterion->levels) < count($newdefinition[$i]->levels)
            ) {  // Level deleted or added
                return true;
            }

            foreach ($criterion->levels as $j => $cri) {

                if (
                    count($cri->descriptors) > count(($newdefinition[$i]->levels)->descriptors)
                    || count($cri->descriptors) < count(($newdefinition[$i]->levels)->descriptors)
                ) { // descript deleted or added
                    return true;
                }
            }
        }

        return false;
    }

    public function continue_change() {
        $form = $this->_form;
        $el = &$form->getElement('regrade');
        $val = $form->getSubmitValue($el->getName());

        return $val;
    }
}
