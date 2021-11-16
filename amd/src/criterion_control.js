/* eslint-disable capitalized-comments */
/* eslint-disable max-len */
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

define(['jquery', 'core/log', 'core/templates', 'core/ajax', 'core/str', 'core/notification', 'gradingform_frubric/level_control', 'gradingform_frubric/feditor_helper'],
    function ($, Log, Templates, Ajax, Str, Notification, LevelControl, FeditorHelper) {
        'use strict';

        function init(id) {
            Log.debug('Criterion control...');
            Log.debug(id);
            const mode = FeditorHelper.getMode();
            let control = new CriterionControl(mode, id);
            control.main();
        }

        /**
         *
         * @param {*} mode
         */
        function CriterionControl(mode, id) {
            const self = this;
            self.LEVEL_ROW = 'gradingform_frubric/editor_level_row';
            self.mode = mode;
            self.id = id;
        }

        /**
         * Run the controller.
         *
         */
        CriterionControl.prototype.main = function () {
            let self = this;
            Log.debug(self);
            const currentRow = document.getElementById(self.id);

            if (self.mode == 'edit') { // Add the listeners to all the criterion rows.
                try {
                    self.setupEvents(currentRow);
                    // Attach level listeners.
                    const cgid = currentRow.getAttribute('data-criterion-group');
                   // const level = document.querySelector(`.level-${cgid}`);

                    LevelControl.init(self.id);

                } catch (error) {
                    Log.debug(error);
                }

            } else {
                self.setupEvents(currentRow);
            }

        };

        CriterionControl.prototype.setupEvents = function (currentRow) {
            let self = this;
            Log.debug('setupEvents');
            Log.debug(currentRow);

            const actions = currentRow.querySelector('.act'); // Get actions cell

            if (!actions) {
                return;
            }

            const actionChildren = actions.children;
         
            // Criterion actions. TODO: Remove commented code WHEN its going to prod.
          //  actionChildren[0].addEventListener('click', self.moveCriterionUp.bind(self, currentRow));
            actionChildren[0].addEventListener('click', self.removeCriterion.bind(currentRow));
            actionChildren[1].addEventListener('click', self.addLevel.bind(self, currentRow));
            //actionChildren[3].addEventListener('click', self.copyCriterion);
            // actionChildren[4].addEventListener('click', self.moveCriterionDown.bind(self, currentRow));

            // if (document.getElementsByClassName("criterion-header").length > 1) { // There is at least one criterion.
            //     // Display row up
            //     actionChildren[0].removeAttribute('hidden');
            // }

            const description = currentRow.querySelector('.crit-desc'); // Get description
            description.addEventListener('click', self.editCriterionDescription.bind(this));
            description.addEventListener('focusout', self.focusoutHandlerDisabled);

            // Get the previous criterion to attach events to the total criterion row at the top of this one.
            const resultRow = FeditorHelper.getNextElement(currentRow, '.result-r');

            if (resultRow != undefined) {
               // const cgid = resultRow.getAttribute('data-criterion-group');

                Log.debug("Result row");
               // resultRow.classList.remove('level-'); // Remove the class without the id.
                //resultRow.classList.add(`level-${cgid}`);
                //resultRow.setAttribute('data-criterion-group', cgid);
                Log.debug(resultRow);

                const [criterionTitletd, criterionTotaltd] = resultRow.children;
                Log.debug(criterionTitletd);
                Log.debug(criterionTotaltd);
                Log.debug(criterionTotaltd.querySelector('.total-input'));
                criterionTotaltd.querySelector('.total-input').addEventListener('blur', self.validateTotal);
            }


        };


        CriterionControl.prototype.moveCriterionUp = function (row) {
            Log.debug('moveCriterionUp');

            const criteriongroupid = row.getAttribute('data-criterion-group');
            const currentCriterionInPlace = FeditorHelper.getPreviousElement(row, '.criterion-header'); // Get the criterion on top of this one.
            const criteriontomove = Array.from(document.querySelectorAll(`[data-criterion-group='${criteriongroupid}']`));
            const firstCriterion = (currentCriterionInPlace.rowIndex == 0) ? true : false;
            const notLastCriterion = FeditorHelper.getNextElement(document.querySelector(`[data-criterion-group='${criteriongroupid}']`), '.criterion-header');
            const inBetween = !firstCriterion && !notLastCriterion;
            const criteriaCollection = this.getCriteriaJSON();
            // Get the criterion that will be moved and the one that is in place to update the rowIndex
            Log.debug("criterioncollection");
            Log.debug(criteriaCollection);

            const filterCriterionToMove = criteriaCollection.filter(function (criterion) {

                if (criteriongroupid == criterion.id) {
                    return criterion;
                }
            }, criteriongroupid);

            const criteriongroupidfromCurrentInPlace = currentCriterionInPlace.getAttribute('data-criterion-group');
            const filterCriterionInPlace = criteriaCollection.filter(function (criterion) {

                if (criteriongroupidfromCurrentInPlace == criterion.id) {
                    return criterion;
                }
            }, criteriongroupidfromCurrentInPlace);


            // Update the row number
            const riaux = filterCriterionToMove[0].rowindex;
            filterCriterionToMove[0].rowindex = filterCriterionInPlace[0].rowindex;
            filterCriterionInPlace[0].rowindex = riaux;

            if (firstCriterion) {
                criteriontomove[0].children[0].children[0].setAttribute('hidden', true);
                currentCriterionInPlace.children[0].children[0].removeAttribute('hidden');
            }
            if (inBetween) {
                criteriontomove[0].children[0].children[0].removeAttribute('hidden');
                criteriontomove[0].children[0].children[4].removeAttribute('hidden');
            }
            if (!notLastCriterion) {
                currentCriterionInPlace.children[0].children[4].setAttribute('hidden', true); // Its the last criterion, hide the down arrow.
            }

            try {
                for (const level of criteriontomove) {
                    currentCriterionInPlace.parentNode.insertBefore(level, currentCriterionInPlace);
                }
            } catch (error) {
                Log.debug(error);
            }

        };

        CriterionControl.prototype.moveCriterionDown = function (row) {
            Log.debug('moveCriterionDown');

            const criteriongroupid = row.getAttribute('data-criterion-group');
            const criterionToMove = document.querySelector(`[data-criterion-group='${criteriongroupid}']`);
            const firstCriterion = criterionToMove.rowIndex == 0 ? true : false;
            const criterionToMoveArray = Array.from(document.querySelectorAll(`[data-criterion-group='${criteriongroupid}']`));
            let criterionBelow = FeditorHelper.getNextElement(criterionToMove, '.criterion-header');
            const notlastCriterion = FeditorHelper.getNextElement(criterionToMove, '.criterion-header');
            const inBetween = !firstCriterion && (notlastCriterion != undefined);

            Log.debug('firstCriterion', firstCriterion);
            Log.debug('notlastCriterion', notlastCriterion);
            Log.debug('inBetween', inBetween);

            criterionBelow = criterionBelow.getAttribute('data-criterion-group');
            criterionBelow = Array.from(document.querySelectorAll(`[data-criterion-group='${criterionBelow}']`));

            if (firstCriterion) {
                criterionBelow[0].children[0].children[0].setAttribute('hidden', true);
                criterionBelow[0].children[0].children[4].removeAttribute('hidden');
                // Check if the criterion below is the last one.
                if (!FeditorHelper.getNextElement(criterionBelow, '.criterion-header')) {
                    criterionToMoveArray[0].children[0].children[4].setAttribute('hidden', true);
                }
            }

            if (firstCriterion && notlastCriterion != undefined) { // Moving the first criterion, its next position is in between
                criterionToMoveArray[0].children[0].children[0].removeAttribute('hidden');
            }

            if (inBetween) {
                criterionBelow[0].children[0].children[0].removeAttribute('hidden');
                criterionBelow[0].children[0].children[4].removeAttribute('hidden');

                // Check if the criterion below is the last one.
                if (!FeditorHelper.getNextElement(criterionBelow, '.criterion-header')) {
                    criterionToMoveArray[0].children[0].children[4].setAttribute('hidden', true);
                }
            }

            if (notlastCriterion == undefined) { // The criterion below is the last one
                criterionToMoveArray[0].children[0].children[0].removeAttribute('hidden');
                criterionToMoveArray[0].children[0].children[4].setAttribute('hidden', true);
            }

            if (inBetween && notlastCriterion == undefined) {
                criterionBelow[0].children[0].children[4].removeAttribute('hidden');
            }

            try {
                for (const level of criterionBelow) {
                    criterionToMove.parentNode.insertBefore(level, criterionToMove); // JS doesnt have an insertAfter. So we put the element below on top of the one the one we want to move
                }
            } catch (error) {
                Log.debug(error);
            }

        };

        CriterionControl.prototype.addLevel = function (row) {
            Log.debug('addLevel');
            let self = this;
            const randomid = FeditorHelper.getRandomID();
            const context = {
                'score': 'Click to edit Mark',
                'definition': 'Click to edit Level description',
                'criteriongroupid': row.getAttribute('data-criterion-group'),
                'id': randomid,
            };

            Templates.render(self.LEVEL_ROW, context)
                .done(function (html, js) {

                    var prevlevel;
                    const classname = `.level-${row.getAttribute('data-criterion-group')}`;
                    let nextCriterion = FeditorHelper.getNextElement(row, '.criterion-header');

                    if (nextCriterion != undefined) { //  If there is another criterion the level is on top of this row

                        const prevresultlevel = FeditorHelper.getPreviousElement(nextCriterion, '.result-r'); // The new level is on top of the result row for this criterion.
                        prevlevel = FeditorHelper.getPreviousElement(prevresultlevel, classname);

                        if (prevlevel == undefined) { // If undefined, the criterion represented by row has no levels. Add the level under the current criterion
                            prevlevel = row;
                        }

                    }  else {

                        const resultrow =  FeditorHelper.getNextElement(row, '.result-r'); // Get the result row for this criterion
                        
                        prevlevel = FeditorHelper.getPreviousElement(resultrow, classname); 
                        
                        if (prevlevel == undefined) {  // This criterion doesnt have levels yet.
                            prevlevel = row;
                        } 
                    }

                  

                    prevlevel.insertAdjacentHTML('afterend', html);

                    const levelObject = {
                        score: 0,
                        status: 'NEW',
                        id: randomid,
                        descriptors: []
                    }
                    const criterioncollection = FeditorHelper.getCriteriaJSON();
                    const filterCriterion = FeditorHelper.getCriterionFromCriteriaCollection(row, criterioncollection);

                    filterCriterion[0].levels.push(levelObject);
                    // Refresh the JSON input
                    FeditorHelper.setCriteriaJSON(criterioncollection);

                    LevelControl.init(randomid, self.id);
                })
                .fail(function (ex) {
                    Log.debug("error...");
                    Log.debug(ex);
                });


        };


        CriterionControl.prototype.removeCriterion = function (row) {
            Log.debug("remove...");

            Str.get_strings([{
                    key: 'confirm',
                    component: 'gradingform_frubric'
                },
                {
                    key: 'confirmdeletecriterion',
                    component: 'gradingform_frubric'
                },
                {
                    key: 'yes'
                },
                {
                    key: 'no'
                },

            ]).done(function (strs) {
                Notification.confirm(strs[0], strs[1], strs[2], strs[3], function () {
                    let criTable = document.getElementById('criteriaTable');
                    let criterionToDelete = row.target.parentNode.parentNode.parentNode.parentNode.rowIndex;

                    const criteariaID = row.target.parentNode.parentNode.parentNode.parentNode.getAttribute('data-criterion-group');

                    // Change the criterion status tu DELETE, to be able to delete from the DB.
                    let frc = JSON.parse(document.getElementById('id_criteria').value);

                    // Change the description in the JSON
                    const frctodel = frc.filter(function (criterion, index) {
                        const criteariaID = this.getAttribute('data-criterion-group');
                        criterion.rowindex = index;
                        if (criteariaID == criterion.id) {
                            return criterion;
                        }
                    }, row.target.parentNode.parentNode.parentNode.parentNode);

                    Log.debug("TO DELETE");
                    frctodel[0].status = "DELETE";
                    const levels = frctodel[0].levels;
                    Log.debug(levels);
                    // Change the status for the levels in this criterion

                    for (let j = 0; j < levels.length; j++) {
                        levels[j].status = "DELETE";
                    }

                    document.getElementById('id_criteria').value = JSON.stringify(frc);

                    // Delete all the levels in that criterion.
                    for (let i = (criterionToDelete + 1); i < criTable.rows.length; i++) {
                        if (criTable.rows[i].getAttribute('data-criterion-group') == criteariaID) {
                            criTable.rows[i].classList.add('to-remove'); // Tag the rows to delete.
                        } else {
                            break;
                        }
                    }

                    removeElementsByClass('to-remove');

                    function removeElementsByClass(className) {
                        var elements = document.getElementsByClassName(className);
                        while (elements.length > 0) {
                            elements[0].parentNode.removeChild(elements[0]);
                        }
                    }
                    criTable.deleteRow(criterionToDelete);

                }, function () {
                    return;
                });
            });
        };

        CriterionControl.prototype.copyCriterion = function (e) {

            Log.debug('copyCriterion');
        };

        CriterionControl.prototype.editCriterionDescription = function (e) {
            e.stopPropagation();
            Log.debug('edit criterion description');
            let textarea = e.target;
            if (textarea.innerHTML == 'Click to edit criterion') {
                textarea.innerHTML = '';
            }
            textarea.removeAttribute('disabled');
            textarea.focus();
            textarea.addEventListener('change', this.changeCriterionHandlerDisabled.bind(this));
        };


        CriterionControl.prototype.focusoutHandlerDisabled = function (e) {
            Log.debug("focusoutHandlerDisabled");
            Log.debug(this);
            if (e.target.innerHTML == '') {
                e.target.innerHTML = 'Click to edit criterion';
            }
            e.target.setAttribute('disabled', true);
        };

        CriterionControl.prototype.changeCriterionHandlerDisabled = function (e) {
            Log.debug('changeCriterionHandlerDisabled');
            Log.debug(e);

            const criterioncollection = (document.getElementById('id_criteria').value) ? JSON.parse(document.getElementById('id_criteria').value) : [];

            // Change the description in the JSON.
            const filterCriterion = criterioncollection.filter(function (criterion, index) {
                const id = e.target.parentNode.parentNode.getAttribute('data-criterion-group');
                criterion.rowindex = index;
                if (id == criterion.id) {
                    return criterion;
                }
            }, e);

            Log.debug(filterCriterion);

            if (filterCriterion[0].description != e.target.value && (FeditorHelper.getMode() == 'edit' 
                && !filterCriterion[0].cid.includes('frubric-criteria-NEWID'))) { // A new criterion is added to an existing definition
                    filterCriterion[0].status = 'UPDATE';
                }

            filterCriterion[0].description = e.target.value;
            // Refresh the Criteria JSON input
            document.getElementById('id_criteria').value = JSON.stringify(criterioncollection);

        };

        CriterionControl.prototype.getCriteriaJSON = function () {
            return (document.getElementById('id_criteria').value) ? JSON.parse(document.getElementById('id_criteria').value) : [];
        }; // TODO: borrar y usar el que cree en el helper

        CriterionControl.prototype.validateTotal = function (e) {
            
            // In case the user put the wrong value before, clean the style
            e.target.classList.remove('total-input-error');
            e.target.removeAttribute('data-toggle');
            e.target.removeAttribute('data-placement');
            e.target.removeAttribute('data-title');

            const inputvalue = e.target.valueAsNumber;
            const min = e.target.getAttribute('min');
            const max = e.target.getAttribute('max');

            if ((inputvalue != NaN) && (inputvalue < min || inputvalue > max)) {
                e.target.classList.add('total-input-error');
                e.target.setAttribute('data-toggle', 'tooltip');
                e.target.setAttribute('data-placement', 'right');
                e.target.setAttribute('data-title', 'Value out of range');
            } else {
                // Change the description in the JSON.
                const criteria = FeditorHelper.getCriteriaJSON();
                const criterion = FeditorHelper.getCriterionFromCriteriaCollection(e.target.parentNode.parentNode.parentNode, criteria);

                Log.debug(criterion);
                criterion[0].sumscore = inputvalue;
                FeditorHelper.setCriteriaJSON(criteria);
            }

        }


        return {
            init: init
        };
    });