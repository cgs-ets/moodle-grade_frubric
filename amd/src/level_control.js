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

    function init(id) {
      Log.debug('Level control...');
      Log.debug(id);
      const mode = FeditorHelper.getMode();
      const level  = document.getElementById(id);
      let control = new LevelControl(level, mode, id);
      control.main();

    }


 /**
  *
  * @param {*} level
  * @param {*} mode
  */
    function LevelControl(level, mode, id) {
      const self = this;
      self.level = level;
      self.mode = mode;
      self.id = id;
      self.LEVEL_DESCRIPTOR_INPUT = 'gradingform_frubric/level_descriptor_input';
    }

    /**
     * Run the controller.
     */
    LevelControl.prototype.main = function () {
      let self = this;
      Log.debug("main");
  

      if (self.level != null) {
        self.setupEvents(self.level);
      }

    };

    LevelControl.prototype.setupEvents = function (level) {
      let self = this;
      Log.debug("Level control: setupEvents");
    
      const [del, markandesc] = level.children;
      const markdesctable = markandesc.querySelector('.level-mark-desc-table');
      const[marktd, descriptortd] = markdesctable.rows[0].children;
     
      descriptortd.querySelector('.add-desc-btn').addEventListener('click', self.addDescriptor.bind(self));
      del.addEventListener('click', self.deleteLevel.bind(self));

    };

    LevelControl.prototype.addDescriptor = function (e) {
      const self = this;
      Log.debug(e);
      e.preventDefault();

      // Get the standard-desc-container  that contains the descritors 
      const descriptorContainer = FeditorHelper.getPreviousElement(e.target.parentNode, '.standard-desc-container');
      Log.debug("En test..");
      Log.debug(descriptorContainer);
      const context = {id: self.id};

      Templates.render(self.LEVEL_DESCRIPTOR_INPUT, context)
        .done(function (html, js) {
          descriptorContainer.insertAdjacentHTML('beforeend', html);
          const checkboxcontainer = descriptorContainer.lastChild;
          const action = descriptorContainer.lastChild.querySelector('.action-el');
          action.addEventListener('click', self.deleteDescriptor.bind(this, descriptorContainer, checkboxcontainer));
        })
        .fail(function (ex) {
          Log.debug("error...");
        });

    }
  
    LevelControl.prototype.deleteDescriptor = function (descriptorContainer, checkboxcontainer) {
      Log.debug('deleteDescriptor');
      Log.debug(checkboxcontainer);
      Log.debug(descriptorContainer);
      descriptorContainer.removeChild(checkboxcontainer);
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
                Log.debug("en el if");
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
      Log.debug(e);
      Log.debug(this);
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
      // Get the criterion row this level belongs to
      const criterionheader = FeditorHelper.getPreviousElement(levelrow, '.criterion-header');
      let leveldbids = criterionheader.getAttribute('data-criterion-levels').length > 0 ? JSON.parse(criterionheader.getAttribute('data-criterion-levels')) : []; // Get the dbids
    
      const criteriaJSON = FeditorHelper.getCriteriaJSON();
      const rowIndex = e.target.parentNode.parentNode.parentNode.rowIndex;
    
      let levelid = id + '_' + rowIndex;
 
      if(leveldbids.length > 0) {
        levelid = (leveldbids[rowIndex - 1] != undefined)  ? `${id}_${leveldbids[rowIndex - 1]}` : levelid; // The criterion already exists but this is a new level, not saved in the DB yet.
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

    LevelControl.prototype.focusoutHandlerDisabled = function(e) {
      Log.debug('focusoutHandlerDisabled');
      e.target.setAttribute('disabled', true);
    };


    return {
      init: init
    };
  });