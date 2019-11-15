$('.select2').select2();

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
            'targets': [6,7,8],
            'visible': false
        }
    ],
    order: [[4, 'desc']]
});

let modalNewLitiges = $('#modalNewLitiges');
let submitNewLitiges = $('#submitNewLitiges');
let urlNewLitiges = Routing.generate('litige_new', true);
InitialiserModal(modalNewLitiges, submitNewLitiges, urlNewLitiges, tableLitigesArrivage);

let modalEditLitige = $('#modalEditLitige');
let submitEditLitige = $('#submitEditLitige');
let urlEditLitige = Routing.generate('litige_edit', true);
initModalArrivage(modalEditLitige, submitEditLitige, urlEditLitige, tableLitigesArrivage);

let ModalDeleteLitige = $("#modalDeleteLitige");
let SubmitDeleteLitige = $("#submitDeleteLitige");
let urlDeleteLitige = Routing.generate('litige_delete', true);
InitialiserModal(ModalDeleteLitige, SubmitDeleteLitige, urlDeleteLitige, tableLitigesArrivage);

function editRowLitige(button, afterLoadingEditModal = () => {}, arrivageId, litigeId) {
    let path = Routing.generate('litige_api_edit', true);
    let modal = $('#modalEditLitige');
    let submit = $('#submitEditLitige');

    let params = {
        litigeId: litigeId,
        arrivageId: arrivageId
    };

    $.post(path, JSON.stringify(params), function (data) {
        modal.find('.error-msg').html('');
        modal.find('.modal-body').html(data.html);
        modal.find('#colisEditLitige').val(data.colis).select2();
        afterLoadingEditModal()
    }, 'json');

    modal.find(submit).attr('value', litigeId);
}

let tableHistoLitige;
function openTableHisto() {

    let pathHistoLitige = Routing.generate('histo_litige_api', {litige: $('#litigeId').val()}, true);
    tableHistoLitige = $('#tableHistoLitige').DataTable({
        language: {
            url: "/js/i18n/dataTableLanguage.json",
        },
        ajax: {
            "url": pathHistoLitige,
            "type": "POST"
        },
        columns: [
            {"data": 'user', 'name': 'Utilisateur', 'title': 'Utilisateur'},
            {"data": 'date', 'name': 'date', 'title': 'Date'},
            {"data": 'commentaire', 'name': 'commentaire', 'title': 'Commentaire'},
        ],
        dom: '<"top">rt<"bottom"lp><"clear">'
    });
}

$.fn.dataTable.ext.search.push(
    function (settings, data) {
        let dateMin = $('#dateMin').val();
        let dateMax = $('#dateMax').val();
        let indexDate = tableLitigesArrivage.column('creationDate:name').index();

        if (typeof indexDate === "undefined") return true;
        if (typeof data[indexDate] !== "undefined") {
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
        return true;
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
    let data = {};
    $('.filterService, select').first().find('input').each(function () {
        if ($(this).attr('name') !== undefined) {
            data[$(this).attr('name')] = $(this).val();
        }
    });

    if (data['dateMin'] && data['dateMax']) {
        let $spinner = $('#spinnerLitigesArrivages');
        loadSpinner($spinner);
        let params = JSON.stringify(data);
        let path = Routing.generate('get_litiges_arrivages_for_csv', true);

        $.post(path, params, function(response) {
            if (response) {
                $('.error-msg').empty();
                let csv = "";
                $.each(response, function (index, value) {
                    csv += value.join(';');
                    csv += '\n';
                });
                aFile(csv);
                hideSpinner($spinner);
            }
        }, 'json');

    } else {
        $('.error-msg').html('<p>Saisissez une date de départ et une date de fin dans le filtre en en-tête de page.</p>');
    }
}

let aFile = function (csv) {
    let d = new Date();
    let date = checkZero(d.getDate() + '') + '-' + checkZero(d.getMonth() + 1 + '') + '-' + checkZero(d.getFullYear() + '');
    date += ' ' + checkZero(d.getHours() + '') + '-' + checkZero(d.getMinutes() + '') + '-' + checkZero(d.getSeconds() + '');
    let exportedFilenmae = 'export-litiges-' + date + '.csv';
    let blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    if (navigator.msSaveBlob) { // IE 10+
        navigator.msSaveBlob(blob, exportedFilenmae);
    } else {
        let link = document.createElement("a");
        if (link.download !== undefined) {
            let url = URL.createObjectURL(blob);
            link.setAttribute("href", url);
            link.setAttribute("download", exportedFilenmae);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    }
};

function getCommentAndAddHisto()
{
    let path = Routing.generate('add_comment', {litige: $('#litigeId').val()}, true);
    let commentLitige = $('#modalEditLitige').find('#litige-edit-commentaire');
    let dataComment = commentLitige.val();

    $.post(path, JSON.stringify(dataComment), function () {
        tableHistoLitige.ajax.reload();
        commentLitige.val('');
    });
}
