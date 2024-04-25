import {ERROR} from "@app/flash";
import {POST} from "@app/ajax";

let tableLocationMission;

global.openShowScannedArticlesModal = openShowScannedArticlesModal;
global.onOpenModalAddLocationAndZone = onOpenModalAddLocationAndZone;

$(function () {
    $(`.select2`).select2();
    const missionId = $(`#missionId`).val();
    const typeLocation = $(`#typeLocation`).val();
    const locationsAlreadyAdded = Boolean($(`#locationsAlreadyAdded`).val());
    const $addInventoryLocationContainer = $(`.add-inventory-location-container`);

    initDateTimePicker();
    getUserFiltersByPage(PAGE_INV_SHOW_MISSION);

    initFormAddInventoryLocations($addInventoryLocationContainer);
    if (typeLocation && !locationsAlreadyAdded) {
        const $tableLocations = $addInventoryLocationContainer.find(`table`);
        onOpenModalAddLocationAndZone($tableLocations, missionId);
    }

    $(`#modalShowScannedArticles`).on(`hidden.bs.modal`, function () {
        $(this).find(`.table`).DataTable().clear().destroy();
    });

    initLocationMissionsDataTable(missionId);
    const [tableArticle, tableRefArticle] = initInventoryEntryDatatables(missionId);

    initModals(tableArticle, tableRefArticle);
});

function initModals(tableArticle, tableRefArticle) {
    const $modalAddToMission = $(`#modalAddToMission`);
    const $submitAddToMission = $(`#submitAddToMission`);
    const urlAddToMission = Routing.generate(`add_to_mission`, true);
    InitModal($modalAddToMission, $submitAddToMission, urlAddToMission, {
        tables: [tableArticle, tableRefArticle],
        success: () => {
            $modalAddToMission.find(`input[name=barcodesWithUL]`).val(``); //reset of the input hidden
        },
        error: ({msg, data}) => {
            /* Display of the confirmation modal if the user enters barcodes associated with ULs */
            if (data) {
                const {barcodesUL, barcodesToAdd} = data;

                $modalAddToMission.modal(`hide`);
                if (barcodesUL) {
                    Modal.confirm({
                        title: `Ajouter les articles`,
                        message: msg,
                        validateButton: {
                            color: `success`,
                            label: `Continuer`,
                            click: () => {
                                displayFirstModal({barcodesUL, barcodesToAdd, $modalAddToMission});
                            },
                        },
                    });
                }
            }
        }
    });

    const $modalRemoveRefFromMission = $(`#modalDeleteRefFromMission`);
    const $submitRemoveRefFromMission = $(`#submitDeleteRefFromMission`);
    const urlRemoveRefFromMission = Routing.generate(`mission_remove_ref`, true);
    InitModal($modalRemoveRefFromMission, $submitRemoveRefFromMission, urlRemoveRefFromMission, {
        tables: [tableRefArticle],
    });

    const modalAddLocationToMission = $(`#modalAddLocationToMission`);
    const submitAddLocationToMission = $(`#submitAddLocationToMission`);
    const urlAddLocationToMission = Routing.generate(`add_location_to_mission`, true);
    InitModal(modalAddLocationToMission, submitAddLocationToMission, urlAddLocationToMission, {
        tables: [tableArticle, tableRefArticle],
    });
}

function initInventoryEntryDatatables(missionId) {
    const paths = {
        tableMissionInvArticle: `inv_entry_article_api`,
        tableMissionInvReferenceArticle: `inv_entry_reference_article_api`,
    };

    let tables = [];
    for (const [selector, path] of Object.entries(paths)) {
        const table = initDataTable(selector, {
            processing: true,
            serverSide: true,
            order: [[`location`, `desc`]],
            ajax: {
                url: Routing.generate(path, {id: missionId}, true),
                type: POST,
                dataSrc: function (json) {
                    json.data.some(({emptyLocation}) => {
                        if (emptyLocation) {
                            Flash.add(ERROR, `Il manque un ou plusieurs emplacements, ils n'apparaîtront pas sur le nomade.`);
                        }
                    });

                    return json.data;
                }
            },
            domConfig: {
                removeInfo: true,
            },
            columns: [
                {data: `reference`, title: `Reférence`},
                {data: `barcode`, title: `Code barre`},
                {data: `label`, title: `Libellé`},
                ...(selector === `tableMissionInvArticle`
                    ? [{data: `logisticUnit`, title: `Unité Logistique`}]
                    : []),
                {data: `location`, title: `Emplacement`},
                {data: `date`, title: `Date de saisie`},
                {data: `anomaly`, title: `Anomalie`},
                {data: `stockQuantity`, title: `Quantité en stock`},
                {data: `countedQuantity`, title: `Quantité comptée`},
            ],
        });

        initSearchDate(table);

        tables.push(table);
    }

    return tables;
}


