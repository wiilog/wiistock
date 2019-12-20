$('.select2').select2();

let pathCollecte = Routing.generate('collecte_api', true);
let table = $('#tableCollecte_id').DataTable({
    processing: true,
    serverSide: true,
    order: [[1, 'desc']],
    columnDefs: [
        {
            "type": "customDate",
            "targets": 1
        },
        {
            "orderable": false,
            "targets": [0]
        }
    ],
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: {
        "url": pathCollecte,
        "type": "POST",
        'data' : {
            'filterStatus': $('#statut').val()
        },
    },
    drawCallback: function() {
        overrideSearch($('#tableCollecte_id_filter input'), table);
    },
    columns: [
        {"data": 'Actions', 'name': 'Actions', 'title': 'Actions'},
        {"data": 'Création', 'name': 'Création', 'title': 'Création'},
        {"data": 'Validation', 'name': 'Validation', 'title': 'Validation'},
        {"data": 'Demandeur', 'name': 'Demandeur', 'title': 'Demandeur'},
        {"data": 'Objet', 'name': 'Objet', 'title': 'Objet'},
        {"data": 'Statut', 'name': 'Statut', 'title': 'Statut'},
        {"data": 'Type', 'name': 'Type', 'title': 'Type'},
    ]
});

let $submitSearchCollecte = $('#submitSearchCollecte');
$submitSearchCollecte.on('click', function () {
    $('#dateMin').data("DateTimePicker").format('YYYY-MM-DD');
    $('#dateMax').data("DateTimePicker").format('YYYY-MM-DD');

    let filters = {
        page: PAGE_DEM_COLLECTE,
        dateMin: $('#dateMin').val(),
        dateMax: $('#dateMax').val(),
        statut: $('#statut').val(),
        users: $('#utilisateur').select2('data'),
        type: $('#type').val()
    };

    $('#dateMin').data("DateTimePicker").format('DD/MM/YYYY');
    $('#dateMax').data("DateTimePicker").format('DD/MM/YYYY');

    saveFilters(filters, table);

    // supprime le filtre de l'url
    let str = window.location.href.split('/');
    if (str[5]) {
        window.location.href = Routing.generate('collecte_index');
    }
});

let modalNewCollecte = $("#modalNewCollecte");
let SubmitNewCollecte = $("#submitNewCollecte");
let urlNewCollecte = Routing.generate('collecte_new', true)
InitialiserModal(modalNewCollecte, SubmitNewCollecte, urlNewCollecte, table);

let modalDeleteCollecte = $("#modalDeleteCollecte");
let submitDeleteCollecte = $("#submitDeleteCollecte");
let urlDeleteCollecte = Routing.generate('collecte_delete', true)
InitialiserModal(modalDeleteCollecte, submitDeleteCollecte, urlDeleteCollecte, table);

let modalModifyCollecte = $('#modalEditCollecte');
let submitModifyCollecte = $('#submitEditCollecte');
let urlModifyCollecte = Routing.generate('collecte_edit', true);
InitialiserModal(modalModifyCollecte, submitModifyCollecte, urlModifyCollecte, table);

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

