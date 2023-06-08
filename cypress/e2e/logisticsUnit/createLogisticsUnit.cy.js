/*
To run only this file use command : npx cy:run --spec "cypress\e2e\logisticsUnit\createLogisticsUnit.cy.js"
To run all files in this folder use command : npx cy:run --spec "cypress\e2e\logisticsUnit\*"
To run all files in this folder and subfolders use command : npx cy:run --spec "cypress\e2e\**\*"
To run all files use command : npx cy:run
*/
describe('Create incoming logistics unit', () => {

    // run one time before all tests
    before(() => {
        cy.register("test", "Test123456!", "Test123456!");
        cy.login("test",'Test123456!');
    })

    it("openMenu", () => {
        cy.openMenu();
    })

    it("create incoming logistics unit", () => {
        //todo :
    })
})
