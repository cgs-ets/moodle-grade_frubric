/* eslint-disable max-len */
/* eslint-disable promise/always-return */
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
 * Controls the visibility of the criterion levels in the evaluated view.
 * @package   gradingform_frubric
 * @copyright 2021 Veronica Bermegui
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


if (document.querySelector('.graded-form > .frubric-show-hide-levels') != null) {

    document.querySelector('.graded-form > .frubric-show-hide-levels').addEventListener('click', function (e) {

        const icon = e.target;

        if (icon.classList.contains('fa-eye')) {

            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
            icon.setAttribute('title', 'Show levels');

            // Hide all levels
            document.querySelectorAll('.inner-level-table').forEach(level => {
                level.style.display = 'none';
            });
        } else {

            icon.classList.add('fa-eye');
            icon.classList.remove('fa-eye-slash');
            icon.setAttribute('title', 'Hide levels');
            document.querySelectorAll('.inner-level-table').forEach(level => {
                level.style.display = '';
            });
        }
    });
}