//AJOUTE_ARTICLE
let pathAddArticle = Routing.generate('collecte_article_api', {'id': id}, true);
let tableArticle = $('#tableArticle_id').DataTable({
    language: {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: {
        "url": pathAddArticle,
        "type": "POST"
    },
    columns: [
        {"data": 'Référence', 'title': 'Référence'},
        {"data": 'Libellé', 'title': 'Libellé'},
        {"data": 'Emplacement', 'title': 'Emplacement'},
        {"data": 'Quantité', 'title': 'Quantité'},
        {"data": 'Actions', 'title': 'Actions'}
    ],
});

$.fn.dataTable.ext.search.push(
    function (settings, data, dataIndex) {
        let dateMin = $('#dateMin').val();
        let dateMax = $('#dateMax').val();
        let indexDate = table.column('Création:name').index();

        if (typeof indexDate === "undefined") return true;

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

let modal = $("#modalNewArticle");
let submit = $("#submitNewArticle");
let url = Routing.generate('collecte_add_article', true);
InitialiserModal(modal, submit, url, tableArticle);

let modalEditArticle = $("#modalEditArticle");
let submitEditArticle = $("#submitEditArticle");
let urlEditArticle = Routing.generate('collecte_edit_article', true);
InitialiserModal(modalEditArticle, submitEditArticle, urlEditArticle, tableArticle);

let modalDeleteArticle = $("#modalDeleteArticle");
let submitDeleteArticle = $("#submitDeleteArticle");
let urlDeleteArticle = Routing.generate('collecte_remove_article', true);
InitialiserModal(modalDeleteArticle, submitDeleteArticle, urlDeleteArticle, tableArticle);

// applique les filtres si pré-remplis
$(function() {
    ajaxAutoUserInit($('.ajax-autocomplete-user'), 'Demandeurs');

    let val = $('#statut').val();
    if (!val) {
        // filtres enregistrés en base pour chaque utilisateur
        let path = Routing.generate('filter_get_by_page');
        let params = JSON.stringify(PAGE_DEM_COLLECTE);
        $.post(path, params, function (data) {
            data.forEach(function (element) {
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
                }  else if (element.field == 'dateMin' || element.field == 'dateMax') {
                    $('#' + element.field).val(moment(element.value, 'YYYY-MM-DD').format('DD/MM/YYYY'));
                } else {
                    $('#' + element.field).val(element.value);
                }
            });
        }, 'json');
    }
});

function ajaxGetCollecteArticle(select) {
    let $selection = $('#selection');
    let $editNewArticle = $('#editNewArticle');
    let modalNewArticle = '#modalNewArticle';
    xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            data = JSON.parse(this.responseText);
            $selection.html(data.selection);
            if (data.modif) $editNewArticle.html(data.modif);
            $(modalNewArticle).find('.modal-footer').removeClass('d-none');
            toggleRequiredChampsLibres(select.closest('.modal').find('#type'), 'edit');
            ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement-edit'));
            initEditor(modalNewArticle + ' .editor-container-edit');
        }
    }
    path = Routing.generate('get_collecte_article_by_refArticle', true)
    $selection.html('');
    $editNewArticle.html('');
    let data = {};
    data['referenceArticle'] = $(select).val();
    json = JSON.stringify(data);
    xhttp.open("POST", path, true);
    xhttp.send(json);
}

function deleteRowCollecte(button, modal, submit) {
    let id = button.data('id');
    let name = button.data('name');
    modal.find(submit).attr('value', id);
    modal.find(submit).attr('name', name);
}

//initialisation editeur de texte une seule fois à la création
let editorNewCollecteAlreadyDone = false;

function initNewCollecteEditor(modal) {
    if (!editorNewCollecteAlreadyDone) {
        initEditorInModal(modal);
        editorNewCollecteAlreadyDone = true;
    }
    ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement'))
};

function validateCollecte(collecteId) {
    let params = JSON.stringify({id: collecteId});

    $.post(Routing.generate('demande_collecte_has_articles'), params, function (resp) {
        if (resp === true) {
            window.location.href = Routing.generate('ordre_collecte_new', {'id': collecteId});
        } else {
            $('#cannotValidate').click();
        }
    });
}

let ajaxEditArticle = function (select) {
    let path = Routing.generate('article_api_edit', true);
    let params = {id: select.val(), isADemand: 1};

    $.post(path, JSON.stringify(params), function(data) {
        $('#editNewArticle').html(data);
        ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement-edit'));
        initEditor('.editor-container-edit');
    }, 'json');
}

//TODO MH utilisé ?
$submitSearchCollecte.on('keypress', function (e) {
    if (e.which === 13) {
        $submitSearchCollecte.click();
    }
});
