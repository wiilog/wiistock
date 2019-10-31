$('.select2').select2();

$('#utilisateur').select2({
    placeholder: {
        text: 'Opérateur',
    }
});

$('#emplacement').select2({
    placeholder: {
        id: 0,
        text: 'Emplacement',
    }
});

$(function() {
    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_MVT_STOCK);;
    $.post(path, params, function(data) {
        data.forEach(function(element) {
            if (element.field == 'utilisateurs') {
                $('#utilisateur').val(element.value.split(',')).select2();
            } else if (element.field == 'emplacement') {
                $('#emplacement').val(element.value).select2();
            } else {
                $('#'+element.field).val(element.value);
            }
        });
        if (data.length > 0) $submitSearchMvt.click();
    }, 'json');
});

let pathMvt = Routing.generate('mouvement_stock_api', true);
let tableMvt = $('#tableMvts').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    "order": [[0, "desc"]],
    ajax: {
        "url": pathMvt,
        "type": "POST"
    },
    columns: [
        {"data": 'date attendue', 'name': 'date attendue', 'title': 'Date attendue'},
        {"data": 'date', 'name': 'date', 'title': 'Date'},
        {"data": "refArticle", 'name': 'refArticle', 'title': 'Référence article'},
        {"data": "quantite", 'name': 'quantite', 'title': 'Quantité'},
        {"data": 'origine', 'name': 'origine', 'title': 'Origine'},
        {"data": 'destination', 'name': 'destination', 'title': 'Destination'},
        {"data": 'type', 'name': 'type', 'title': 'Type'},
        {"data": 'operateur', 'name': 'operateur', 'title': 'Opérateur'},
        {"data": 'actions', 'name': 'Actions', 'title': 'Actions'},
    ],
});

$.fn.dataTable.ext.search.push(
    function (settings, data) {
        let emplacement = $('#emplacement').val();
        if (emplacement !== '') {
            let originIndex = tableMvt.column('origine:name').index();
            let destinationIndex = tableMvt.column('destination:name').index();
            return data[originIndex] == emplacement || data[destinationIndex] == emplacement;
        } else {
            return true;
        }
    }
);

$.fn.dataTable.ext.search.push(
    function (settings, data) {
        let dateMin = $('#dateMin').val();
        let dateMax = $('#dateMax').val();
        let indexDate = tableMvt.column('date:name').index();

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

let modalDeleteArrivage = $('#modalDeleteMvtStock');
let submitDeleteArrivage = $('#submitDeleteMvtStock');
let urlDeleteArrivage = Routing.generate('mvt_stock_delete', true);
InitialiserModal(modalDeleteArrivage, submitDeleteArrivage, urlDeleteArrivage, tableMvt);

let $submitSearchMvt = $('#submitSearchMvt');
$submitSearchMvt.on('click', function () {
    let dateMin = $('#dateMin').val();
    let dateMax = $('#dateMax').val();
    let statut = $('#statut').val();
    let emplacement = $('#emplacement').val();
    let demandeur = $('#utilisateur').val()
    let demandeurString = demandeur.toString();
    demandeurPiped = demandeurString.split(',').join('|')

    saveFilters(PAGE_MVT_STOCK, dateMin, dateMax, statut, demandeurPiped, null, emplacement);

    tableMvt
        .columns('type:name')
        .search(statut ? '^' + statut + '$' : '', true, false)
        .draw();

    tableMvt
        .columns('operateur:name')
        .search(demandeurPiped ? '^' + demandeurPiped + '$' : '', true, false)
        .draw();

    tableMvt.draw();
});

function generateCSVMouvement () {
    loadSpinner($('#spinnerMouvementStock'));
    let data = {};
    $('.filterService, select').first().find('input').each(function () {
        if ($(this).attr('name') !== undefined) {
            data[$(this).attr('name')] = $(this).val();
        }
    });

    if (data['dateMin'] && data['dateMax']) {
        let params = JSON.stringify(data);
        let path = Routing.generate('get_mouvements_stock_for_csv', true);

        $.post(path, params, function(response) {
            if (response) {
                $('.error-msg').empty();
                let csv = "";
                $.each(response, function (index, value) {
                    csv += value.join(';');
                    csv += '\n';
                });
                mFile(csv);
                hideSpinner($('#spinnerMouvementStock'));
            }
        }, 'json');

    } else {
        $('.error-msg').html('<p>Saisissez une date de départ et une date de fin dans le filtre en en-tête de page.</p>');
        hideSpinner($('#spinnerMouvementStock'));
    }
}

let mFile = function (csv) {
    let d = new Date();
    let date = checkZero(d.getDate() + '') + '-' + checkZero(d.getMonth() + 1 + '') + '-' + checkZero(d.getFullYear() + '');
    date += ' ' + checkZero(d.getHours() + '') + '-' + checkZero(d.getMinutes() + '') + '-' + checkZero(d.getSeconds() + '');
    let exportedFilename = 'export-mouvements-stock-' + date + '.csv';
    let blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    if (navigator.msSaveBlob) { // IE 10+
        navigator.msSaveBlob(blob, exportedFilename);
    } else {
        let link = document.createElement("a");
        if (link.download !== undefined) {
            let url = URL.createObjectURL(blob);
            link.setAttribute("href", url);
            link.setAttribute("download", exportedFilename);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    }
}