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
 * Display only the frubric name  in the page-grade-grading-pick
 * Add an expand/collapse button to show/hide the body of the frubric
 * @package   gradingform_frubric
 * @copyright 2024 Veronica Bermegui
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (document.getElementById('page-grade-grading-pick') != null) {

    Array.from(document.querySelectorAll('h2.template-name')).forEach(templateNameEl => {
        InsertCaretElements(templateNameEl);
    });

    Array.from(document.querySelectorAll('.template-preview')).forEach(el => {
        el.classList.add('hidden-in-form-search');
    });

    Array.from(document.querySelectorAll('.template-description')).forEach(el => {
        el.classList.add('hidden-in-form-search');
    });

    Array.from(document.querySelectorAll('.template-actions')).forEach(el => {
        el.classList.add('hidden-in-form-search');
    });

}

function InsertCaretElements(templateNameEl) {

    //  Create <span><i class="fa-solid fa-caret-down rubric-expand-body"></i></span>
    const spanEl = document.createElement('span');
    const iconEl = document.createElement('i');

    // Set the class and title attributes
    iconEl.className = 'fa-solid fa-caret-down rubric-expand-body';
    iconEl.title = 'Display Template';

    // Insert the created element in span
    spanEl.className = 'template-view-control';
    // console.log(iconEl);
    spanEl.insertAdjacentElement('beforeend', iconEl);

    // Insert span in h2 title element.
    templateNameEl.insertAdjacentElement('beforeend', spanEl);

    spanEl.addEventListener('click', toggleTemplateBody);

}

function hideCaretDown(e) {
    // Change the arrow down element for arrow up

    e.target.classList.remove('fa-caret-down')
    e.target.classList.add('fa-caret-up')
    e.target.title = 'Hide Template';

    // Display the template
    const previewEl = findClosestSiblingWithClass(e.target, ['template-preview']);
    const actionsEl = findClosestSiblingWithClass(e.target, ['template-actions']);

    previewEl.classList.remove('hidden-in-form-search');
    actionsEl.classList.remove('hidden-in-form-search');

}

function hideCaretUp(e) {

    e.target.classList.add('fa-caret-down')
    e.target.classList.remove('fa-caret-up')
    e.target.title = 'Display Template';

    // Hide the template
    const previewEl = findClosestSiblingWithClass(e.target, ['template-preview']); //document.querySelector('.template-preview');
    const actionsEl = findClosestSiblingWithClass(e.target, ['template-actions']);//document.querySelector('.template-actions');


    previewEl.classList.add('hidden-in-form-search');
    actionsEl.classList.add('hidden-in-form-search');
}

function toggleTemplateBody(e) {

    if (e.target.classList.contains('fa-caret-down')) {
        hideCaretDown(e);
    } else {
        hideCaretUp(e);
    }
}

function findClosestSiblingWithClass(element, classNames) {
    // Get the parent parent of the element
    const parent = element.parentElement.parentElement;
    // Find the closest sibling with the specified class
    let sibling = parent.nextElementSibling;

    while (sibling) {
        // some() checks if at least one element in an array satisfies a given condition. 
        if (sibling !== element && classNames.some(className => sibling.classList.contains(className))) {
            // Found the closest sibling with any of the specified class names
            return sibling;
        }
        sibling = sibling.nextElementSibling;
    }

    // If no matching sibling is found
    return null;
}