function displayFirstModal({barcodesUL, barcodesToAdd, $modalAddToMission}) {
    clearModal($modalAddToMission);

    const $barcodesWithUL = $modalAddToMission.find(`input[name=barcodesWithUL]`);
    $barcodesWithUL.val(barcodesUL);

    const $articlesInput = $modalAddToMission.find(`input[name=articles]`);
    $articlesInput.val(barcodesToAdd.join(` `));

    let errorMessage = `</ul><span class="text-danger pl-2">L'ensemble des articles des unités logistiques associées aux articles ci-dessous va être ajouté à la mission d'inventaire :</span>`;
    barcodesUL.forEach(function (barcode) {
        errorMessage += `<li class="text-danger list-group-item">${barcode}</li>`;
    });

    displayFormErrors($modalAddToMission, {
        errorMessages: [errorMessage],
        keepModal: true,
    });
    $modalAddToMission.modal(`show`);
}

function initLocationMissionsDataTable(missionId) {
    const tableLocationMissionsConfig = {
        lengthMenu: [5, 10, 25],
        processing: true,
        serverSide: true,
        paging: true,
        ajax: {
            url: Routing.generate(`mission_location_ref_api`, {mission: missionId}, true),
            type: POST,
        },
        columns: [
            {data: `actions`, title: ``, className: `noVis`, orderable: false},
            {data: `zone`, title: `Zone`},
            {data: `location`, title: `Emplacement`},
            {data: `date`, title: `Date de scan`},
            {data: `operator`, title: `Opérateur`},
            {data: `percentage`, title: `Pourcentage`},
        ],
        order: [
            [`percentage`, `asc`],
        ],
        rowConfig: {
            needsRowClickAction: true,
        },
    };
    tableLocationMission = initDataTable(`tableLocationMissions`, tableLocationMissionsConfig);
}

function onOpenModalAddLocationAndZone(tableLocations, missionId) {
    const $modalAddLocationAndZoneToMission = $(`#modalAddLocationAndZoneToMission`);

    Form.create($modalAddLocationAndZoneToMission)
        .onSubmit(() => {
            wrapLoadingOnActionButton($modalAddLocationAndZoneToMission.find(`button[type=submit]`), () => {
                return AJAX.route(POST, `add_locations_or_zones_to_mission`, {
                    mission: missionId,
                    locations: tableLocations.DataTable().column(0).data().toArray()
                })
                    .json()
                    .then((response) => {
                        if (response.success) {
                            $modalAddLocationAndZoneToMission.modal(`hide`);
                            tableLocationMission.ajax.reload();
                        }
                    });
            });
        });

    $modalAddLocationAndZoneToMission.modal(`show`);
}

function openShowScannedArticlesModal($button) {
    const locationLine = $button.data(`id`);
    const $modalShowScannedArticles = $(`#modalShowScannedArticles`);

    $modalShowScannedArticles.modal(`show`);

    const tableScannedArticlesConfig = {
        lengthMenu: [5, 10, 25],
        processing: true,
        paging: true,
        ajax: {
            url: Routing.generate(`mission_location_art_api`, {locationLine}, true),
            type: POST,
        },
        columns: [
            {data: `reference`, title: `Référence`, orderable: false},
            {data: `barcode`, title: `Article`, orderable: false},
            {data: `RFIDtag`, title: `Tag RFID`, orderable: false},
        ],
        order: [
            [`reference`, `asc`],
        ],
    };

    initDataTable($modalShowScannedArticles.find(`.table`), tableScannedArticlesConfig);
}
