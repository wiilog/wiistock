$(function () {
    initDateTimePicker();

    initSearchDate(tableArticle);
    initSearchDate(tableRefArticle);

    $('.select2').select2();

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_INV_SHOW_MISSION);
    $.post(path, params, function (data) {
        displayFiltersSup(data);
    }, 'json');

    InitLocationMissionsDataTable();
});


let mission = $('#missionId').val();
let pathApiArticle = Routing.generate('inv_entry_article_api', {id: mission}, true);
let tableArticleConfig = {
    processing: true,
    serverSide: true,
    order: [['Location', 'desc']],
    ajax: {
        "url": pathApiArticle,
        "type": "POST",
        'dataSrc': function (json) {
            json.data.some((data) => {
                if (data.EmptyLocation) {
                    showBSAlert('Il manque un ou plusieurs emplacements : ils n\'apparaîtront pas sur le nomade.', 'danger');
                }
            });
            return json.data;
        }
    },
    drawConfig: {
        needsSearchOverride: true,
    },
    domConfig: {
        removeInfo: true
    },
    columns: [
        {"data": 'Ref', 'title': 'Reférence'},
        {"data": 'CodeBarre', 'title': 'Code barre'},
        {"data": 'Label', 'title': 'Libellé'},
        {"data": 'UL', 'title': 'Unité Logistique'},
        {"data": 'Location', 'title': 'Emplacement', 'name': 'location'},
        {"data": 'Date', 'title': 'Date de saisie', 'name': 'date'},
        {"data": 'Anomaly', 'title': 'Anomalie', 'name': 'anomaly'},
        {"data": 'QuantiteStock', 'title': 'Quantité en stock', 'name': 'quantitestock'},
        {"data": 'QuantiteComptee', 'title': 'Quantité comptée', 'name': 'quantitecomptee'}
    ],
};
let tableArticle = initDataTable('tableMissionInvArticle', tableArticleConfig);

let pathApiReferenceArticle = Routing.generate('inv_entry_reference_article_api', {id: mission}, true);
let tableRefArticleConfig = {
    processing: true,
    serverSide: true,
    order: [['Location', 'desc']],
    ajax: {
        "url": pathApiReferenceArticle,
        "type": "POST",
        'dataSrc': function (json) {
            json.data.some((data) => {
                if (data.EmptyLocation) {
                    showBSAlert('Il manque un ou plusieurs emplacements : ils n\'apparaîtront pas sur le nomade.', 'danger');
                }
            });
            return json.data;
        }
    },
    drawConfig: {
        needsSearchOverride: true,
    },
    domConfig: {
        removeInfo: true
    },
    columns: [
        {"data": 'Actions', 'name': 'actions', 'title': '', className: 'noVis', orderable: false},
        {"data": 'Ref', 'title': 'Reférence'},
        {"data": 'CodeBarre', 'title': 'Code barre'},
        {"data": 'Label', 'title': 'Libellé'},
        {"data": 'Location', 'title': 'Emplacement', 'name': 'location'},
        {"data": 'Date', 'title': 'Date de saisie', 'name': 'date'},
        {"data": 'Anomaly', 'title': 'Anomalie', 'name': 'anomaly'},
        {"data": 'QuantiteStock', 'title': 'Quantité en stock', 'name': 'quantitestock'},
        {"data": 'QuantiteComptee', 'title': 'Quantité comptée', 'name': 'quantitecomptee'}
    ],
};
let tableRefArticle = initDataTable('tableMissionInvReferenceArticle', tableRefArticleConfig);

let $modalAddToMission = $("#modalAddToMission");
let $submitAddToMission = $("#submitAddToMission");
let urlAddToMission = Routing.generate('add_to_mission', true);
InitModal($modalAddToMission, $submitAddToMission, urlAddToMission, {
    tables: [tableArticle, tableRefArticle],
    success: () => {
        $modalAddToMission.find("input[name=barcodesWithUL]").val(""); //reset of the input hidden
    },
    error: (data) => {
        /* Display of the confirmation modal if the user enters barcodes associated with ULs */
        if (data.data) {
            const msg = data.msg;
            const barcodesUL = data.data.barcodesUL;
            const barcodesToAdd = data.data.barcodesToAdd;

            $modalAddToMission.modal('hide');
            if (barcodesUL) {
                Modal.confirm({
                    title: 'Ajouter les articles',
                    message: msg,
                    validateButton: {
                        color: 'success',
                        label: 'Continuer',
                        click: () => {
                            displayFirstModal({barcodesUL, barcodesToAdd, $modalAddToMission});
                        }
                    },
                });
            }
        }
    }
});

const $modalRemoveRefFromMission = $('#modalDeleteRefFromMission');
const $submitRemoveRefFromMission = $('#submitDeleteRefFromMission');
const urlRemoveRefFromMission = Routing.generate('mission_remove_ref', true);

InitModal($modalRemoveRefFromMission, $submitRemoveRefFromMission, urlRemoveRefFromMission, {
    tables: [tableRefArticle]
});

let modalAddLocationToMission = $("#modalAddLocationToMission");
let submitAddLocationToMission = $("#submitAddLocationToMission");
let urlAddLocationToMission = Routing.generate('add_location_to_mission', true);
InitModal(modalAddLocationToMission, submitAddLocationToMission, urlAddLocationToMission, {
    tables: [tableArticle, tableRefArticle]
});

function displayFirstModal({barcodesUL, barcodesToAdd, $modalAddToMission}) {
    clearModal($modalAddToMission);

    const $barcodesWithUL = $modalAddToMission.find("input[name=barcodesWithUL]");
    $barcodesWithUL.val(barcodesUL);

    const $articlesInput = $modalAddToMission.find("input[name=articles]");
    $articlesInput.val(barcodesToAdd.join(' '));

    let errorMessage = "</ul><span class=\"text-danger pl-2\">L'ensemble des articles des unités logistiques associées aux articles ci-dessous va être ajouté à la mission d'inventaire :</span>";
    barcodesUL.forEach(function(barcode){
        errorMessage += "<li class=\"text-danger list-group-item\">" + barcode + "</li>";
    });

    displayFormErrors($modalAddToMission, {
        errorMessages: [errorMessage],
        keepModal: true,
    });
    $modalAddToMission.modal('show');
}

function clearMissionListSearching() {
    const $logisticUnitsContainer = $('.logistic-units-container');
    const $searchInput = $logisticUnitsContainer
        .closest('.content')
        .find('input[type=search]');
    $searchInput.val(null);
}


function InitLocationMissionsDataTable() {
    let pathLocationMission = Routing.generate('mission_location_ref_api', {mission: mission}, true);

    let tableLocationMissionsConfig = {
        lengthMenu: [5, 10, 25],
        processing: true,
        serverSide: true,
        paging: true,
        ajax: {
            url: pathLocationMission,
            type: "POST",
        },
        columns: [
            {data: 'zone', name: 'zone', title: 'Zone'},
            {data: 'location', name: 'location', title: 'Emplacement'},
            {data: 'reference', name: 'reference', title: 'Référence'},
            {data: 'scanDate', name: 'scanDate', title: 'Date de scan'},
            {data: 'operator', name: 'operator', title: 'Opérateur'},
            {data: 'percentage', name: 'percentage', title: 'Pourcentage'},
        ],
        order: [
            ['percentage', 'desc'],
        ],
        rowConfig: {
            needsRowClickAction: true,
            needsColor: true,
            dataToCheck: 'urgence',
            color: 'danger',
        },
    };
    return initDataTable('tableLocationMissions', tableLocationMissionsConfig);
}
