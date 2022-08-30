$('.select2').select2();

let pathCollecte = Routing.generate('collecte_api', true);
let collecteTableConfig = {
    processing: true,
    serverSide: true,
    order: [['Création', 'desc']],
    ajax: {
        "url": pathCollecte,
        "type": "POST",
        'data' : {
            'filterStatus': $('#filterStatus').val()
        },
    },
    rowConfig: {
        needsRowClickAction: true,
    },
    drawConfig: {
        needsSearchOverride: true,
    },
    columns: [
        {data: 'Actions', name: 'Actions', title: '', className: 'noVis', orderable: false},
        {data: 'pairing', title: '', name: 'Actions', className: 'pairing-row', orderable: false},
        {data: 'Création', name: 'Création', title: 'Création'},
        {data: 'Validation', name: 'Validation', title: 'Validation'},
        {data: 'Demandeur', name: 'Demandeur', title: 'Demandeur'},
        {data: 'Numéro', name: 'Numéro', title: 'Numéro'},
        {data: 'Objet', name: 'Objet', title: 'Objet'},
        {data: 'Statut', name: 'Statut', title: 'Statut'},
        {data: 'Type', name: 'Type', title: 'Type'},
    ]
};
let table = initDataTable('tableCollecte_id', collecteTableConfig);
console.log("coucou");
console.log(table);


let modalNewCollecte = $("#modalNewCollecte");
let SubmitNewCollecte = $("#submitNewCollecte");
let urlNewCollecte = Routing.generate('collecte_new', true)
InitModal(modalNewCollecte, SubmitNewCollecte, urlNewCollecte, {tables: [table]});

let modalDeleteCollecte = $("#modalDeleteCollecte");
let submitDeleteCollecte = $("#submitDeleteCollecte");
let urlDeleteCollecte = Routing.generate('collecte_delete', true)
InitModal(modalDeleteCollecte, submitDeleteCollecte, urlDeleteCollecte, {tables: [table]});

$.fn.dataTable.ext.search.push(
    function (settings, data, dataIndex) {
        let dateMin = $('#dateMin').val();
        let dateMax = $('#dateMax').val();
        let indexDate = table.column('Création:name').index();

        if (typeof indexDate === "undefined") return true;

        let dateInit = (data[indexDate]).split('/').reverse().join('-') || 0;

        if (
            (dateMin === "" && dateMax === "")
            ||
            (dateMin === "" && moment(dateInit).isSameOrBefore(dateMax))
            ||
            (moment(dateInit).isSameOrAfter(dateMin) && dateMax === "")
            ||
            (moment(dateInit).isSameOrAfter(dateMin) && moment(dateInit).isSameOrBefore(dateMax))
        ) {
            return true;
        }
        return false;
    }
);

$(function() {
    initDateTimePicker();
    Select2Old.user('Demandeurs');

    // applique les filtres si pré-remplis
    let val = $('#filterStatus').val();

    if (val && val.length > 0) {
        let valuesStr = val.split(',');
        let valuesInt = [];
        valuesStr.forEach((value) => {
            valuesInt.push(parseInt(value));
        })
        $('#statut').val(valuesInt).select2();
    } else {
        // sinon, filtres enregistrés en base pour chaque utilisateur
        let path = Routing.generate('filter_get_by_page');
        let params = JSON.stringify(PAGE_DEM_COLLECTE);
        $.post(path, params, function (data) {
            displayFiltersSup(data);
        }, 'json');
    }

    const $modalNewCollecte = $('#modalNewCollecte');
    $modalNewCollecte.on('show.bs.modal', function () {
        initNewCollecteEditor("#modalNewCollecte");
        clearModal("#modalNewCollecte");
    });

    $modalNewCollecte.find(`select[name="type"]`).on(`change`, function() {
        const $locationSelector = $(`#modalNewCollecte select[name="emplacement"]`);
        const type = $(this).val();
        const $restrictedResults = $modalNewCollecte.find(`input[name="restrictResults"]`);

        $locationSelector.prop(`disabled`, type === '');
        $locationSelector.val(null).trigger(`change`);

        Select2Old.init(
            $locationSelector,
            '',
            $restrictedResults.val() ? 0 : 1,
            {
                route: 'get_locations_by_type',
                param: {
                    type,
                }
            });
    })
});

function initNewCollecteEditor(modal) {
    Select2Old.location($('.ajax-autocomplete-location'));

    const type = $(modal).find('select[name="type"] option:selected').val();
    const $locationSelector = $(modal).find(`select[name="emplacement"]`);

    $locationSelector.val(null).trigger('change');

    if(!type) {
        $locationSelector.prop(`disabled`, true);
    }
}

function callbackSaveFilter() {
    // supprime le filtre de l'url
    let str = window.location.href.split('/');
    if (str[5]) {
        window.location.href = Routing.generate('collecte_index');
    }
}
