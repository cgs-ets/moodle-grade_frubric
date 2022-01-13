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

define(['jquery', 'core/log', 'core/templates', 'gradingform_frubric/feditor_helper', 'gradingform_frubric/rerender_control'],
    function ($, Log, Templates, FeditorHelper, Rerender) {
        'use strict';

        function init() {
            Log.debug("Feditor control...");
            const mode = FeditorHelper.getMode();
            let criterioncollection;
            // let rerender = 0;

            if (mode === 'create' && document.getElementById('id_criteria').classList.contains('is-invalid')) { // Re render the template with the previous values
                //  rerender = 1;
                Rerender.init('rerendercreate')
            }

            if (mode === 'edit' || (mode === 'create' && document.getElementById('id_criteria').classList.contains('is-invalid') != undefined)) { // Validation error, keep the values inserted
                criterioncollection = document.getElementById('id_criteria').value;

            } else {
                // Initialise the first criterion
                var criterion = {
                    id: 1,
                    cid: `frubric-criteria-NEWID${document.querySelectorAll(".criterion-header").length}`, // Criterion ID for the DB
                    status: "NEW",
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
                document.getElementById('id_criteria').value = JSON.stringify(criterioncollection);

            }

            if (document.getElementById('cont').getAttribute('data-rerendered') == 0 ) {

                if (mode == 'edit' && document.getElementById('id_criteria').classList.contains('is-invalid') == true) {
                    Rerender.init('rerenderupdate');
                }
            }

          
            let control = new Feditor(criterioncollection);
            control.main();

        }

        /**
         *
         * @param {*} criterioncollection
         */
        function Feditor(criterioncollection) {
            Log.debug('Feditor: initialising...');
            let self = this;
            self.CRITERION_ROW = 'gradingform_frubric/editor_criterion_row';
            self.FEDITOR = 'gradingform_frubric/frubriceditor';
            self.criterioncollection = criterioncollection;
            //self.rerender = rerender;
        }

        /**
         * Run the controller.
         *
         */
        Feditor.prototype.main = function () {
            let self = this;
            // Log.debug(this);
            // if (self.rerender == 1) {
            //     self.rerenderEditor();
            // } else {
            self.setupEvents();
            // }

        };

        Feditor.prototype.setupEvents = function () {
            let self = this;
            // Add criterio btn
            document.getElementById('addCriterion').addEventListener('click', self.addCriterion.bind(this));
        };

        Feditor.prototype.addCriterion = function () {
            Log.debug("Add criterion...");
            let self = this;

            const countcriteria = document.querySelectorAll('.criterion-header').length;
            // Set context
            const context = {
                id: `frubric-criteria-NEWID${countcriteria + 1}`,
                criteriongroupid: countcriteria + 1,
                // description: 'Click to edit criterion',
                new: 1, // Adds the result row
            };


            Templates.render(self.CRITERION_ROW, context)
                .done(function (html, js) {

                    Templates.appendNodeContents(document.getElementById('criteriaTable'), html, js);
                    const cr = document.querySelector("tbody").lastElementChild;

                    let criterion = {
                        id: cr.getAttribute('data-criterion-group'),
                        cid: `frubric-criteria-NEWID${countcriteria + 1}`, // Criterion ID for the DB
                        status: 'NEW',
                        new: 1, // to add the result row
                        description: '', // Criterion descrption
                        rowindex: cr.rowIndex, // Keep track of the header row.
                        definitionid: document.getElementById('cont').getAttribute('data-definition-id'), // Id from mdl_grading_definitions
                        levels: [],
                        sumscore: "",
                    };


                    if (FeditorHelper.getCriteriaJSON() != '') {
                        self.criterioncollection = JSON.parse(document.getElementById('id_criteria').value);
                        // document.getElementById('id_criteriajsonshadow').value = JSON.parse(document.getElementById('id_criteria').value);
                    }

                    self.criterioncollection.push(criterion);
                    FeditorHelper.setCriteriaJSON(self.criterioncollection);
                    //document.getElementById('id_criteria').value = JSON.stringify(self.criterioncollection);

                })
                .fail(function (ex) {
                    Log.debug("error...");
                    Log.debug(ex);
                });

            const isreadytouse = document.getElementById('id_status').value == '20'; // 20 ==> STATUS READY
            if (isreadytouse) {
                document.querySelector('.gradingform_rubric-regrade').setAttribute('hidden', true)
            }

        };


        // Feditor.prototype.rerenderEditor = function () {
        //     var self = this;

        //     const context = JSON.parse(self.criterioncollection);
        //     const definitionid = document.getElementById("cont").getAttribute("data-definition-id")

        //     const data = {
        //         edit: 1,
        //         rerender: 1,
        //         definitionid: definitionid,
        //         "criteria": []
        //     }

        //     context.forEach(function (element) {
        //         let auxid = element.id;
        //         //element.id = element.cid;
        //         element.criteriongroupid = auxid;
        //         if (element.levels.length == 0) {
        //             element.levels.push({
        //                 score: '',
        //                 status: "NEW",
        //                 id: FeditorHelper.getRandomID(),
        //                 descriptors: [{
        //                     checked: false,
        //                     descText: '',
        //                     delete: 0
        //                 }]
        //             });
        //         } else {
        //             element.levels.forEach(level => {
        //                 if (level.descriptors.length == 0) {
        //                     level.descriptors.push({
        //                         checked: false,
        //                         descText: '',
        //                         delete: 0
        //                     });
        //                 }
        //             });
        //         }
        //         data.criteria.push(element);

        //     });

        //     Templates.render(self.FEDITOR, data)
        //         .done(function (html, js) {
        //             Y.log(html);
        //             // Replace with editor with previous values
        //             const editor = document.getElementById('cont');
        //             Templates.replaceNode(editor, html, js);

        //         }).fail(function (ex) {
        //             Log.debug("error...");
        //             Log.debug(ex);
        //         });

        // }


        return {
            init: init
        };
    });