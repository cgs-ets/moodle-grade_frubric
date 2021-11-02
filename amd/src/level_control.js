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

define(['jquery', 'core/log', 'core/str', 'core/notification', 'gradingform_frubric/feditor_helper', 'core/templates'],
    function ($, Log, Str, Notification, FeditorHelper, Templates) {
        'use strict';

        function init(id, parentid) {
            Log.debug('Level control...');
            const mode = FeditorHelper.getMode();
            const level = document.getElementById(id);

            let control = new LevelControl(level, mode, id, parentid);
            control.main();

        }


        /**
         *
         * @param {*} level
         * @param {*} mode
         */
        function LevelControl(level, mode, id, parentid) {
            const self = this;
            self.level = level;
            self.mode = mode;
            self.id = id;
            self.parentid = parentid;
            self.LEVEL_DESCRIPTOR_INPUT = 'gradingform_frubric/level_descriptor_input';
        }

        /**
         * Run the controller.
         */
        LevelControl.prototype.main = function () {
            let self = this;

            if (self.mode == 'edit') {
                self.editModeSetupEvents(self.level.nextElementSibling);
            } else {
                if (self.level != null) {
                    self.validatePreviousMarkValue() // CASE: last level  has 0 mark. A new level is added, previous level can't be zero. As 0 is only allowed for the last level,
                    self.setupEvents(self.level);
                }
            }

        };

        LevelControl.prototype.editModeSetupEvents = function (level) {

            Log.debug("editModeSetupEvents");
            const self = this;

            // TODO: ADD LEVEL y ADD CRITERION
            if (level.getAttribute('data-row-type') == 'result') { // get the current level. Its the new level added.
                level = self.level.previousElementSibling;
            }

            self.setupEvents(level);

            const [del, markandesc] = level.children;
            const markdesctable = markandesc.querySelector('.level-mark-desc-table');

            if (markdesctable) {

                let rows = markdesctable.rows;
                $(rows).each(function (index, row) {
    
                    $(row).children().each(function (j, td) {
    
                        $(td).children().each(function (i) {
    
                            const container = this;
    
                            if (container.classList.contains('standard-desc-container')) {
                                self.editModeSetupEventsHelper(container);
                            }
    
                            if (container.classList.contains('add-descriptor')) {
                                container.querySelector('.add-desc-btn').addEventListener('click', self.addDescriptor.bind(self));
                            }
    
                        });
    
                    })
    
                });
            }


        }

        LevelControl.prototype.editModeSetupEventsHelper = function (desciptorContainer) {
            var self = this;
            Log.debug("editModeSetupEventsHelper");
            let counter = 0;
            counter = desciptorContainer.children.length;

            if (counter > 0) {  // All the descriptors for this level where deleted previously.

                self.descriptorIndex = counter;
                self.parentid = desciptorContainer.children[0].getAttribute('data-parent-id');
                self.lid = desciptorContainer.children[0].getAttribute('id');
    
                $(desciptorContainer).each(function (i, td) {
                    $(td).children().each(function (i) {
    
                        const container = this;
    
                        if (container.classList.contains('fmark')) {
                            return;
                        }
    
                        container.setAttribute('descriptor-index', i);
    
                        const action = container.querySelector('.action-el');
                        const checkbox = container.querySelector('.standard-check');
                        const descriptor = container.querySelector('.standard-desc');
    
                        descriptor.addEventListener('click', self.clickDescriptorHandler.bind(this, self));
                        action.addEventListener('click', self.deleteDescriptor.bind(self, descriptor, container));
                        checkbox.addEventListener('click', self.selectdescriptor.bind(this, self));
    
                    });
                });
            }
        }

        LevelControl.prototype.setupEvents = function (level) {
            let self = this;
            Log.debug("Level control: setupEvents");
            // Log.debug(level);
            const [del, markandesc] = level.children;
            const markdesctable = markandesc.querySelector('.level-mark-desc-table');
            Log.debug(markdesctable);
            
            if (markdesctable) {
                const [marktd, descriptortd] = markdesctable.rows[0].children;
                marktd.querySelector('.level-mark > textarea').addEventListener('click', self.editmark.bind(self));
    
                if (self.mode != 'edit') {
                    descriptortd.querySelector('.add-desc-btn').addEventListener('click', self.addDescriptor.bind(self));
                }
                del.addEventListener('click', self.deleteLevel.bind(self));
            }

        };

        LevelControl.prototype.editmark = function (e) {
            Log.debug("editmark...");
            const self = this;
            const score = e.target;

            if (score.innerHTML == '[Min - Max]') {
                score.innerHTML = '';
            }

            score.focus();

            if (!score.classList.contains('changeh')) {
                score.addEventListener('change', self.changeMarkHandler.bind(this, self));
                score.classList.add('changeh');
            }


        }

        LevelControl.prototype.changeMarkHandler = function (s, e) {
            Log.debug("changeMarkHandler");

            // If it came from validatePreviousMarkValue we need to remove the class warning
            s.cleanPreviousMarkWarning();
            // Remove error message if the user inserted wrong data before.
            e.target.classList.remove('total-input-error');
            e.target.removeAttribute('data-toggle');
            e.target.removeAttribute('data-placement');
            e.target.removeAttribute('data-title');


            const el = document.getElementById(e.target.getAttribute('aria-describedby'));

            if (el != null) {
                el.parentNode.removeChild(el);
                e.target.removeAttribute('data-original-title');
                e.target.removeAttribute('title');
                e.target.removeAttribute('aria-describedby');

            }

            // Update the score for this criterion.
            const criteria = FeditorHelper.getCriteriaJSON();
            const criterion = FeditorHelper.getCriterionFromCriteriaCollection(document.getElementById(s.id), criteria);
            const levelsdesc = s.getLevelDescriptors(s.id, criteria);
            const score = e.target.value;

            let error = false;
            let message = '';
            // First check if its 0. This value is valid in the last row. Meaning the student didnt reach the min. standard
            if (score == 0) {

                // Only the last row can score 0.
                // Check its the last by checking the next sibling => it has to be the result row.
                // Check prevoious sibling => It has to be a leevl row
                const previousRow = (document.getElementById(s.id).previousElementSibling).getAttribute('data-row-type');
                const nextRow = (document.getElementById(s.id).nextElementSibling).getAttribute('data-row-type');

                if (!(previousRow == 'level' && nextRow == 'result')) {
                    error = true;
                    message = 'Zero mark only allowed in last level'
                }

            } else {

                if (score.indexOf('-') == -1 && score.indexOf('/') == -1) {
                    error = true;
                    message = 'Invalid value. Accepts min-max or min/max';
                } else {
                    // Evaluate min/max 
                    var [min, max] = s.getMinMaxMark(score);
                    if (min.length == 0 || max.length == 0) {
                        error = true;
                    } else if ((min.length > 0 && max.length > 0) && (parseFloat(min) > parseFloat(max))) {
                        error = true;
                        message = 'Min value is greater than max value';
                    }

                }
            }

            if (error) {
                s.setErrorMessage(e, message);
                error = false;
                return;
            }


            // Update the total result input. with the highest value.
            const groupid = document.getElementById(s.parentid).getAttribute('data-criterion-group');
            const resultRow = document.querySelector(`[data-criterion-group="${groupid}"][data-row-type="result"]`);
            var total = (resultRow.querySelector(`#out-of-value-${groupid}`).innerHTML).split("/");
            const maxinput = resultRow.querySelector('.total-input');

            levelsdesc[0].score = e.target.value;
            total = total[total.length - 1];

            if (total === "") { //  Its the first value available.
                resultRow.querySelector(`#out-of-value-${groupid}`).innerHTML = `/${max}`;
                total = max;

            } else { // check if the max we have has to be replaced by a new max

                if (parseFloat(total) < max) {
                    total = max;
                    resultRow.querySelector(`#out-of-value-${groupid}`).innerHTML = `/${max}`;
                }
            }

            // Update the max attribute.
            maxinput.setAttribute("max", total);
            criterion[0].totaloutof = total;

            FeditorHelper.setCriteriaJSON(criteria);


        }

        LevelControl.prototype.addDescriptor = function (e) {

            const self = this;
            Log.debug ("ADD DESCRIPTOR ANTES DEL PREVENT...");
            e.preventDefault();
            // Get the standard-desc-container  that contains the descritors.
            const descriptorContainer = FeditorHelper.getPreviousElement(e.target.parentNode, '.standard-desc-container');
            let  fromscratch = false; 
            // if (descriptorContainer.children.length == 0 && self.mode == 'edit') {  // Edit mode, all descriptors where deleted before
            //     fromscratch = true;
            // }
            const context = {
                id: (self.mode != 'edit') ? `${self.id}-${FeditorHelper.getRandomID()}` : `${self.id}-${descriptorContainer.children[0].getAttribute('id')}`,
                parentid: self.parentid,
                edit: self.mode == 'edit',
                index: descriptorContainer.children.length,
            };

            if (self.mode != 'edit') {
                delete context.index;
            }


            Templates.render(self.LEVEL_DESCRIPTOR_INPUT, context)
                .done(function (html, js) {
                    descriptorContainer.insertAdjacentHTML('beforeend', html);
                    const container = descriptorContainer.lastChild;
                    const action = container.querySelector('.action-el');
                    const checkbox = container.querySelector('.standard-check');
                    const descriptor = container.querySelector('.standard-desc');
                    descriptor.addEventListener('click', self.clickDescriptorHandler.bind(this, self));
                    action.addEventListener('click', self.deleteDescriptor.bind(self, descriptorContainer, container));
                    checkbox.addEventListener('click', self.selectdescriptor.bind(this, self));

                })
                .fail(function (ex) {
                    Log.debug("error...");
                });

        }


        LevelControl.prototype.clickDescriptorHandler = function (s, e) {
            Log.debug('clickDescriptorHandler...');
            let descriptor = e.target;

            if (descriptor.innerHTML == 'Click to edit level descriptor') {
                descriptor.innerHTML = '';
            }

            descriptor.focus();

            // Attach change event
            descriptor.addEventListener('change', s.changeDescriptorHandler.bind(this, s));

        }

        LevelControl.prototype.changeDescriptorHandler = function (e, s) {
            Log.debug("changeDescriptorHandler");
            //TODO: When all descriptors are deleted but the level is saved in the DB it throws an error.
            const criteria = FeditorHelper.getCriteriaJSON();
            const containerid = s.target.getAttribute('data-container-id');
            let levelid;
            let flag = false;

            let descriptorIndex = s.target.parentNode.getAttribute('descriptor-index');

            if (descriptorIndex != null) {
                levelid = s.target.parentNode.getAttribute('id');
            } else {
                descriptorIndex = Array.from(document.getElementById(containerid).parentNode.children).indexOf(s.target.parentNode)
            }

            if (levelid != undefined && levelid.includes("-")) { // We are adding a descriptor to a level that is already in the DB.
                levelid.slice(levelid.indexOf('-') + 1, levelid.length);
                flag = true;

            }

            const levelsdesc = e.getLevelDescriptors(e.id, criteria, levelid);
            const desc = levelsdesc[0].descriptors[descriptorIndex];


            if (desc == undefined) { // New entry

                levelsdesc[0].descriptors.push({
                    checked: false,
                    descText: s.target.value,
                    delete: 0
                });

                if (flag) { // A descriptor has been added to a level that is already in the DB.
                    levelsdesc[0].status = 'UPDATE';
                }

            } else { // Existing entry, update text.
                desc.descText = s.target.value;


                if (levelsdesc[0].status == 'CREATED' || levelsdesc[0].status == 'UPDATED') {
                    levelsdesc[0].status = 'UPDATE';
                }
            }


            FeditorHelper.setCriteriaJSON(criteria);

        }

        LevelControl.prototype.selectdescriptor = function (e, s) {

            Log.debug("selectdescriptor");

            const containerid = s.target.getAttribute('data-container-id');
            const criteria = FeditorHelper.getCriteriaJSON();
            let levelsdesc;
            let descriptorIndex = s.target.parentNode.getAttribute('descriptor-index');

            if (descriptorIndex != null) {
                const levelid = s.target.parentNode.getAttribute('id');
                levelsdesc = e.getLevelDescriptors(e.id, criteria, levelid);
            } else {
                descriptorIndex = Array.from(document.getElementById(containerid).parentNode.children).indexOf(s.target.parentNode);
                levelsdesc = e.getLevelDescriptors(e.id, criteria);
            }

            const d = levelsdesc[0].descriptors[descriptorIndex];
            d.checked = s.target.checked;

            // check if its already in the DB, if so, change the status
            if (levelsdesc[0].status == 'CREATED' || levelsdesc[0].status == 'UPDATED') {
                levelsdesc[0].status = 'UPDATE';
            }
            // Update the input.
            FeditorHelper.setCriteriaJSON(criteria);
        }

        LevelControl.prototype.deleteDescriptor = function (descriptorContainer, checkboxcontainer) {
            Log.debug('deleteDescriptor');
            var self = this;


            if (checkboxcontainer.getAttribute('descriptor-index')) { // This means the descriptor is already saved in the BD. ask if theya re sure to remove

                Str.get_strings([{
                        key: 'confirm',
                        component: 'gradingform_frubric'
                    },
                    {
                        key: 'confirmdeletedescriptor',
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
                        Log.debug("Notification...");

                        const criteria = FeditorHelper.getCriteriaJSON();
                        const levelid = checkboxcontainer.getAttribute('id');
                        const parentid = self.id;
                        const descriptorIndex = checkboxcontainer.getAttribute('descriptor-index');
                        const levelsdesc = self.getLevelDescriptors(parentid, criteria, levelid);
                        const d = levelsdesc[0].descriptors[descriptorIndex];
                        d.delete = 1;

                        // if (descriptorIndex == levelsdesc[0].descriptors.length - 1) {
                        //     levelsdesc[0].status = 'DELETE';
                        // } else {
                            
                        // }
                        levelsdesc[0].status = 'UPDATE';
                        // Update the input.
                        FeditorHelper.setCriteriaJSON(criteria);
                        checkboxcontainer.remove();

                    }, function () {
                        // For the cancel btn.
                        return;
                    });
                });

            } else {
                descriptorContainer.removeChild(checkboxcontainer);
                // TODO: UPDATE JSON
            }
        }

        LevelControl.prototype.deleteLevel = function (e) {
            Log.debug('deleteLevel');
            Log.debug(e);

            Str.get_strings([{
                    key: 'confirm',
                    component: 'gradingform_frubric'
                },
                {
                    key: 'confirmdeletelevel',
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
                    const tr = e.target.parentNode.parentNode.parentNode;
                    const criterion = FeditorHelper.getPreviousElement(tr, '.criterion-header');
                    let criteria = JSON.parse(document.getElementById('id_criteria').value);

                    if (criterion.getAttribute('data-criterion-levels').length > 0) {

                        const dbids = JSON.parse(criterion.getAttribute('data-criterion-levels')); // These are the ids of the levels given by the DB.
                        const index = FeditorHelper.getDistanceFromCriterionHeader(tr, '.criterion-header');
                        const dblevelid = dbids[index];
                        const crid = tr.getAttribute('data-criterion-group')

                        var levels;
                        for (let i = 0; i < criteria.length; i++) {
                            if (criteria[i].id == crid) {
                                criteria[i].status = 'UPDATE';
                                levels = criteria[i].levels;
                                break;
                            }
                        }

                        for (let j = 0; j < levels.length; j++) {
                            if (levels[j].dbid == dblevelid) {
                                levels[j].status = 'DELETE';
                                break;
                            }
                        }

                    }


                    document.getElementById('id_criteria').value = JSON.stringify(criteria);
                    criTable.deleteRow(tr.rowIndex); // span -> td -> tr.

                }, function () {
                    // For the cancel btn.
                    return;
                });
            });
        };

        LevelControl.prototype.editlevelhandler = function (e) {
            Log.debug('editlevelhandler');
            let textarea = e.target;

            if (textarea.innerHTML == 'Click to edit Level description' || textarea.innerHTML == 'Click to edit Mark') {
                textarea.innerHTML = '';
            }

            textarea.removeAttribute('disabled');
            textarea.focus();
            textarea.addEventListener('change', this.changeLevelHandlerDisabled.bind(this, textarea));
            textarea.addEventListener('focusout', this.focusoutHandlerDisabled);
        };

        LevelControl.prototype.changeLevelHandlerDisabled = function (txtarea, e) {
            Log.debug('changeLevelHandlerDisabled');

            let id = e.target.parentNode.parentNode.parentNode.getAttribute('data-criterion-group');
            const levelrow = e.target.parentNode.parentNode.parentNode;

            Log.debug(levelrow);
            // Get the criterion row this level belongs to
            const criterionheader = FeditorHelper.getPreviousElement(levelrow, '.criterion-header');
            let leveldbids = criterionheader.getAttribute('data-criterion-levels').length > 0 ? JSON.parse(criterionheader.getAttribute('data-criterion-levels')) : []; // Get the dbids

            const criteriaJSON = FeditorHelper.getCriteriaJSON();
            const rowIndex = e.target.parentNode.parentNode.parentNode.rowIndex;

            let levelid = id + '_' + rowIndex;

            if (leveldbids.length > 0) {
                levelid = (leveldbids[rowIndex - 1] != undefined) ? `${id}_${leveldbids[rowIndex - 1]}` : levelid; // The criterion already exists but this is a new level, not saved in the DB yet.
            }

            const filterCriterion = criteriaJSON.filter(function (criterion, index) {
                const id = e.target.parentNode.parentNode.parentNode.getAttribute('data-criterion-group');
                criterion.rowindex = index;
                if (id == criterion.id) {
                    return criterion;
                }
            }, e);
            
            // Find the level and update the values
            if (filterCriterion[0].levels != undefined) {
                var lev = null;
                const critlevs = filterCriterion[0].levels;

                for (let i = 0; i < critlevs.length; i++) {

                    if ((critlevs[i]).id != levelid) {
                        continue;
                    } else {
                        lev = critlevs[i];
                        break;

                    }
                }

                if (lev != null) {
                    if (e.target.classList.contains('mark_txtarea')) {
                        lev.score = e.target.value;
                    } else {
                        lev.definition = e.target.value;
                    }
                    if (lev.status == 'CREATED' || lev.status == 'UPDATED') { // It was updated before. 
                        lev.status = 'UPDATE';
                    }

                } else {

                    let criterionLevel = {
                        id: levelid,
                        status: 'NEW',
                        score: '',
                        definition: ''
                    };

                    if (e.target.classList.contains('mark_txtarea')) {
                        criterionLevel.score = e.target.value;
                    } else {
                        criterionLevel.definition = e.target.value;
                    }
                    (filterCriterion[0]).levels.push(criterionLevel);
                }

                if (FeditorHelper.getMode() == 'edit') {

                    if (filterCriterion[0].cid.includes("NEWID") != true) { // A new criterion added
                        filterCriterion[0].status = "UPDATE"; // The criterion was updated because it has new levels.
                    } else {
                        filterCriterion[0].status = "NEW";
                    }
                }

            }

            document.getElementById('id_criteria').value = JSON.stringify(criteriaJSON); // Criterionjson;


            e.target.setAttribute('disabled', true);
        };

        LevelControl.prototype.focusoutHandlerDisabled = function (e) {
            Log.debug('focusoutHandlerDisabled');
            e.target.setAttribute('disabled', true);
        };

        LevelControl.prototype.getLevelDescriptors = function (parentid, criteria, levelid) {
            Log.debug("getLevelDescriptors...");
            Log.debug('parentid', parentid);
            Log.debug( criteria);
            Log.debug('levelid', levelid);
            var self = this;
            const row = document.getElementById(parentid);
            const criterion = FeditorHelper.getCriterionFromCriteriaCollection(row, criteria);
            // Get the level to add the descriptor
            if (levelid != undefined) {
                parentid = levelid; // compare to the id the DB gave to the level.
            }

            Log.debug('parentid', parentid);

            if (self.mode == 'edit') { // if its come from the add descriptor we have the id of the level in the second part of the id given le
                parentid = levelid.slice(levelid.indexOf('-') + 1, levelid.length);
            }

            const levelsdesc = criterion[0].levels.filter(function (level, index) {
                if (parentid == level.id) {
                    return level.descriptors;
                }

                
            }, parentid);

            return levelsdesc;
        }

        LevelControl.prototype.getMinMaxMark = function (score) {

            if (score.indexOf('-') != -1) {
                return score.split('-');
            } else {
                return score.split('/');
            }
        }

        LevelControl.prototype.setErrorMessage = function (e, message) {

            e.target.classList.add('total-input-error');
            e.target.setAttribute('data-toggle', 'tooltip');
            e.target.setAttribute('data-placement', 'right');
            e.target.setAttribute('data-title', message);
        }

        LevelControl.prototype.validatePreviousMarkValue = function () {
            const self = this;

            const previousLevel = document.getElementById(self.id).previousElementSibling;
            const previousMark = previousLevel.querySelector('.fmark');
            if (previousMark != null && previousMark.value == 0 && previousMark.value != "") {
                Log.debug(previousMark.value);

                previousLevel.classList.add('alert-warning');
                previousMark.insertAdjacentHTML('afterend', '<small>Change Mark</small>');

            }

        }

        LevelControl.prototype.cleanPreviousMarkWarning = function () {
            const self = this;

            const level = document.getElementById(self.id);
            if (level.classList.contains('alert-warning')) {
                level.classList.remove('alert-warning');
                level.querySelector('.level-mark').removeChild(level.querySelector('small'));
            }

        }

        LevelControl.prototype.countSelectedDescriptors = function () {

        }






        return {
            init: init
        };
    });