// TODO AB
// import Routing from '../../router';
// import '../../form-reference-article';
let $printTag;
let pageTables = [];

$(function () {
    $('.select2').select2();
    $printTag = $('#printTag');
    let activeFilter;
    if ($('#filters').find('.filter').length <= 0) {
        $('#noFilters').removeClass('d-none');
        activeFilter = true;
    } else {
        activeFilter = false;
    }
    managePrintButtonTooltip(activeFilter, $printTag.is('button') ? $printTag.parent() : $printTag);
    initTableRefArticle();
    console.log(pageTables);
    displayActifOrInactif($('#toggleActivOrInactiv'), true);
    registerNumberInputProtection($('#modalNewRefArticle').find('input[type="number"]'));

    initPageModals();
});

function initPageModals() {

    let modalRefArticleNew = $("#modalNewRefArticle");
    let submitNewRefArticle = $("#submitNewRefArticle");
    let urlRefArticleNew = Routing.generate('reference_article_new', true);
    InitModal(modalRefArticleNew, submitNewRefArticle, urlRefArticleNew, {tables: pageTables});

    let modalDeleteRefArticle = $("#modalDeleteRefArticle");
    let SubmitDeleteRefArticle = $("#submitDeleteRefArticle");
    let urlDeleteRefArticle = Routing.generate('reference_article_delete', true);
    InitModal(modalDeleteRefArticle, SubmitDeleteRefArticle, urlDeleteRefArticle, {tables: pageTables, clearOnClose: true});

    let modalModifyRefArticle = $('#modalEditRefArticle');
    let submitModifyRefArticle = $('#submitEditRefArticle');
    let urlModifyRefArticle = Routing.generate('reference_article_edit', true);
    InitModal(modalModifyRefArticle, submitModifyRefArticle, urlModifyRefArticle, {tables: pageTables, clearOnClose: true});

    let $modalPlusDemande = $('#modalPlusDemande');
    let $submitPlusDemande = $('#submitPlusDemande');
    let urlPlusDemande = Routing.generate('plus_demande', true);
// TODO InitialiserModalRefArticle($modalPlusDemande, $submitPlusDemande, urlPlusDemande);

    let modalColumnVisible = $('#modalColumnVisible');
    let submitColumnVisible = $('#submitColumnVisible');
    let urlColumnVisible = Routing.generate('save_column_visible', true);
    InitModal(modalColumnVisible, submitColumnVisible, urlColumnVisible);

    let modalNewFilter = $('#modalNewFilter');
    let submitNewFilter = $('#submitNewFilter');
    let urlNewFilter = Routing.generate('filter_ref_new', true);
// TODO InitialiserModalRefArticle(modalNewFilter, submitNewFilter, urlNewFilter, displayNewFilter, true);
}


function InitialiserModalRefArticle(modal, submit, path, callback = function () {}, close = true) {
    submit.click(function () {
        submitActionRefArticle(modal, path, callback, close);
    });
}

function afterLoadingEditModal($button) {
    initRequiredChampsFixes($button);
    $('#typeContentEdit .list-multiple').select2();
}

function submitActionRefArticle(modal, path, callback = null, close = true) {
    let {Data, missingInputs, wrongNumberInputs, doublonRef} = getDataFromModalReferenceArticle(modal);

    // si tout va bien on envoie la requête ajax...
    if (missingInputs.length == 0 && wrongNumberInputs.length == 0 && !doublonRef) {
        if (close == true) modal.find('.close').click();
        $.post(path, JSON.stringify(Data), function (data) {
            if (!data) {
                $('#cannotDelete').click();
            }
            if (typeof data === 'object') {
                if (!data.success) {
                    alertErrorMsg(data.msg);
                }
            }

            if (callback !== null) callback(data, modal);

            clearModalRefArticle(modal, data);
        });

        modal.find('.error-msg').html('');

    } else {
        // ... sinon on construit les messages d'erreur
        let msg = buildErrorMsgReferenceArticle(missingInputs, wrongNumberInputs, doublonRef);
        modal.find('.error-msg').html(msg);
    }

}

