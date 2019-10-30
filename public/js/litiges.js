$('#utilisateur').select2({
    placeholder: {
        text: 'Acheteurs',
    }
});
$('#carriers').select2({
    placeholder: {
        text: 'Transporteurs',
    }
});
$('#providers').select2({
    placeholder: {
        text: 'Fournisseurs',
    }
});

let pathLitigesArrivage = Routing.generate('litige_arrivage_api', true);
let tableLitigesArrivage = $('#tableLitigesArrivages').DataTable({
    responsive: true,
    language: {
        url: "/js/i18n/dataTableLanguage.json",
    },
    scrollX: true,
    ajax: {
        "url": pathLitigesArrivage,
        "type": "POST",
    },
    columns: [
        {"data": 'actions', 'name': 'Actions', 'title': 'Actions'},
        {"data": 'type', 'name': 'type', 'title': 'Type'},
        {"data": "arrivalNumber", 'name': 'arrivalNumber', 'title': "N° d'arrivage"},
        {"data": 'buyers', 'name': 'buyers', 'title': 'Acheteurs'},
        {"data": 'lastHistoric', 'name': 'lastHistoric', 'title': 'Dernier historique'},
        {"data": 'creationDate', 'name': 'creationDate', 'title': 'Créé le'},
        {"data": 'updateDate', 'name': 'updateDate', 'title': 'Modifié le'},
        {"data": 'status', 'name': 'status', 'title': 'Statut', 'target': 7},
        {"data": 'provider', 'name': 'provider', 'title': 'Fournisseur', 'target': 8},
        {"data": 'carrier', 'name': 'carrier', 'title': 'Transporteur', 'target': 9},
    ],
    columnDefs: [
        {
            'targets': [7,8,9],
            'visible': false
        }
    ]
});

$.fn.dataTable.ext.search.push(
    function (settings, data) {
        let dateMin = $('#dateMin').val();
        let dateMax = $('#dateMax').val();
        let indexDate = tableLitigesArrivage.column('creationDate:name').index();

        if (typeof indexDate === "undefined") return true;

        let dateInit = (data[indexDate]).split(' ')[0].split('/').reverse().join('-') || 0;

        if (
            (dateMin == "" && dateMax == "")
            ||
            (dateMin == "" && moment(dateInit).isSameOrBefore(dateMax))
            ||
            (moment(dateInit).isSameOrAfter(dateMin) && dateMax == "")
            ||
            (moment(dateInit).isSameOrAfter(dateMin) && moment(dateInit).isSameOrBefore(dateMax))
        ) {
            return true;
        }
        return false;
    }
);

$('#submitSearchLitigesArrivages').on('click', function () {
    let dateMin = $('#dateMin').val();
    let dateMax = $('#dateMax').val();
    let statut = $('#statut').val();
    let type = $('#type').val();

    let carriers = $('#carriers').val();
    let carriersString = carriers.toString();
    let carriersPiped = carriersString.split(',').join('|');

    let providers = $('#providers').val();
    let providersString = providers.toString();
    let providersPiped = providersString.split(',').join('|');

    let utilisateur = $('#utilisateur').val();
    let utilisateurString = utilisateur.toString();
    let utilisateurPiped = utilisateurString.split(',').join('|');

    saveFilters(PAGE_LITIGE_ARR, dateMin, dateMax, statut, utilisateurPiped, type, null, null, carriersPiped, providersPiped);

    tableLitigesArrivage
        .columns('status:name')
        .search(statut ? '^' + statut + '$' : '', true, false)
        .draw();

    tableLitigesArrivage
        .columns('buyers:name')
        .search(utilisateurPiped ? '' + utilisateurPiped : '', true, false)
        .draw();

    tableLitigesArrivage
        .columns('type:name')
        .search(type ? '^' + type + '$' : '', true, false)
        .draw();

    tableLitigesArrivage
        .columns('carrier:name')
        .search(carriersPiped ? '' + carriersPiped : '', true, false)
        .draw();

    tableLitigesArrivage
        .columns('provider:name')
        .search(providersPiped ? '' + providersPiped : '', true, false)
        .draw();

    tableLitigesArrivage
        .draw();
});

function generateCSVLitigeArrivage() {
    loadSpinner($('#spinnerLitigesArrivages'));
    let data = {};
    $('.filterService, select').first().find('input').each(function () {
        if ($(this).attr('name') !== undefined) {
            data[$(this).attr('name')] = $(this).val();
        }
    });

    // if (data['dateMin'] && data['dateMax']) {
    //     let params = JSON.stringify(data);
    //     let path = Routing.generate('get_litiges_arrivages_for_csv', true);
    //
    //     $.post(path, params, function(response) {
    //         if (response) {
    //             $('.error-msg').empty();
    //             let csv = "";
    //             $.each(response, function (index, value) {
    //                 csv += value.join(';');
    //                 csv += '\n';
    //             });
    //             aFile(csv);
    //             hideSpinner($('#spinnerArrivage'));
    //         }
    //     }, 'json');
    //
    // } else {
    //     $('.error-msg').html('<p>Saisissez une date de départ et une date de fin dans le filtre en en-tête de page.</p>');
    //     hideSpinner($('#spinnerArrivage'))
    // }
}