Cypress.Commands.add('navigateInNavMenu', (menu, subMenu) => {
    cy
        .get('nav')
        .click()
        .get('.dropdown-menu')
        .should('be.visible')
        .get(`[data-cy-container="${menu}"]`)
        .click()
        .then((element) => {
            if (subMenu === undefined) {
                return;
            }
            cy.get(`nav [data-cy-icon="${menu}"`)
                .parents('.dropdown-item-sub')
                .siblings('.dropdown-menu.dropdown-menu-sub')
                .should('be.visible')
                .get(`.dropdown-item[data-cy-nav-item="${subMenu}"]`).first()
                .click();
        });
});

/**
 * Allows us the possibility to make a request from the + button.
 * @param request The name of the request item.
 */
Cypress.Commands.add('navigateInQuickMoreMenu', (request) => {
    cy
        .get('.quick-plus')
        .click()
        .get('.dropdown-menu')
        .should('be.visible')
        .get(`[data-cy-request-item="${request}"]`)
        .click()
        .wait(1000);
});

/**
 * Allows us to fill all types of free fields.
 * @param freeFields Free fields to fill with the label of the input as key and the value as value.
 */
Cypress.Commands.add('fillFreeFields', (freeFields) => {
    Object.keys(freeFields).forEach((key) => {
        const value = freeFields[key];

        cy.contains('.free-fields-container label', key).then($label => {
            const $inputOrSelect = $label.find('input, select, textarea');

            if ($inputOrSelect.length > 0) {
                const elementType = $inputOrSelect[0].tagName.toLowerCase();
                switch (elementType) {
                    case 'input':
                        fillInputsRadioCheckbox($inputOrSelect, value);
                        break;
                    case 'select':
                        fillSelect($inputOrSelect, value);
                        break;
                    case 'textarea':
                        fillTextarea($inputOrSelect, value);
                        break;
                    default:
                        cy.log(`Type d'élément non supporté: ${elementType}`);
                }
            } else {
                cy.log(`Aucun champ trouvé pour le label: ${key}`);
            }
        });
    });
});

/**
 * Fill an input element.
 * @param $input Input to fill.
 * @param value Value to put in input.
 */
function fillInputsRadioCheckbox($input, value){
    const type = $input.attr('type');
    if (type === 'checkbox' || type === 'radio') {
        cy.wrap($input).each(($input) => {
            cy.get(`label[for=${$input[0].id}] > span`).then(($inputLabel) => {
                if ($inputLabel.text().trim() === value) {
                    cy.wrap($input).check({ force: true });
                }
            });
        });
    } else {
        cy.wrap($input).type(value, { force: true });
    }
}

/**
 * Fill a select element.
 * @param $select Select to fill.
 * @param value Value to put in the select.
 */
function fillSelect($select, value){
    if ($select.hasClass('list-multiple')) {
        cy.select2($select.attr('name'), value);
    } else {
        cy.select2Ajax($select.attr('name'), value, '', true, '/select/*', true);
    }
}

/**
 * Fill a textarea element.
 * @param $textarea Textarea to fill.
 * @param value Value to put in the textarea.
 */
function fillTextarea($textarea, value){
    cy.wrap($textarea).type(value, { force: true });
}

/**
 * Allows us to fill a comment field.
 * @param selector Selector of the comments.
 * @param comment Comment to type in comment field.
 */
Cypress.Commands.add('fillComment', (selector, comment) => {
    cy
        .get(selector)
        .click()
        .clear()
        .type(comment);
})

/**
 * Allows us to fill a file input.
 * @param path path of the file.
 */
Cypress.Commands.add('fillFileInput', (path) => {
    cy.get('input[type=file]').selectFile(path, {force: true});
})

/**
 * Allows us to verify the comment in the show page.
 * @param comment Comment expected.
 */
Cypress.Commands.add('verifyComment', (comment) => {
    cy
        .get('.comment-container')
        .find('p')
        .invoke('text')
        .then((text) => {
            expect(text).to.equal(comment)
        });
})

/**
 * Allows us to verify the status in the show page.
 * @param status Status expected.
 */
Cypress.Commands.add('verifyStatus', (status) => {
    cy
        .get('.timeline')
        .find('strong')
        .invoke('text')
        .then((text) => {
            expect(text).to.equal(status)
        });
})

/**
 * Allows us to search in a datatable.
 * @param selector Id of the div who contains the input.
 * @param value Value to search.
 */
Cypress.Commands.add('searchInDatatable', (selector, value) => {
    cy
        .get(selector).should('be.visible', {timeout: 8000}).then((div) => {
            cy
                .wrap(div)
                .find('input')
                .type(`${value}{enter}`)
                .wait(1000);
    });
})

/**
 * Allows us to use the dropdown dropright button to delete or edit in show page.
 * @param action The action you want to do.
 */
Cypress.Commands.add('dropdownDroprightAction', (action) => {
    cy
        .get('.dropright.dropdown')
        .click()
        .find(`span[title=${action}]`)
        .click()
        .wait(200);
})

/**
 * Allows us to check if the datatable is empty.
 * @param selector The selector of the datatable.
 */
Cypress.Commands.add('checkDatatableIsEmpty', (selector) => {
    cy
        .get(selector)
        .find('.dataTables_empty')
        .should("be.visible");
})

/**
 * Allows us to confirm a modal like the delete modal.
 */
Cypress.Commands.add('confirmModal', () => {
    cy
        .get("#confirmation-modal")
        .find("button[name=request]")
        .click();
})
