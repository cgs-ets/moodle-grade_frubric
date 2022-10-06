/* eslint-disable no-eq-null */
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
        /**
         *
         * @param {*} id
         * @param {*} parentid
         */
        function init(id, parentid) {

            const mode = FeditorHelper.getMode();
            const level = document.getElementById(id);

            let control = new LevelControl(level, mode, id, parentid);
            control.main();

        }


     /**
      *
      * @param {*} level
      * @param {*} mode
      * @param {*} id
      * @param {*} parentid
      */
        function LevelControl(level, mode, id, parentid) {
            const self = this;
            self.level = level;
            self.mode = mode;
            self.id = id;
            self.parentid = parentid;
            self.parentidaux = parentid;
            self.LEVEL_DESCRIPTOR_INPUT = 'gradingform_frubric/level_descriptor_input';
            self.LEVEL_DECRIPTORS_DELETE_SET = 'gradingform_frubric/level_decriptors_delete_set';
        }

        /**
         * Run the controller.
         */
        LevelControl.prototype.main = function() {
            let self = this;

            if (self.mode == 'edit') {
                if (self.level.classList.contains('criterion-header')) {
                    self.editModeSetupEvents(self.level.nextElementSibling);
                } else {
                    self.editModeSetupEvents(self.level);
                }
            } else {
                if (self.level != null) {
                    // CASE: last level  has 0 mark.
                    // A new level is added, previous level can't be zero.
                    // As 0 is only allowed for the last level,
                    self.validatePreviousMarkValue();
                    self.setupEvents(self.level);
                }
            }

        };

        LevelControl.prototype.editModeSetupEvents = function (level) {
            const self = this;
            // Get the current level. Its the new level added.
            if (level.getAttribute('data-row-type') == 'result'
                || level.getAttribute('data-row-type') == 'add-level-r') {
                level = level.previousElementSibling;
            }

            const [del, markandesc] = level.children;

            const markdesctable = markandesc.querySelector('.level-mark-desc-table');

            if (markdesctable) {
                var firstcell = markdesctable.closest('tr'); // Get the delete column for the entire level.
                firstcell = $(firstcell).children('td:first')[0];
                firstcell.querySelector('.action-el').addEventListener('click', self.deleteLevel.bind(self));

                let rows = markdesctable.rows;
                $(rows).each(function (index, row) {

                    $(row).children().each(function (j, td) {
                        if (td.classList.contains('level-mark')) {
                            td.querySelector('.level-mark > textarea').addEventListener('focus', self.editmark.bind(self));
                        }

                        $(td).children().each(function (wy, i) {

                            const container = this;

                            if (container.classList.contains('standard-desc-container')) {

                                self.editModeSetupEventsHelper(container);
                            }

                            if (container.classList.contains('add-descriptor')) {
                                container.querySelector('.add-desc-btn').addEventListener('click', self.addDescriptor.bind(self));
                            }

                            if (container.classList.contains('action-delete-set-desc')) {

                                container.addEventListener('click', self.deleteSetCriterion.bind(self));
                            }

                        });

                    });

                });
            }


        };

        LevelControl.prototype.editModeSetupEventsHelper = function (desciptorContainer) {

            var self = this;
            let counter = 0;
            counter = desciptorContainer.children.length;

            if (counter > 0) { // All the descriptors for this level where deleted previously.

                self.descriptorIndex = counter;
                self.parentid = desciptorContainer.children[0].getAttribute('data-parent-id');
                self.lid = desciptorContainer.children[0].getAttribute('id');

                $(desciptorContainer).each(function (i, td) {
                    $(td).children().each(function (x) {

                        const container = this;

                        if (container.classList.contains('fmark')) {
                            return;
                        }

                        container.setAttribute('descriptor-index', x);
                        const action = container.querySelector('.action-el');
                        const checkbox = container.querySelector('.standard-check');
                        const descriptor = container.querySelector('.standard-desc');

                        /**Add focus and click events to the descriptor. SO it always picks up the change */
                        descriptor.addEventListener('click', self.clickDescriptorHandler.bind(this, self));
                        descriptor.addEventListener('focus', self.clickDescriptorHandler.bind(this, self));
                        action.addEventListener('click', self.deleteDescriptor.bind(self, descriptor, container));
                        checkbox.addEventListener('click', self.selectdescriptor.bind(this, self));

                    });
                });
            }
        }

        LevelControl.prototype.setupEvents = function (level) {

            let self = this;
            const [del, markandesc] = level.children;
            const markdesctable = markandesc.querySelector('.level-mark-desc-table');

            if (markdesctable) {

                const [marktd, descriptortd] = markdesctable.rows[0].children;
                marktd.querySelector('.level-mark > textarea').addEventListener('focus', self.editmark.bind(self));

                if (self.mode != 'edit') {
                    descriptortd.querySelector('.add-desc-btn').addEventListener('click', self.addDescriptor.bind(self));
                }

                del.addEventListener('click', self.deleteLevel.bind(self));
            }

        };

        LevelControl.prototype.editmark = function (e) {

            const self = this;
            const score = e.target;

            if (score.innerHTML == '[Min - Max]') {
                score.innerHTML = '';
            }

            if (e.target.classList.contains('is-invalid')) {
                e.target.classList.remove('is-invalid');
                e.target.classList.remove('form-control');
                e.target.removeAttribute('title');
            }

            score.focus();

            if (!score.classList.contains('changeh')) {
                score.addEventListener('change', self.changeMarkHandler.bind(this, self));
                score.classList.add('changeh');
            }

        };

        LevelControl.prototype.changeMarkHandler = function (s, e) {

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

            var levelid;

            if (e.target.getAttribute('data-level-id') != null) {
                levelid = e.target.getAttribute('data-level-id');
            }
            // Update the score for this criterion.
            const criteria = FeditorHelper.getCriteriaJSON();
            const criterion = FeditorHelper.getCriterionFromCriteriaCollection(document.getElementById(s.id), criteria);
            const levelsdesc = s.getLevelDescriptors(s.id, criteria, levelid);
            const score = e.target.value;
            const nonum = /[a-z]/gi.test(e.target.value);

            let error = false;
            let message = '';

            if (nonum) {
                error = true;
                message = 'Please insert a number value range';
            } else {

                if (score.indexOf('-') == -1 && score.indexOf('/') == -1) {
                    error = true;
                    message = 'Invalid value. Accepts min-max or min/max';
                } else {
                    // Evaluate min/max
                    var [min, max] = FeditorHelper.getMinMax(score);
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

            var groupid;
            // Update the total result input. with the highest value.
            groupid = document.getElementById(s.id).getAttribute('data-criterion-group');

            const resultRow = document.querySelector(`[data-criterion-group="${groupid}"][data-row-type="result"]`);
            var total = (resultRow.querySelector(`#out-of-value-${groupid}`).innerHTML).split("/");

            total =  FeditorHelper.getMaxValueInLevelInCriterion(groupid);
            const maxinput = resultRow.querySelector('.total-input');

            levelsdesc[0].score = e.target.value;

            if (levelsdesc[0].status == 'CREATED' || levelsdesc[0].status == 'UPDATED') {
                levelsdesc[0].status = 'UPDATE';
            }

            resultRow.querySelector(`#out-of-value-${groupid}`).innerHTML = `/${total}`;

            // Update the max attribute.
            maxinput.setAttribute("max", total);
            criterion[0].totaloutof = total;

            FeditorHelper.setCriteriaJSON(criteria);
            FeditorHelper.setHiddenCriteriaJSON(criteria);

        };

        LevelControl.prototype.addDescriptor = function (e) {
            const self = this;
            e.stopImmediatePropagation();
            e.preventDefault();

            // Get the standard-desc-container  that contains the descritors.
            const descriptorContainer = FeditorHelper.getPreviousElement(e.target.parentNode, '.standard-desc-container');
            var positionLevel = 0;

            let id;
            if ((self.mode != 'edit') || descriptorContainer.children.length == 0) {
                id = `${self.id}-${FeditorHelper.getRandomID()}`;
                positionLevel = descriptorContainer.children.length; // By adding the delete set
            } else {
                id = `${self.id}-${descriptorContainer.children[0].getAttribute('id')}`;
            }


            var countingdel = false;

            if (descriptorContainer.children.length > 0) {
                positionLevel = descriptorContainer.children.length - 1; // When we add the first element, the delete set span is addedd too. we need to only count the divs t oget the right index.

                if (descriptorContainer.children[0].classList.contains('action-delete-set-desc')) {
                    countingdel = true;
                }
            }

            if (self.parentid == undefined) { // Level with no descriptor, User clicked save anf make it ready
                var editaddnewlevel = false;

            } else if (self.parentid.includes('frubric-criteria-NEWID')) {
                editaddnewlevel = self.parentid.includes('frubric-criteria-NEWID');
            }


            const context = {
                id: id,
                parentid: self.parentid,
                edit: self.mode == 'edit',
                editaddnewlevel: editaddnewlevel,
                index: (countingdel) ? descriptorContainer.children.length - 1 : descriptorContainer.children.length,
                poslevel: positionLevel
            };


            if (self.mode != 'edit') {
                delete context.index;
            }

            Templates.render(self.LEVEL_DESCRIPTOR_INPUT, context)
                .done(function (html, js) {

                    let addDeleteSet = false;
                    if (descriptorContainer.children.length == 0) {
                        addDeleteSet = true
                    }
                    descriptorContainer.insertAdjacentHTML('beforeend', html);

                    if (addDeleteSet) {
                        descriptorContainer.insertAdjacentHTML("afterbegin", '  <span class="action-delete-set-desc  first-time-render"> <i class="fa fa-close  first-time-render" title="Delete set of descriptors"></i></span>');
                        descriptorContainer.querySelector('.action-delete-set-desc').addEventListener('click', self.deleteSetCriterion.bind(self));
                    }

                    const container = descriptorContainer.lastChild;
                    const action = container.querySelector('.action-el');
                    const checkbox = container.querySelector('.standard-check');
                    const descriptor = container.querySelector('.standard-desc');

                    descriptor.addEventListener('click', self.clickDescriptorHandler.bind(this, self));
                    action.addEventListener('click', self.deleteDescriptor.bind(self, descriptorContainer, container));
                    checkbox.addEventListener('click', self.selectdescriptor.bind(this, self)); // TODO: DO I NEED THIS?

                })
                .fail(function (ex) {
                    Log.debug("error...");
                });


            let criteria = FeditorHelper.getCriteriaJSON();
            let container = descriptorContainer.lastElementChild;

            if (container != null) {
                FeditorHelper.setCriteriaJSON(criteria);
                FeditorHelper.setHiddenCriteriaJSON(criteria);
            }
        }



        LevelControl.prototype.clickDescriptorHandler = function (s, e) {

            let descriptor = e.target;
            descriptor.focus();

            if (e.target.classList.contains('is-invalid')) {
                e.target.classList.remove('is-invalid');
                e.target.classList.remove('form-control');
                e.target.removeAttribute('title')
            }
            // Attach change event
            descriptor.addEventListener('change', s.changeDescriptorHandler.bind(this, s));
            // If you copy and paste content without clicking it doesnt pick up the change. Add paste event
            descriptor.addEventListener('paste', s.changeDescriptorHandler.bind(this, s));

        }

        LevelControl.prototype.changeDescriptorHandler = function (s, e) {
            const criteria = FeditorHelper.getCriteriaJSON();
            const containerid = e.target.getAttribute('data-container-id');
            let levelid;
            let descriptorIndex = e.target.parentNode.getAttribute('descriptor-index');

            if (descriptorIndex != null) {
                levelid = e.target.parentNode.getAttribute('id');
            } else {
                descriptorIndex = Array.from(document.getElementById(containerid).parentNode.children).indexOf(e.target.parentNode) - 1;
            }

            // We are adding a descriptor to a level that is already in the DB. Make sure is not a rerender for failing
            if (levelid != undefined && levelid.includes("-") && !document.getElementById('id_criteria').classList.contains('is-invalid')) {
                levelid.slice(levelid.indexOf('-') + 1, levelid.length);
            }

            const levelsdesc = s.getLevelDescriptors(s.id, criteria, levelid);
            if (descriptorIndex > levelsdesc[0].descriptors.length) { // Add descriptors inbetween when updating descriptors in different order.
                for (var i = (levelsdesc[0].descriptors.length); i < descriptorIndex; i++) {

                    levelsdesc[0].descriptors.push({
                        checked: false,
                        descText: '',
                        delete: 0,
                        descriptorid: 0
                    });
                }

            }

            const desc = levelsdesc[0].descriptors[descriptorIndex];

            if (desc == undefined) { // New entry
                levelsdesc[0].descriptors.push({
                    checked: false,
                    descText: e.target.value,
                    delete: 0,
                    descriptorid: 0
                });

            } else { // Existing entry, update text.

                desc.descText = e.target.value;
            }

            if (levelsdesc[0].status == 'CREATED' || levelsdesc[0].status == 'UPDATED') {
                levelsdesc[0].status = 'UPDATE';
            }

            FeditorHelper.setCriteriaJSON(criteria);
            FeditorHelper.setHiddenCriteriaJSON(criteria);

        }

        LevelControl.prototype.selectdescriptor = function (e, s) {

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
            FeditorHelper.setHiddenCriteriaJSON(criteria);
        }

        LevelControl.prototype.deleteDescriptor = function (descriptorContainer, checkboxcontainer) {

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

                        const criteria = FeditorHelper.getCriteriaJSON();
                        const levelid = checkboxcontainer.getAttribute('id');
                        const parentid = self.id;
                        const descriptorIndex = checkboxcontainer.getAttribute('descriptor-index');

                        const levelsdesc = self.getLevelDescriptors(parentid, criteria, levelid);
                        const d = levelsdesc[0].descriptors[descriptorIndex];

                        // Check that the descriptor you want to delete was saved in the DB.
                        // Else, you just have to remove it and update the JSON to avoid updates to the level.
                        if (d.descriptorid != 0) {
                            d.delete = 1;
                            levelsdesc[0].status = 'UPDATE';

                            let countdeleted = 0;
                            levelsdesc[0].descriptors.forEach(function (desc, index) {
                                if (desc.delete == 1) {
                                    countdeleted++;
                                }
                            }, countdeleted);

                            // Check if its the only descritor in the level. If it is, then remove the level completely
                            if (levelsdesc[0].descriptors.length == countdeleted) {
                                const checkboxaux = checkboxcontainer;
                                levelsdesc[0].status = 'DELETE';

                                // Check if the definition only has one criterion. If it does, and the level is removed. then the criterion has to be deleted too
                                if (criteria.length == 1) {
                                    criteria[0].status = 'DELETE';
                                }

                                criteria.forEach(function (criterion) {
                                    if (criterion.id == parentid) {
                                        if (criterion.levels.length == 1) { // There is only one level in this criterion. Check if the descriptor has info.
                                            criterion.status = 'DELETE'

                                        } else {
                                            criterion.status = 'UPDATE'
                                        }
                                    }
                                }, parentid);


                            }
                        } else {
                            levelsdesc[0].descriptors.splice(descriptorIndex, 1);
                            self.updateDescriptorIndex(descriptorContainer);
                        }

                        checkboxcontainer.remove();

                        // Update the input.
                        FeditorHelper.setCriteriaJSON(criteria);
                        FeditorHelper.setHiddenCriteriaJSON(criteria);


                    }, function () {
                        // For the cancel btn.
                        return;
                    });
                });

            } else {

                const descriptorindex = checkboxcontainer.getAttribute('data-pos-level');
                const parentid = self.id;
                const criteria = FeditorHelper.getCriteriaJSON();
                const row = document.getElementById(parentid);
                const criterion = FeditorHelper.getCriterionFromCriteriaCollection(row, criteria);
                const level = criterion[0].levels.filter(function (l) {
                    if (l.id == parentid) {
                        return l.descriptors;
                    }
                }, parentid);

                const descriptors = (level[0]).descriptors;
                descriptors.splice(descriptorindex, 1);
                descriptorContainer.removeChild(checkboxcontainer);
                self.updateDescriptorIndex(descriptorContainer);

                // UPDATE JSON
                FeditorHelper.setCriteriaJSON(criteria);
                FeditorHelper.setHiddenCriteriaJSON(criteria);

            }
        };

        LevelControl.prototype.deleteSingleDescriptorNotSavedInDB = function (checkboxcontainer) {

            const descriptorindex = checkboxcontainer.getAttribute('data-pos-level');
            const parentid = self.id;
            const criteria = FeditorHelper.getCriteriaJSON();
            const row = document.getElementById(parentid);
            const criterion = FeditorHelper.getCriterionFromCriteriaCollection(row, criteria);
            const level = criterion[0].levels.filter(function (l) {
                if (l.id == parentid) {
                    return l.descriptors;
                }
            }, parentid);

            const descriptors = (level[0]).descriptors;
            descriptors.splice(descriptorindex, 1);
            descriptorContainer.removeChild(checkboxcontainer);
            self.updateDescriptorIndex(descriptorContainer);
            // UPDATE JSON
            FeditorHelper.setCriteriaJSON(criteria);
            FeditorHelper.setHiddenCriteriaJSON(criteria);
        }


        LevelControl.prototype.updateDescriptorIndex = function (descriptorContainer) {

            $(descriptorContainer).children('div.checkbox-container').each(function (i) {
                this.setAttribute('data-pos-level', i);
            });
        }



        LevelControl.prototype.deleteSetCriterion = function (e) {

            Str.get_strings([{
                    key: 'confirm',
                    component: 'gradingform_frubric'
                },
                {
                    key: 'confirmdeletesetcriterion',
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
                    const tr = e.target.closest('tr'); // get the tr this element is in
                    const table = e.target.closest('.level-mark-desc-table');
                    const criteria = JSON.parse(document.getElementById('id_criteria').value);
                    const fromRenderer = e.target.classList.contains("first-time-render"); // When adding a new descriptor that was not saved in the db, just delete it.

                    if (table.rows.length == 1) {
                        const level = table.closest("[data-criterion-group]");

                        if (!fromRenderer) {
                            criteria.forEach(function (criterion) {
                                if (criterion.id == level.getAttribute('data-criterion-group')) {
                                    criterion.status = 'DELETE';

                                    const cl = Object.values(criterion.levels);

                                    cl.forEach(function (level) {

                                        if (level.status != "NEW") { // Case: We want to add a new level and delete the current one.
                                            level.status = 'DELETE';
                                            criterion.status = "UPDATE";

                                        }
                                    }, criterion);
                                }
                            });

                        }

                        level.remove();

                    } else {

                        // It has a few levels.
                        const crit = tr.closest("[data-criterion-group]"); // This row has the criterion these levels belong to
                        const descriptors = Array.from(e.target.parentNode.nextElementSibling.children);
                        const descriptorids = [];

                        descriptors.forEach(function (desc) {
                            descriptorids.push(desc.id);
                        }, descriptorids);

                        const data = {
                            crit: crit,
                            descids: descriptorids
                        }

                        if (!fromRenderer) {
                            criteria.forEach(function (criterion) {
                                if (criterion.id == data.crit.getAttribute('data-criterion-group')) {
                                    criterion.status = 'UPDATE';

                                    if (!Array.isArray(criterion.levels)) { // Check if the criteria has levels
                                        criterion.levels = Object.values(criterion.levels);
                                    }
                                    criterion.levels.forEach(function (level) {

                                        if (data.descids.includes((level.id).toString())) {

                                            level.descriptors.forEach(function (d) {
                                                d.delete = 1;
                                            });
                                            level.status = 'DELETE';

                                        }
                                    }, data.descids);

                                }
                            }, data);
                        }

                        table.deleteRow(tr.rowIndex);
                        // Controlar que el max value  refleje el maximo si hay cambios
                    }

                    // document.getElementById('id_criteria').value = JSON.stringify(criteria);
                    FeditorHelper.setCriteriaJSON(criteria);
                    FeditorHelper.setHiddenCriteriaJSON(criteria);

                }, function () {
                    // For the cancel btn.
                    return;
                });
            });
        }

        LevelControl.prototype.deleteLevel = function (e) {

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
                    let criteria = FeditorHelper.getCriteriaJSON();
                    const crid = tr.getAttribute('data-criterion-group')


                    if (criterion.getAttribute('data-criterion-levels').length > 0) {

                        const dbids = JSON.parse(criterion.getAttribute('data-criterion-levels')); // These are the ids of the levels given by the DB.
                        const index = FeditorHelper.getDistanceFromCriterionHeader(tr, '.criterion-header');
                        const dblevelid = dbids[index];


                        var levels;

                        for (let i = 0; i < criteria.length; i++) {
                            if (criteria[i].id == crid) {
                                criteria[i].status = 'UPDATE';
                                levels = criteria[i].levels;
                                break;
                            }
                        }

                        for (let j = 0; j < levels.length; j++) {
                            if (levels[j].id == dblevelid) {
                                levels[j].status = 'DELETE';
                                break;
                            }
                        }

                    } else {

                        // The Criteria has not been saved. No need to send it to the DB.

                        const tr = e.target.closest('tr');
                        const lid = tr.getAttribute('id');
                        const cid = criterion.getAttribute('id');
                        const d = {
                            levelid: parseInt(lid),
                            criterionid: cid
                        }


                        criteria.forEach(function (criterion) {

                            if (criterion.cid == d.criterionid) {
                                criterion.levels = criterion.levels.filter(function (level) {

                                    if (level.id != d.levelid) {
                                        return level;
                                    }
                                }, d);

                            }
                        }, d);

                    }


                    FeditorHelper.setCriteriaJSON(criteria);
                    FeditorHelper.setHiddenCriteriaJSON(criteria);
                    criTable.deleteRow(tr.rowIndex);


                }, function () {
                    // For the cancel btn.
                    return;
                });
            });
        };

        LevelControl.prototype.editlevelhandler = function (e) {
            let textarea = e.target;

            textarea.removeAttribute('disabled');
            textarea.focus();
            textarea.addEventListener('change', this.changeLevelHandlerDisabled.bind(this, textarea));
            textarea.addEventListener('focusout', this.focusoutHandlerDisabled);
        };

        LevelControl.prototype.changeLevelHandlerDisabled = function (txtarea, e) {

            let id = e.target.parentNode.parentNode.parentNode.getAttribute('data-criterion-group');
            const levelrow = e.target.parentNode.parentNode.parentNode;

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


            FeditorHelper.setCriteriaJSON(criteriaJSON);
            FeditorHelper.setHiddenCriteriaJSON(criteriaJSON);

            e.target.setAttribute('disabled', true);
        };

        LevelControl.prototype.focusoutHandlerDisabled = function (e) {
            e.target.setAttribute('disabled', true);
        };

        LevelControl.prototype.getLevelDescriptors = function (parentid, criteria, levelid) {
            var self = this;
            let row = '';

            if (document.getElementById(parentid) == null) {
                row = document.getElementById(self.level.id);
            } else {
                row = document.getElementById(parentid);
            }

            const criterion = FeditorHelper.getCriterionFromCriteriaCollection(row, criteria);
            let ids;
            // Get the level to add the descriptor
            if (levelid != undefined) {
                parentid = levelid; // compare to the id the DB gave to the level.
            }

            if (self.mode == 'edit' && levelid != undefined) { // if its come from the add descriptor we have the id of the level in the second part of the id given le
                if (levelid.toString().indexOf('-') > -1) {
                    parentid = levelid.slice(levelid.indexOf('-') + 1, levelid.length);
                    ids = levelid.split('-');
                    ids.forEach(function (id, index) {
                        ids[index] = id.toString();
                    }, ids);
                }
            }

            const obj = {
                mode: self.mode,
                parentid: parentid,
                parentidaux: self.parentidaux,
                ids: ids

            }

            if (criterion[0].levels.length == 0) {
                criterion[0].levels.push({
                    score: '',
                    status: "NEW",
                    id: parentid,
                    descriptors: [{
                        checked: false,
                        descText: '',
                        delete: 0,
                        descriptorid: 0
                    }]
                });
            }
            // User tried to save and make ready a criteria with a level with no descriptor.
            //We are in the fix error view of the form
            const levelsdesc = criterion[0].levels.filter(function (level) {
                if (obj.ids != undefined) {
                    if ((obj.ids).includes((level.id).toString())) {
                        return level.descriptors;
                    }
                } else if (obj.parentid == (level.id).toString()
                            || (obj.parentid).toString().includes((level.id).toString())) {
                    return level.descriptors;
                }


            }, obj);


            FeditorHelper.setCriteriaJSON(criteria);
            FeditorHelper.setHiddenCriteriaJSON(criteria);

            return levelsdesc;
        };

        LevelControl.prototype.setErrorMessage = function (e, message) {

            e.target.classList.add('total-input-error');
            e.target.setAttribute('data-toggle', 'tooltip');
            e.target.setAttribute('data-placement', 'right');
            e.target.setAttribute('data-title', message);
        };

        LevelControl.prototype.validatePreviousMarkValue = function () {
            const self = this;

            const previousLevel = document.getElementById(self.id).previousElementSibling;
            const previousMark = previousLevel.querySelector('.fmark');
            if (previousMark != null && previousMark.value == 0 && previousMark.value != "") {

                previousLevel.classList.add('alert-warning');
                previousMark.insertAdjacentHTML('afterend', '<small>Change Mark</small>');

            }

        };

        LevelControl.prototype.cleanPreviousMarkWarning = function () {
            const self = this;

            const level = document.getElementById(self.id);
            if (level.classList.contains('alert-warning')) {
                level.classList.remove('alert-warning');
                level.querySelector('.level-mark').removeChild(level.querySelector('small'));
            }

        };

        LevelControl.prototype.countSelectedDescriptors = function () {

        };






        return {
            init: init
        };
    });