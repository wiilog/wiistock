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
let $submitSearchMvt = $('#submitSearchMvt');

$(function() {
    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_MVT_TRACA);
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

let pathMvt = Routing.generate('mvt_traca_api', true);
let tableMvt = $('#tableMvts').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    "processing": true,
    "order": [[0, "desc"]],
    ajax: {
        "url": pathMvt,
        "type": "POST"
    },
    "columnDefs": [
        {
            "type": "customDate",
            "targets": 0
        }
    ],
    columns: [
        {"data": 'date', 'name': 'date', 'title': 'Date'},
        {"data": "refArticle", 'name': 'refArticle', 'title': "Colis"},
        {"data": 'refEmplacement', 'name': 'refEmplacement', 'title': 'Emplacement'},
        {"data": 'type', 'name': 'type', 'title': 'Action'},
        {"data": 'operateur', 'name': 'operateur', 'title': 'Operateur'},
        {"data": 'Actions', 'name': 'Actions', 'title': 'Actions'},
    ],
});

$.extend($.fn.dataTableExt.oSort, {
    "customDate-pre": function (a) {
        let dateStr = a.split(' ')[0];
        let hourStr = a.split(' ')[1];
        let dateSplitted = dateStr.split('/');
        let hourSplitted = hourStr.split(':');

        let date = new Date(dateSplitted[2], dateSplitted[1], dateSplitted[0], hourSplitted[0], hourSplitted[1], hourSplitted[2]);

        return Date.UTC(date.getFullYear(), date.getMonth(), date.getDate(), date.getHours(), date.getMinutes(), date.getSeconds());
    },
    "customDate-asc": function (a, b) {
        return ((a < b) ? -1 : ((a > b) ? 1 : 0));
    },
    "customDate-desc": function (a, b) {
        return ((a < b) ? 1 : ((a > b) ? -1 : 0));
    }
});

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

let modalNewMvtTraca = $("#modalNewMvtTraca");
let submitNewMvtTraca = $("#submitNewMvtTraca");
let urlNewMvtTraca = Routing.generate('mvt_traca_new', true);
initModalWithAttachments(modalNewMvtTraca, submitNewMvtTraca, urlNewMvtTraca, tableMvt);

let modalDeleteArrivage = $('#modalDeleteMvtTraca');
let submitDeleteArrivage = $('#submitDeleteMvtTraca');
let urlDeleteArrivage = Routing.generate('mvt_traca_delete', true);
InitialiserModal(modalDeleteArrivage, submitDeleteArrivage, urlDeleteArrivage, tableMvt);

$submitSearchMvt.on('click', function () {
    let dateMin = $('#dateMin').val();
    let dateMax = $('#dateMax').val();
    let article = $('#colis').val();
    let emplacement = $('#emplacement').val();
    let statut = $('#statut').val();
    let demandeur = $('#utilisateur').val();
    let demandeurString = demandeur.toString();
    let demandeurPiped = demandeurString.split(',').join('|');

    saveFilters(PAGE_MVT_TRACA, dateMin, dateMax, statut, demandeurPiped, null, emplacement, article);

    tableMvt
        .columns('type:name')
        .search(statut ? '^' + statut + '$' : '', true, false)
        .draw();

    tableMvt
        .columns('operateur:name')
        .search(demandeurPiped ? '^' + demandeurPiped + '$' : '', true, false)
        .draw();
    tableMvt
        .columns('refEmplacement:name')
        .search(emplacement ? '^' + emplacement + '$' : '', true, false)
        .draw();
    tableMvt
        .columns('refArticle:name')
        .search(article)
        .draw();

    tableMvt.draw();
});

function generateCSVMouvement () {
    loadSpinner($('#spinnerMouvementTraca'));
    let data = {};
    $('.filterService, select').first().find('input').each(function () {
        if ($(this).attr('name') !== undefined) {
            data[$(this).attr('name')] = $(this).val();
        }
    });

    if (data['dateMin'] && data['dateMax']) {
        let params = JSON.stringify(data);
        let path = Routing.generate('get_mouvements_traca_for_csv', true);

        $.post(path, params, function(response) {
            if (response) {
                $('.error-msg').empty();
                let csv = "";
                $.each(response, function (index, value) {
                    csv += value.join(';');
                    csv += '\n';
                });
                mFile(csv);
                hideSpinner($('#spinnerMouvementTraca'));
            }
        }, 'json');

    } else {
        $('.error-msg').html('<p>Saisissez une date de départ et une date de fin dans le filtre en en-tête de page.</p>');
        hideSpinner($('#spinnerMouvementTraca'));
    }
}

let mFile = function (csv) {
    let d = new Date();
    let date = checkZero(d.getDate() + '') + '-' + checkZero(d.getMonth() + 1 + '') + '-' + checkZero(d.getFullYear() + '');
    date += ' ' + checkZero(d.getHours() + '') + '-' + checkZero(d.getMinutes() + '') + '-' + checkZero(d.getSeconds() + '');
    let exportedFilenmae = 'export-mouvement-traca-' + date + '.csv';
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
}

let editorNewMvtTracaAlreadyDone = false;

function initNewMvtTracaEditor(modal) {
    if (!editorNewMvtTracaAlreadyDone) {
        quillNew = initEditor(modal + ' .editor-container-new');
        editorNewMvtTracaAlreadyDone = true;
    }
    ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement'));
};