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
 * Grading method controller for the frubric plugin
 *
 * @package     gradingform_frubric
 * @copyright   2021 Veronica Bermegui
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/grade/grading/form/lib.php');
require_once($CFG->dirroot . '/lib/filelib.php');

/** frubric: Used to compare our gradeitem_type against. */
const FRUBRIC = 'frubric';

class gradingform_frubric_controller extends gradingform_controller {

    // Modes of displaying the frubric (used in gradingform_rubric_renderer).
    /** Rubric display mode: For editing (moderator or teacher creates a rubric) */
    const DISPLAY_EDIT_FULL     = 1;
    /** Rubric display mode: Preview the frubric design with hidden fields */
    const DISPLAY_EDIT_FROZEN   = 2;
    /** frubric display mode: Preview the frubric design (for person with manage permission) */
    const DISPLAY_PREVIEW       = 3;
    /** frubric display mode: Preview the frubric (for people being graded) */
    const DISPLAY_PREVIEW_GRADED = 8;
    /** frubric display mode: For evaluation, enabled (teacher grades a student) */
    const DISPLAY_EVAL          = 4;
    /** frubric display mode: For evaluation, with hidden fields */
    const DISPLAY_EVAL_FROZEN   = 5;
    /** frubric display mode: Teacher reviews filled frubric */
    const DISPLAY_REVIEW        = 6;
    /** frubric display mode: Dispaly filled rubric (i.e. students see their grades) */
    const DISPLAY_VIEW          = 7;


    /**
     * Extends the module settings navigation with the frubric grading settings
     *
     * This function is called when the context for the page is an activity module with the
     * FEATURE_ADVANCED_GRADING, the user has the permission moodle/grade:managegradingforms
     * and there is an area with the active grading method set to 'frubric'.
     *
     * @param settings_navigation $settingsnav {@link settings_navigation}
     * @param navigation_node $node {@link navigation_node}
     */
    public function extend_settings_navigation(settings_navigation $settingsnav, navigation_node $node = null) {
        $node->add(
            get_string('definefrubric', 'gradingform_frubric'),
            $this->get_editor_url(),
            settings_navigation::TYPE_CUSTOM,
            null,
            null,
            new pix_icon('icon', '', 'gradingform_frubric')
        );
    }

    /**
     * Extends the module navigation
     *
     * This function is called when the context for the page is an activity module with the
     * FEATURE_ADVANCED_GRADING and there is an area with the active grading method set to the given plugin.
     *
     * @param global_navigation $navigation {@link global_navigation}
     * @param navigation_node $node {@link navigation_node}
     */
    public function extend_navigation(global_navigation $navigation, navigation_node $node = null) {
        if (has_capability('moodle/grade:managegradingforms', $this->get_context())) {
            // No need for preview if user can manage forms,
            // It will have link to manage.php in settings instead.
            return;
        }
        if ($this->is_form_defined() && ($options = $this->get_options()) && !empty($options['alwaysshowdefinition'])) {
            $node->add(
                get_string('gradingof', 'gradingform_frubric', get_grading_manager($this->get_areaid())->get_area_title()),
                new moodle_url(
                    '/grade/grading/form/' . $this->get_method_name() . '/preview.php',
                    array('areaid' => $this->get_areaid())
                ),
                settings_navigation::TYPE_CUSTOM
            );
        }
    }

    /**
     * This function is called when displaying the frubric preview.
     */
    public function render_preview($page) {

        if (!$this->is_form_defined()) {
            throw new coding_exception('It is the caller\'s responsibility to make sure that the form is actually defined');
        }

        $criteria = $this->definition->frubric_criteria;
        $renderer = $page->get_renderer('gradingform_frubric');
        $options = $this->get_options();

        $frubric = '';

        if (has_capability('moodle/grade:managegradingforms', $page->context)) {
            $showdescription = true;
        } else {
            if (empty($options['alwaysshowdefinition'])) {
                // Ensure we don't display unless show frubric option enabled.
                return '';
            }
            $showdescription = $options['showdescriptionstudent'];
        }

        $output = $this->get_renderer($page);

        if ($showdescription) {
            $frubric .= $output->box($this->get_formatted_description(), 'gradingform_frubric-description');
        }

        if (has_capability('moodle/grade:managegradingforms', $page->context)) {
            $frubric .= $renderer->render_template(self::DISPLAY_PREVIEW, $criteria);
        } else {

            $frubric .= $renderer->render_template(self::DISPLAY_PREVIEW_GRADED, $criteria);
        }

        return $frubric;
    }

    protected function delete_plugin_definition() {
        global $DB;
        // Get the list of instances.
        $instances = array_keys($DB->get_records(
            'grading_instances',
            array('definitionid' => $this->definition->id),
            '',
            'id'
        ));
        // Delete all fillings.
        $DB->delete_records_list('gradingform_frubric_fillings', 'instanceid', $instances);
        // Delete instances.
        $DB->delete_records_list('grading_instances', 'id', $instances);
        // Get the list of criteria records.
        $criteria = array_keys($DB->get_records(
            'gradingform_frubric_criteria',
            array('definitionid' => $this->definition->id),
            '',
            'id'
        ));
        // Delete level descriptors.
        $DB->delete_records_list('gradingform_frubric_descript', 'criterionid', $criteria);
        // Delete levels.
        $DB->delete_records_list('gradingform_frubric_levels', 'criterionid', $criteria);
        // Delete criteria.
        $DB->delete_records_list('gradingform_frubric_criteria', 'id', $criteria);
    }

    /**
     * Converts the current definition into an object suitable for the editor form's set_data()
     * bool    $addemptycriterion    whether to add an empty criterion if the guide is completely empty (just being created)
     */
    public function get_definition_for_editing($addemptycriterion = false) {

        $definition = $this->get_definition();
        $properties = new stdClass();
        $properties->areaid = $this->areaid;

        if ($definition) {
            foreach (array('id', 'name', 'description', 'descriptionformat', 'status') as $key) {
                $properties->$key = $definition->$key;
            }
            $options = self::description_form_field_options($this->get_context());
            $properties = file_prepare_standard_editor(
                $properties,
                'description',
                $options,
                $this->get_context(),
                'grading',
                'description',
                $definition->id
            );
        }

        $properties->frubric = array('criteria' => array());

        $properties->options = $this->get_options();

        if (!empty($definition->frubric_criteria)) {
            $properties->frubric['criteria'] = $definition->frubric_criteria;
        } else if (!$definition && $addemptycriterion) {
            $criterion = new stdClass();
            $criterion->id = 1;
            $criterion->cid = "frubric-criteria-NEWID1"; // Criterion ID for the DB.
            $criterion->status = "NEW";
            $criterion->visibility = true; // Default.
            $criterion->description  = ""; // Criterion descrption.
            $criterion->rowindex = 1; // Keep track of the header row.
            $criterion->definitionid = 0; // Id from mdl_grading_definitions.

            $criterion->levels = [];
            $criterion->idsumscore = "";
            $criterion->totaloutof = "";
            $data = [
                'criteria' => [$criterion],
                'definitionid' => 0,
                'counter' => 0,
                'first' => 1,
                'edit' => 1
            ];
            $properties->frubric['criteria'] = $data;
            $properties->criteria = json_encode(array($criterion));

            $d = new \stdClass();
            $d->editfull = self::DISPLAY_EDIT_FULL; // Default is DISPLAY_EDIT_FULL.
            $d->definitionid = 0; // Default when its new rubric.
            $d->id = "frubric-criteria-NEWID1";
            $d->criteriongroupid = 1;
            $d->description = get_string('editcriterion', 'gradingform_frubric');
            $d->new = 1;

            $dataobject = new \stdClass();
            $dataobject->editfull = self::DISPLAY_EDIT_FULL;
            $dataobject->definitionid = 0;
            $dataobject->id = "frubric-criteria-NEWID1";
            $dataobject->description = "Click to edit criterion";
            $dataobject->new = 1;

            $data = [
                'criteria' => [$d],
                'definitionid' => 0,
                'counter' => 0,
                'first' => 1
            ];
            $properties->frubric = $data;
        }

        return $properties;
    }