function clearModalRefArticle(modal, data) {
    if (typeof (data.msg) == 'undefined') {
        // on vide tous les inputs
        let inputs = modal.find('.modal-body').find(".data, .newContent>input");
        inputs.each(function () {
            if ($(this).attr('disabled') !== 'disabled' && $(this).attr('type') !== 'hidden' && $(this).attr('id') !== 'type_quantite') { //TODO type quantite trop specifique -> pq ne pas passer par celui de script-wiilog ? (et ajouter la classe checkbox)
                $(this).val("");
            }
        });
        // on vide tous les select2
        let selects = modal.find('.modal-body').find('.select2, .ajax-autocompleteFournisseur');
        selects.each(function () {
            $(this).val(null).trigger('change');
        });
        // on remet toutes les checkboxes sur off
        let checkboxes = modal.find('.checkbox');
        checkboxes.each(function () {
            $(this).prop('checked', false);
        })
    } else {
        if (typeof (data.codeError) != 'undefined') {
            switch (data.codeError) {
                case 'DOUBLON-REF':
                    modal.find('.is-invalid').removeClass('is-invalid');
                    modal.find('#reference').addClass('is-invalid');
                    break;
            }
        }
    }
}

function clearDemandeContent() {
    $('.plusDemandeContent').find('#collecteShow, #livraisonShow').addClass('d-none');
    $('.plusDemandeContent').find('#collecteShow, #livraisonShow').removeClass('d-block');
    //TODO supprimer partout où pas nécessaire d-block
}

function initTableRefArticle() {
    let url = Routing.generate('ref_article_api', true);
    $.post(Routing.generate('ref_article_api_columns'), function (columns) {
        let tableRefArticleConfig = {
            processing: true,
            serverSide: true,
            paging: true,
            order: [[1, 'asc']],
            ajax: {
                'url': url,
                'type': 'POST',
                'dataSrc': function (json) {
                    return json.data;
                }
            },
            length: 10,
            columns: columns.map(function (column) {
                return {
                    ...column,
                    class: column.title === 'Actions' ? 'noVis' : undefined,
                    title: column.title === 'Actions' ? '' : column.title
                }
            }),
            columnDefs: [
                {
                    orderable: false,
                    targets: 0
                }
            ],
            drawConfig: {
                needsResize: true
            },
            rowConfig: {
                needsRowClickAction: true
            },
            isArticleOrRefSpecifConfig: {
                columns,
                tableFilter: 'tableRefArticle_id_filter'
            },
        };
        const tableRefArticle = initDataTable('tableRefArticle_id', tableRefArticleConfig);
        tableRefArticle.on('responsive-resize', function () {
            resizeTable();
        });
        pageTables.length = 0;
        pageTables.push(tableRefArticle);
    });
}

function resizeTable() {
    pageTables[0]
        .columns.adjust()
        .responsive.recalc();
}

function showDemande(bloc) {
    let $livraisonShow = $('#livraisonShow');
    let $collecteShow = $('#collecteShow');

    if (bloc.data("title") == "livraison") {
        $collecteShow.removeClass('d-block');
        $collecteShow.addClass('d-none');
        $collecteShow.find('div').find('select, .quantite').removeClass('data');
        $collecteShow.find('.data').removeClass('needed');

        $livraisonShow.removeClass('d-none');
        $livraisonShow.addClass('d-block');
        $livraisonShow.find('div').find('select, .quantite').addClass('data');
        $livraisonShow.find('.data').addClass('needed');

        setMaxQuantityByArtRef($livraisonShow.find('#quantity-to-deliver'));

    } else if (bloc.data("title") == "collecte") {
        $collecteShow.removeClass('d-none');
        $collecteShow.addClass('d-block');
        $collecteShow.find('div').find('select, .quantite').addClass('data');
        $collecteShow.find('.data').addClass('needed');

        $livraisonShow.removeClass('d-block');
        $livraisonShow.addClass('d-none');
        $livraisonShow.find('div').find('select, .quantite').removeClass('data')
        $livraisonShow.find('.data').removeClass('needed');
    }
}


