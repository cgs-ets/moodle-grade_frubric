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

define(['core/log', 'core/templates',  'core/str', 'core/notification', 'gradingform_frubric/level_control', 'gradingform_frubric/feditor_helper'],
    function (Log, Templates, Str, Notification, LevelControl, FeditorHelper) {
        'use strict';

        function init(id) {
            
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
            const currentRow = document.getElementById(self.id);

            if (self.mode == 'edit') { // Add the listeners to all the criterion rows.
                try {
                    self.setupEvents(currentRow);
                    // Attach level listeners.
                    currentRow.getAttribute('data-criterion-group');
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
            const actions = currentRow.querySelector('.act'); // Get actions cell data-row-type="criterion-add-level"
            const addLevelRow = FeditorHelper.getNextElement(currentRow, '.add-level-r');
       
            if (!actions) {
                return;
            }

            const actionChildren = actions.children;

            // Criterion actions.
            actionChildren[0].addEventListener('click', self.removeCriterion.bind(currentRow));
         //   actionChildren[1].addEventListener('click', self.addLevel.bind(self, currentRow));
           addLevelRow.children[0].addEventListener('click', self.addLevel.bind(self, currentRow));

            const description = currentRow.querySelector('.crit-desc'); // Get description
            description.addEventListener('click', self.editCriterionDescription.bind(this));
            description.addEventListener('focusout', self.focusoutHandlerDisabled);

            // Get the previous criterion to attach events to the total criterion row at the top of this one.
            const resultRow = FeditorHelper.getNextElement(currentRow, '.result-r');

            if (resultRow != undefined) {
                const [criterionTitletd, criterionTotaltd] = resultRow.children;
                criterionTotaltd.querySelector('.total-input').addEventListener('blur', self.validateTotal);
            }


        };

        CriterionControl.prototype.addLevel = function (row) {
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

                    } else {

                        const resultrow = FeditorHelper.getNextElement(row, '.result-r'); // Get the result row for this criterion

                        prevlevel = FeditorHelper.getPreviousElement(resultrow, classname);
                        
                       
                        if (prevlevel == undefined) { // This criterion doesnt have levels yet.
                            prevlevel = row;
                        }

                          //Check if the row has a red border around when it failed validation
                        if (prevlevel.classList.contains('is-invalid')) {
                            prevlevel.classList.remove('is-invalid');
                            prevlevel.classList.remove('form-control');
                            

                        }
                    }

                    prevlevel.insertAdjacentHTML('beforebegin', html); 

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
                    FeditorHelper.setHiddenCriteriaJSON(criterioncollection);

                    LevelControl.init(randomid, self.id);
                })
                .fail(function (ex) {
                    Log.debug("error...");
                    Log.debug(ex);
                });


        };


        CriterionControl.prototype.removeCriterion = function (row) {

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

                
                    frctodel[0].status = "DELETE";
                    const levels = frctodel[0].levels;
               
                    // Change the status for the levels in this criterion

                    for (let j = 0; j < levels.length; j++) {
                        levels[j].status = "DELETE";
                    }
                 
                    FeditorHelper.setCriteriaJSON(frc);
                    FeditorHelper.setHiddenCriteriaJSON(frc);
                  
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

   
        CriterionControl.prototype.editCriterionDescription = function (e) {
            e.stopPropagation();
            
            let textarea = e.target;
           
            textarea.removeAttribute('disabled');
            textarea.focus();

            textarea.addEventListener('change', this.changeCriterionHandlerDisabled.bind(this));
        };


        CriterionControl.prototype.focusoutHandlerDisabled = function (e) {
            e.target.setAttribute('disabled', true);
        };

        CriterionControl.prototype.changeCriterionHandlerDisabled = function (e) {

            const criterioncollection = (document.getElementById('id_criteria').value) ? JSON.parse(document.getElementById('id_criteria').value) : [];

            // Change the description in the JSON.
            const filterCriterion = criterioncollection.filter(function (criterion, index) {
                const id = e.target.parentNode.parentNode.getAttribute('data-criterion-group');
                criterion.rowindex = index;
                if (id == criterion.id) {
                    return criterion;
                }
            }, e);

            if (filterCriterion[0].description != e.target.value && (FeditorHelper.getMode() == 'edit' &&
                    !filterCriterion[0].cid.includes('frubric-criteria-NEWID'))) { // A new criterion is added to an existing definition
                filterCriterion[0].status = 'UPDATE';
            }

            filterCriterion[0].description = e.target.value;
            // Refresh the Criteria JSON input
            //document.getElementById('id_criteria').value = JSON.stringify(criterioncollection);
            FeditorHelper.setCriteriaJSON(criterioncollection);
            FeditorHelper.setHiddenCriteriaJSON(criterioncollection);
            
            // Check if the criterion has a red border because it comes from a failed attempt to save and make ready.

            if (e.target.classList.contains('is-invalid')) {
                e.target.classList.remove('is-invalid');
                e.target.classList.remove('form-control');
                e.target.removeAttribute('title');
            }
        };

        CriterionControl.prototype.getCriteriaJSON = function () {
            return (document.getElementById('id_criteria').value) ? JSON.parse(document.getElementById('id_criteria').value) : [];
        }; // TODO:DElete and use the one in the helper.
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

                criterion[0].sumscore = inputvalue;
                FeditorHelper.setCriteriaJSON(criteria);
                FeditorHelper.setHiddenCriteriaJSON(criteria);
            }

        }


        return {
            init: init
        };
    });