    // Generate the context for the feditor template.
    public function getcriteriondata($criteriacollection = null) {

        $criteria = json_decode($criteriacollection);

        if ($criteria != null && !is_string($criteria)) {
            foreach ($criteria as $i => $criterion) {
                if (!empty($criterion)) {
                    if (count($criterion->levels) == 0) {
                        $dummylevel = new \stdClass();
                        $dummylevel->status = 'NEW';
                        $dummylevel->score = 0;
                        $dummylevel->id = 0;
                        $dummydescriptor = new stdClass();
                        $dummydescriptor->checked = false;
                        $dummydescriptor->descText = '';
                        $dummydescriptor->delete = 0;
                        $dummydescriptor->descriptorid = 0;
                        $dummylevel->descriptors = [$dummydescriptor];
                        $criterion->levels = [$dummylevel];
                    }

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
                    }
                }
            }
        }

        $criteriajson = json_encode($criteria);

        return  $criteriajson;
    }

    /**
     * Options for displaying the frubric description field in the form
     *
     * @param object $context
     * @return array options for the form description field
     */
    public static function description_form_field_options($context) {
        global $CFG;
        return array(
            'maxfiles' => -1,
            'maxbytes' => get_user_max_upload_file_size($context, $CFG->maxbytes),
            'context'  => $context,
        );
    }

    /**
     * Gets the options of this Flexrubric definition, fills the missing options with default values
     *
     * The only exception is 'lockzeropoints' - if other options are present in the json string but this
     * one is absent, this means that the Flexrubric was created before Moodle 3.2 and the 0 value should be used.
     *
     * @return array
     */
    public function get_options() {

        $options = self::get_default_options();
        if (!empty($this->definition->options)) {
            $thisoptions = (array)json_decode($this->definition->options, true); // Assoc. array is expected.

            foreach ($thisoptions as $option => $value) {
                $options[$option] = $value;
            }
        }

        return $options;
    }

    /**
     * Returns the default options for the flexible Flexrubric display
     *
     * @return array
     */
    public static function get_default_options() {
        $options = array(
            'alwaysshowdefinition' => 0,
            'showdescriptionstudent' => 0,
            'disablecriteriacomments' => 0,
            'lockzeropoints' => 0,

        );
        return $options;
    }

    /**
     * Saves the frubric definition into the database
     *
     * @see parent::update_definition()
     * @param stdClass $newdefinition frubric definition data as coming from gradingform_rubric_editrubric::get_data()
     * @param int|null $usermodified optional userid of the author of the definition, defaults to the current user
     */
    public function update_definition(stdClass $newdefinition, $usermodified = null) {

        $this->update_or_check_frubric($newdefinition, $usermodified, true);
        if (isset($newdefinition->regrade) && $newdefinition->regrade == 1) {
            $this->mark_for_regrade();
        }
    }

    /**
     * Either saves the frubric definition into the database or check if it has been changed.
     * Returns the level of changes:
     * 0 - no changes
     * 1 - only texts or criteria sortorders are changed, students probably do not require re-grading
     * 2 - added levels but maximum score on frubric is the same, students still may not require re-grading
     * 3 - removed criteria or added levels or changed number of points, students require re-grading
     *     but may be re-graded automatically
     * 4 - removed levels - students require re-grading and not all students may be re-graded automatically
     * 5 - added criteria - all students require manual re-grading
     *
     * @param stdClass $newdefinition frubric definition data as coming from gradingform_frubric_editfrubric::get_data()
     * @param int|null $usermodified optional userid of the author of the definition, defaults to the current user
     * @param boolean $doupdate if true actually updates DB, otherwise performs a check
     *
     */
    public function update_or_check_frubric(stdClass $newdefinition, $usermodified = null, $doupdate = false) {
        global $DB;

        if ($this->definition === false) {
            if (!$doupdate) {
                // If we create the new definition there is no such thing as re-grading anyway.
                return 5;
            }

            // If definition does not exist yet, create a blank one
            // We need id to save files embedded in description.
            parent::update_definition(new stdClass(), $usermodified);
            parent::load_definition();
        }

        if (!isset($newdefinition->options)) {
            $newdefinition->options = self::get_default_options();
        }

        $newdefinition->options = json_encode($newdefinition->options);

        $editoroptions = self::description_form_field_options($this->get_context());
        $newdefinition = file_postupdate_standard_editor(
            $newdefinition,
            'description',
            $editoroptions,
            $this->get_context(),
            'grading',
            'description',
            $this->definition->id
        );

        // Reload the definition from the database.
        $currentdefinition = $this->get_definition(true);
        $haschanges = array();

        if (!isset($newdefinition->frubric['criteria'])) {
            $newcriteria = json_decode($newdefinition->criteria);
        } else {
            // Comes from the create from template.
            $newcriteria = array_values($newdefinition->frubric['criteria']);
            $newcriteria = $this->format_definition($newdefinition->frubric['criteria']);
        }

        $changes = $this->compare_existing_definition($currentdefinition, $newcriteria);

        if ($changes) {
            $haschanges[1] = true;
        }

        $criteriafields      = array('sortorder', 'description', 'descriptionformat');
        $trackcriteriondbids = []; // Keep track of the ids generated by the DB. this will be used to update the id of the criterion.
        $trackleveldbids     = []; // Keep track of the ids generated by the DB. this will be used to update the id of the level.
        $levelstodelete      = [];

        foreach ($newcriteria as $i => &$criterion) {
            $dummylevel = new \stdClass();
            $dummylevel->status = 'NEW';
            $dummylevel->score = 0;
            $dummylevel->id = 0;
            $dummydescriptor = new stdClass();
            $dummydescriptor->checked = false;
            $dummydescriptor->descText = '';
            $dummydescriptor->delete = 0;
            $dummydescriptor->descriptorid = 0;
            $dummylevel->descriptors = [$dummydescriptor];

            $levels = $criterion->levels;

            if (is_object($criterion->levels)) {
                $levels = (array)$criterion->levels;
            }

            if (count($levels) == 0) {
                array_push($levels, $dummylevel);
            }
            // The criteria is marked as delete but is the only one it has.
            if ($criterion->status == "NEW" || ($criterion->status == "DELETE" &&  strpos($criterion->cid, 'NEWID') != false)) {
                // Insert criterion into DB.
                $data = array('definitionid' => $this->definition->id, 'descriptionformat' => FORMAT_MOODLE);

                foreach ($criteriafields as $key) {
                    if ($key == 'descriptionformat' || $key == 'criteriajson') {
                        continue;
                    }
                    $data[$key] = ($key == 'sortorder') ? $criterion->rowindex : $criterion->{$key};
                }

                $cid = explode('-', $criterion->cid);
                array_pop($cid);
                $cid = implode('-', $cid);
                $criterion->cid = $cid; // Remove the word NEWID from the element.

                if ($doupdate) {
                    $criterion->status = 'CREATED';
                    unset($criterion->new);
                    $data['criteriajson'] = json_encode($criterion);
                    $id = $DB->insert_record('gradingform_frubric_criteria', $data, true);
                    $trackcriteriondbids[$criterion->id] = $id; // The index its the old id.
                    $criterion->id = $id;
                    $haschanges[5] = true;
                }
            } else if ($criterion->status == "UPDATE") {
                // Update criterion in DB.
                if ($doupdate) {
                    $updatecriterion = new \stdClass();
                    $updatecriterion->id = $criterion->id;
                    $criterion->status = "UPDATED";
                    $id = $criterion->id; // Need it to update the frubri_levels table.
                    $updatecriterion->description = s($criterion->description, true);
                    $updatecriterion->visibility = $criterion->visibility;
                    $updatecriterion->criteriajson = json_encode($criterion);
                    $DB->update_record('gradingform_frubric_criteria', $updatecriterion);
                }
                $haschanges[1] = true;
            } else if ($criterion->status == "DELETE") { // DELETE CRITERION.
                if (isset($criterion->cid)) {
                    if (strpos($criterion->cid, 'NEWID') != false) {
                        continue;
                    }
                }
                if ($doupdate) {
                    foreach ($criterion->levels as $level) {
                        $DB->delete_records('gradingform_frubric_levels', array('id' => $level->id)); // DBID.
                    }
                    $DB->delete_records('gradingform_frubric_criteria', array('id' => $criterion->id));
                }
                $haschanges[4] = true;
            } else if ($criterion->status == "CREATED" || $criterion->status == "UPDATED") {
                $id = $criterion->id;
            }

            foreach ($levels as $l => $level) {
                if ($level->status == 'UPDATED') {
                    continue;
                } // This level was updated before. Not this time.
                if ($level->status == 'NEW') {
                    if ($this->is_descriptor_empty($level)) {
                        $level->descriptors = [$dummydescriptor];
                    }
                    // Insert level into DB.
                    if (isset($id)) {
                        $leveldata = array('criterionid' => $id, 'definitionformat' => FORMAT_MOODLE);
                    }
                    $leveldata['score'] = 0;

                    if ($doupdate) {
                        $levelid = $DB->insert_record('gradingform_frubric_levels', $leveldata, true);
                        $trackleveldbids[$level->id] = $levelid;  // Track ids.
                        $leveldescriptorids = [];

                        // Insert the descriptors.
                        foreach ($level->descriptors as $d => &$descriptor) {
                            list($score, $maxscore) = $this->get_level_score($level->score);
                            $sc = $level->score;

                            $descriptordata = new \stdClass();
                            $descriptordata->criterionid = isset($id) ? $id : $level->id;
                            $descriptordata->levelid = $levelid;
                            $descriptordata->score = $maxscore / count($level->descriptors);
                            $descriptordata->maxscore = $maxscore;
                            $descriptordata->description = s($descriptor->descText, true);

                            $descriptorid = $DB->insert_record('gradingform_frubric_descript', $descriptordata);
                            $descriptordata->id = $descriptorid;
                            $records[] = $descriptordata;

                            $descriptor->descriptorid = $descriptorid;
                            // Update the level definition. Just save the descriptors for this level.
                            // Collect all the descriptorids.
                            $leveldescriptorids[] = $levelid;

                            $level->status = 'CREATED';
                            $updatelevel = new \stdClass();
                            $updatelevel->id = $levelid;
                            $updatelevel->score = $sc;
                            $level->id = $levelid;
                            $level->score = $level->score;

                            $updatelevel->definition = json_encode($level);

                            $DB->update_record('gradingform_frubric_levels', $updatelevel);

                            $haschanges[3] = true;
                        }

                        unset($records);
                    }
                } else if ($level->status == 'UPDATE') {
                    if ($doupdate) {
                        // Update level in DB.
                        $lr = $DB->get_record('gradingform_frubric_levels', ['id' => $level->id]); // Level record.
                        $lr->score = $level->score;

                        $level->status = 'UPDATED';
                        $descriptorstodelete = [];
                        $levelaux = $level->descriptors;

                        // Update the descriptors.
                        foreach ($levelaux as $j => $ld) {
                            $descupdate = new \stdClass();

                            if (!isset($ld->descriptorid) || $ld->descriptorid == 0) { // A new descriptor has been added.
                                $descupdate->criterionid = $criterion->id;
                                $descupdate->score = 1;
                                $descupdate->maxscore = 1;
                                $descupdate->description = $ld->descText;
                                $descupdate->selected = $ld->checked;
                                $descupdate->deleted = $ld->delete;
                                $descupdate->levelid = $level->id;
                                $newdescid = $DB->insert_record('gradingform_frubric_descript', $descupdate);
                                ($level->descriptors[$j])->descriptorid = $newdescid;
                                $haschanges[3] = true;
                            } else {
                                $descupdate->id = $ld->descriptorid;
                                $descupdate->description = $ld->descText;
                                $descupdate->selected = $ld->checked;
                                if (isset($ld->delete) && $ld->delete == 1) {
                                    $descupdate->deleted = $ld->delete;
                                    $destodelete  = new \stdClass();
                                    $destodelete->id = ($levelaux[$j])->descriptorid;
                                    $d = $DB->delete_records('gradingform_frubric_descript', ['id' => ($levelaux[$j])->descriptorid]);
                                    if ($d) {
                                        $descriptorstodelete[] = $j;
                                    }
                                    $haschanges[3] = true;
                                }

                                $DB->update_record('gradingform_frubric_descript', $descupdate);
                            }
                        }

                        foreach ($descriptorstodelete as $i => $index) {
                            unset($level->descriptors[$index]);
                        }

                        $level->descriptors = array_values($level->descriptors);
                        $lr->definition = json_encode($level);
                        $DB->update_record('gradingform_frubric_levels', $lr);
                    }
                } else if ($level->status == 'DELETE') {
                    if ($doupdate) {
                        // Delete level in DB.
                        foreach ($level->descriptors as $descriptor) {
                            if (!isset($descriptor->descriptorid)) {
                                continue;
                            }
                            $DB->delete_records('gradingform_frubric_descript', array('id' => $descriptor->descriptorid));
                        }

                        $DB->delete_records('gradingform_frubric_levels', array('id' => $level->id));
                        $levelstodelete[] = $l;
                        unset($levels[$l]);
                    }
                    $haschanges[3] = true;
                }
            }

            $criterion->levels = array_values($levels);

            $updatecriteriajson = new \stdClass();
            $updatecriteriajson->id = $criterion->id;
            $updatecriteriajson->criteriajson = json_encode($criterion);

            $DB->update_record('gradingform_frubric_criteria', $updatecriteriajson);
        }

        foreach (array('status', 'description', 'descriptionformat', 'name', 'options') as $key) {
            if (isset($newdefinition->$key) && $newdefinition->$key != $this->definition->$key) {
                $haschanges[1] = true;
            }
        }

        if ($usermodified && $usermodified != $this->definition->usermodified) {
            $haschanges[1] = true;
        }

        if (!count($haschanges)) {
            return 0;
        }

        if ($doupdate) {
            parent::update_definition($newdefinition, $usermodified);
            $this->load_definition();
        }

        // Return the maximum level of changes.
        $changelevels = array_keys($haschanges);

        sort($changelevels);

        return array_pop($changelevels);
    }

    /**
     * Check if the level or descriptor is empty.
     */
    private function is_descriptor_empty($level) {
        if (isset($level->descriptors)) {
            if (count($level->descriptors) == 0) {
                return true;
            }
        }

        return false;
    }

    private function compare_existing_definition($currentdefinition, $newcriteria) {

        $frubriccriteria        = $currentdefinition->frubric_criteria;
        $countcurrentcriteria   = count($frubriccriteria);
        $countnewcriteria       = count($newcriteria);

        if ($countnewcriteria > $countcurrentcriteria) {
            return true;
        }

        if ($countnewcriteria < $countcurrentcriteria) {
            return true;
        }

        foreach ($newcriteria as $criterion) {
            if (is_object($criterion->levels)) {
                $criterion->levels = array($criterion->levels);
            }

            $levelscounter = count($criterion->levels);  // Count the levels in the json.
            $existinglevels = count($frubriccriteria[$criterion->id]['levels']);

            if ($levelscounter > $existinglevels) { // New descriptors added.
                return true;
            }

            if ($levelscounter < $existinglevels) { // New descriptors added.
                return true;
            }
        }

        return false;
    }

    /**
     * When creating a new definition, the criteria array is an array of objects
     * When creating templates the criteria is an array of arrays. --> Modify it
     */
    private function format_definition($criteria) {
        $criteriaaux = [];
        if (is_array($criteria)) {
            foreach ($criteria as $criterion) {
                $criterion = (object)$criterion;

                $criterion->levels = array_values($criterion->levels);
                foreach ($criterion->levels as $level) {
                    (object)$level;
                }
                $criteriaaux[] = $criterion;
            }
        }

        return $criteriaaux;
    }

    private function get_level_score($scorefield) {

        if ($scorefield == 0) { // This is the lowest level. The only one it can have zero.
            return [0, 0];
        }

        if (strpos($scorefield, '-')) { // Format MIN-MAX.
            return  explode('-', $scorefield);
        } else { // Format MIN/MAX.
            return  explode('/', $scorefield);
        }
    }

    /**
     * If instanceid is specified and grading instance exists and it is created by this rater for
     * this item, this instance is returned.
     * If there exists a draft for this raterid+itemid, take this draft (this is the change from parent)
     * Otherwise new instance is created for the specified rater and itemid
     *
     * @param int $instanceid
     * @param int $raterid
     * @param int $itemid
     * @return gradingform_instance
     */
    public function get_or_create_instance($instanceid, $raterid, $itemid) {

        global $DB;
        if ($instanceid &&
            $instance = $DB->get_record(
                'grading_instances',
                array('id'  => $instanceid,
                  'raterid' => $raterid,
                  'itemid' => $itemid),
                '*',
                IGNORE_MISSING
            )) {
            return $this->get_instance($instance);
        }
        if ($itemid && $raterid) {
            $params = array('definitionid' => $this->definition->id, 'raterid' => $raterid, 'itemid' => $itemid);
            if ($rs = $DB->get_records('grading_instances', $params, 'timemodified DESC', '*', 0, 1)) {
                $record = reset($rs);
                $currentinstance = $this->get_current_instance($raterid, $itemid);
                if ($record->status == gradingform_frubric_instance::INSTANCE_STATUS_INCOMPLETE
                    && (!$currentinstance || $record->timemodified > $currentinstance->get_data('timemodified'))) {
                    $record->isrestored = true;
                    return $this->get_instance($record);
                }
            }
        }

        return $this->create_instance($raterid, $itemid);
    }

    /**
     * Returns html code to be included in student's feedback.
     *
     * @param moodle_page $page
     * @param int $itemid
     * @param array $gradinginfo result of function grade_get_grades
     * @param string $defaultcontent default string to be returned if no active grading is found
     * @param boolean $cangrade whether current user has capability to grade in this context
     * @return string
     */
    public function render_grade($page, $itemid, $gradinginfo, $defaultcontent, $cangrade) {

        return $this->get_renderer($page)->display_instances($this->get_active_instances($itemid), $defaultcontent, ($this->get_min_max_score())['maxscore'], $itemid);
    }

    /**
     * Calculates and returns the possible minimum and maximum score (in points) for this rubric
     *
     * @return array
     */
    public function get_min_max_score() {
        if (!$this->is_form_available()) {
            return null;
        }
        $returnvalue = array('minscore' => 0, 'maxscore' => 0);
        foreach ($this->get_definition()->frubric_criteria as $id => $criterion) {
            foreach ($criterion as $description => $crit) {
                if ($description == 'totaloutof') {
                    $returnvalue['maxscore'] += $crit;
                }
            }
        }
        return $returnvalue;
    }

    /**
     * Returns the rubric plugin renderer
     *
     * @param moodle_page $page the target page
     * @return gradingform_frubric_renderer
     */
    public function get_renderer(moodle_page $page) {

        return $page->get_renderer('gradingform_' . $this->get_method_name());
    }

    /**
     * Formats the definition description for display on page
     *
     * @return string
     */
    public function get_formatted_description() {
        if (!isset($this->definition->description)) {
            return '';
        }
        $context = $this->get_context();

        $options = self::description_form_field_options($this->get_context());
        $description = file_rewrite_pluginfile_urls(
            $this->definition->description,
            'pluginfile.php',
            $context->id,
            'grading',
            'description',
            $this->definition->id,
            $options
        );

        $formatoptions = array(
            'noclean' => false,
            'trusted' => false,
            'filter' => true,
            'context' => $context
        );

        return format_text($description, $this->definition->descriptionformat, $formatoptions);
    }

    /**
     * Marks all instances filled with this rubric with the status INSTANCE_STATUS_NEEDUPDATE
     */
    public function mark_for_regrade() {
        global $DB;
        if ($this->has_active_instances()) {
            $conditions = array(
                'definitionid'  => $this->definition->id,
                'status'  => gradingform_instance::INSTANCE_STATUS_ACTIVE
            );
            $DB->set_field('grading_instances', 'status', gradingform_instance::INSTANCE_STATUS_NEEDUPDATE, $conditions);
        }
    }

    /**
     * Loads the frubric form definition if it exists
     *
     * There is a new array called 'frubric_criteria' appended to the list of parent's definition properties.
     */
    protected function load_definition() {

        global $DB;
        $sql = "SELECT gd.*,
                       rc.id AS rcid, rc.sortorder AS rcsortorder,
                       rc.description AS rcdescription,
                       rc.descriptionformat AS rcdescriptionformat,
                       rc.criteriajson AS criteriajson,
                       rl.id AS rlid, rl.score AS rlscore,
                       rl.definition AS rldefinition,
                       rl.definitionformat AS rldefinitionformat
                  FROM {grading_definitions} gd
             LEFT JOIN {gradingform_frubric_criteria} rc ON (rc.definitionid = gd.id)
             LEFT JOIN {gradingform_frubric_levels} rl ON (rl.criterionid = rc.id)
                 WHERE gd.areaid = :areaid AND gd.method = :method
              ORDER BY rl.id";
        $params = array('areaid' => $this->areaid, 'method' => $this->get_method_name());

        $rs = $DB->get_recordset_sql($sql, $params);

        $this->definition = false;

        foreach ($rs as $record) {
            // Pick the common definition data.
            if ($this->definition === false) {
                $this->definition = new stdClass();
                foreach (array(
                    'id', 'name', 'description', 'descriptionformat', 'status', 'copiedfromid',
                    'timecreated', 'usercreated', 'timemodified', 'usermodified', 'timecopied', 'options',

                ) as $fieldname) {
                    $this->definition->$fieldname = $record->$fieldname;
                }
                $this->definition->frubric_criteria = array();
            }

            // Pick the criterion data.
            if (!empty($record->rcid) && empty($this->definition->frubric_criteria[$record->rcid])) {
                foreach (array('id', 'sortorder', 'description', 'descriptionformat') as $fieldname) {
                    $this->definition->frubric_criteria[$record->rcid][$fieldname] = $record->{'rc' . $fieldname};
                }
                $this->definition->frubric_criteria[$record->rcid]['levels'] = array();
            }
            // Pick the level data.
            if (!empty($record->rlid)) {
                foreach (array('id', 'score', 'definition', 'definitionformat') as $fieldname) {
                    $value = $record->{'rl' . $fieldname};
                    $this->definition->frubric_criteria[$record->rcid]['levels'][$record->rlid][$fieldname] = $value;

                    if ($fieldname == 'definition') { // Get the descriptors for the level.
                        $descrip = json_decode($value);
                        if (isset($descrip->descriptors)) {
                            $this->definition->frubric_criteria[$record->rcid]['levels'][$record->rlid]['descriptors'] = $descrip->descriptors;
                        }
                    }
                }
            }

            $criteriajson = json_decode($record->criteriajson);

            if (!isset($this->definition->frubric_criteria[$record->rcid]['sumscore'])) {
                if (isset($criteriajson->sumscore)) {
                    $this->definition->frubric_criteria[$record->rcid]['sumscore'] = $criteriajson->sumscore;
                }
            }

            if ($criteriajson != null) {
                if (isset($criteriajson->totaloutof)) {
                    $this->definition->frubric_criteria[$record->rcid]['totaloutof'] = $criteriajson->totaloutof;
                }
                if (isset($criteriajson->visibility)) {
                    $this->definition->frubric_criteria[$record->rcid]['visibility'] = $criteriajson->visibility;
                }
            }
        }

        $rs->close();
    }

    /**
     * Returns the form definition suitable for cloning into another area
     *
     * @see parent::get_definition_copy()
     * @param gradingform_controller $target the controller of the new copy
     * @return stdClass definition structure to pass to the target's {@link update_definition()}
     */
    public function get_definition_copy(gradingform_controller $target) {

        $new                        = parent::get_definition_copy($target);
        $old                        = $this->get_definition_for_editing();
        $new->description_editor    = $old->description_editor;
        $new->options               = $old->options;
        $listcriterion              = [];

        foreach ($old->frubric as $criteria) {
            foreach ($criteria as $cr) {
                $cr = (object)($cr);

                $criterion = new stdClass();
                $criterion->id = 1;
                $criterion->cid = "frubric-criteria-NEWID1"; // Criterion ID for the DB.
                $criterion->status = "NEW";
                $criterion->description  = $cr->description; // Criterion descrption.
                $criterion->rowindex = 1; // Keep track of the header row.
                $criterion->definitionid = 0; // Id from mdl_grading_definitions.
                if (!isset($cr->visibility)) { // For those cases where we are creating from a rubric that was created with the previous version.
                    $criterion->visibility = true;
                } else {
                    $criterion->visibility = $cr->visibility;
                }
                $levels = [];
                foreach ($cr->levels as $level) {
                    $level = (object) $level;
                    $l = new stdClass();
                    $l->score = $level->score;
                    $l->status = "NEW";
                    $l->id = rand();

                    foreach ($level->descriptors as $descriptor) {
                        $descr = new stdClass();
                        $descr->checked = $descriptor->checked;
                        $descr->descText = $descriptor->descText;
                        $descr->descriptorid = 0;
                        $l->descriptors[] = $descr;
                    }
                    $levels[] = $l;
                }
                $criterion->levels = $levels;
                $criterion->idsumscore = "";
                $criterion->totaloutof = $cr->totaloutof;

                $listcriterion[] = $criterion;
            }

            $data = [
                'criteria' => $listcriterion,
                'definitionid' => 0,

            ];
        }

        $new->frubric = $data;

        return $new;
    }


    /**
     * Returns an array that defines the structure of the frubric's filling. This function is used by
     * the web service function core_grading_external::get_gradingform_instances().
     *
     * @return An array containing a single key/value pair with the 'criteria' external_multiple_structure
     * @see gradingform_controller::get_external_instance_filling_details()
     * @since Moodle 2.6
     */
    public static function get_external_instance_filling_details() {
        $criteria = new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'filling id'),
                    'criterionid' => new external_value(PARAM_INT, 'criterion id'),
                    'levelid' => new external_value(PARAM_INT, 'level id', VALUE_OPTIONAL),
                    'remark' => new external_value(PARAM_RAW, 'remark', VALUE_OPTIONAL),
                    'remarkformat' => new external_format_value('remark', VALUE_OPTIONAL),
                    'levelscore' => new external_format_value('levelscore', VALUE_OPTIONAL),
                    'leveljson' => new external_format_value('leveljson', VALUE_OPTIONAL)
                )
            ),
            'filling',
            VALUE_OPTIONAL
        );
        return array('criteria' => $criteria);
    }
}


