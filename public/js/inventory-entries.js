$(function () {
    initSearchDate(tableEntries);

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

    $('.ajax-autocomplete-inv-entries').select2({
        ajax: {
            url: Routing.generate('get_ref_and_articles', {activeOnly: 0}, true),
            dataType: 'json',
            delay: 250,
        },
        allowClear: true,
        placeholder: {
            id: 0,
            text: 'Référence',
        },
        language: {
            inputTooShort: function () {
                return 'Veuillez entrer au moins 3 caractères.';
            },
            searching: function () {
                return 'Recherche en cours...';
            },
            noResults: function () {
                return 'Aucun résultat.';
            }
        },
        minimumInputLength: 3,
    });

    ajaxAutoUserInit($('.ajax-autocomplete-user'), 'Opérateur');

});

let mission = $('#missionId').val();
let pathEntry = Routing.generate('entries_api', { id: mission}, true);
let tableEntries = $('#tableMissionEntries').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax:{
        "url": pathEntry,
        "type": "POST"
    },
    columns:[
        { "data": 'Ref', 'title' : 'Reférence', 'name': 'reference' },
        { "data": 'Label', 'title' : 'Libellé' },
        { "data": 'Date', 'title' : 'Date de saisie', 'name': 'date' },
        { "data": 'Location', 'title' : 'Emplacement', 'name': 'location' },
        { "data": 'Operator', 'title' : 'Opérateur', 'name': 'operator' },
        { "data": 'Quantity', 'title' : 'Quantité' }
    ],
});

let $submitSearchEntry = $('#submitSearchEntry');
$submitSearchEntry.on('click', function () {
    let emplacement = $('#emplacement').val();
    let operator = $('#utilisateur').val();
    let reference = $('#reference').text();
    let operatorStr = operator.toString();
    let operatorPiped = operatorStr.split(',').join('|')
    tableEntries
        .columns('operator:name')
        .search(operatorPiped ? '^' + operatorPiped + '$' : '', true, false)
        .draw();
    tableEntries
        .columns('location:name')
        .search(emplacement ? '^' + emplacement + '$' : '', true, false)
        .draw();
    tableEntries
        .columns('reference:name')
        .search(reference ? '^' + reference + '$' : '', true, false)
        .draw();
});

function generateCSVEntries () {
    let path = Routing.generate('get_entries_for_csv', true);

    $.post(path, function(response) {
        if (response) {
            let csv = "";
            $.each(response, function (index, value) {
                csv += value.join(';');
                csv += '\n';
            });
            mFile(csv);
        }
    }, 'json');
}

let mFile = function (csv) {
    let exportedFilename = 'export-entries' + '.csv';
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