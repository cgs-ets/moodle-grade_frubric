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

require_once($CFG->dirroot . '/grade/grading/form/frubric/log_test.php');
require_once($CFG->dirroot . '/grade/grading/form/lib.php');
require_once($CFG->dirroot . '/lib/filelib.php');

/** frubric: Used to compare our gradeitem_type against. */
const FRUBRIC = 'frubric';

class gradingform_frubric_controller extends gradingform_controller {


    public function render_preview($page) {
        global $OUTPUT;

        if (!$this->is_form_defined()) {
            throw new coding_exception('It is the caller\'s responsibility to make sure that the form is actually defined');
        }

        $criteria = $this->definition->frubric_criteria;
        $options = $this->get_options();
        $frubric = '';

        if (has_capability('moodle/grade:managegradingforms', $page->context)) {
            $showdescription = true;
        } else {
            if (empty($options['alwaysshowdefinition'])) {
                // ensure we don't display unless show frubric option enabled
                return '';
            }
            $showdescription = $options['showdescriptionstudent'];
        }

        if ($showdescription) {
            $frubric .= $OUTPUT->box($this->get_formatted_description(), 'gradingform_frubric-description');
        }

        //$data = new \stdClass();
        // print_object($criteria); exit;
        foreach ($criteria as $c => $criterion) {
            $crite = new \stdClass();
            foreach ($criterion as $cr => $def) {  // The index has the name of the property.
                if ($cr == 'levels') {
                    //array_splice($def, 0, 0); // Re index the array
                    $levels = [];
                    foreach ($def as $l => $level) {
                        $levels[] = toObject($level);
                    }

                    $crite->definitions = $levels;
                }
                $crite->{$cr} = $def;
            }
            unset($crite->levels); // The levels property is not needed anymore. The relevant information is in the definit
            $data['criteria'][] =  $crite;
        }

        if (has_capability('moodle/grade:managegradingforms', $page->context)) {
            if (!$options['lockzeropoints']) {
                // Warn about using grade calculation method where minimum number of points is flexible.
                //$frubric .= $output->display_rubric_mapping_explained($this->get_min_max_score());
            }
            //$frubric .= $output->display_rubric($criteria, $options, self::DISPLAY_PREVIEW, 'frubric'); 
            // $data = [ TODO: VER QUE FUNCIONE EL RESTO
            //     'definitionid' => $this->definition->id,
            //     'preview' => true
            // ];
            $data['definitionid'] = $this->definition->id;
            $data['preview'] = true;


            $frubric .= $OUTPUT->render_from_template('gradingform_frubric/editor_preview_progress', $data);
        } else {

            $data['preview'] = true; // Default is DISPLAY_EDIT_FULL TODO: HACER LOS TEMPLATES PARA ESTO
            // $frubric .= $output->display_rubric($criteria, $options, self::DISPLAY_PREVIEW_GRADED, 'frubric');
            $frubric .=  $OUTPUT->render_from_template('gradingform_frubric/editor_preview_graded', $data);
        }

        //   print_object($frubric); exit;
        //print_object($data);  exit;
        return $frubric;
    }

    // private function toObject($array) {

    //     // Create new stdClass object
    //     $object = new stdClass();

    //     // Use loop to convert array into
    //     // stdClass object
    //     foreach ($array as $key => $value) {

    //         if (is_array($value)) {
    //             $value = $this->ToObject($value);
    //         }

    //         if ($key == 'definition') {
    //             $value = $this->format_descriptors(json_decode($value));
    //         }
    //         $object->$key = $value;
    //     }

    //     return $object;
    // }

    // private function format_descriptors($level) {

    //     $descriptors = $level->descriptors;

    //     foreach ($descriptors as $d => $descriptor) {

    //         $data['descriptors']['descriptor'][] = $descriptor;
    //     }

    //     //print_object($data);
    //     return $data;
    // }

