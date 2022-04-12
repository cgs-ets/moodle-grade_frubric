// Controls the regrading messages.


document.querySelector('.regrade_confirm').removeAttribute('hidden');

if (document.getElementById('id_savefrubric').value == 'Continue') {
    document.querySelector('.gradingform_rubric_editform').querySelector('input[name="regrade"]').value = 1;
    
}
