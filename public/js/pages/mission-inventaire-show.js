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
    error: (data) => {
        const msg = data.data.msg;
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
});

const $modalRemoveRefFromMission = $('#modalDeleteRefFromMission');
const $submitRemoveRefFromMission = $('#submitDeleteRefFromMission');
const urlRemoveRefFromMission = Routing.generate('mission_remove_ref', true);

InitModal($modalRemoveRefFromMission, $submitRemoveRefFromMission, urlRemoveRefFromMission, {
    tables: [tableRefArticle]
});

function displayFirstModal({barcodesUL, barcodesToAdd, $modalAddToMission}) {
    $modalAddToMission.modal('show');
    AJAX.route('GET', `add_to_mission_after_validation`, {
        barcodesUL,
        barcodesToAdd
    });
}
