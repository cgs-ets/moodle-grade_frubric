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

define(['core/log', 'core/templates', 'gradingform_frubric/feditor_helper'],
    function (Log, Templates, FeditorHelper) {
        'use strict';

        function init() {
            const mode = FeditorHelper.getMode();
            let criterioncollection;

            // In case the all the rubric is deleted and saved in the DB. We need to put an empty one again.
            //Otherwise the add button submits the form.
            const empty = JSON.parse(document.getElementById('id_criteria').value).length == 0;

            if (mode === 'create' && empty) {
                // Initialise the first criterion
                var criterion = {
                    id: '1',
                    cid: `frubric-criteria-NEWID${document.querySelectorAll(".criterion-header").length}`, // Criterion ID for the DB
                    status: "NEW",
                    visibility: true,  // By default the criterion is visible.
                    description: "", // Criterion descrption
                    rowindex: 1, // Keep track of the header row.
                    definitionid: document
                        .getElementById("cont")
                        .getAttribute("data-definition-id"), // Id from mdl_grading_definitions/
                    levels: [],
                    sumscore: "",
                    totaloutof: ""
                };

                criterioncollection = [criterion]; // Collects all the criterions

                FeditorHelper.setCriteriaJSON(criterioncollection);
                FeditorHelper.setHiddenCriteriaJSON(criterioncollection);

            }

            if (mode === 'edit' || (mode === 'create' && document.getElementById('id_criteria').classList.contains('is-invalid') != undefined)) { // Validation error, keep the values inserted
                criterioncollection = document.getElementById('id_criteria').value;

            } else {
                // Initialise the first criterion
                var criterion = {
                    id: "1",
                    cid: `frubric-criteria-NEWID${document.querySelectorAll(".criterion-header").length}`, // Criterion ID for the DB
                    status: "NEW",
                    visibility: true,  // By default the criterion is visible.
                    description: "", // Criterion descrption
                    rowindex: 1, // Keep track of the header row.
                    definitionid: document
                        .getElementById("cont")
                        .getAttribute("data-definition-id"), // Id from mdl_grading_definitions/
                    levels: [],
                    sumscore: "",
                    totaloutof: "",
                };

                criterioncollection = [criterion]; // Collects all the criterions

                FeditorHelper.setCriteriaJSON(criterioncollection);
                FeditorHelper.setHiddenCriteriaJSON(criterioncollection);

            }

            let control = new Feditor(criterioncollection);
            control.main();

        }

        /**
         *
         * @param {*} criterioncollection
         */
        function Feditor(criterioncollection) {
            let self = this;
            self.CRITERION_ROW = 'gradingform_frubric/editor_criterion_row';
            self.FEDITOR = 'gradingform_frubric/frubriceditor';
            self.criterioncollection = criterioncollection;
        }

        /**
         * Run the controller.
         *
         */
        Feditor.prototype.main = function () {
            let self = this;
            self.setupEvents();

        };

        Feditor.prototype.setupEvents = function () {
            let self = this;
            // Add criterio btn
            document.getElementById('addCriterion').addEventListener('click', self.addCriterion.bind(this));
        };

        Feditor.prototype.addCriterion = function () {
            let self = this;

            const countcriteria = document.querySelectorAll('.criterion-header').length;
            // Set context
            const context = {
                id: `frubric-criteria-NEWID${countcriteria + 1}`,
                criteriongroupid: countcriteria + 1,
                new: 1, // Adds the result row
                visibility: true
            };


            Templates.render(self.CRITERION_ROW, context)
                .done(function (html, js) {

                    Templates.appendNodeContents(document.getElementById('criteriaTable'), html, js);
                    const cr = document.querySelector("tbody").lastElementChild;

                    let criterion = {
                        id: cr.getAttribute('data-criterion-group'),
                        cid: `frubric-criteria-NEWID${countcriteria + 1}`, // Criterion ID for the DB
                        status: 'NEW',
                        visibility: true,
                        new: 1, // to add the result row
                        description: '', // Criterion descrption
                        rowindex: cr.rowIndex, // Keep track of the header row.
                        definitionid: document.getElementById('cont').getAttribute('data-definition-id'), // Id from mdl_grading_definitions
                        levels: [],
                        sumscore: "",
                    };


                    if (FeditorHelper.getCriteriaJSON() != '') {
                        self.criterioncollection = JSON.parse(document.getElementById('id_criteria').value);
                    }

                    self.criterioncollection.push(criterion);
                    FeditorHelper.setCriteriaJSON(self.criterioncollection);
                    FeditorHelper.setHiddenCriteriaJSON(self.criterioncollection);

                })
                .fail(function (ex) {
                    Log.debug(ex);
                });


        };

        return {
            init: init
        };
    });