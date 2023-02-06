describe('Display My courses', () => {
    beforeEach(() => {
        cy.login(Cypress.env('username'), Cypress.env('password'))
        cy.turnEditingOn();
    })
    it('Creates assessment and config Frubric', () => {
        cy.visit('/course/modedit.php?add=assign&type=&course=7&section=0&return=0&sr=0');
        // Name the assessment
        cy.get('input#id_name').type('Frubric Testing assessment');

        // Select frubric
        cy.get('select[name=advancedgradingmethod_submissions]')
            .select('Frubric', { force: true }); // Force it otherwise it fails because the parent is hidden.

        // Click on Save and display button
        cy.get('#id_submitbutton').click();


    })

    const addCriterion = (criterionNumber) => {
        cy.get(`#frubric-criteria-NEWID${criterionNumber}`)
            .children()
            .last()
            .trigger('click');

        cy.get('.criterion-header')
            .children()
            .last()
            .type(`Criterion ${criterionNumber}`);

    }
    const AddDescriptorsToLevel = (criterionNumber) => {
        let i = 1;

        while (i < 3) {
            cy.get(`.level-${criterionNumber}.add-level-r`).prev().find('div.add-descriptor').click();
            i++;
        }
        // If i put them together it doesnt work properly
        for (i = 1; i < 3; i++) {
            cy.get(`.level-${criterionNumber}.add-level-r`)
                .prev()
                .find('textarea')
                .last()
                .type(`Level descriptor dummy text ${i}`);
        }
    };

    it('Configure the frubric for the assessment created previously', () => {
        cy.visit('/course/view.php?id=7#section-0');
        // get the last assignment in the section 0. That is the assignment created in the previous test.
        cy.get('div#coursecontentcollapse0')
            .find('.section ')
            .first('ul')
            .children()
            .last()
            .find('a.stretched-link ')
            .click();

        // Click the Advanced grading link from the nav bar
        cy.get('[data-key="advgrading"]').click();
        cy.get('div.actions')
            .children()
            .first()
            .click();

        // Define the frubric
        cy.get('input#id_name')
            .type('Frubric definition test');
        cy.get('div#id_description_editoreditable')
            .find('p')
            .type('Description test');

        cy.get('[data-row-type="criterion-add-level"]')
            .last()
            .find('button')
            .click();

        addCriterion(1);
        const mark = cy.get('[data-row-type="criterion-add-level"]')
            .prev()
            .find('table.level-mark-desc-table')
            .find('textarea.fmark');

        mark.click();

        mark.type('1-1');

        AddDescriptorsToLevel(1);

        // Add a new criterion

        cy.get('#addCriterion').click();

        addCriterion(2);

        cy.get(`.level-${2}.add-level-r`)
            .last()
            .find('button')
            .click();
        cy.get('.level-2').children().find('textarea.fmark').type('2-2');

        AddDescriptorsToLevel(2);

        // Save as draft
        cy.get("#id_savefrubricdraft").click();
    });

})