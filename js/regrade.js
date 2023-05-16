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


// Controls the regrading messages.
document.querySelector('.regrade-container').removeAttribute('hidden');

document.getElementById("regradeOptions").addEventListener('change', function (e) {
    document.querySelector('.gradingform_rubric_editform').querySelector('input[name="regrade"]').value = e.target.value;
    document.getElementById("regradeOptions").setAttribute('data-initial-value', e.target.value);
    console.log(e.target.value);
    document.querySelector('input[name="regradeoptionselected"]').value = e.target.value;
});

if (document.getElementById('id_savefrubric').value == 'Continue') {
    document.querySelector('.gradingform_rubric_editform').querySelector('input[name="regrade"]').value = 1;
    if (document.querySelector('input[name="regradeoptionselected"]').value == '') {
        document.querySelector('input[name="regradeoptionselected"]').value = 0;
    }
}