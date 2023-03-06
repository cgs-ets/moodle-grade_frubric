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
 * Support for restore API
 *
 * @package    gradingform_frubric
 * @copyright  2011 David Mudrak <david@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Restores the rubric specific data from grading.xml file
 *
 * @package    gradingform_frubric
 * @copyright  2011 David Mudrak <david@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_gradingform_frubric_plugin extends restore_gradingform_plugin {

    /**
     * Declares the rubric XML paths attached to the form definition element
     *
     * @return array of {@link restore_path_element}
     */
    protected function define_definition_plugin_structure() {

        $paths = array();

        $paths[] = new restore_path_element(
            'gradingform_frubric_criterion',
            $this->get_pathfor('/frcriteria/frcriterion')
        );

        $paths[] = new restore_path_element(
            'gradingform_frubric_level',
            $this->get_pathfor('/frcriteria/frcriterion/frlevels/frlevel')
        );

        $paths[] = new restore_path_element(
            'gradingform_frubric_descriptor',
            $this->get_pathfor('/frcriteria/frcriterion/frlevels/frlevel/frdescriptors/frdescriptor')
        );

        return $paths;
    }

    /**
     * Declares the rubric XML paths attached to the form instance element
     *
     * @return array of {@link restore_path_element}
     */
    protected function define_instance_plugin_structure() {

        $paths = array();

        $paths[] = new restore_path_element(
            'gradinform_frubric_filling',
            $this->get_pathfor('/fillings/filling')
        );

        return $paths;
    }

    /**
     * Processes criterion element data
     *
     * Sets the mapping 'gradingform_frubric_criterion' to be used later by
     * {@link self::process_gradinform_rubric_filling()}
     *
     * @param stdClass|array $data
     */
    public function process_gradingform_frubric_criterion($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->definitionid = $this->get_new_parentid('grading_definition');

        $newid = $DB->insert_record('gradingform_frubric_criteria', $data);
        $this->set_mapping('gradingform_frubric_criterion', $oldid, $newid);
    }

    /**
     * Processes level element data
     *
     * Sets the mapping 'gradingform_frubric_level' to be used later by
     * {@link self::process_gradinform_rubric_filling()}
     *
     * @param stdClass|array $data
     */
    public function process_gradingform_frubric_level($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->criterionid = $this->get_new_parentid('gradingform_frubric_criterion');
        $newid = $DB->insert_record('gradingform_frubric_levels', $data);
        $this->set_mapping('gradingform_frubric_level', $oldid, $newid);
    }

    /**
     * Processes descriptor element data
     *
     * Sets the mapping 'gradingform_frubric_descript
     *
     * @param stdClass|array $data
     */
    public function process_gradingform_frubric_descriptor($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->criterionid = $this->get_new_parentid('gradingform_frubric_criterion');
        $data->levelid = $this->get_new_parentid('gradingform_frubric_level');

        $newid = $DB->insert_record('gradingform_frubric_descript', $data);
        $levelidmap = [];
        // The definition JSON has to update.
        if ($level = $DB->get_record('gradingform_frubric_levels', ['id' => $data->levelid], 'definition')) {
            $levelaux = json_decode($level->definition);

            foreach ($levelaux as $prop => &$definition) {

                if ($prop == 'id') {
                    $levelidmap[$data->levelid] = $definition; // new id for the level is the index.
                    $definition = $data->levelid;
                }

                if ($prop == 'descriptors') {

                    foreach ($definition as &$descriptor) {
                        if ($descriptor->descriptorid == $oldid) {
                            $descriptor->descriptorid = $newid;
                        }
                    }
                }
            }

            $level->definition = json_encode($levelaux);

            // We need to  update the definition json.
            $sql = "UPDATE mdl_gradingform_frubric_levels SET definition = :definition WHERE id = :id";
            $DB->execute($sql, ['definition' => $level->definition, 'id' => $data->levelid]);
            // Update the criteriajson.
            $this->update_criteriajson($data, $levelidmap);
        }

        $this->set_mapping('gradingform_frubric_descriptor', $oldid, $newid);
    }

    private function update_criteriajson($data, $levelidmap) {
        global $DB;
        if ($criterion = $DB->get_record('gradingform_frubric_criteria', ['id' => $data->criterionid], 'criteriajson')) {
            $criterionaux = json_decode($criterion->criteriajson);

            foreach ($criterionaux as $prop => &$val) {
                if ($prop == 'id') {
                    $val = $data->criterionid;
                }

                if ($prop == 'levels') {
                    foreach ($val as &$la) {
                        if ($la->id == $levelidmap[$data->levelid] ) {
                            $la->id = $data->levelid;
                        }
                    }
                }
            }

            $criterionaux = json_encode($criterionaux);
            // // We need to  update the definition json.
            $sql = "UPDATE mdl_gradingform_frubric_criteria SET criteriajson = :criteriajson WHERE id = :id";
            $DB->execute($sql, ['criteriajson' => $criterionaux, 'id' => $data->criterionid]);
        }
    }

    /**
     * Processes filling element data
     *
     * @param stdClass|array $data
     */
    public function process_gradinform_frubric_filling(&$data) {
        global $DB;

        $data = (object)$data;
        $data->instanceid = $this->get_new_parentid('grading_instance');
        $data->criterionid = $this->get_mappingid('gradingform_frubric_criterion', $data->criterionid);
        $data->levelid = $this->get_mappingid('gradingform_frubric_level', $data->levelid);
        $leveljsonupdated = [];
        $data->leveljson = json_decode($data->leveljson);

        if (!empty($data->criterionid)) {
            // We need to update the leveljson for the fillings table.
            foreach ($data->leveljson as $i => &$level) {

                $level->id = $this->get_mappingid('gradingform_frubric_level',  $level->id);
                $definitionaux = json_decode($level->definition);
                $definitionaux->id = $level->id;
                $level->definition = json_encode($definitionaux);

                foreach ($level->descriptors as $d => &$desc) {
                    $desc->descriptorid = $this->get_mappingid('gradingform_frubric_descriptor', $desc->descriptorid);;
                }

                $leveljsonupdated[$level->id] = $level;
            }

            $data->leveljson = json_encode($leveljsonupdated);
            $DB->insert_record('gradingform_frubric_fillings', $data);
        }
    }
}