// affiche le filtre après ajout
function displayNewFilter(data) {
    $('#filters').append(data.filterHtml);
    if ($printTag.is('button')) {
        $printTag.addClass('btn-primary');
        $printTag.removeClass('btn-disabled');
        $printTag.addClass('pointer');
    } else {
        $printTag.removeClass('disabled');
        $printTag.addClass('pointer');
    }
    managePrintButtonTooltip(false, $printTag.is('button') ? $printTag.parent() : $printTag);
    $('#noFilters').addClass('d-none');
    $printTag.removeClass('has-tooltip');
    $printTag.tooltip('dispose');
    pageTables[0].clear();
    pageTables[0].ajax.reload();
    initTooltips($('.has-tooltip'));
}

function removeFilter() {
    let params = JSON.stringify({'filterId': $(this).parents('.filter').find('.filter-id').val()});
    $(this).parents('.filter').tooltip('dispose');
    $(this).parents('.filter').parent().remove();
    $.post(Routing.generate('filter_ref_delete', true), params, function () {
        pageTables[0].clear();
        pageTables[0].ajax.reload();
    });
    if ($('#filters').find('.filter').length <= 0) {
        $('#noFilters').removeClass('d-none');
        if ($('#tableRefArticle_id_filter input').val() === '') {
            if ($printTag.is('button')) {
                $printTag
                    .addClass('btn-disabled')
                    .removeClass('btn-primary');
                managePrintButtonTooltip(true, $printTag.parent());
            } else {
                $printTag
                    .removeClass('pointer')
                    .addClass('disabled')
                    .addClass('has-tooltip');
                managePrintButtonTooltip(true, $printTag);
            }
            $printTag.removeClass('d-none');
        }
    }
}

// modale ajout d'un filtre, affichage du champ "contient" en fonction du champ sélectionné
function displayFilterValue(elem) {
    let type = elem.find(':selected').data('type');
    let val = elem.find(':selected').val();
    let modalBody = elem.closest('.modal-body');

    let label = '';
    let datetimepicker = false;
    switch (type) {
        case 'booleen':
            label = 'Oui / Non';
            type = 'checkbox';
            break;
        case 'number':
        case 'list':
            label = 'Valeur';
            break;
        case 'list multiple':
            label = 'Contient';
            break;
        case 'date':
            label = 'Date';
            type = 'text';
            datetimepicker = true;
            break;
        case 'datetime':
            label = 'Date et heure'
            type = 'text';
            datetimepicker = true;
            break;
        case 'sync':
            label = 'Oui / Non';
            type = 'checkbox';
            break;
        default:
            label = 'Contient';
    }

    // cas particulier de liste déroulante pour type
    if (type === 'list' || type === 'list multiple') {
        let params = {
            'value': val
        };
        $.post(Routing.generate('display_field_elements'), JSON.stringify(params), function (data) {
            modalBody.find('.input-group').html(data);
            $('.list-multiple').select2();
        }, 'json');
    } else {
        modalBody.find('.input-group').html('<input type="' + type + '" class="form-control cursor-default data needed ' + type + '" id="value" name="value">');
        if (datetimepicker) initDateTimePicker('#modalNewFilter .text');
    }

    elem.closest('.modal-body').find('.valueLabel').text(label);
}

let recupIdRefArticle = function (div) {
    let id = div.data('id');
    $('#submitPlusDemande').val(id);
    $('.editChampLibre').html('');
    $('.boutonCreationDemande').addClass('d-none');
};

let ajaxPlusDemandeContent = function (button, demande) {
    let plusDemandeContent = $('.plusDemandeContent');
    let editChampLibre = $('.editChampLibre');
    let modalFooter = button.closest('.modal').find('.modal-footer');
    plusDemandeContent.html('');
    editChampLibre.html('');
    modalFooter.addClass('d-none');

    xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            dataReponse = JSON.parse(this.responseText);
            if (dataReponse.plusContent) {
                plusDemandeContent.html(dataReponse.plusContent);
            } else {
                //TODO gérer erreur
            }
            if (dataReponse.editChampLibre) {
                editChampLibre.html(dataReponse.editChampLibre);
                modalFooter.removeClass('d-none');
            }
            if (dataReponse.temp || dataReponse.byRef) {
                modalFooter.removeClass('d-none');
            } else {
                //TODO gérer erreur
            }
            showDemande(button);
            ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement-edit'));
        }
    }
    let json = {
        'demande': demande,
        'id': $('#submitPlusDemande').val(),
    };
    let Json = JSON.stringify(json)
    let path = Routing.generate('ajax_plus_demande_content', true);
    xhttp.open("POST", path, true);
    xhttp.send(Json);
}

