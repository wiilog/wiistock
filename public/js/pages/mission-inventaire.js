$(function () {
    initDateTimePicker();
    $(`.select2`).select2();
    Select2Old.init($(".filter-select2[name=multipleTypes]"), "Types de missions");

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate(`filter_get_by_page`);
    let params = JSON.stringify(PAGE_INV_MISSIONS);
    $.post(path, params, function(data) {
        displayFiltersSup(data);
    }, `json`);

    let pathMissions = Routing.generate(`inv_missions_api`, true);
    let tableMisionsConfig = {
        serverSide: true,
        processing: true,
        searching: false,
        order: [[`start`, `desc`]],
        ajax:{
            url: pathMissions,
            type: "POST"
        },
        rowConfig: {
            needsRowClickAction: true,
        },
        columns: [
            {data: `actions`, title: ``, className: `noVis`, orderable: false},
            {data: `name`, title: `Libellé`},
            {data: `start`, title: `Date de début`},
            {data: `end`, title: `Date de fin`},
            {data: `rate`, title: `Taux d\'avancement`, orderable: false},
            {data: `type`, title: `Type`},
            {data: `requester`, title: `Demandeur`}
        ]
    };
    let tableMissions = initDataTable(`tableMissionsInv`, tableMisionsConfig);

    let modalNewMission = $("#modalNewMission");
    Form
        .create(modalNewMission, { resetView: ['open', 'close'] })
        .submitTo(
            AJAX.POST,
            'mission_new',
            {
                tables: tableMissions,
                success: ({redirect}) => {
                    window.location.href = redirect;
                }
            }
        );

    let modalDeleteMission = $("#modalDeleteMission");
    Form
        .create(modalDeleteMission, { resetView: ['open', 'close'] })
        .submitTo(
            AJAX.POST,
            'mission_delete',
            {
                tables: tableMissions,
            }
        );

    Form
        .create($('#modalDuplicateMission'))
        .submitTo(
            AJAX.POST,
            'mission_new',
            {
                tables: tableMissions,
                success: ({redirect}) => {
                    window.location.href = redirect;
                }
            }
        );
    initSearchDate(tableMissions);
});

function openDuplicateInventoryMissionModal($button) {
    wrapLoadingOnActionButton($('#tableMissionsInv'), ()=>{
        return AJAX
            .route(AJAX.GET, "get_form_mission_duplicate", {'id': $button.data('id')})
            .json()
            .then((data)=>{
                const $modal = $('#modalDuplicateMission');
                $modal.find('.modal-body').html(data.html);
                $modal.modal('show');
        })
    })
}
