const user = {
    email: 'Test@test.fr',
    password: 'Test123456!',
}
describe('Edit and save components in Stock > Articles settings', () => {
    const settingsItemName = 'types_champs_libres';
    const tableName = 'article';
    beforeEach(() => {
        cy.login(user.email, user.password);
        cy.openSettingsItem('articles');
    })

    it('should add a new type in Stock > Articles settings', () => {
        cy.addTypeInSettings(settingsItemName);
    })

    it('should edit the created type in Stock > Articles settings', () => {
        cy.editTypeInSettings(settingsItemName);
    })

    it('should add a new free field in Stock > Articles settings', () => {
        cy.addFreeFieldInSettings(settingsItemName);
    })

    it('should edit the created free field in Stock > Articles settings', () => {
        cy.editFreeFieldInSettings(settingsItemName);
    })

    it('should uncheck all fixed field in Stock > Articles settings', () => {
        cy.uncheckAllFixedFieldInSettings(tableName);
    })

    it('should check all fixed field in Stock > Articles settings', () => {
        cy.checkAllFixedFieldInSettings(tableName);
    })
})

describe('Edit and save components in Stock > Réceptions settings', () => {
    const settingsItemName = 'champs_libres';
    const tableName = 'reception';
    beforeEach(() => {
        cy.login(user.email, user.password);
        cy.openSettingsItem('receptions');
    })

    it('should add a new free field in Stock > Réceptions settings', () => {
        cy.addFreeFieldInSettings(settingsItemName);
    })

    it('should edit the created free field in Stock > Réceptions settings', () => {
        cy.editFreeFieldInSettings(settingsItemName);
    })

    it('should uncheck all fixed field in Stock > Réceptions settings', () => {
        cy.uncheckAllFixedFieldInSettings(tableName);
    })

    it('should check all fixed field in Stock > Réceptions settings', () => {
        cy.checkAllFixedFieldInSettings(tableName);
    })
})

describe('Edit and save components in Trace > Acheminements settings', () => {
    const settingsItemName = 'types_champs_libres'
    const tableName = 'dispatch';
    beforeEach(() => {
        cy.login(user.email, user.password);
        cy.openSettingsItem('acheminements');
    })

    it('should add a new type in Trace > Acheminements settings', () => {
        cy.addTypeInSettings(settingsItemName);
    })

    it('should edit the created type in Trace > Acheminements settings', () => {
        cy.editTypeInSettings(settingsItemName);
    })

    it('should add a new free field in Trace > Acheminements settings', () => {
        cy.addFreeFieldInSettings(settingsItemName);
    })

    it('should edit the created free field in Trace > Acheminements settings', () => {
        cy.editFreeFieldInSettings(settingsItemName);
    })

    it('should uncheck all fixed field in Trace > Acheminements settings', () => {
        cy.uncheckAllFixedFieldInSettings(tableName);
    })

    it('should check all fixed field in Trace > Acheminements settings', () => {
        cy.checkAllFixedFieldInSettings(tableName);
    })
})

describe('Edit and save components in Trace > Arrivages UL settings', () => {
    const settingsItemName = 'types_champs_libres';
    const tableName = 'arrival';
    beforeEach(() => {
        cy.login(user.email, user.password);
        cy.openSettingsItem('arrivages');
    })

    it('should add a new type in Trace > Arrivages UL settings', () => {
        cy.addTypeInSettings(settingsItemName);
    })

    it('should edit the created type in Trace > Arrivages UL settings', () => {
        cy.editTypeInSettings(settingsItemName);
    })

    it('should add a new free field in Trace > Arrivages UL settings', () => {
        cy.addFreeFieldInSettings(settingsItemName);
    })

    it('should edit the created free field in Trace > Arrivages UL settings', () => {
        cy.editFreeFieldInSettings(settingsItemName);
    })

    it('should uncheck all fixed field in Trace > Arrivages UL settings', () => {
        cy.uncheckAllFixedFieldInSettings(tableName);
    })

    it('should check all fixed field in Trace > Arrivages UL settings', () => {
        cy.checkAllFixedFieldInSettings(tableName);
    })
})

describe('Edit and save components in Trace > Arrivages camion settings', () => {
    const tableName = 'truck-arrival';
    beforeEach(() => {
        cy.login(user.email, user.password);
        cy.openSettingsItem('arrivages_camion');
    })

    it('should uncheck all fixed field in Trace > Arrivages camion settings', () => {
        cy.uncheckAllFixedFieldInSettings(tableName);
    })

    it('should check all fixed field in Trace > Arrivages camion settings', () => {
        cy.checkAllFixedFieldInSettings(tableName);
    })
})

