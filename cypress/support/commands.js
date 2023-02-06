// ***********************************************
// This example commands.js shows you how to
// create various custom commands and overwrite
// existing commands.
//
// For more comprehensive examples of custom
// commands please read more here:
// https://on.cypress.io/custom-commands
// ***********************************************
//
//
// -- This is a parent command --
// Cypress.Commands.add('login', (email, password) => { ... })
//
//
// -- This is a child command --
// Cypress.Commands.add('drag', { prevSubject: 'element'}, (subject, options) => { ... })
//
//
// -- This is a dual command --
// Cypress.Commands.add('dismiss', { prevSubject: 'optional'}, (subject, options) => { ... })
//
//
// -- This will overwrite an existing command --
// Cypress.Commands.overwrite('visit', (originalFn, url, options) => { ... })

Cypress.Commands.add('login', (username, password) => {
    cy.session([username, password], () => {
        cy.visit('/login/index.php');
        cy.get('input#username[name=username]').type(username);
        cy.get('input#password[name=password]').type(password);
        cy.get('button#loginbtn').click();
    })
})

Cypress.Commands.add('turnEditingOn', () => {
    cy.visit('/course/view.php?id=7');
    cy.get('input[name=setmode]').check();
    cy.get('form.editmode-switch-form').submit()
})
