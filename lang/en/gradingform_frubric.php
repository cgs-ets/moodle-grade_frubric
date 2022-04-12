<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin strings are defined here.
 *
 * @package     gradingform_frubric
 * @category    string
 * @copyright   2021 Veronica Bermegui
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Frubric';
$string['name'] = 'Name';
$string['definefrubric'] = 'Define Frubric';
$string['gradingof'] = '{$a} grading';
$string['description'] = 'Description';
$string['frubricstatus'] = 'Status';
$string['criteriajson'] = 'Criteria JSON';
$string['savefrubric'] = 'Save Frubric and make it ready';
$string['savefrubricdraft'] = 'Save as draft';
$string['save'] = 'Save';
$string['backtoediting'] = 'Back to editing';
$string['addcriterion'] = 'Add criterion';
$string['addlevel'] = 'Add Level';
$string['confirmdeletecriterion'] = 'Are you sure you want to delete this criterion?';
$string['confirmdeletelevel'] = 'Are you sure you want to delete this level?';
$string['confirmdeletedescriptor'] = 'Are you sure you want to delete this descriptor?';
$string['confirmdeletesetcriterion'] = 'Are you sure you want to delete the set of descriptors?';
$string['frubriceditor'] = 'frubriceditor';
$string['confirm_regrade'] = 'You are about to save significant changes to a rubric that has already been used for grading. The gradebook value will be unchanged, but the rubric will be hidden from students until their item is regraded.';
$string['confirm'] = 'Confirmation';
$string['editcriterion'] = 'Click to edit criterion';
$string['criterioncantbeempty'] = 'Criterion cannot be empty';
$string['editcriterion'] = 'Click to edit criterion';
$string['err_nocriteria'] = 'Flexibe rubric must contain at least one criterion';
$string['err_nodefinition'] = 'Level definition can not be empty';
$string['err_nodescription'] = 'You need at least one descriptor defined.';
$string['err_nodescriptiondef'] = 'Descriptor can not be empty';
$string['err_noscore'] = "Score can not be empty";
$string['err_levels'] = 'Levels can not be empty';
$string['levelorderasc'] = 'Ascending by number of points';
$string['levelorderdesc'] = 'Descending by number of points';
$string['lockzeropoints'] = 'Calculate grade having a minimum score of the minimum achievable grade for the rubric';
$string['alwaysshowdefinition'] = 'Allow users to preview rubric (otherwise it will only be displayed after grading)';
$string['showdescriptionteacher'] = 'Display rubric description during evaluation';
$string['showdescriptionstudent'] = 'Display rubric description to those being graded';
$string['showscoreteacher'] = 'Display points for each level during evaluation';
$string['showscorestudent'] = 'Display points for each level to those being graded';
$string['enableremarks'] = 'Allow grader to add text remarks for each criterion';
$string['showremarksstudent'] = 'Show remarks to those being graded';
$string['editmark'] = 'Click to edit Mark';
$string['editleveldesc'] = 'Click to edit level descriptor';

$string['adddescriptorlabel'] = 'Add descriptor';
$string['deletedescriptor'] = 'Delete descriptor';
$string['mark'] = 'Min - Max';

$string['frubricnotcompleted'] = 'Not saved.
Please fix the following error(s) and save changes.';
$string['nodescriptor'] = '* Each level has to have at least one descriptor checked.';
$string['nodscore'] = '* Level score cannot be empty.';

$string['needregrademessage'] = 'The frubric definition was changed after this student had been graded. The student can not see this rubric until you check the rubric and update the grade.';
$string['regrademessage5'] = 'You are about to save changes to a rubric that has already been used for grading. The gradebook value will be unchanged, but the frubric will be hidden from students until their item is regraded.';
