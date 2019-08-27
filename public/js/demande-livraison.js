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
        {"data": 'Référence', 'title': 'Référence'},
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
        {"data": 'Type', 'name': 'Type', 'title': 'Type'},
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

    let path = Routing.generate('compare_stock', true);
    let params = {'demande': submit.data('id')};

    $.post(path, JSON.stringify(params), function (data) {
        if (data.status === true) {
            $('.zone-entete').html(data.entete);
            $('#tableArticle_id').DataTable().ajax.reload();
            $('#boutonCollecteSup, #boutonCollecteInf').addClass('d-none');
            tableArticle.ajax.reload(function (json) {
                if (data !== undefined) {
                    $('#myInput').val(json.lastInput);
                }
            });
        } else {
            $('#restantQuantite').html(data.stock);
            $('#negativStock').click();
        }
    }, 'json');
}

function setMaxQuantity(select) {
    let params = {
        refArticleId: select.val(),
    };
    $.post(Routing.generate('get_quantity_ref_article'), params, function (data) {
        if (data) {
            let modalBody = select.closest(".modal-body");
            modalBody.find('#quantity-to-deliver').attr('max', data);
        }

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

// applique les filtres si pré-remplis
$(function() {
    if ($('#statut').val() !== null) {
        $('#submitSearchDemandeLivraison').click();
    }
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
        initEditorInModal(modal);
        editorNewLivraisonAlreadyDone = true;
    }
    ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement'))
};

$('#submitSearchDemandeLivraison').on('click', function () {
    let statut = $('#statut').val();
    let type = $('#type').val();
    let utilisateur = $('#utilisateur').val()
    let utilisateurString = utilisateur.toString();
    let utilisateurPiped = utilisateurString.split(',').join('|');
    tableDemande
        .columns('Statut:name')
        .search(statut ? '^' + statut + '$' : '', true, false)
        .draw();

    tableDemande
        .columns('Type:name')
        .search(type ? '^' + type + '$' : '', true, false)
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
            toggleRequiredChampsLibres($('#typeEdit'), 'edit');
            ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement-edit'));
        }, 'json');
    }
}

// function switchWantedGlobal(checkbox) {
// //     let path = Routing.generate('switch_choice', true);
// //     let params = {
// //         'checked': checkbox.is(':checked'),
// //         'reference': checkbox.data('ref')
// //     };
// //     let $modal = checkbox.closest('.modal');
// //     $.post(path, JSON.stringify(params), function (data) {
// //         $modal.find('#choiceContent').html(data.content);
// //         $modal.find('.error-msg').html('');
// //     });
// // }

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
                let quantityToTake = $('#quantityToTake');
                let valMax = $('#quantite').val();

                let attrMax = quantityToTake.find('input').attr('max');
                if (attrMax > valMax) quantityToTake.find('input').attr('max', valMax);
                quantityToTake.removeClass('d-none');
                ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement-edit'));
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

let generateCSVDemande = function () {
    let data = {};
    $('.filterService, select').first().find('input').each(function () {
        if ($(this).attr('name') !== undefined) {
            data[$(this).attr('name')] = $(this).val();
        }
    });

    if (data['dateMin'] && data['dateMax']) {
        let params = JSON.stringify(data);
        let path = Routing.generate('get_livraisons_for_csv', true);

        $.post(path, params, function(response) {
            if (response) {
                $('.error-msg').empty();
                let csv = "";
                $.each(response, function (index, value) {
                    csv += value.join(';');
                    csv += '\n';
                });
                dlFile(csv);
            }
        }, 'json');

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

$('#submitSearchDemandeLivraison').on('keypress', function (e) {
    if (e.which === 13) {
        $('#submitSearchDemandeLivraison').click();
    }
});