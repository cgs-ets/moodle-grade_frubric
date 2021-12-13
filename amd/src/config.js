define([], function () {
    window.requirejs.config({
        paths: {
           "domjson" : M.cfg.wwwroot + '/grade/grading/form/frubric/js/domjson/domJSON.min'
        },
        shim: {
           'domjson': {exports:'domjson'}
        }
    });
});