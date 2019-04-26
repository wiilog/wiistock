$('.select2').select2();

$('#utilisateur').select2({
    placeholder: {
        text: 'Demandeur',
    }
});

let pathCollecte = Routing.generate('collecte_api', true);
let table = $('#tableCollecte_id').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    "order": [[0, "desc"]],
    ajax: {
        "url": pathCollecte,
        "type": "POST"
    },
    columns: [
        { "data": 'Date', 'name': 'Date' },
        { "data": 'Demandeur', 'name': 'Demandeur' },
        { "data": 'Objet', 'name': 'Objet' },
        { "data": 'Statut', 'name': 'Statut' },
        { "data": 'Actions', 'name': 'Actions' }
    ],
});

// recherche par défaut demandeur = utilisateur courant
let demandeur = $('.current-username').val();
if (demandeur !== undefined) {
    let demandeurPiped = demandeur.split(',').join('|')
    table
        .columns('Demandeur:name')
        .search(demandeurPiped ? '^' + demandeurPiped + '$' : '', true, false)
        .draw();
    // affichage par défaut du filtre select2 demandeur = utilisateur courant
    $('#utilisateur').val(demandeur).trigger('change');
}

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


//AJOUTE_ARTICLE
let pathAddArticle = Routing.generate('collecte_article_api', { 'id': id }, true);
let tableArticle = $('#tableArticle_id').DataTable({
    language: {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: {
        "url": pathAddArticle,
        "type": "POST"
    },
    columns: [
        { "data": 'Référence CEA' },
        { "data": 'Libellé' },
        { "data": 'Emplacement' },
        { "data": 'Quantité' },
        { "data": 'Actions' }
    ],

});

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

// $('.ajax-autocomplete').select2({
//     ajax: {
//         url: Routing.generate('get_ref_articles'),
//         dataType: 'json',
//         delay: 250,
//     },
//     language: {
//         inputTooShort: function() {
//             return 'Veuillez entrer au moins 1 caractère.';
//         },
//         searching: function() {
//             return 'Recherche en cours...';
//         },
//         noResults: function() {
//             return 'Aucun résultat.';
//         }
//     },
//     minimumInputLength: 1,
// });

// function ajaxGetArticle(select) {
//     xhttp = new XMLHttpRequest();
//     xhttp.onreadystatechange = function () {
//         if (this.readyState == 4 && this.status == 200) {
//             data = JSON.parse(this.responseText);
//            $('#newContent').html(data);
//            $('#modalNewArticle').find('div').find('div').find('.modal-footer').removeClass('d-none');
//         }
//     }
//     path =  Routing.generate('get_article_by_refArticle', true)
//     let data = {};
//     data['referenceArticle'] = select.val();
//     json = JSON.stringify(data);
//     xhttp.open("POST", path, true);
//     xhttp.send(json);
// }

function ajaxGetCollecteArticle(select) {
    xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            data = JSON.parse(this.responseText);
            $('#selection').html(data.selection);
            $('#editNewArticle').html(data.modif);
            $('#modalNewArticle').find('.modal-footer').removeClass('d-none');
            displayRequireChamp($('#typeEdit'), 'edit');
        }
    }
    path = Routing.generate('get_collecte_article_by_refArticle', true)
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


//initialisation editeur de texte une seule fois à l'édit
let editorEditCollecteAlreadyDone = false;
function initEditCollecteEditor(modal) {
    if (!editorEditCollecteAlreadyDone) {
        initEditor(modal);
        editorEditCollecteAlreadyDone = true;
    }
};


//initialisation editeur de texte une seule fois à la création
let editorNewCollecteAlreadyDone = false;
function initNewCollecteEditor(modal) {
    if (!editorNewCollecteAlreadyDone) {
        initEditor(modal);
        editorNewCollecteAlreadyDone = true;
    }
    ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement'))
};

$('#submitSearchCollecte').on('click', function () {
    let statut = $('#statut').val();
    // let demandeur = [];
    let demandeur = $('#utilisateur').val()
    let demandeurString = demandeur.toString();
    let demandeurPiped = demandeurString.split(',').join('|')

    table
        .columns('Statut:name')
        .search(statut)
        .draw();

    table
        .columns('Demandeur:name')
        .search(demandeurPiped ? '^' + demandeurPiped + '$' : '', true, false)
        .draw();

    $.fn.dataTable.ext.search.push(
        function (settings, data, dataIndex) {
            let dateMin = $('#dateMin').val();
            let dateMax = $('#dateMax').val();
            let dateInit = (data[0]).split('/').reverse().join('-') || 0;

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
    table
        .draw();
});

function destinationCollecte(button) {
    let sel = $(button).data('title');
    let tog = $(button).data('toggle');
    if ($(button).hasClass('not-active')) {
        if ($("#destination").val() == "0") {
            $("#destination").val("1");
        } else {
            $("#destination").val("0");
        }
    }

    $('span[data-toggle="' + tog + '"]').not('[data-title="' + sel + '"]').removeClass('active').addClass('not-active');
    $('span[data-toggle="' + tog + '"][data-title="' + sel + '"]').removeClass('not-active').addClass('active');
}

function validateCollecte(collecteId) {
    let params = JSON.stringify({ id: collecteId });

    $.post(Routing.generate('demande_collecte_has_articles'), params, function (resp) {
        if (resp === true) {
            window.location.href = Routing.generate('ordre_collecte_new', { 'id': collecteId });
        } else {
            $('#cannotValidate').click();
        }
    });
}

let ajaxEditArticle = function (select) {
    xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            dataReponse = JSON.parse(this.responseText);
            if (dataReponse) {
                $('#editNewArticle').html(dataReponse);
                // displayRequireChamp($('#typeEditArticle'), 'edit');
                initEditor('.editor-container');
            } else {
                //TODO gérer erreur
            }
        }
    }
    let json = select.val();
    let path = Routing.generate('article_api_edit', true);
    xhttp.open("POST", path, true);
    xhttp.send(json);
}
