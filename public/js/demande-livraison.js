// $( document ).ready(function () {
//     $('#modalNewArticle').modal('show')
// })
$('.select2').select2();

$('#utilisateur').select2({
    placeholder: {
        text: 'Utilisateur',
    }
});
//ARTICLE DEMANDE
let pathArticle = Routing.generate('ligne_article_api', { id: id }, true);
let tableArticle = $('#table-lignes').DataTable({
    "language": {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    "processing": true,
    "ajax": {
        "url": pathArticle,
        "type": "POST"
    },
    columns: [
        { "data": 'Référence CEA' },
        { "data": 'Libellé' },
        { "data": 'Quantité' },
        { "data": 'Actions' }
    ],
});

let modalNewArticle = $("#modalNewArticle");
let submitNewArticle = $("#submitNewArticle");
let pathNewArticle = Routing.generate('ligne_article_new', true);
InitialiserModal(modalNewArticle, submitNewArticle, pathNewArticle, tableArticle);

let modalDeleteArticle = $("#modalDeleteArticle");
let submitDeleteArticle = $("#submitDeleteArticle");
let pathDeleteArticle = Routing.generate('ligne_article_delete', true);
InitialiserModal(modalDeleteArticle, submitDeleteArticle, pathDeleteArticle, tableArticle);

let modalEditArticle = $("#modalEditArticle");
let submitEditArticle = $("#submitEditArticle");
let pathEditArticle = Routing.generate('ligne_article_edit', true);
InitialiserModal(modalEditArticle, submitEditArticle, pathEditArticle, tableArticle);



//DEMANDE
let pathDemande = Routing.generate('demande_api', true);
let tableDemande = $('#table_demande').DataTable({
    order: [[0, "desc"]],
    language: {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json",
    },
    ajax: {
        "url": pathDemande,
        "type": "POST",
    },
    columns: [
        { "data": 'Date' },
        { "data": 'Demandeur' },
        { "data": 'Numéro' },
        { "data": 'Statut' },
        { "data": 'Actions' },
    ],
});


let urlNewDemande = Routing.generate('demande_new', true);
let modalNewDemande = $("#modalNewDemande");
let submitNewDemande = $("#submitNewDemande");
InitialiserModal(modalNewDemande, submitNewDemande, urlNewDemande, tableDemande);

let urlDeleteDemande = Routing.generate('demande_delete', true);
let modalDeleteDemande = $("#modalDeleteDemande");
let submitDeleteDemande = $("#submitDeleteDemande");
InitialiserModal(modalDeleteDemande, submitDeleteDemande, urlDeleteDemande, tableDemande);

let urlEditDemande = Routing.generate('demande_edit', true);
let modalEditDemande = $("#modalEditDemande");
let submitEditDemande = $("#submitEditDemande");
InitialiserModal(modalEditDemande, submitEditDemande, urlEditDemande, tableDemande);

function updateQuantity(input) {
    let params = {
        refArticleId: input.val()
    };

    $.post(Routing.generate('get_quantity_ref_article'), params, function (data) {
        let modalBody = input.closest('.modal-body');
        modalBody.find('#in-stock').val(data);
        modalBody.find('#quantite').attr('max', data);


    }, 'json');
}

$('.ajax-autocomplete').select2({
    ajax: {
        url: Routing.generate('get_ref_articles'),
        dataType: 'json',
        delay: 250,
    },
    language: {
        inputTooShort: function () {
            return 'Veuillez entrer au moins 1 caractère.';
        },
        searching: function () {
            return 'Recherche en cours...';
        },
        noResults: function () {
            return 'Aucun résultat.';
        }
    },
    minimumInputLength: 1,
});


let ajaxAuto = function () {

    $('.ajax-autocomplete').select2({
        ajax: {
            url: Routing.generate('get_ref_articles'),
            dataType: 'json',
            delay: 250,
        },
        language: {
            inputTooShort: function () {
                return 'Veuillez entrer au moins 1 caractère.';
            },
            searching: function () {
                return 'Recherche en cours...';
            }
        },
        minimumInputLength: 1,
    });
}

var editorNewLivraisonAlreadyDone = false;
function initNewLivraisonEditor(modal) {

    if (!editorNewLivraisonAlreadyDone) {

        initEditor(modal);
        editorNewLivraisonAlreadyDone = true;

    }
    ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement'))
};

$('#submitSearchDemandeLivraison').on('click', function () {

    let statut = $('#statut').val();
    let utilisateur = [];
    utilisateur = $('#utilisateur').val()
    utilisateurString = utilisateur.toString();
    utilisateurPiped = utilisateurString.split(',').join('|')

    tableDemande
        .columns(3)
        .search(statut)
        .draw();

    tableDemande
        .columns(1)
        .search(utilisateurPiped ? '^' + utilisateurPiped + '$' : '', true, false)
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
    tableDemande
        .draw();
});

function finishDemandeLivraison(submit) {
    xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            data = JSON.parse(this.responseText);
            $('#tableArticle_id').DataTable().ajax.reload();
            $('.zone-entete').html(data.entete);
            $('#boutonCollecteSup').addClass('d-none')
            $('#boutonCollecteInf').addClass('d-none')
            tableArticle.ajax.reload(function (json) {
                if (this.responseText !== undefined) {
                    $('#myInput').val(json.lastInput);
                }
            });
        }
    }
    path = Routing.generate('finish_demande', true)
    let data = {};
    data['demande'] = submit.data('id')
    json = JSON.stringify(data);
    xhttp.open("POST", path, true);
    xhttp.send(json);
}

function ajaxGetAndFillArticle(select) {
    if ($(select).val() !== null) {
        let path = Routing.generate('demande_article_by_refArticle', true)

        let refArticle = $(select).val();
        let params = JSON.stringify(refArticle);

        $.post(path, params, function (data) {
            $('#newContent').html(data);
            $('#modalNewArticle').find('div').find('div').find('.modal-footer').removeClass('d-none');
        })
    }
}