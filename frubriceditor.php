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
 * File contains definition of class MoodleQuickForm_rubriceditor
 *
 * @package    gradingform_frubric
 * @copyright  2021 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once("HTML/QuickForm/input.php");

/**
 * Form element for handling rubric editor
 *
 * The rubric editor is defined as a separate form element. This allows us to render
 * criteria, levels and buttons using the rubric's own renderer. Also, the required
 * Javascript library is included, which processes, on the client, buttons needed
 * for reordering, adding and deleting criteria.
 *
 * If Javascript is disabled when one of those special buttons is pressed, the form
 * element is not validated and, instead of submitting the form, we process button presses.
 *
 * @package    gradingform_rubric
 * @copyright  2011 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class MoodleQuickForm_frubriceditor extends HTML_QuickForm_input {

    /** @var string help message */
    public $_helpbutton = '';
    /** @var string|bool stores the result of the last validation: null - undefined, false - no errors, string - error(s) text */
    protected $validationerrors = null;
    /** @var bool if element has already been validated **/
    protected $wasvalidated = false;
    /** @var bool If non-submit (JS) button was pressed: null - unknown, true/false - button was/wasn't pressed */
    protected $nonjsbuttonpressed = false;
    /** @var bool Message to display in front of the editor (that there exist grades on this rubric being edited) */
    protected $regradeconfirmation = false;


    /**
     * Constructor for rubric editor
     *
     * @param string $elementName
     * @param string $elementLabel
     * @param array $attributes
     */
    public function __construct($elementName = null, $elementLabel = null, $attributes = null) {
        parent::__construct($elementName, $elementLabel, $attributes);
    }

    /**
     * get html for help button
     *
     * @return string html for help button
     */
    public function getHelpButton() {
        return $this->_helpbutton;
    }
    /**
     * The renderer will take care itself about different display in normal and frozen states
     *
     * @return string
     */
    public function getElementTemplateType() {
        return 'default';
    }

    /**
     * Specifies that confirmation about re-grading needs to be added to this rubric editor.
     * $changelevel is saved in $this->regradeconfirmation and retrieved in toHtml()
     *
     * @see gradingform_rubric_controller::update_or_check_rubric()
     * @param int $changelevel
     */
    public function add_regrade_confirmation($changelevel) {
        $this->regradeconfirmation = $changelevel;
    }

    /**
     * Returns html string to display this element
     *
     * @return string
     */
    public function toHtml() {
        global $OUTPUT;
        $html = $this->_getTabs();
        $data = $this->getCriterionData();

        $data  = $this->prepare_data(null, $this->wasvalidated);

        if ($this->validationerrors) {
            $html .= html_writer::div($this->validationerrors, 'alert alert-danger', ['id' => 'frubric-is-invalid']);
        }

        $html .= $OUTPUT->render_from_template('gradingform_frubric/frubriceditor', $data);
        return $html;
    }

    private function getCriterionData() {

        $definitionid = $this->_attributes['definitionid'];;

        $d = new \stdClass();
        $d->editfull = '1'; // Default is DISPLAY_EDIT_FULL.
        $d->definitionid = 0; // Default when its new rubric.
        $d->id = "frubric-criteria-NEWID1";
        $d->criteriongroupid = 1;
        $d->description = get_string('editcriterion', 'gradingform_frubric');
        $d->new = 1;

        $data = [
            'criteria' => [$d],
            'definitionid' => $definitionid,
            'counter' => 0,
            'first' => 1
        ];
        // To avoid multiple events attachment.
        $edit = 0;

        $data['edit'] = $edit;
        return $data;
    }

    protected function prepare_data($value = null, $withvalidation = false) {
        if (null === $value) {
            $value = $this->getValue();
            return ($value);
        }
    }


    public function validate() {

        $frubricel = $this->getCriterionData();
        $err = [];

        foreach ($frubricel as $criteria) {
            foreach ($criteria as $j => $criterion) {
                if ($criterion->description == '') {
                    $err[] = get_string('err_nocriteria', 'gradingform_frubric');
                }

                if (count($criterion->levels) == 0) {
                    $err[] = get_string('err_levels', 'gradingform_frubric');
                }

                foreach ($criterion->levels as $level) {

                    if ($level->score == '') {
                        $err[] = get_string('err_noscore', 'gradingform_frubric');
                    }
                    if (count($level->descriptors) == 0) {
                        $err[] = get_string('err_nocriteria', 'gradingform_frubric');
                    } else {
                        foreach ($level->descriptors as $descriptor) {
                            if ($descriptor->descText == '') {
                                $err[] = get_string('err_nodescriptiondef', 'gradingform_frubric');
                            }
                        }
                    }
                }
            }
        }

        if (count($err) > 0) {
            $err = array_unique($err);
            $this->validationerrors = implode(', ', $err);
        }

        return (count($err) == 0);
    }
}