let ajaxEditArticle = function ($select) {
    const selectVal = $select.val();
    if (selectVal) {
        let modalFooter = $select.closest('.modal').find('.modal-footer');
        let path = Routing.generate('article_show', true);
        let params = {id: $select.val(), isADemand: 1};

        $.post(path, JSON.stringify(params), function (data) {
            if (data) {
                $('.editChampLibre').html(data);
                ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement-edit'));
                toggleRequiredChampsLibres($select.closest('.modal').find('#type'), 'edit');
                $('#livraisonShow').find('#quantityToTake').removeClass('d-none').addClass('data');
                modalFooter.removeClass('d-none');
                setMaxQuantityByArtRef($('#livraisonShow').find('#quantity-to-deliver'));
            }
        }, 'json');
        modalFooter.addClass('d-none');
    }
}

//initialisation editeur de texte une seule fois
let editorNewReferenceArticleAlreadyDone = false;

function initNewReferenceArticleEditor(modal) {
    if (!editorNewReferenceArticleAlreadyDone) {
        initEditor('.editor-container-new');
        editorNewReferenceArticleAlreadyDone = true;
    }
    ajaxAutoFournisseurInit($('.ajax-autocompleteFournisseur'));
    ajaxAutoFournisseurInit($('.ajax-autocompleteFournisseurLabel'), '', 'demande_label_by_fournisseur');
    ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement'));
    clearModal(modal);
}

function deleteArticleFournisseur(button) {
    xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            dataReponse = JSON.parse(this.responseText);
            $('#articleFournisseursEdit').html(dataReponse);
        }
    }

    let path = Routing.generate('ajax_render_remove_fournisseur', true);
    let sendArray = {};
    sendArray['articleF'] = $(button).data('value');
    sendArray['articleRef'] = $(button).data('title');
    let toSend = JSON.stringify(sendArray);
    xhttp.open("POST", path, true);
    xhttp.send(toSend);
}

function passArgsToModal(button) {
    let path = Routing.generate('article_fournisseur_can_delete', true);
    let params = JSON.stringify({articleFournisseur: $(button).data('value')});
    $.post(path, params, function (response) {
        if (response) {
            $('#modalDeleteFournisseur').find('.modal-body').html('Voulez-vous réellement supprimer le lien entre ce<br> fournisseur et cet article ? ');
            $("#submitDeleteFournisseur").data('value', $(button).data('value'));
            $("#submitDeleteFournisseur").data('title', $(button).data('title'));
            $('#modalDeleteFournisseur').find('#submitDeleteFournisseur').removeClass('d-none');
        } else {
            $('#modalDeleteFournisseur').find('.modal-body').html('Cet article fournisseur est lié à des articles<br> il est impossible de le supprimer');
            $('#modalDeleteFournisseur').find('#submitDeleteFournisseur').addClass('d-none');
        }
    }, 'json');
}

function setMaxQuantityByArtRef(input) {
    let val = 0;
    $('input[name="quantite"]').each(function () {
        if ($(this).val() !== '' && $(this).val()) {
            val = $(this).val();
        }
    });
    input.attr('max', val);
}

function initRequiredChampsFixes(button) {
    let params = {id: button.data('id')};
    let path = Routing.generate('get_quantity_type');

    $.post(path, JSON.stringify(params), function (data) {
        displayRequiredChampsFixesByTypeQuantiteReferenceArticle(data, button)
    }, 'json');
}

function toggleRequiredChampsFixes(button) {
    let $modal = button.closest('.modal');
    clearErrorMsg(button);
    clearInvalidInputs($modal);
    displayRequiredChampsFixesByTypeQuantiteReferenceArticle(button.data('title'), button);
}

function submitPlusAndGoToDemande(button) {
    let modal = button.closest('.modal');
    let path = Routing.generate('plus_demande');

    submitActionRefArticle(modal, path, redirectToDemande);
}