describe('Edit and save components in Trace > Mouvements settings', () => {
    const settingsItemName = 'champs_libres';
    beforeEach(() => {
        cy.login(user.email, user.password);
        cy.openSettingsItem('mouvements');
    })

    it('should add a new free field in Trace > Mouvements settings', () => {
        cy.addFreeFieldInSettings(settingsItemName);
    })

    it('should edit the created free field in Trace > Mouvements settings', () => {
        cy.editFreeFieldInSettings(settingsItemName);
    })
})

describe('Edit and save components in Trace > Services settings', () => {
    const settingsItemName = 'types_champs_libres';
    const tableName = 'handling';
    beforeEach(() => {
        cy.login(user.email, user.password);
        cy.openSettingsItem('services');
    })

    it('should add a new type in Trace > Services settings', () => {
        cy.addTypeInSettings(settingsItemName);
    })

    it('should edit the created type in Trace > Services settings', () => {
        cy.editTypeInSettings(settingsItemName);
    })

    it('should add a new free field in Trace > Services settings', () => {
        cy.addFreeFieldInSettings(settingsItemName);
    })

    it('should edit the created free field in Trace > Services settings', () => {
        cy.editFreeFieldInSettings(settingsItemName);
    })

    it('should uncheck all fixed field in Trace > Services settings', () => {
        cy.uncheckAllFixedFieldInSettings(tableName);
    })

    it('should check all fixed field in Trace > Services settings', () => {
        cy.checkAllFixedFieldInSettings(tableName);
    })
})

describe('Edit and save components in Trace > Urgences settings', () => {
    const tableName = 'emergencies';
    beforeEach(() => {
        cy.login(user.email, user.password);
        cy.openSettingsItem('urgences');
    })

    it('should uncheck all fixed field in Trace > Urgences settings', () => {
        cy.uncheckAllFixedFieldInSettings(tableName);
    })

    it('should check all fixed field in Trace > Urgences settings', () => {
        cy.checkAllFixedFieldInSettings(tableName);
    })
})

describe('Edit and save components in Track > Demandes settings', () => {
    const settingsItemNameForLivraisons = 'types_champs_libres_livraisons'
    const settingsItemNameForCollectes = 'types_champs_libres_collectes';
    beforeEach(() => {
        cy.login(user.email, user.password);
        cy.openSettingsItem('demande_transport');
    })

    it('should add a new type in Track > Demandes > Livraisons settings', () => {
        cy.addTypeInSettings(settingsItemNameForLivraisons);
    })

    it('should edit the created type in Track > Demandes > Livraisons settings', () => {
        cy.editTypeInSettings(settingsItemNameForLivraisons);
    })

    it('should add a new free field in Track > Demandes > Livraisons settings', () => {
        cy.addFreeFieldInSettings(settingsItemNameForLivraisons);
    })

    it('should edit the created free field in Track > Demandes > Livraisons settings', () => {
        cy.editFreeFieldInSettings(settingsItemNameForLivraisons);
    })

    it('should add a new type in Track > Demandes > Collectes settings', () => {
        cy.addTypeInSettings(settingsItemNameForCollectes);
    })
    it('should edit the created type in Track > Demandes > Collectes settings', () => {
        cy.editTypeInSettings(settingsItemNameForCollectes);
    })

    it('should add a new free field in Track > Demandes > Collectes settings', () => {
        cy.addFreeFieldInSettings(settingsItemNameForCollectes);
    })

    it('should edit the created free field in Track > Demandes > Collectes settings', () => {
        cy.editFreeFieldInSettings(settingsItemNameForCollectes);
    })
})

describe('Edit and save components in IoT > Types et champs libres settings', () => {
    const settingsItemName = 'types_champs_libres';
    beforeEach(() => {
        cy.login(user.email, user.password);
        cy.openSettingsItem('types_champs_libres');
    })

    it('should add a new free field in IoT > Types et champs libres settings', () => {
        cy.addFreeFieldInSettings(settingsItemName);
    })

    it('should edit the created free field in IoT > Types et champs libres settings', () => {
        cy.editFreeFieldInSettings(settingsItemName);
    })
})





