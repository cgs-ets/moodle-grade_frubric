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

define(['jquery', 'core/log', 'core/templates', 'core/ajax', 'core/str', 'gradingform_frubric/feditor_helper'],
    function ($, Log, Templates, Ajax, Str, FeditorHelper) {
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
                // Log.debug("criteria.forEach");
                // Log.debug(self);
               // Log.debug(FeditorHelper.getLevelsJSON(element.criteriaid));
            //    self.levelsJSON = JSON.parse(FeditorHelper.getLevelsJSON(element.criteriaid));
                Array.from(level).forEach(function (leveldetails) {
                //   Log.debug("Array.from(level).forEach");
                //   Log.debug(self);
                    // Log.debug("levelsJSON");
                    // Log.debug(self.levelsJSON);
                    const descriptorsCollection = leveldetails.children[1];
                    const descriptors = descriptorsCollection.children;
                    Array.from(descriptors).forEach(function (descriptor) {
                        // Log.debug("criteriaid");
                        // Log.debug(element.criteriaid);
                        if (descriptor instanceof HTMLInputElement) {
                            descriptor.addEventListener('click', self.clickDescriptorHandler.bind(self));
                        }
                    });
                }, self);


            }, self);


        }

        EvaluateControl.prototype.clickDescriptorHandler = function (e) {
            const self = this;
            
            Log.debug("CHECKED...");
            Log.debug(e);
            const id = (e.target.id).split('-');
            const criteriaID = id[3];
            const levelID = id[5];
            const descriptorID = id [id.length - 1];
            const levelsInput = JSON.parse(FeditorHelper.getLevelsJSON(criteriaID));
            Log.debug(criteriaID);
            Log.debug(levelID);
            Log.debug(descriptorID);
           

            const levelDescriptors = levelsInput[levelID].descriptors;
            Log.debug(levelDescriptors)

            levelDescriptors.filter(descriptor => {
                if (descriptor.descriptorid == descriptorID) {
                    descriptor.checked = !descriptor.checked;
                }
            }, descriptorID);

         
            Log.debug(levelsInput);

            // Replace the json value with the updated one
            document.getElementById(`advancedgrading-frubric-${criteriaID}-leveljson`).value = JSON.stringify(levelsInput);
        }




        return {
            init: init
        };
    });