function redirectToDemande() {
    let livraisonId = $('.data[name="livraison"]').val();
    let collecteId = $('.data[name="collecte"]').val();

    let demandeId = null;
    let demandeType = null;
    if (typeof (collecteId) !== 'undefined') {
        demandeId = collecteId;
        demandeType = 'collecte';
    } else if (typeof (livraisonId) !== 'undefined') {
        demandeId = livraisonId;
        demandeType = 'demande';
    }

    window.location.href = Routing.generate(demandeType + '_show', {'id': demandeId});
}

function saveRapidSearch() {
    let searchesWanted = [];
    $('#rapidSearch tbody td').each(function () {
        searchesWanted.push($(this).html());
    });
    let params = {
        recherches: searchesWanted
    };
    let json = JSON.stringify(params);
    $.post(Routing.generate('update_user_searches', true), json, function (data) {
        $("#modalRapidSearch").find('.close').click();
        pageTables[0].search(pageTables[0].search()).draw();
    });
}

function printReferenceArticleBarCode($button, event) {
    if (!$button.hasClass('disabled')) {
        if (pageTables[0].data().count() > 0) {
            window.location.href = Routing.generate(
                'reference_article_bar_codes_print',
                {
                    length: pageTables[0].page.info().length,
                    start: pageTables[0].page.info().start,
                    search: $('#tableRefArticle_id_filter input').val()
                },
                true
            );
        } else {
            alertErrorMsg('Les filtres et/ou la recherche n\'ont donnés aucun résultats, il est donc impossible de les imprimer.', true);
        }
    } else {
        event.stopPropagation();
    }
}

function displayActifOrInactif(select, onInit) {
    let donnees;
    if (select.is(':checked')) {
        donnees = 'actif';
    } else {
        donnees = 'consommé';
    }

    let params = {donnees: donnees};
    let path = Routing.generate('reference_article_actif_inactif');

    $.post(path, JSON.stringify(params), function () {
        if (!onInit) pageTables[0].ajax.reload();
    });
}

function initDatatableMovements(referenceArticleId) {
    extendsDateSort('customDate');
    let pathRefMouvements = Routing.generate('ref_mouvements_api', {referenceArticle: referenceArticleId}, true);
    let tableRefMvtOptions = {

        ajax: {
            "url": pathRefMouvements,
            "type": "POST"
        },
        drawConfig: {
            needsResize: true
        },
        domConfig: {
            removeInfo: true,
        },
        columns: [
            {"data": 'Date', 'title': 'Date', 'type': 'customDate'},
            {"data": 'from', 'title': 'Issu de', className: 'noVis'},
            {"data": 'ArticleCode', 'title': 'Code article'},
            {"data": 'Quantity', 'title': 'Quantité'},
            {"data": 'Origin', 'title': 'Origine'},
            {"data": 'Destination', 'title': 'Destination'},
            {"data": 'Type', 'title': 'Type'},
            {"data": 'Operator', 'title': 'Opérateur'}
        ],
    };
    initDataTable('tableMouvements', tableRefMvtOptions);
}

function showRowMouvements(button) {

    let id = button.data('id');
    let params = JSON.stringify(id);
    let path = Routing.generate('ref_mouvements_list', true);
    let modal = $('#modalShowMouvements');

    $.post(path, params, function (data) {
        modal.find('.modal-body').html(data);
        initDatatableMovements(id);
    }, 'json');
}

function toggleEmergency($switch) {
    if ($switch.is(':checked')) {
        $('.emergency-comment').removeClass('d-none');
    } else {
        $('.emergency-comment').addClass('d-none');
        $('.emergency-comment').val('');
    }
}

function updateQuantity(referenceArticleId) {
    let path = Routing.generate('update_qte_refarticle', {referenceArticle: referenceArticleId}, true);
    $.ajax({
        url: path,
        type: 'patch',
        dataType: 'json',
        success: (response) => {
            if (response.success) {
                pageTables[0].ajax.reload();
                alertSuccessMsg('Les quantités de la réference article ont bien été recalculées.');
            } else {
                alertErrorMsg('Une erreur lors du calcul des quantités est survenue');
            }
        }
    });
}
