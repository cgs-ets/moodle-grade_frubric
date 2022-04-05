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

define(['core/log'],
    function (Log) {
        'use strict';

        function init(submiteddata, definitionID) {

            Log.debug("gradingform_frubric: Submission Control...");
            submiteddata = JSON.parse(submiteddata);
            const control = new SubmissionControl(submiteddata, definitionID);
            control.main(submiteddata, definitionID);
        }

        function SubmissionControl(submiteddata, definitionID) {
            const self = this;
            self.definitionID = definitionID;
            self.submiteddata = submiteddata;
            self.definitionID = document.getElementById('advancedgrading-criteria').getAttribute('data-definition-id');


        }

        SubmissionControl.prototype.main = function () {
            const self = this;
            self.checkCriteria(self.submiteddata);
            self.checkScore(self.criteria);
        }



        SubmissionControl.prototype.checkCriteria = function () {
            Log.debug('checkCriteria...')
            var self = this;
            Object.entries(self.submiteddata.criteria).forEach(([key, value]) => {

                // Check score given to the criterion
                const scoregiven = document.getElementById(`advancedgrading-frubric-criteria-${key}-level-grade`);
                if (scoregiven.value != '') {
                    scoregiven.classList.remove('total-input-error');
                    if (!document.querySelector('span.frubric-no-descriptor-error').hasAttribute('hidden')) {
                        document.querySelector('span.frubric-no-descriptor-error').hidden = true;
                    }
                } else {
                    scoregiven.classList.add('total-input-error');
                    document.querySelector('span.frubric-no-descriptor-error').removeAttribute('hidden');
                }

                let maxallowed = document.getElementById(`advancedgrading-frubric-criteria-${key}-level-grade-out-of-value`).innerText.split('/');
                maxallowed = maxallowed[maxallowed.length - 1];

                if (parseFloat(scoregiven.value) > parseFloat(maxallowed)) {
                    scoregiven.classList.add('total-input-error');
                }

                // Check that at least one  descriptor is selected
                var totaldescriptor = 0;
                var totalnotchecked = 0;
                
                Object.values(JSON.parse(value.leveljson)).forEach(val => {
                    totaldescriptor += val.descriptors.length;
                    
                    val.descriptors.forEach(d => {
                        if (d.checked == false) {
                            totalnotchecked++;
                        }
                    });

                  
                });

                if (totaldescriptor == totalnotchecked) {
                    document.getElementById(`advancedgrading-frubric-criteria-${key}`).classList.add('error_no_descriptors_selected'); 
                    document.querySelector('span.frubric-no-score-error').removeAttribute('hidden');
                } else {
                    if (!document.querySelector('span.frubric-no-score-error').hasAttribute('hidden')) {
                        document.querySelector('span.frubric-no-score-error').hidden = true;
                    }
                }
            });

        }

        SubmissionControl.prototype.checkScore = function () {
            var self = this;
            let scoregiven = document.getElementById(`advancedgrading-${self.definitionID}-frubric-total-grade`);
            scoregiven.classList.remove('total-input-error');
            let enteredscore = parseFloat(scoregiven.value);
            let maxscore = document.getElementById(`advancedgrading-${self.definitionID}-frubric-total-grade-given`).innerText.split('/');
            maxscore = parseFloat(maxscore[maxscore.length - 1]);

            if (enteredscore > maxscore || enteredscore < 0) {
                scoregiven.classList.add('total-input-error');
            }

        }

      

        



        return {
            init: init
        };
    });