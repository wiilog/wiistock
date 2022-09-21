global.displayNewExportModal = displayNewExportModal;
global.onExportTypeChange = onExportTypeChange;

let $modalNewExport = $("#modalNewExport");
let $submitNewExport = $("#submitNewExport");

$(document).ready(() => {
    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate(`filter_get_by_page`);
    let params = JSON.stringify(PAGE_EXPORT);

    $.post(path, params, function (data) {
        displayFiltersSup(data);
    }, `json`);

    const tableExport = initDataTable(`tableExport`, {
        processing: true,
        serverSide: true,
        ajax: {
            url: Routing.generate(`export_api`),
            type: `POST`
        },
        columns: [
            {data: `actions`, title: ``, orderable: false, className: `noVis`},
            {data: `status`, title: `Statut`},
            {data: `creationDate`, title: `Date de création`},
            {data: `startDate`, title: `Date début`},
            {data: `endDate`, title: `Date fin`},
            {data: `nextRun`, title: `Prochaine exécution`},
            {data: `frequency`, title: `Fréquence`},
            {data: `user`, title: `Utilisateur`},
            {data: `type`, title: `Type`},
            {data: `dataType`, title: `Type de données exportées`},
        ],
        rowConfig: {
            needsRowClickAction: true
        },
    });
});

function displayNewExportModal(){
    clearModal($modalNewExport);
    InitModal($modalNewExport, $submitNewExport, ''); //TODO faire la route de validation de la modale

    $.get(Routing.generate('new_export_modal', true), function(resp){
        $modalNewExport.find('.modal-body').html(resp);

        $('.export-references').on('click', function(){
            console.log('Export de références');
            $('.ref-articles-sentence').removeClass('d-none');
            $('.date-limit').addClass('d-none');
        });

        $('.export-articles').on('click', function(){
            console.log("Export d'articles");
            $('.ref-articles-sentence').removeClass('d-none');
            $('.date-limit').addClass('d-none');
        });

        $('.export-transport-rounds').on('click', function(){
            console.log('Export de tournées');
            $('.ref-articles-sentence').addClass('d-none');
            $('.date-limit').removeClass('d-none');
        });

        $('.export-arrivals').on('click', function(){
            console.log("Export d'arrivages");
            $('.ref-articles-sentence').addClass('d-none');
            $('.date-limit').removeClass('d-none');
        });
    });

    $modalNewExport.modal('show');
}

function onExportTypeChange(element){
    $('.unique-export-container');
    $('.scheduled-export-container');
}
