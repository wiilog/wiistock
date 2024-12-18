import routes, {interceptRoute} from "/cypress/support/utils/routes";
const user = Cypress.config('user');
import {uncaughtException} from "/cypress/support/utils";

describe('Add and edit components in Referentiel > Projet', () => {
    beforeEach(() => {
        interceptRoute(routes.project_api);
        interceptRoute(routes.project_new);
        interceptRoute(routes.project_edit);

        cy.login(user);
        cy.visit('/');
        cy.navigateInNavMenu('referentiel', 'project_index');
        uncaughtException();
    })

    it('should add a new project', () => {

        const newProject = {
            code: 'GAZO',
            projectManager: 'Admin',
        }
        const propertiesMap = {
            'Code': 'code',
            'Chef de projet': 'projectManager',
        }
        const selectorModal = '#modalNewProject';
        // open modal
        cy.openModal(selectorModal, 'code','[data-target="#modalNewProject"]' );

        cy.get(selectorModal).should('be.visible', {timeout: 8000}).then(() => {

            // edit values (let .wait() to wait for input be selected i don't know why it doesn't work without it)
            cy.get(`${selectorModal} input[name=code]`).wait(500).type(newProject.code);

            cy.select2Ajax('projectManager', newProject.projectManager);

            // submit form
            cy.closeAndVerifyModal(selectorModal, undefined, 'project_new', true);
        })
        // check datatable is reloaded
        cy.wait('@project_api');

        // check datatable after edit
        cy.checkDataInDatatable(newProject, 'code', 'projectTable_id', propertiesMap);
    })

    it('should edit a project', () => {

        const projectToEdit = ['PROJET']
        const newProjects = [{
            code: 'RACLETTE',
            projectManager: 'Lambda',
        }]
        const propertiesMap = {
            'Code': 'code',
            'Chef de projet': 'projectManager',
        }
        const selectorModal = '#modalEditProject';
        cy.wait('@project_api');

        projectToEdit.forEach((projectToEditName, index) => {
            cy.clickOnRowInDatatable('projectTable_id', projectToEditName);

            cy.get(selectorModal).should('be.visible');

            // edit values
            cy.typeInModalInputs(selectorModal, newProjects[index], ['projectManager']);
            // remove previous value
            cy.clearSelect2AjaxValues(`${selectorModal} [name="projectManager"]`);
            // add new value
            cy.select2Ajax('projectManager', newProjects[index].projectManager, 'modalEditProject')

            // submit form
            cy.closeAndVerifyModal(selectorModal, 'submitEditProject', 'project_edit');
            cy.wait('@project_api');

            cy.checkDataInDatatable(newProjects[index], 'code', 'projectTable_id', propertiesMap)
        })
    })
})
