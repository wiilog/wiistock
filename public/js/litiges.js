$('.select2').select2();

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

let $submitSearchLitigesArr = $('#submitSearchLitigesArrivages');

$(function() {
    ajaxAutoUserInit($('.ajax-autocomplete-user'), 'Acheteurs');
    ajaxAutoFournisseurInit($('.filters').find('.ajax-autocomplete-fournisseur'), 'Fournisseurs');

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_LITIGE_ARR);
    $.post(path, params, function(data) {
        data.forEach(function(element) {
            if (element.field == 'utilisateurs') {
                let values = element.value.split(',');
                let $utilisateur = $('#utilisateur');
                values.forEach((value) => {
                    let valueArray = value.split(':');
                    let id = valueArray[0];
                    let username = valueArray[1];
                    let option = new Option(username, id, true, true);
                    $utilisateur.append(option).trigger('change');
                });
            } else if (element.field == 'providers') {
                let values = element.value.split(',');
                let $providers = $('#providers');
                values.forEach((value) => {
                    let valueArray = value.split(':');
                    let id = valueArray[0];
                    let username = valueArray[1];
                    let option = new Option(username, id, true, true);
                    $providers.append(option).trigger('change');
                });
            } else if (element.field == 'carriers') {
                $('#carriers').val(element.value).select2();
            } else {
                $('#'+element.field).val(element.value);
            }
        });
    }, 'json');
});

let pathLitigesArrivage = Routing.generate('litige_arrivage_api', true);
let tableLitigesArrivage = $('#tableLitigesArrivages').DataTable({
    responsive: true,
    serverSide: true,
    processing: true,
    language: {
        url: "/js/i18n/dataTableLanguage.json",
    },
    order: [[4, 'desc']],
    ajax: {
        "url": pathLitigesArrivage,
        "type": "POST",
    },
    'drawCallback': function() {
        overrideSearch($('#tableLitigesArrivages_filter input'), tableLitigesArrivage);
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
    ],
    columnDefs: [
        {
            orderable: false,
            targets: [0]
        }
    ],
    dom: '<"row"<"col-4"B><"col-4"l><"col-4"f>>t<"bottom"ip>',
    buttons: [
        {
            extend: 'colvis',
            columns: ':not(.noVis)',
            className: 'dt-btn'
        },
        // {
        //     extend: 'csv',
        //     className: 'dt-btn'
        // }
    ]
});

let modalNewLitiges = $('#modalNewLitiges');
let submitNewLitiges = $('#submitNewLitiges');
let urlNewLitiges = Routing.generate('litige_new', true);
InitialiserModal(modalNewLitiges, submitNewLitiges, urlNewLitiges, tableLitigesArrivage);

let modalEditLitige = $('#modalEditLitige');
let submitEditLitige = $('#submitEditLitige');
let urlEditLitige = Routing.generate('litige_edit', true);
initModalWithAttachments(modalEditLitige, submitEditLitige, urlEditLitige, tableLitigesArrivage);

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

$submitSearchLitigesArr.on('click', function () {
    let filters = {
        page: PAGE_LITIGE_ARR,
        dateMin: $('#dateMin').val(),
        dateMax: $('#dateMax').val(),
        statut: $('#statut').val(),
        type: $('#type').val(),
        carriers: $('#carriers').select2('data'),
        providers: $('#providers').select2('data'),
        users: $('#utilisateur').select2('data'),
    }

    saveFilters(filters, tableLitigesArrivage);
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
