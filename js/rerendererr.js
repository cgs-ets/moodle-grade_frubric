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
 * @package   gradingform_frubric
 * @copyright 2021 Veronica Bermegui
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * When trying to save and make ready, check if there are errors. If they are
 * display a red border to the elements that are missing and a ! icon
 */
if (document.getElementById('fitem_id_criteria').classList.contains('has-danger')) {

    Array.from((document.getElementById('criteriaTable').querySelector('tbody').children)).forEach(function (tr) {

        if (!tr.classList.contains('result-r')) {

            Array.from(tr.children).forEach(function (th) {
                if (th.classList.contains('fr-header') && th.classList.contains('crit-desc')) {
                    if (th.children[0].value == '') {
                        th.children[0].classList.add('form-control', 'is-invalid');
                        th.children[0].setAttribute('title', 'Description cannot be empty');

                    }
                } else if (!(th.classList.contains('fr-header') && th.classList.contains('act'))) {
                    Array.from(th.children).forEach(function (ch, index) {
                        if (!ch.classList.contains('action-el')) {
                            Array.from(ch.children).forEach(function (t) {

                                Array.from(t.querySelectorAll('tr')).forEach(function (itd, index) {

                                    Array.from(itd.children).forEach(function (tdch, index) {
                                        Y.log(itd.children);
                                        if (tdch.classList.contains('level-mark')) {

                                            Array.from(tdch.children).forEach(function (mark) {
                                                if (mark.value == '') {
                                                    mark.classList.add('form-control', 'is-invalid');
                                                    mark.setAttribute('title', 'Score cannot be empty');

                                                }
                                            });


                                        } else {
                                            Array.from(tdch.children).forEach(function (descriptor) {
                                                if (descriptor.classList.contains('standard-desc-container')) {

                                                    Array.from(descriptor.children).forEach(function (desc) {

                                                        if (desc.classList.contains('checkbox-container')) {

                                                            if (desc.querySelector('.standard-desc').value == '') {

                                                                desc.querySelector('.standard-desc').classList.add('form-control', 'is-invalid');
                                                                desc.querySelector('.standard-desc').setAttribute('title', 'Level descriptor cannot be empty');
                                                            }
                                                        }
                                                    })
                                                }
                                            });
                                        }

                                    })
                                })
                            })
                        }
                    });
                }
            });
        }
    });

    document.querySelector('.frubric-error-when-saving').removeAttribute('hidden'); // Display error.

    //Rebuild the criterionjson in case the error came because hte user didnt add a level to the descriptor.
    //For some reason, the form doesn't refresh json



}