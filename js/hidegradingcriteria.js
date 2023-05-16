// Once the student is graded, dont display the grading criteria in the submission status section

if (document.querySelector('div.graded-form-h') != null) {
    document.querySelector('div.graded-form-h').closest('tr').style.display = 'none';
}