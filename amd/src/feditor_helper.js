/* eslint-disable no-return-assign */
/* eslint-disable no-unused-vars */
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
 * Helper
 * @package   gradingform_frubric
 * @copyright 2021 Veronica Bermegui
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(["core/log"], function (Log) {
    "use strict";

    return {
        // Helper function to get the closest parent with a matching selector
        getClosest: function (elem, selector) {
            for (; elem && elem !== document; elem = elem.parentNode) {
                if (elem.matches(selector)) {
                    return elem;
                }
            }
            return null;
        },

        getPreviousElement: function (elem, selector) {

            // Get the previous sibling element
            var sibling = elem.previousElementSibling;

            // If there's no selector return.
            if (!selector) {
                return;
            }

            // If the sibling matches our selector, use it
            // If not, jump to the next sibling and continue the loop
            while (sibling) {

                if (sibling.matches(selector)) {
                    return sibling;
                }
                sibling = sibling.previousElementSibling;
            }
        },

        getDistanceFromCriterionHeader: function (elem, selector) {
            var sibling = elem.previousElementSibling;
            var distance = -1;
            // If there's no selector return.
            if (!selector) {
                return;
            }

            while (sibling) {
                distance++;
                if (sibling.matches(selector)) {
                    return distance;
                }
                sibling = sibling.previousElementSibling;
            }

        },

        getNextElement: function (elem, selector) {
            // Get the next sibling element
            var sibling = elem.nextElementSibling;
            // If there's no selector, return
            if (!selector) {
                return;
            }
            // If the sibling matches our selector, use it
            // If not, jump to the next sibling and continue the loop
            while (sibling) {
                if (sibling.matches(selector)) {
                    return sibling;
                }
                sibling = sibling.nextElementSibling;
            }
        },
        // Returns the criteria parsed to array.
        getCriteriaJSON: function () {
            return JSON.parse(document.getElementById("id_criteria").value);
        },

        getLevelsJSON: function (criteriaid) {
            return document.getElementById(`advancedgrading-frubric-${criteriaid}-leveljson`).value;
        },

        setCriteriaJSON: function (criterioncollection) {
            return document.getElementById('id_criteria').value = JSON.stringify(criterioncollection);
        },

        getCriterionFromCriteriaCollection: function (row, criterioncollection) {

            const filteredCriterion = criterioncollection.filter(function (criterion, index) {
                const id = row.getAttribute('data-criterion-group');

                criterion.rowindex = index;
                if (id == criterion.id) {
                    return criterion;
                }
            }, row);

            return filteredCriterion;
        },

        getRandomID: function () {
            return (Math.floor(Math.random() * 9999));
        },

        getMode: function () {
            return document
                .querySelector(".criterion-header")
                .getAttribute("data-mode");
        },

        getMinMax: function (score) {
            if (score.indexOf('-') != -1) {
                return score.split('-');
            } else {
                return score.split('/');
            }
        },

        // The table structure is different when creating the frubric.
        // We need a way to find the max value. getMaxValueInLevelInCriterion
        getMaxValueInLevelInCriterion: function (groupid) {
            const maxvalues = [];
            const marks = document.querySelectorAll(`.level-${groupid} .level-mark .fmark`);

            Array.from(marks).forEach(score => {
                const minmax = score.value.split('-');
                maxvalues.push(Number(minmax[minmax.length - 1]));

            }, maxvalues);

            return Math.max(...maxvalues);
        },

        /**
         * Keep track of the JSON so in case it the validation fails.
         * We send this value that has the data set to be rerended.
         * @param {JSON} criteria
         */
        setHiddenCriteriaJSON: function (criteria) {
            document.querySelector('input[name="criteriajsonhelper"]').value = JSON.stringify(criteria);
        },

    };
});