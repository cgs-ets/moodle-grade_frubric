// Controls the regrading messages.
document.querySelector('.regrade-container').removeAttribute('hidden');

document.getElementById("regradeOptions").addEventListener('change', function (e) {
    document.querySelector('.gradingform_rubric_editform').querySelector('input[name="regrade"]').value = e.target.value;
    document.getElementById("regradeOptions").setAttribute('data-initial-value', e.target.value);
    console.log(e.target.value);
    document.querySelector('input[name="regradeoptionselected"]').value = e.target.value;
});

if (document.getElementById('id_savefrubric').value == 'Continue') {
    document.querySelector('.gradingform_rubric_editform').querySelector('input[name="regrade"]').value = 1;
    if (document.querySelector('input[name="regradeoptionselected"]').value == '') {
        document.querySelector('input[name="regradeoptionselected"]').value = 0;
    }
}