$(function() {
    initDateTimePicker();

    const {tableArticle, tableRefArticle} = initPageElements();

    initSearchDate(tableArticle);
    initSearchDate(tableRefArticle);

    $('.select2').select2();

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_INV_SHOW_MISSION);
    $.post(path, params, function(data) {
        displayFiltersSup(data);
    }, 'json');
});


function initPageElements() {
    let mission = $('#missionId').val();
    let pathApiArticle = Routing.generate('inv_entry_article_api', { id: mission}, true);
    let tableArticleConfig = {
        processing: true,
        serverSide: true,
        order: [[3, 'desc']],
        scrollX: true,
        ajax:{
            "url": pathApiArticle,
            "type": "POST",
            'dataSrc': function (json) {
                json.data.some((data) => {
                    if (data.EmptyLocation) {
                        alertErrorMsg('Il manque un ou plusieurs emplacements : ils n\'apparaîtront pas sur le nomade.');
                    }
                });
                return json.data;
            }
        },
        drawConfig: {
            needsSearchOverride: true,
        },
        columns:[
            { "data": 'Ref', 'title' : 'Reférence' },
            { "data": 'Label', 'title' : 'Libellé' },
            { "data": 'Location', 'title' : 'Emplacement', 'name': 'location' },
            { "data": 'Date', 'title' : 'Date de saisie', 'name': 'date' },
            { "data": 'Anomaly', 'title' : 'Anomalie', 'name' : 'anomaly'  }
        ],
    };
    let tableArticle = initDataTable('tableMissionInvArticle', tableArticleConfig);

    let pathApiReferenceArticle = Routing.generate('inv_entry_reference_article_api', { id: mission}, true);
    let tableRefArticleConfig = {
        processing: true,
        serverSide: true,
        order: [[3, 'desc']],
        scrollX: true,
        ajax:{
            "url": pathApiReferenceArticle,
            "type": "POST",
            'dataSrc': function (json) {
                json.data.some((data) => {
                    if (data.EmptyLocation) {
                        alertErrorMsg('Il manque un ou plusieurs emplacements : ils n\'apparaîtront pas sur le nomade.');
                    }
                });
                return json.data;
            }
        },
        drawConfig: {
            needsSearchOverride: true,
        },
        columns:[
            { "data": 'Ref', 'title' : 'Reférence' },
            { "data": 'Label', 'title' : 'Libellé' },
            { "data": 'Location', 'title' : 'Emplacement', 'name': 'location' },
            { "data": 'Date', 'title' : 'Date de saisie', 'name': 'date' },
            { "data": 'Anomaly', 'title' : 'Anomalie', 'name' : 'anomaly'  }
        ],
    };
    let tableRefArticle = initDataTable('tableMissionInvReferenceArticle', tableRefArticleConfig);

    let modalAddToMission = $("#modalAddToMission");
    let submitAddToMission = $("#submitAddToMission");
    let urlAddToMission = Routing.generate('add_to_mission', true);
    InitialiserModal(
        modalAddToMission,
        submitAddToMission,
        urlAddToMission,
        {
            ajax: {
                reload(param1, param2) {
                    tableArticle.ajax.reload(param1, param2);
                    tableRefArticle.ajax.reload(param1, param2);
                }
            }
        },
        displayErrorAddToMission
    );

    return {
        tableArticle,
        tableRefArticle
    };
}

function displayErrorAddToMission(data)
{
    if (!data) {
        alertErrorMsg("Cette référence est déjà présente dans une mission sur la même période. Vous ne pouvez pas l'ajouter.", true);
    }
}