/**
 * Class to manage one frubric grading instance.
 *
 * Stores information and performs actions like update, copy, validate, submit, etc.
 *
 * @package    gradingform_rubric
 * @copyright  2021 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradingform_frubric_instance extends gradingform_instance {

    /** @var array stores the frubric */
    protected $frubric;

    /**
     * Deletes this (INCOMPLETE) instance from database.
     */
    public function cancel() {
        global $DB;
        parent::cancel();
        $DB->delete_records('gradingform_frubric_fillings', array('instanceid' => $this->get_id()));
    }

    /**
     * Duplicates the instance before editing (optionally substitutes raterid and/or itemid with
     * the specified values)
     *
     * @param int $raterid value for raterid in the duplicate
     * @param int $itemid value for itemid in the duplicate
     * @return int id of the new instance
     */
    public function copy($raterid, $itemid) {
        global $DB;
        $instanceid = parent::copy($raterid, $itemid);
        $currentgrade = $this->get_frubric_filling();
        foreach ($currentgrade['criteria'] as $criterionid => $record) {
            $params = array(
                'instanceid' => $instanceid, 'criterionid' => $criterionid,
                'levelid' => $record['levelid'],
                'remark' => $record['remark'],
                'remarkformat' => $record['remarkformat'],
                'levelscore'  => $record['levelscore'],
                'leveljson' => $record['leveljson']
            );
            $DB->insert_record('gradingform_frubric_fillings', $params);
        }
        return $instanceid;
    }

    /**
     * Removes the attempt from the gradingform_guide_fillings table
     * @param array $data the attempt data
     */
    public function clear_attempt($data) {
        global $DB;
        foreach ($data['criteria'] as $criterionid => $record) {
            $DB->delete_records(
                'gradingform_frubric_fillings',
                array('criterionid' => $criterionid, 'instanceid' => $this->get_id())
            );
        }
    }

    /**
     * Validates that frubric. Only check the totaloutof property.
     * That way teachers can have rubrics and not check any descriptor,
     * this will let them save a result, similar to the checklist  grading method.
     *
     * @param array $elementvalue value of element as came in form submit
     * @return boolean true if the form data is validated and contains no errors
     */
    public function validate_grading_element($elementvalue) {

        $criteria = $this->get_controller()->get_definition()->frubric_criteria;

        if (!isset($elementvalue['criteria'])
            || !is_array($elementvalue['criteria'])
            || count($elementvalue['criteria']) < count($criteria)) {
            return false;
        }

        foreach ($criteria as $id => $criterion) {
            $max = $criterion['totaloutof'];

            if ($elementvalue['criteria'][$id]['levelscore'] > $max) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array An array containing a single key/value pair with the 'rubric_criteria' external_multiple_structure.
     * @see gradingform_controller::get_external_definition_details()
     * @since Moodle 2.5
     */
    public static function get_external_definition_details() {
        $frubriccriteria = new external_multiple_structure(
            new external_single_structure(
                array(
                    'id'   => new external_value(PARAM_INT, 'criterion id', VALUE_OPTIONAL),
                    'sortorder' => new external_value(PARAM_INT, 'sortorder', VALUE_OPTIONAL),
                    'description' => new external_value(PARAM_RAW, 'description', VALUE_OPTIONAL),
                    'descriptionformat' => new external_format_value('description', VALUE_OPTIONAL),
                    'levels' => new external_multiple_structure(
                        new external_single_structure(
                            array(
                                'id' => new external_value(PARAM_INT, 'level id', VALUE_OPTIONAL),
                                'score' => new external_value(PARAM_FLOAT, 'score', VALUE_OPTIONAL),
                                'definition' => new external_value(PARAM_RAW, 'definition', VALUE_OPTIONAL),
                                'definitionformat' => new external_format_value('definition', VALUE_OPTIONAL)
                            )
                        ),
                        'levels',
                        VALUE_OPTIONAL
                    )
                )
            ),
            'definition details',
            VALUE_OPTIONAL
        );
        return array('frubric_criteria' => $frubriccriteria);
    }


    /**
     * Retrieves from DB and returns the data how this frubric was filled
     *
     * @param boolean $force whether to force DB query even if the data is cached
     * @return array
     */
    public function get_frubric_filling($force = false) {
        global $DB;

        if ($this->frubric === null || $force) {
            $records = $DB->get_records('gradingform_frubric_fillings', array('instanceid' => $this->get_id()));
            $this->frubric = array('criteria' => array());
            foreach ($records as $record) {
                $this->frubric['criteria'][$record->criterionid] = (array)$record;
            }
        }

        return $this->frubric;
    }

    /**
     * Updates the instance with the data received from grading form. This function may be
     * called via AJAX when grading is not yet completed, so it does not change the
     * status of the instance.
     *
     * @param array $data
     */
    public function update($data) {
        global $DB;

        $currentgrade = $this->get_frubric_filling();
        parent::update($data);

        foreach ($data['criteria'] as $criterionid => $record) {
            if (!array_key_exists($criterionid, $currentgrade['criteria'])) {
                $newrecord = array(
                    'instanceid' => $this->get_id(), 'criterionid' => $criterionid,
                    'levelid' => $record['levelid'], 'remarkformat' => FORMAT_MOODLE,
                    'levelscore' => $record['levelscore'], 'leveljson' => $record['leveljson']
                );
                if (isset($record['remark'])) {
                    $newrecord['remark'] = $record['remark'];
                }
                $DB->insert_record('gradingform_frubric_fillings', $newrecord);
            } else {
                $newrecord = array('id' => $currentgrade['criteria'][$criterionid]['id']);
                foreach (array('levelid', 'remark'/*, 'remarkformat' */, 'levelscore', 'leveljson') as $key) {
                    if (isset($record[$key]) && $currentgrade['criteria'][$criterionid][$key] != $record[$key]) {
                        $newrecord[$key] = $record[$key];
                    }
                }
                if (count($newrecord) > 1) {
                    $DB->update_record('gradingform_frubric_fillings', $newrecord);
                }
            }
        }
        foreach ($currentgrade['criteria'] as $criterionid => $record) {
            if (!array_key_exists($criterionid, $data['criteria'])) {
                $DB->delete_records('gradingform_frubric_fillings', array('id' => $record['id']));
            }
        }
        $this->get_frubric_filling(true);
    }

    /**
     * Calculates the grade to be pushed to the gradebook
     *
     * @return float|int the valid grade from $this->get_controller()->get_grade_range()
     */
    public function get_grade() {
        $grade = $this->get_frubric_filling();

        if (!($scores = $this->get_controller()->get_min_max_score()) || $scores['maxscore'] <= $scores['minscore']) {
            return -1;
        }

        $graderange = array_keys($this->get_controller()->get_grade_range());
        if (empty($graderange)) {
            return -1;
        }

        sort($graderange);

        $mingrade = $graderange[0];
        $maxgrade = $graderange[count($graderange) - 1];

        $curscore = 0;
        foreach ($grade['criteria'] as $id => $record) {
            $curscore += $record['levelscore'];
        }

        $allowdecimals = $this->get_controller()->get_allow_grade_decimals();
        $options = $this->get_controller()->get_options();

        if ($options['lockzeropoints']) {
            // Grade calculation method when 0-level is locked.
            $grade = max($mingrade, $curscore / $scores['maxscore'] * $maxgrade);
            return $allowdecimals ? $grade : round($grade, 0);
        } else {
            // Alternative grade calculation method.
            $gradeoffset = ($curscore - $scores['minscore']) / ($scores['maxscore'] - $scores['minscore']) * ($maxgrade - $mingrade);
            return ($allowdecimals ? $gradeoffset : round($gradeoffset, 0)) + $mingrade;
        }
    }

    /**
     * Returns html for form element of type 'grading'.
     *
     * @param moodle_page $page
     * @param MoodleQuickForm_grading $gradingformelement
     * @return string
     */
    public function render_grading_element($page, $gradingformelement) {
        global  $OUTPUT;

        $definition = $this->get_controller()->get_definition();
        $areaid = $this->get_controller()->get_areaid();
        $criteria = $definition->frubric_criteria;
        $commentsoption = json_decode($definition->options);

        $data = [
            'criteria' => [],
            'preview' => 1, // Doesnt display criterion controls.
            'name' => $gradingformelement->getName(),
            'totalscore' => ($this->get_controller()->get_min_max_score())['maxscore'],
            'sumscores' => 0,
            'criteriadefinitionid' => $definition->id
        ];

        $counter = 1;

        foreach ($criteria as $c => $criterion) {
            $crite = new \stdClass();
            $crite->labelcrit  = "Criterion $counter";
            foreach ($criterion as $cr => $def) {  // The index has the name of the property.

                if ($cr == 'levels') {
                    // Re index the array.
                    $levels = [];
                    foreach ($def as $l => $level) {
                        $levels[] = toobject($level);
                    }

                    $crite->definitions = sortlevels($levels);
                }
                if ($cr == 'id') {
                    $crite->criteriaid = $def;
                }

                $crite->{$cr} = $def;
            }

            $crite->feedback        = '';
            $crite->disablecomment  = $commentsoption->disablecriteriacomments;
            $crite->levelscore      = '0';
            $crite->leveljson       = json_encode($crite->levels);
            unset($crite->id);      // This is the criteria id. I made it available with the name criteriaid.
            $data['criteria'][]     = $crite;
            // Only count the position if the criterion is visible.
            if ($crite->visibility) {
                $counter++;
            }
        }

        $dataobject                     = new stdClass();
        $dataobject->commentsoption     = $commentsoption;
        $dataobject->maxscore           = ($this->get_controller()->get_min_max_score())['maxscore'];
        $dataobject->name               = $gradingformelement->getName();
        $dataobject->definitionid       = $definition->id;
        $dataobject->areaid             = $areaid;
        $value                          = $gradingformelement->getValue();

        if ($value === null) {
            $value = $this->get_frubric_filling();
        } else if (!$this->validate_grading_element($value)) {
            $data['valuejson'] = json_encode($value); // Pass the data as data-attribute.
            $page->requires->js_call_amd('gradingform_frubric/submission_control', 'init', array($definition->id));
            $data['incomplete'] = 1;
            // In case there are some descriptors that where checked, we need to render it.
            $data['criteria'] = $this->format_element_value($value, $data['criteria']);
        }

        if (!$gradingformelement->_flagFrozen) {
            $data['datajson'] = json_encode($data); // Pass the data as data-attribute.
            $page->requires->js_call_amd('gradingform_frubric/evaluate_control', 'init', array(''));
        } else {
            if ($gradingformelement->_persistantFreeze) {
                $data['frozen'] = 1;
            } else {
                $data['review'] = 1;
            }
        }

        $html            = '';
        $currentinstance = $this->get_current_instance();

        if ($currentinstance && $currentinstance->get_status() == gradingform_instance::INSTANCE_STATUS_NEEDUPDATE) {
            $data['needupdate'] = 1;
        }

        $haschanges = false;
        $totalscore = 0;

        if ($currentinstance) {
            $curfilling = $currentinstance->get_frubric_filling();
            foreach ($curfilling['criteria'] as $criterionid => $curvalues) {
                $value['criteria'][$criterionid]['savedlevelid'] = $curvalues['levelid'];
                $newremark  = null;
                $newlevelid = null;

                if (isset($value['criteria'][$criterionid]['remark'])) {
                    $newremark = $value['criteria'][$criterionid]['remark'];
                    $this->set_criteria_fillings_feedback($data, $criterionid, $newremark);
                }

                if (isset($value['criteria'][$criterionid]['levelscore'])) {
                    $levelscore     = $value['criteria'][$criterionid]['levelscore'];
                    $totalscore    += $levelscore;
                    $this->set_criteria_fillings_levelscore($data, $criterionid, $levelscore);
                }

                if (isset($value['criteria'][$criterionid]['leveljson'])) {
                    $leveljson = $value['criteria'][$criterionid]['leveljson'];

                    $this->set_criteria_fillings_level($data, $criterionid, $leveljson, $haschanges);
                }

                if (isset($value['criteria'][$criterionid]['levelid'])) {
                    $newlevelid = $value['criteria'][$criterionid]['levelid'];
                }

                if ($newlevelid != $curvalues['levelid'] || $newremark != $curvalues['remark']) {
                    $haschanges = true;
                }
            }
        }

        $data['sumscores'] = $totalscore;

        if (!empty($options['showdescriptionteacher'])) {
            $contents       = $this->get_controller()->get_formatted_description();
            $attributes     = array('class' => 'gradingform_rubric-description');
            $html .= html_writer::tag('div', $contents, $attributes);
        }

        $html .= $OUTPUT->render_from_template('gradingform_frubric/editor_evaluate', $data);

        return $html;
    }


    private function format_element_value($elementvalue, &$criteria) {

        foreach ($criteria as $crit) {
            if (isset($elementvalue['criteria'][$crit->criteriaid]['levelscore'])) {
                $crit->levelscore = $elementvalue['criteria'][$crit->criteriaid]['levelscore'];
            }

            if (isset($elementvalue['criteria'][$crit->criteriaid]['remark'])) {
                $crit->feedback = $elementvalue['criteria'][$crit->criteriaid]['remark'];
            }

            $aux = get_object_vars(json_decode($crit->leveljson));

            foreach ($crit->definitions as $def) {
                list($definition, $descriptor) = $this->format_element_value_helper($elementvalue, $def->id);

                if ($descriptor != '') {
                    $def->definition['descriptors']['descriptor'] = $descriptor;
                    ($aux[$def->id])->definition = $definition;
                    ($aux[$def->id])->descriptors = $descriptor;
                }
            }
            $crit->leveljson = json_encode($aux);
        }

        return $criteria;
    }

    private function format_element_value_helper($elementvalue, $levelid) {
        foreach ($elementvalue['criteria'] as $criterionid => $data) {
            if ($data['leveljsonaux'] != '') {
                $levels = json_decode($data['leveljsonaux']);
                foreach ($levels as $index => $level) {
                    if ($level->id == $levelid) {
                        return [$level->definition, $level->descriptors];
                    }
                }
            }
        }
    }

    private function set_criteria_fillings_feedback($criteria, $criteriaid, $feedback) {

        foreach ($criteria as $i => $criterion) {
            foreach ($criterion as $j => $cri) {
                if ($cri->criteriaid == $criteriaid) {
                    $cri->feedback = $feedback;
                    return;
                }
            }
        }
    }

    private function set_criteria_fillings_levelscore($criteria, $criteriaid, $levelscore) {

        foreach ($criteria as $i => $criterion) {
            foreach ($criterion as $j => $cri) {
                if ($cri->criteriaid == $criteriaid) {
                    $cri->levelscore = round($levelscore, 1);
                    return;
                }
            }
        }
    }

    private function set_criteria_fillings_level(&$criteria, $criteriaid, $leveljson, &$haschanges) {
        $levelfilling = json_decode($leveljson);

        foreach ($criteria as $i => $criterion) {
            if (is_array($criterion)) {
                foreach ($criterion as $j => $cri) {
                    if ($cri->criteriaid == $criteriaid) {
                        foreach ($cri->definitions as $level) {
                            // The frubric was updated after grading. More levels added.
                            if (!isset($levelfilling->{$level->id})) {
                                $levelfilling->{$level->id} = $cri->levels[$level->id];
                            }
                            $lf = $levelfilling->{$level->id};
                            // Checks if there were updates on the number of descriptors.
                            if (count($level->definition['descriptors']['descriptor']) > count($lf->descriptors)) {
                                $deschecker                     = $this->descriptorschecker(
                                    $lf->descriptors,
                                    $level->definition['descriptors']['descriptor']
                                );
                                $level->definition['descriptors']['descriptor'] = $deschecker;
                                $haschanges = true;
                            } else {
                                // Check if the descriptor changed. after grading.
                                foreach ($cri->levels[$level->id]['descriptors'] as $index => $cd) {
                                    if ($cd->descText != ($lf->descriptors[$index])->descText) {
                                        ($lf->descriptors[$index])->descText = $cd->descText;
                                    }
                                }

                                $level->definition['descriptors']['descriptor'] = $lf->descriptors;
                                $haschanges = true;
                            }
                        }
                        $cri->leveljson = json_encode($levelfilling);
                    }
                }
            }
        }
        return json_encode($criteria);
    }

    private function descriptorschecker(&$levelfilldesc, $newdef) {

        foreach ($newdef as $i => $def) {
            if (!isset($levelfilldesc[$i])) {
                $levelfilldesc[$i] = $def;
            }
        }
        return $levelfilldesc;
    }
}


function toobject($array) {

    // Create new stdClass object.
    $object = new stdClass();
    if ($array == '') {
        return $object;
    }
    // Use loop to convert array into.
    // stdClass object.
    foreach ($array as $key => $value) {
        if (is_array($value)) {
            $value = toobject($value);
        }

        if ($key == 'definition') {
            if (!is_object($value)) {
                $value = format_descriptors(json_decode($value));
            }
        }
        $object->$key = $value;
    }

    return $object;
}

function format_descriptors($level) {

    $descriptors = $level->descriptors;
    $data = [];

    foreach ($descriptors as $d => $descriptor) {
        $data['descriptors']['descriptor'][] = $descriptor;
    }

    return $data;
}

/*Make this load definition available to be able to call from WS */
function load_definition($areaid) {
    global $DB;
    $sql = "SELECT gd.*,
                   rc.id AS rcid, rc.sortorder AS rcsortorder,
                   rc.description AS rcdescription,
                   rc.descriptionformat AS rcdescriptionformat,
                   rc.criteriajson AS criteriajson,
                   rl.id AS rlid, rl.score AS rlscore,
                   rl.definition AS rldefinition,
                   rl.definitionformat AS rldefinitionformat
              FROM {grading_definitions} gd
         LEFT JOIN {gradingform_frubric_criteria} rc ON (rc.definitionid = gd.id)
         LEFT JOIN {gradingform_frubric_levels} rl ON (rl.criterionid = rc.id)
             WHERE gd.areaid = :areaid AND gd.method = :method
          ORDER BY rl.id";
    $params = array('areaid' => $areaid, 'method' => 'frubric');
    $rs     = $DB->get_recordset_sql($sql, $params);

    foreach ($rs as $record) {
        // Pick the common definition data.
        $definition = new stdClass();
        foreach (array(
            'id', 'name', 'description', 'descriptionformat', 'status', 'copiedfromid',
            'timecreated', 'usercreated', 'timemodified', 'usermodified', 'timecopied', 'options',

        ) as $fieldname) {
            $definition->$fieldname = $record->$fieldname;
        }
        $definition->frubric_criteria = array();

        // Pick the criterion data.
        if (!empty($record->rcid) && empty($definition->frubric_criteria[$record->rcid])) {
            foreach (array('id', 'sortorder', 'description', 'descriptionformat') as $fieldname) {
                $definition->frubric_criteria[$record->rcid][$fieldname] = $record->{'rc' . $fieldname};
            }
            $definition->frubric_criteria[$record->rcid]['levels'] = array();
        }
        // Pick the level data.
        if (!empty($record->rlid)) {
            foreach (array('id', 'score', 'definition', 'definitionformat') as $fieldname) {
                $value = $record->{'rl' . $fieldname};
                $definition->frubric_criteria[$record->rcid]['levels'][$record->rlid][$fieldname] = $value;

                if ($fieldname == 'definition') { // Get the descriptors for the level.
                    $descrip = json_decode($value);
                    if (isset($descrip->descriptors)) {
                        $definition->frubric_criteria[$record->rcid]['levels'][$record->rlid]['descriptors'] = $descrip->descriptors;
                    }
                }
            }
        }

        $criteriajson = json_decode($record->criteriajson);

        if (!isset($definition->frubric_criteria[$record->rcid]['sumscore'])) {
            if (isset($criteriajson->sumscore)) {
                $definition->frubric_criteria[$record->rcid]['sumscore'] = $criteriajson->sumscore;
            }
        }
        if ($criteriajson != null) {
            if (isset($criteriajson->totaloutof)) {
                $definition->frubric_criteria[$record->rcid]['totaloutof'] = $criteriajson->totaloutof;
            }

            if (isset($criteriajson->visibility)) {
                $definition->frubric_criteria[$record->rcid]['visibility'] = $criteriajson->visibility;
            }
        }
    }

    $rs->close();
    return $definition->frubric_criteria;
}

function get_formated_criteria($dataobject) {
    $counter = 1;
    $data = [
        'criteria'              => [],
        'preview'               => 1, // Doesnt display criterion controls.
        'name'                  => $dataobject->name,
        'totalscore'            => $dataobject->maxscore,
        'sumscores'             => 0,
        'eval'                  => 1,
        'criteriadefinitionid'  => $dataobject->definitionid,
    ];

    $criteria = load_definition($dataobject->areaid);
    foreach ($criteria as $c => $criterion) {
        $crite = new \stdClass();
        $crite->labelcrit  = "Criterion $counter";
        foreach ($criterion as $cr => $def) {  // The index has the name of the property.
            if ($cr == 'levels') {
                // Re index the array.
                $levels = [];
                foreach ($def as $l => $level) {
                    $levels[] = toobject($level);
                }

                $crite->definitions = $levels;
            }
            if ($cr == 'id') {
                $crite->criteriaid = $def;
            }

            $crite->{$cr} = $def;
        }

        $crite->feedback        = '';
        $crite->disablecomment  = $dataobject->commentsoption->disablecriteriacomments;
        $crite->levelscore      = '0';
        $crite->leveljson       = json_encode($crite->levels);
        unset($crite->id); // This is the criteria id. I made it available with the name criteriaid.
        $data['criteria'][]     = $crite;
        $counter++;
    }

    return $data;
}

function sortlevels(&$levels)
{
    // Sort the levels from in descent order.
    usort($levels, function ($l1, $l2) {
        $score1 = explode('-', $l1->score);
        $score1 = trim($score1[count($score1) - 1]);
        $score2 = explode('-', $l2->score);
        $score2 = trim($score2[count($score2) - 1]);
        return ( (int)$score1 < (int)$score2);
    });

    return $levels;
}