    protected function delete_plugin_definition() {
        global $DB;
        // get the list of instances
        $instances = array_keys($DB->get_records('grading_instances', array('definitionid' => $this->definition->id), '', 'id'));
        // delete all fillings
        $DB->delete_records_list('gradingform_frubric_fillings', 'instanceid', $instances);
        // delete instances
        $DB->delete_records_list('grading_instances', 'id', $instances);
        // get the list of criteria records
        $criteria = array_keys($DB->get_records('gradingform_frubric_criteria', array('definitionid' => $this->definition->id), '', 'id'));
        // delete level descriptors
        $DB->delete_records_list('gradingform_frubric_descript', 'criterionid', $criteria);
        // delete levels
        $DB->delete_records_list('gradingform_frubric_levels', 'criterionid', $criteria);
        // delete critera
        $DB->delete_records_list('gradingform_frubric_criteria', 'id', $criteria);
    }


    /**
     * Converts the current definition into an object suitable for the editor form's set_data()
     * bool	$addemptycriterion	whether to add an empty criterion if the guide is completely empty (just being created)
     */
    public function get_definition_for_editing() {

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
            // $properties->definitionid = $definition->id;
        }

        $properties->flexrubric = array('criteria' => array(), 'options' => $this->get_options());

        if (!empty($definition->flexrubric_criteria)) {
            $properties->flexrubric['criteria'] = $definition->frubric_criteria;
        } else if (!$definition) {
            $properties->flexrubric['criteria'] = array();
        }

        return $properties;
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
            $thisoptions = json_decode($this->definition->options, true); // Assoc. array is expected.
            foreach ($thisoptions as $option => $value) {
                $options[$option] = $value;
            }
            if (!array_key_exists('lockzeropoints', $thisoptions)) {
                // Rubrics created before Moodle 3.2 don't have 'lockzeropoints' option. In this case they should not
                // assume default value 1 but use "legacy" value 0.
                $options['lockzeropoints'] = 0;
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
            'sortlevelsasc' => 1,
            'lockzeropoints' => 1,
            'alwaysshowdefinition' => 1,
            'showdescriptionteacher' => 1,
            'showdescriptionstudent' => 1,
            'showscoreteacher' => 1,
            'showscorestudent' => 1,
            'enableremarks' => 1,
            'showremarksstudent' => 1
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
        if (isset($newdefinition->frubric['regrade']) && $newdefinition->frubric['regrade']) {
            //$this->mark_for_regrade();
        }
    }


