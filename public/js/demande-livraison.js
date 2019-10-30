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
        {"data": 'Quantité', 'title': 'Quantité disponible'},
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
    processing: true,
    serverSide: true,
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

let $submitSearchDemande = $('#submitSearchDemandeLivraison');
$submitSearchDemande.on('click', function() {
    let dateMin = $('#dateMin').val();
    let dateMax = $('#dateMax').val();
    let statut = $('#statut').val();
    let user = $('#utilisateur').val();
    let userString = user.toString();
    let userPiped = userString.split(',').join('|');
    let type = $('#type').val();

    saveFilters(PAGE_DEM_LIVRAISON, dateMin, dateMax, statut, userPiped, type);

    tableDemande.draw();
});

$.fn.dataTable.ext.search.push(
    function (settings, data, dataIndex) {
        let dateMin = $('#dateMin').val();
        let dateMax = $('#dateMax').val();
        let indexDate = tableDemande.column('Date:name').index();

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

let $submitSearchDemandeLivraison = $('#submitSearchDemandeLivraison');

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

// applique les filtres si pré-remplis
$(function() {
    let val = $('#statut').val();
    if (val != null && val != '') {
        $submitSearchDemandeLivraison.click();
    }

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_DEM_LIVRAISON);;
    $.post(path, params, function(data) {
        data.forEach(function(element) {
            if (element.field == 'utilisateurs') {
                $('#utilisateur').val(element.value.split(',')).select2();
            } else {
                $('#'+element.field).val(element.value);
            }
        });
        if (data.length > 0)$submitSearchDemandeLivraison.click();
    }, 'json');

    ajaxAutoRefArticleInit($('.ajax-autocomplete'));
});

let editorNewLivraisonAlreadyDone = false;

function initNewLivraisonEditor(modal) {
    if (!editorNewLivraisonAlreadyDone) {
        initEditorInModal(modal);
        editorNewLivraisonAlreadyDone = true;
    }
    ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement'))
};

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
    let path = Routing.generate('article_api_edit', true);
    let params = {id: select.val(), isADemand: 1};

    $.post(path, JSON.stringify(params), function(data) {
        if (data) {
            $('#editNewArticle').html(data);
            let quantityToTake = $('#quantityToTake');
            let valMax = $('#quantite').val();

            let attrMax = quantityToTake.find('input').attr('max');
            if (attrMax > valMax) quantityToTake.find('input').attr('max', valMax);
            quantityToTake.removeClass('d-none');
            ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement-edit'));
        }
    });
}

let generateCSVDemande = function () {
    loadSpinner($('#spinnerlivrai'));
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
                hideSpinner($('#spinnerlivrai'));
            }
        }, 'json');
    } else {
        $('.error-msg').html('<p>Saisissez une date de départ et une date de fin dans le filtre en en-tête de page.</p>');
        hideSpinner($('#spinnerlivrai'));
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

$submitSearchDemandeLivraison.on('keypress', function (e) {
    if (e.which === 13) {
        $submitSearchDemandeLivraison.click();
    }
});