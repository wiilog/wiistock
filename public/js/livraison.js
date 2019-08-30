$('.select2').select2();

$('#utilisateur').select2({
    placeholder: {
        text: 'Opérateur',
    }
});

let $submitSearchLivraison = $('#submitSearchLivraison');

$(function() {
    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_ORDRE_LIVRAISON);;
    $.post(path, params, function(data) {
        data.forEach(function(element) {
            if (element.field == 'utilisateurs') {
                $('#utilisateur').val(element.value.split(',')).select2();
            } else {
                $('#'+element.field).val(element.value);
            }
        });
        if (data.length > 0)$submitSearchLivraison.click();
    }, 'json');
});

let pathLivraison = Routing.generate('livraison_api');
let tableLivraison = $('#tableLivraison_id').DataTable({
    language: {
        url: "/js/i18n/dataTableLanguage.json",
    },
    order: [[2, "desc"]],
    ajax: {
        'url': pathLivraison,
        "type": "POST"
    },
    columnDefs: [
        {
            "type": "customDate",
            "targets": 2
        }
    ],
    columns: [
        {"data": 'Numéro', 'title': 'Numéro', 'name': 'Numéro'},
        {"data": 'Statut', 'title': 'Statut', 'name': 'Statut'},
        {"data": 'Date', 'title': 'Date de création', 'name': 'Date'},
        {"data": 'Opérateur', 'title': 'Opérateur', 'name': 'Opérateur'},
        {"data": 'Type', 'title': 'Type', 'name': 'Type'},
        {"data": 'Actions', 'title': 'Actions', 'name': 'Actions'},
    ],
});

$.fn.dataTable.ext.search.push(
    function (settings, data, dataIndex) {
        let dateMin = $('#dateMin').val();
        let dateMax = $('#dateMax').val();
        let indexDate = tableLivraison.column('Date:name').index();
        let dateInit = (data[indexDate]).split('/').reverse().join('-') || 0;

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

$.extend($.fn.dataTableExt.oSort, {
    "customDate-pre": function (a) {
        let dateParts = a.split('/'),
            year = parseInt(dateParts[2]) - 1900,
            month = parseInt(dateParts[1]),
            day = parseInt(dateParts[0]);
        return Date.UTC(year, month, day, 0, 0, 0);
    },
    "customDate-asc": function (a, b) {
        return ((a < b) ? -1 : ((a > b) ? 1 : 0));
    },
    "customDate-desc": function (a, b) {
        return ((a < b) ? 1 : ((a > b) ? -1 : 0));
    }
});

$submitSearchLivraison.on('click', function () {
    let dateMin = $('#dateMin').val();
    let dateMax = $('#dateMax').val();
    let statut = $('#statut').val();
    let utilisateur = $('#utilisateur').val()
    let utilisateurString = utilisateur.toString();
    let utilisateurPiped = utilisateurString.split(',').join('|');
    let type = $('#type').val();

    saveFilters(PAGE_ORDRE_LIVRAISON, dateMin, dateMax, statut, utilisateurPiped, type);

    tableLivraison
        .columns('Statut:name')
        .search(statut ? '^' + statut + '$' : '', true, false)
        .draw();

    tableLivraison
        .columns('Type:name')
        .search(type ? '^' + type + '$' : '', true, false)
        .draw();

    tableLivraison
        .columns('Opérateur:name')
        .search(utilisateurPiped ? '^' + utilisateurPiped + '$' : '', true, false)
        .draw();

    tableLivraison.draw();
});

let pathArticle = Routing.generate('livraison_article_api', {'id': id});
let tableArticle = $('#tableArticle_id').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: {
        'url': pathArticle,
        "type": "POST"
    },
    columns: [
        {"data": 'Référence', 'title': 'Référence'},
        {"data": 'Libellé', 'title': 'Libellé'},
        {"data": 'Emplacement', 'title': 'Emplacement'},
        {"data": 'Quantité', 'title': 'Quantité'},
        {"data": 'Actions', 'title': 'Actions'},
    ],
});

let modalDeleteLivraison = $('#modalDeleteLivraison');
let submitDeleteLivraison = $('#submitDeleteLivraison');
let urlDeleteLivraison = Routing.generate('livraison_delete', {'id': id}, true);
InitialiserModal(modalDeleteLivraison, submitDeleteLivraison, urlDeleteLivraison, tableLivraison);