    /**
     * Either saves the frubric definition into the database or check if it has been changed.
     * Returns the level of changes:
     * 0 - no changes
     * 1 - only texts or criteria sortorders are changed, students probably do not require re-grading
     * 2 - added levels but maximum score on frubric is the same, students still may not require re-grading
     * 3 - removed criteria or added levels or changed number of points, students require re-grading but may be re-graded automatically
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
                // if we create the new definition there is no such thing as re-grading anyway
                return 5;
            }

            // if definition does not exist yet, create a blank one
            // (we need id to save files embedded in description)
            parent::update_definition(new stdClass(), $usermodified);
            parent::load_definition();
        }

        if (!isset($newdefinition->frubric['options'])) {
            $newdefinition->frubric['options'] = self::get_default_options();
        }

        $newdefinition->options = json_encode($newdefinition->frubric['options']);
        $editoroptions = self::description_form_field_options($this->get_context());
        $newdefinition = file_postupdate_standard_editor($newdefinition, 'description', $editoroptions, $this->get_context(), 'grading', 'description', $this->definition->id);

        // reload the definition from the database
        $currentdefinition = $this->get_definition(true);
        $haschanges = array();

        // Check if 'lockzeropoints' option has changed.
        $newlockzeropoints =  $newdefinition->frubric['options']['lockzeropoints'];
        $currentoptions = $this->get_options();

        if ((bool)$newlockzeropoints != (bool)$currentoptions['lockzeropoints']) {
            $haschanges[3] = true;
        }

        $newcriteria = json_decode($newdefinition->criteria);
        $criteriafields = array('sortorder', 'description', 'descriptionformat');
        $trackcriteriondbids = []; // Keep track of the ids generated by the DB. this will be used to update the id of the criterion
        $trackleveldbids = []; // Keep track of the ids generated by the DB. this will be used to update the id of the level
        //  log_messages(print_r($newcriteria, true));
        foreach ($newcriteria as $i => $criterion) {
            // log_messages(print_r($criterion, true));
            $levels = $criterion->levels;
            $criterionmaxscore = null;

            if ($criterion->status == "NEW") {
                // Insert criterion into DB.
                $data = array('definitionid' => $this->definition->id, 'descriptionformat' => FORMAT_MOODLE); // TODO MDL-31235 format is not supported yet

                foreach ($criteriafields as $key) {
                    if ($key == 'descriptionformat' || $key == 'criteriajson') continue;
                    $data[$key] = ($key == 'sortorder') ? $criterion->rowindex : $criterion->{$key};
                }

                $cid = explode('-', $criterion->cid);
                array_pop($cid);
                $cid = implode('-', $cid);
                $criterion->cid = $cid; // Remove the word NEWID from the element.

                if ($doupdate) {
                    $criterion->status = 'CREATED';
                    $data['criteriajson'] = json_encode($criterion);
                    $id = $DB->insert_record('gradingform_frubric_criteria', $data, true);
                    $trackcriteriondbids[$criterion->id] = $id; // The index its the old id t
                    $criterion->id = $id;
                }

                $haschanges[5] = true;
            } else if ($criterion->status == "UPDATE") {
                // Update criterion in DB
                $updatecriterion = new \stdClass();
                $updatecriterion->id = $criterion->id;
                $criterion->status = "UPDATED";
                $updatecriterion->description = $criterion->description;
                $updatecriterion->criteriajson = json_encode($criterion);
                $DB->update_record('gradingform_frubric_criteria', $updatecriterion);
            } else  if ($criterion->status == "DELETE") { // DELETE CRITERION
                if ($doupdate) {
                    foreach ($criterion->levels as $level) {
                        $DB->delete_records('gradingform_frubric_levels', array('id' => $level->dbid));
                    }
                    $DB->delete_records('gradingform_frubric_criteria', array('id' => $criterion->id));
                    $haschanges[4] = true;
                }
            }
            //  print_object($levels); exit;
            foreach ($levels as $l => $level) {
                if ($level->status == 'UPDATED') continue; // This level was updated before. Not this time.
                if ($level->status == 'NEW') {
                    // insert level into DB.
                    $leveldata = array('criterionid' => $id, 'definitionformat' => FORMAT_MOODLE); // TODO MDL-31235 format is not supported yet

                    $leveldata['score'] =  1;  // TODO 

                    if ($doupdate) {
                        $levelid = $DB->insert_record('gradingform_frubric_levels', $leveldata, true);
                        $trackleveldbids[$level->id] = $levelid;  // Track ids.

                        $countselected = 0;

                        $leveldescriptorids = [];
                        // Insert the descriptors
                        foreach ($level->descriptors as $d => &$descriptor) {

                            list($score, $maxscore) = $this->get_level_score($level->score);
                            $sc = $level->score;

                            if ($descriptor->checked) {
                                $countselected++;
                            }

                            $descriptordata = new \stdClass();
                            $descriptordata->criterionid = $id;
                            $descriptordata->levelid = $levelid;
                            $descriptordata->score = $score;
                            $descriptordata->maxscore = $maxscore;
                            $descriptordata->description = $descriptor->descText;
                            $descriptordata->selected = $descriptor->checked;
                            $descriptordata->deleted = $descriptor->delete;


                            $descriptorid = $DB->insert_record('gradingform_frubric_descript', $descriptordata);
                            $descriptordata->id = $descriptorid;
                            $records[] = $descriptordata;

                            $descriptor->descriptorid =  $descriptorid;
                            // Update the level definition. Just save the descriptors for this level.
                            // Collect all the descriptorids
                            $leveldescriptorids[] = $levelid;

                            $level->status = 'CREATED';
                            $updatelevel = new \stdClass();
                            $updatelevel->id = $levelid;
                            $updatelevel->score = $sc;
                            $level->id = $levelid;
                            $level->score = $level->score;

                            $updatelevel->definition = json_encode($level);

                            $DB->update_record('gradingform_frubric_levels', $updatelevel);
                        }

                        // Set the score
                        if ($countselected > 0) {

                            foreach ($records as $r => $desdata) {
                                if ($desdata->selected) {
                                    $desdata->score = 1; // $desdata->score / $countselected;
                                } else {
                                    $desdata->score = 0;
                                }
                            }

                            $DB->update_record('gradingform_frubric_descript', $desdata);
                        }


                        unset($records);
                    }

                    if ($criterionmaxscore !== null && $criterionmaxscore >= $level->score) {
                        // new level is added but the maximum score for this criteria did not change, re-grading may not be necessary
                        $haschanges[2] = true;
                    } else {
                        $haschanges[3] = true;
                    }
                } else if ($level->status == 'UPDATE') {
                    // Update level in DB 
                    $lr = $DB->get_record('gradingform_frubric_levels', ['id' => $level->id]); // Level record.
                    $lr->score = $level->score;

                    $level->status = 'UPDATED';
                    $descriptorstodelete = [];
                    $levelaux = $level->descriptors;

                    // Update the descriptors.
                    foreach ($levelaux as $j => $ld) {
                        $descupdate = new \stdClass();
                        // log_messages(print_r($ld, true));

                        if (!isset($ld->descriptorid)) { // a new descriptor has been added
                            $descupdate->criterionid = $criterion->id;
                            $descupdate->score = 1; //$level->score;
                            $descupdate->maxscore = 1; // TODO
                            $descupdate->description = $ld->descText;
                            $descupdate->selected = $ld->checked;
                            $descupdate->deleted = $ld->delete;
                            $descupdate->levelid = $level->id;
                            $newdescid =  $DB->insert_record('gradingform_frubric_descript', $descupdate);
                            ($level->descriptors[$j])->descriptorid = $newdescid;
                        } else {

                            $descupdate->id = $ld->descriptorid;
                            //  $descupdate->score = $score;
                            //  $descupdate->maxscore = $maxscore;
                            $descupdate->description = $ld->descText;
                            $descupdate->selected = $ld->checked;
                            $descupdate->deleted = $ld->delete;

                            if ($ld->delete == 1) {
                                $destodelete  = new \stdClass();
                                $destodelete->id = ($levelaux[$j])->descriptorid;
                                $descriptorstodelete[] = $j;
                                $DB->delete_records('gradingform_frubric_descript', ['id' => ($levelaux[$j])->descriptorid]);
                            }

                            if ($j == (count($level->descriptors) - 1)) {
                                // log_messages(print_r($descriptorstodelete, true));
                                foreach ($descriptorstodelete as $i => $index) {
                                    unset($level->descriptors[$index]);
                                }

                                $level->descriptors = array_values($level->descriptors);
                            }
                            $DB->update_record('gradingform_frubric_descript', $descupdate);
                        }

                        $lr->definition = json_encode($level);
                    }

                    //log_messages(print_r($lr, true));
                    $DB->update_record('gradingform_frubric_levels', $lr);
                } else if ($level->status == 'DELETE') {
                    // Delete level in DB. 
                    //TODO: Volar los niveles del json
                    $level->status = 'UPDATED';
                    $DB->delete_records('gradingform_frubric_levels', array('id' => $level->dbid));
                }
            }

            // if ($criterion->status == "NEW")  {  // Up until now, the criteriajson field had the entire criteria. Here update the field with only the details of the criterion.
            $updatecriteriajson = new \stdClass();
            $updatecriteriajson->id = $criterion->id;
            //  log_messages(print_r($criterion, true));
            $updatecriteriajson->criteriajson = json_encode($criterion);
            $DB->update_record('gradingform_frubric_criteria', $updatecriteriajson);

            //  }
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

        // return the maximum level of changes
        $changelevels = array_keys($haschanges);
        sort($changelevels);
        return array_pop($changelevels);
    }


    private function get_level_score($scorefield) {

        if ($scorefield == 0) { // This is the lowest level. The only one it can have zero.
            return [0, 1];
        }

        if (strpos($scorefield, '-')) { // format MIN-MAX.  
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
        if (
            $instanceid &&
            $instance = $DB->get_record('grading_instances', array('id'  => $instanceid, 'raterid' => $raterid, 'itemid' => $itemid), '*', IGNORE_MISSING)
        ) {
            return $this->get_instance($instance);
        }
        if ($itemid && $raterid) {
            $params = array('definitionid' => $this->definition->id, 'raterid' => $raterid, 'itemid' => $itemid);
            if ($rs = $DB->get_records('grading_instances', $params, 'timemodified DESC', '*', 0, 1)) {
                $record = reset($rs);
                $currentinstance = $this->get_current_instance($raterid, $itemid);
                if (
                    $record->status == gradingform_frubric_instance::INSTANCE_STATUS_INCOMPLETE &&
                    (!$currentinstance || $record->timemodified > $currentinstance->get_data('timemodified'))
                ) {
                    $record->isrestored = true;
                    return $this->get_instance($record);
                }
            }
        }


        return $this->create_instance($raterid, $itemid);
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
     * Loads the frubric form definition if it exists
     *
     * There is a new array called 'frubric_criteria' appended to the list of parent's definition properties.
     */
    protected function load_definition() {

        global $DB;
        $sql = "SELECT gd.*,
                       rc.id AS rcid, rc.sortorder AS rcsortorder, rc.description AS rcdescription, rc.descriptionformat AS rcdescriptionformat,
                       rc.criteriajson AS criteriajson, rl.id AS rlid, rl.score AS rlscore, rl.definition AS rldefinition, rl.definitionformat AS rldefinitionformat
                  FROM {grading_definitions} gd
             LEFT JOIN {gradingform_frubric_criteria} rc ON (rc.definitionid = gd.id)
             LEFT JOIN {gradingform_frubric_levels} rl ON (rl.criterionid = rc.id)
                 WHERE gd.areaid = :areaid AND gd.method = :method
              ORDER BY rl.id";
        $params = array('areaid' => $this->areaid, 'method' => $this->get_method_name());

        $rs = $DB->get_recordset_sql($sql, $params);

        $this->definition = false;
        foreach ($rs as $record) {

            // pick the common definition data
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

            // pick the criterion data
            if (!empty($record->rcid) and empty($this->definition->frubric_criteria[$record->rcid])) {
                foreach (array('id', 'sortorder', 'description', 'descriptionformat') as $fieldname) {

                    $this->definition->frubric_criteria[$record->rcid][$fieldname] = $record->{'rc' . $fieldname};
                }
                $this->definition->frubric_criteria[$record->rcid]['levels'] = array();
            }
            // pick the level data
            if (!empty($record->rlid)) {
                foreach (array('id', 'score', 'definition', 'definitionformat') as $fieldname) {
                    $value = $record->{'rl' . $fieldname};
                    $this->definition->frubric_criteria[$record->rcid]['levels'][$record->rlid][$fieldname] = $value;

                    if ($fieldname == 'definition') { // Get the descriptors for the level
                        $descrip =  json_decode($value);
                        $this->definition->frubric_criteria[$record->rcid]['levels'][$record->rlid]['descriptors'] = $descrip->descriptors;
                    }
                }
            }

            $criteriajson = json_decode($record->criteriajson);
            $this->definition->frubric_criteria[$record->rcid]['sumscore'] = $criteriajson->sumscore;
            $this->definition->frubric_criteria[$record->rcid]['totaloutof'] = $criteriajson->totaloutof;
        }
        $rs->close();
        $options = $this->get_options();
        if (!$options['sortlevelsasc']) {
            foreach (array_keys($this->definition->frubric_criteria) as $rcid) {
                $this->definition->frubric_criteria[$rcid]['levels'] = array_reverse($this->definition->frubric_criteria[$rcid]['levels'], true);
            }
        }
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
                'levelid' => $record['levelid'], 'remark' => $record['remark'], 'remarkformat' => $record['remarkformat'], 'leveljson' => $record['leveljson']
            );
            $DB->insert_record('gradingform_frubric_fillings', $params);
        }
        return $instanceid;
    }

    /**
     * Determines whether the submitted form was empty.
     *
     * @param array $elementvalue value of element submitted from the form
     * @return boolean true if the form is empty
     */
    public function is_empty_form($elementvalue) {
        $criteria = $this->get_controller()->get_definition()->frubric_criteria;
        log_messages("is_empty_form");
        log_messages(print_r($elementvalue, true));
        log_messages(print_r($criteria, true));
        foreach ($criteria as $id => $criterion) {
            if (
                isset($elementvalue['criteria'][$id]['levelid'])
                || !empty($elementvalue['criteria'][$id]['remark'])
            ) {
                return false;
            }
        }
        return true;
    }

    /**
     * Removes the attempt from the gradingform_guide_fillings table
     * @param array $data the attempt data
     */
    public function clear_attempt($data) {
        global $DB;
        log_messages("CLEAR ATTEMPT");
        log_messages(print_r($data, true));
        foreach ($data['criteria'] as $criterionid => $record) {
            $DB->delete_records(
                'gradingform_frubric_fillings',
                array('criterionid' => $criterionid, 'instanceid' => $this->get_id())
            );
        }
    }

    /**
     * Validates that frubric is fully completed and contains valid grade on each criterion
     *
     * @param array $elementvalue value of element as came in form submit
     * @return boolean true if the form data is validated and contains no errors
     */
    public function validate_grading_element($elementvalue) {
        log_messages("validate_grading_element");
        log_messages($elementvalue);
        $criteria = $this->get_controller()->get_definition()->frubric_criteria;

        if (!isset($elementvalue['criteria']) || !is_array($elementvalue['criteria']) || sizeof($elementvalue['criteria']) < sizeof($criteria)) {
            return false;
        }
        foreach ($criteria as $id => $criterion) {
            if (
                !isset($elementvalue['criteria'][$id]['levelid'])
                || !array_key_exists($elementvalue['criteria'][$id]['levelid'], $criterion['levels'])
            ) {
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
        $frubric_criteria = new external_multiple_structure(
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
        return array('frubric_criteria' => $frubric_criteria);
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
                    'leveljson' => $record['leveljson']
                );
                if (isset($record['remark'])) {
                    $newrecord['remark'] = $record['remark'];
                }
                $DB->insert_record('gradingform_frubric_fillings', $newrecord);
            } else {
                $newrecord = array('id' => $currentgrade['criteria'][$criterionid]['id']);
                foreach (array('levelid', 'remark'/*, 'remarkformat' */) as $key) {
                    // TODO MDL-31235 format is not supported yet
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
        $maxgrade = $graderange[sizeof($graderange) - 1];

        $curscore = 0;
        foreach ($grade['criteria'] as $id => $record) {
            $curscore += $this->get_controller()->get_definition()->frubric_criteria[$id]['levels'][$record['levelid']]['score'];
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

        //  return 100;
    }

    /**
     * Returns html for form element of type 'grading'.
     *
     * @param moodle_page $page
     * @param MoodleQuickForm_grading $gradingformelement
     * @return string
     */
    public function render_grading_element($page, $gradingformelement) {
        global $USER, $OUTPUT;

        $criteria = $this->get_controller()->get_definition()->frubric_criteria;

        $data = [
            'criteria' => [],
            'preview' => 1, // doesnt display criterion controls.
            'name' => $gradingformelement->getName(),
            'totalscore' => ($this->get_controller()->get_min_max_score())['maxscore']
        ];


        foreach ($criteria as $c => $criterion) {
            $crite = new \stdClass();
          
            foreach ($criterion as $cr => $def) {  // The index has the name of the property.
                if ($cr == 'levels') {
                    // Re index the array
                    $levels = [];
                    foreach ($def as $l => $level) {
                        $levels[] = toObject($level);
                    }

                    $crite->definitions = $levels;

                    
                }
                if ($cr == 'id') {
                    $crite->criteriaid = $def;
                }
                $crite->{$cr} = $def;
               
            }
            $crite->feedback = '';
            // log_messages(print_r($crite->levels, true));
            $crite->leveljson = json_encode($crite->levels);
            unset($crite->levels); // The levels property is not needed anymore. The relevant information is in the definition.
            unset($crite->id); // This is the criteria id. I made it available with the name criteriaid
            $data['criteria'][] =  $crite;
        }


        if (!$gradingformelement->_flagFrozen) {
            $data['eval'] = 1;
            $page->requires->js_call_amd('gradingform_frubric/evaluate_control', 'init', array(json_encode($data)));
        } else {
            if ($gradingformelement->_persistantFreeze) {
                $data['frozen'] = 1;
            } else {
                $data['review'] = 1;
            }
        }

        $value = $gradingformelement->getValue();

        $html = '';

        if ($value === null) {
            $value = $this->get_frubric_filling();
        } else if (!$this->validate_grading_element($value)) {
            $data['incomplete'] = 1;
        }
        //   log_messages(print_r($value , true)); 
        $currentinstance = $this->get_current_instance();
        if ($currentinstance && $currentinstance->get_status() == gradingform_instance::INSTANCE_STATUS_NEEDUPDATE) {
            $data['needupdate'] = 1;
        }
        $haschanges = false;
        if ($currentinstance) {
            $curfilling = $currentinstance->get_frubric_filling();
            //      log_messages(print_r($curfilling, true));  // TODO: bring the json
            foreach ($curfilling['criteria'] as $criterionid => $curvalues) {
                $value['criteria'][$criterionid]['savedlevelid'] = $curvalues['levelid'];
                $newremark = null;
                $newlevelid = null;
                if (isset($value['criteria'][$criterionid]['remark'])) {
                    $newremark = $value['criteria'][$criterionid]['remark'];
                    set_criteria_feedback($data, $criterionid, $newremark);
                }
                if (isset($value['criteria'][$criterionid]['levelid'])) $newlevelid = $value['criteria'][$criterionid]['levelid'];
                if ($newlevelid != $curvalues['levelid'] || $newremark != $curvalues['remark']) {
                    $haschanges = true;
                }
            }
        }

        // log_messages(print_r($value , true)); 

        // log_messages("gradingformelement");
        // log_messages(print_r($gradingformelement , true)); 
        if ($this->get_data('isrestored') && $haschanges) {
            $html .= html_writer::tag('div', get_string('restoredfromdraft', 'gradingform_rubric'), array('class' => 'gradingform_rubric-restored'));
        }
        if (!empty($options['showdescriptionteacher'])) {
            $html .= html_writer::tag('div', $this->get_controller()->get_formatted_description(), array('class' => 'gradingform_rubric-description'));
        }
        // log_messages(print_r($gradingformelement, true));
        log_messages(print_r($data, true));
        $html .= $OUTPUT->render_from_template('gradingform_frubric/editor_evaluate', $data);
        return $html;
    }
}

function toObject($array) {

    // Create new stdClass object
    $object = new stdClass();

    // Use loop to convert array into
    // stdClass object
    foreach ($array as $key => $value) {

        if (is_array($value)) {
            $value = ToObject($value);
        }

        if ($key == 'definition') {
            $value = format_descriptors(json_decode($value));
        }
        $object->$key = $value;
    }

    return $object;
}

function format_descriptors($level) {

    $descriptors = $level->descriptors;

    foreach ($descriptors as $d => $descriptor) {

        $data['descriptors']['descriptor'][] = $descriptor;
    }


    return $data;
}


function set_criteria_feedback($criteria, $criteriaid, $feedback) {

    foreach ($criteria as $i => $criterion) {
        foreach ($criterion as $j => $cri) {
            if ($cri->criteriaid == $criteriaid) {
                $cri->feedback = $feedback;
                return;
            }
        }
    }
}
