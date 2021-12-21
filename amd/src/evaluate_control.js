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

define(['core/log', 'gradingform_frubric/feditor_helper'],
    function (Log, FeditorHelper) {
        'use strict';

        function init(data) {

            Log.debug("gradingform_frubric: Evaluate Control...");
            data = JSON.parse(data);
            const control = new EvaluateControl(data.criteria);
            control.main(data.criteria);
        }

        function EvaluateControl(criteria) {
            const self = this;
            self.criteria = criteria;
            self.definitionID = document.getElementById('advancedgrading-criteria').getAttribute('data-definition-id');
        }

        EvaluateControl.prototype.main = function () {
            const self = this;
            self.setupEvents(self.criteria);
        }

        EvaluateControl.prototype.setupEvents = function (criteria) {
            const self = this;

            criteria.forEach(function (element) {
                const self = this;
                const criterion = document.getElementById(`advancedgrading-frubric-criteria-${element.criteriaid}`);
                const level = criterion.children[0].children;
                const score = document.getElementById(`advancedgrading-frubric-criteria-${element.criteriaid}-level-grade`);
                score.addEventListener('focus', self.focusEvaluationHandler.bind(this, self));

                Array.from(level).forEach(function (leveldetails) {
                    const descriptorsCollection = leveldetails.children[1];
                    const descriptors = descriptorsCollection.children;
                    Array.from(descriptors).forEach(function (descriptor) {

                        if (descriptor instanceof HTMLInputElement) {
                            switch (descriptor.type) {
                                case 'checkbox':
                                    descriptor.addEventListener('click', self.clickDescriptorHandler.bind(self));
                                    break;
                            }
                        }
                    });
                }, self);

            }, self);


            const totalgrade = document.getElementById(`advancedgrading-${self.definitionID}-frubric-total-grade`);
            totalgrade.addEventListener('focus', self.focusEvaluationTotalHandler.bind(this, self));

        }

        EvaluateControl.prototype.clickDescriptorHandler = function (e) {
            Log.debug("CHECKED...");

            const id = (e.target.id).split('-');
            const criteriaID = id[3];
            const levelID = id[5];
            const descriptorID = id[id.length - 1];
            let levelsInput = JSON.parse(FeditorHelper.getLevelsJSON(criteriaID));
            levelsInput[levelID].definition.replace(/\\/g, ''); // Remove the //  from the string. 
            let definition = JSON.parse(levelsInput[levelID].definition);
            const levelDescriptors = levelsInput[levelID].descriptors;
            // check if the descriptor exists, (maybe the rubric was updated and a new descriptor is available and its checked.
            // it has to be added here)
            let descriptorids = [];
            levelDescriptors.forEach(element => {
                descriptorids.push(element.descriptorid);
            }, descriptorids);

         
            if (descriptorids.indexOf(parseInt(descriptorID)) == -1) {
                const descText = document.getElementById(`advancedgrading-frubric-criteria-${criteriaID}-level-${levelID}-descriptor-${descriptorID}`).nextSibling.textContent;
                const newdesc = {
                    checked: false,
                    descText: descText,
                    delete: 0,
                    descriptorid: descriptorID
                }

                levelDescriptors.push(newdesc);

            }
            
            levelDescriptors.filter(descriptor => {
                if (descriptor.descriptorid == descriptorID) {
                    descriptor.checked = !descriptor.checked;
                }
            }, descriptorID);


            definition.descriptors = levelDescriptors;
            definition = JSON.stringify(definition);

            levelsInput[levelID].definition = definition;

            // Replace the json value with the updated one
            document.getElementById(`advancedgrading-frubric-${criteriaID}-leveljson`).value = JSON.stringify(levelsInput);
        }


        EvaluateControl.prototype.focusEvaluationHandler = function (s, e) {
            Log.debug("focusEvaluationHandler");

            document.getElementById(e.target.id).addEventListener('change', s.onChangeEvaluationHandler.bind(this, s));
        }

        EvaluateControl.prototype.onChangeEvaluationHandler = function (s, e) {
            Log.debug("onChangeEvaluationHandler");

            e.target.classList.remove('total-input-error');

            let enteredscore = parseFloat(document.getElementById(e.target.id).value);

            let maxscore = document.getElementById(e.target.id + '-out-of-value').innerText.split('/');
            maxscore = parseFloat(maxscore[maxscore.length - 1]);

            if (enteredscore > maxscore || enteredscore < 0) {
                e.target.classList.add('total-input-error');
            }

        }

        EvaluateControl.prototype.focusEvaluationTotalHandler = function (s, e) {
            document.getElementById(e.target.id).addEventListener('change', s.onChangeTotalEvaluationHandler.bind(this, s));
        }

        EvaluateControl.prototype.onChangeTotalEvaluationHandler = function (s, e) {

            e.target.classList.remove('total-input-error');
            let total = parseFloat(document.getElementById(e.target.id).value);
            let maxtotal = document.getElementById(e.target.id + '-given').innerText.split('/');
            maxtotal = parseFloat(maxtotal[maxtotal.length - 1]);
            if (total > maxtotal) {
                e.target.classList.add('total-input-error');
            }

        }


        return {
            init: init
        };
    });