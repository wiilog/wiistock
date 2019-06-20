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
let pathArticle = Routing.generate('demande_article_api', {id: id}, true);
let tableArticle = $('#table-lignes').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    "processing": true,
    "order": [[0, "desc"]],
    "ajax": {
        "url": pathArticle,
        "type": "POST"
    },
    columns: [
        {"data": 'Référence CEA', 'title': 'Référence CEA'},
        {"data": 'Libellé', 'title': 'Libellé'},
        {"data": 'Emplacement', 'title': 'Emplacement'},
        {"data": 'Quantité', 'title': 'Quantité'},
        {"data": 'Quantité à prélever', 'title': 'Quantité à prélever'},
        {"data": 'Actions', 'title': 'Actions'}
    ],
});

let modalNewArticle = $("#modalNewArticle");
let submitNewArticle = $("#submitNewArticle");
let pathNewArticle = Routing.generate('demande_add_article', true);
InitialiserModal(modalNewArticle, submitNewArticle, pathNewArticle, tableArticle);

let modalDeleteArticle = $("#modalDeleteArticle");
let submitDeleteArticle = $("#submitDeleteArticle");
let pathDeleteArticle = Routing.generate('demande_remove_article', true);
InitialiserModal(modalDeleteArticle, submitDeleteArticle, pathDeleteArticle, tableArticle);

let modalEditArticle = $("#modalEditArticle");
let submitEditArticle = $("#submitEditArticle");
let pathEditArticle = Routing.generate('demande_article_edit', true);
InitialiserModal(modalEditArticle, submitEditArticle, pathEditArticle, tableArticle);

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

//DEMANDE
let pathDemande = Routing.generate('demande_api', true);
let tableDemande = $('#table_demande').DataTable({
    order: [[0, 'desc']],
    "columnDefs": [
        {
            "type": "customDate",
            "targets": 0
        }
    ],
    language: {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: {
        "url": pathDemande,
        "type": "POST",
    },
    columns: [
        {"data": 'Date', 'name': 'Date'},
        {"data": 'Demandeur', 'name': 'Demandeur'},
        {"data": 'Numéro', 'name': 'Numéro'},
        {"data": 'Statut', 'name': 'Statut'},
        {"data": 'Actions', 'name': 'Actions'},
    ],
});

// recherche par défaut demandeur = utilisateur courant
// let demandeur = $('.current-username').val();
// if (demandeur !== undefined) {
//     let demandeurPiped = demandeur.split(',').join('|')
//     tableDemande
//         .columns('Demandeur:name')
//         .search(demandeurPiped ? '^' + demandeurPiped + '$' : '', true, false)
//         .draw();
//     // affichage par défaut du filtre select2 demandeur = utilisateur courant
//     $('#utilisateur').val(demandeur).trigger('change');
// }

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

function getCompareStock(submit) {
    xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            data = JSON.parse(this.responseText);
            $('.zone-entete').html(data.entete);
            $('#tableArticle_id').DataTable().ajax.reload();
            $('#boutonCollecteSup').addClass('d-none')
            $('#boutonCollecteInf').addClass('d-none')
            tableArticle.ajax.reload(function (json) {
                if (this.responseText !== undefined) {
                    $('#myInput').val(json.lastInput);
                }
            });
        } else if (this.readyState === 4 && this.status === 250) {
            data = JSON.parse(this.responseText);
            $('#negativStock').click();
        }
    }
    path = Routing.generate('compare_stock', true)
    let data = {};
    data['demande'] = submit.data('id')
    json = JSON.stringify(data);
    xhttp.open("POST", path, true);
    xhttp.send(json);
}

