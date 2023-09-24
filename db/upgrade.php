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
 * Plugin upgrade steps are defined here.
 *
 * @package     gradingform_frubric
 * @category    upgrade
 * @copyright   2021 Veronica Bermegui
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__.'/upgradelib.php');

/**
 * Execute gradingform_frubric upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_gradingform_frubric_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // For further information please read {@link https://docs.moodle.org/dev/Upgrade_API}.
    //
    // You will also have to create the db/install.xml file by using the XMLDB Editor.
    // Documentation for the XMLDB Editor can be found at {@link https://docs.moodle.org/dev/XMLDB_editor}.

    if ($oldversion < 2023091900) {

        // Define field outcomeid to be added to gradingform_frubric_criteria.
        $table = new xmldb_table('gradingform_frubric_criteria');
        $field = new xmldb_field('outcomeid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0, 'descriptionformat');

        // Conditionally launch add field outcomeid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Frubric savepoint reached.
        upgrade_plugin_savepoint(true, 2023091900, 'gradingform', 'frubric');
    }

    return true;

}
