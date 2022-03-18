define(['jquery', 'gradingform_frubric/feditor_helper', 'core/templates'],
    function ($, FeditorHelper, Templates) {
        'use strict';

        function init(mode) {
            Y.log("Rerender control...");
            let control = new RerenderControl(mode);
            control.main();

        }

        function RerenderControl(mode) {
            const self = this;
            self.FEDITOR = 'gradingform_frubric/frubriceditor';
            self.mode = mode;

        }

        RerenderControl.prototype.main = function () {
            var self = this;

            // if (self.mode == 'regrade') {
            //     self.rerendercreatedRegrade();
            // }

            // if (self.mode == 'rerendercreate') {
            //     self.rerenderCreate();
            // }

            // if (self.mode == 'rerenderupdate') {
            //     self.rerenderUpdate();
            // }

            // if (self.mode == 'edit') {
            //    // self.rerenderUpdate();
            // }

            
            // if (document.querySelector('input[name="forrerender"]').value != '') {
            //     const data = JSON.parse(document.querySelector('input[name="forrerender"]').value);
            //     Templates.render(self.FEDITOR, data)
            //     .done(function (html, js) {
            //         Y.log(html);
            //         // Replace with editor with previous values
            //         const editor = document.getElementById('cont');
            //         Templates.replaceNode(editor, html, js);
    
            //     }).fail(function (ex) {
            //         Y.log("error...");
            //         Y.log(ex);
            //     });
            // }


        }

        /**
         * Called when the rubric is already in used  students
         * where graded and the rubric is going to be updated.
         */
        RerenderControl.prototype.rerendercreatedRegrade = function () {
            //Y.log("render regrade...");
            var self = this;

            const context = FeditorHelper.getCriteriaJSON();
            const definitionid = document.getElementById("cont").getAttribute("data-definition-id");

            const data = {
                edit: 1,
                mode: 'edit',
                rerender: 1,
                definitionid: definitionid,
                regradealert: 1,
                "criteria": []
            }

            context.forEach(function (element) {
                data.criteria.push(element);
            });


            Templates.render(self.FEDITOR, data)
                .done(function (html, js) {
                    Y.log(html);
                    // Replace with editor with previous values
                    const editor = document.getElementById('cont');
                    Templates.replaceNode(editor, html, js);

                }).fail(function (ex) {
                    Y.log("error...");
                    Y.log(ex);
                });
            document.querySelector('input[name="regrade"]').value = '-1'; // Change the value to allow submiting
        }


        RerenderControl.prototype.rerenderCreate = function () {
            Y.log('rerenderCreate ....');
            const context = FeditorHelper.getCriteriaJSON();
            const definitionid = document.getElementById("cont").getAttribute("data-definition-id");


            const data = {
                // edit: 1,
                mode:'create',
                rerender: 1,
                fromrr: 1,
                definitionid: definitionid,
                fromrerenderupdate: 1,
                "criteria": []
            }

            context.forEach(function (element) {
                data.criteria.push(element);
            });
            // Y.log(data)
            Templates.render(self.FEDITOR, data)
                .done(function (html, js) {
                    Y.log(html);
                    // Replace with editor with previous values
                    const editor = document.getElementById('cont');
                    Templates.replaceNode(editor, html, js);

                }).fail(function (ex) {
                    Y.log("error...");
                    Y.log(ex);
                });
        }

        RerenderControl.prototype.rerenderUpdate = function () {
            var self = this;
            Y.log('rerenderUpdate ....');
            const context = FeditorHelper.getCriteriaJSON();
            const definitionid = document.getElementById("cont").getAttribute("data-definition-id");

            // Y.log(context);

            const data = {
                // edit: 1,
                mode: 'edit',
                rerender: 1,
                fromrr: 1,
                definitionid: definitionid,
                fromrerenderupdate: 1,
                "criteria": []
            }

            context.forEach(function (element) {
                data.criteria.push(element);
            });
            Y.log(data);
            Templates.render(self.FEDITOR, data)
                .done(function (html, js) {
                    // Y.log(html);
                    // Replace with editor with previous values
                    const editor = document.getElementById('cont');
                    Templates.replaceNode(editor, html, js);

                }).fail(function (ex) {
                    Y.log("error...");
                    Y.log(ex);
                });
        }

        return {
            init: init
        };
    });