function setMaxQuantity(select) {
    let params = {
        refArticleId: select.val(),
    };
    $.post(Routing.generate('get_quantity_ref_article'), params, function (data) {
        let modalBody = select.closest(".modal-body");
        modalBody.find('#quantity-to-deliver').attr('max', data);
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

let editorNewLivraisonAlreadyDone = false;
function initNewLivraisonEditor(modal) {
    if (!editorNewLivraisonAlreadyDone) {
        // initEditor(modal);
        //TODO CG wysiwyg
        initEditor2('.editor-container-new');
        editorNewLivraisonAlreadyDone = true;
    }
    ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement'))
};

$('#submitSearchDemandeLivraison').on('click', function () {
    let statut = $('#statut').val();
    let utilisateur = $('#utilisateur').val()
    let utilisateurString = utilisateur.toString();
    let utilisateurPiped = utilisateurString.split(',').join('|');
    tableDemande
        .columns('Statut:name')
        .search(statut)
        .draw();

    tableDemande
        .columns('Demandeur:name')
        .search(utilisateurPiped ? '^' + utilisateurPiped + '$' : '', true, false)
        .draw();

    $.fn.dataTable.ext.search.push(
        function (settings, data, dataIndex) {
            let dateMin = $('#dateMin').val();
            let dateMax = $('#dateMax').val();
            let indexDate = tableDemande.column('Date:name').index();
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
    tableDemande
        .draw();
});

function ajaxGetAndFillArticle(select) {
    if ($(select).val() !== null) {
        let path = Routing.generate('demande_article_by_refArticle', true)
        let refArticle = $(select).val();
        let params = JSON.stringify(refArticle);
        let selection = $('#selection');
        let editNewArticle = $('#editNewArticle');
        let modalFooter = $('#modalNewArticle').find('div').find('div').find('.modal-footer')

        selection.html('');
        editNewArticle.html('');
        modalFooter.addClass('d-none');

        $.post(path, params, function (data) {
            selection.html(data.selection);
            editNewArticle.html(data.modif);
            modalFooter.removeClass('d-none');
            displayRequireChamp($('#typeEdit'), 'edit');
            initEditor2('.editor-container-edit'); //TODO CG wysiwyg
            ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement-edit'));
        }, 'json');
    }
}

function deleteRowDemande(button, modal, submit) {
    let id = button.data('id');
    let name = button.data('name');
    modal.find(submit).attr('value', id);
    modal.find(submit).attr('name', name);
}

function validateLivraison(livraisonId, elem) {
    let params = JSON.stringify({id: livraisonId});

    $.post(Routing.generate('demande_livraison_has_articles'), params, function (resp) {
        if (resp === true) {
            getCompareStock(elem);
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
                let withdrawQuantity = $('#withdrawQuantity');
                let valMax = $('#quantite').val();
                withdrawQuantity.find('input').attr('max', valMax);
                withdrawQuantity.removeClass('d-none');
                ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement-edit'));
                initEditor2('.editor-container-edit');
            } else {
                //TODO gérer erreur
            }
        }
    }
    let json = {id: select.val(), isADemand: 1};
    let path = Routing.generate('article_api_edit', true);
    xhttp.open("POST", path, true);
    xhttp.send(JSON.stringify(json));
}

let generateCSV = function () {
    xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            response = JSON.parse(this.responseText);
            if (response) {
                $('.error-msg').empty();
                let csv = "";
                $.each(response, function (index, value) {
                    csv += value.join(';');
                    csv += '\n';
                });
                dlFile(csv);
            }
        }
    }
    Data = {};
    $('.filterService, select').first().find('input').each(function () {
        if ($(this).attr('name') !== undefined) {
            Data[$(this).attr('name')] = $(this).val();
        }
    });
    // let utilisateurs = $('#utilisateur').val().toString().split(',');
    if (Data['dateMin'] && Data['dateMax']) {
        json = JSON.stringify(Data);
        xhttp.open("POST", Routing.generate('get_livraisons_for_csv', true));
        xhttp.send(json);
    } else {
        $('.error-msg').html('<p>Saisissez une date de départ et une date de fin dans le filtre en en-tête de page.</p>');
    }
}

let dlFile = function (csv) {
    let d = new Date();
    let date = checkZero(d.getDate() + '') + '-' + checkZero(d.getMonth() + 1 + '') + '-' + checkZero(d.getFullYear() + '');
    date += ' ' + checkZero(d.getHours() + '') + '-' + checkZero(d.getMinutes() + '') + '-' + checkZero(d.getSeconds() + '');
    var exportedFilenmae = 'export-demandes-' + date + '.csv';
    var blob = new Blob([csv], {type: 'text/csv;charset=utf-8;'});
    if (navigator.msSaveBlob) { // IE 10+
        navigator.msSaveBlob(blob, exportedFilenmae);
    } else {
        var link = document.createElement("a");
        if (link.download !== undefined) {
            var url = URL.createObjectURL(blob);
            link.setAttribute("href", url);
            link.setAttribute("download", exportedFilenmae);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    }
}

function checkZero(data) {
    if (data.length == 1) {
        data = "0" + data;
    }
    return data;
}


