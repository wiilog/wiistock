
// TODO AB
// import Routing from '../../router';
// import '../../form-reference-article';
let $printTag ;
$(function () {
    $printTag = $('#printTag');
    managePrintButtonTooltip(true, $printTag);
});

$('.select2').select2();
let tableRefArticle;
function InitialiserModalRefArticle(modal, submit, path, callback = function () { }, close = true) {
    submit.click(function () {
        submitActionRefArticle(modal, path, callback, close);
    });
}

function afterLoadingEditModal($button) {
    toggleRequiredChampsLibres($button, 'edit');
    initRequiredChampsFixes($button);
}

function submitActionRefArticle(modal, path, callback = null, close = true) {
    if (path === Routing.generate('save_column_visible', true)) {
        tableColumnVisible.search('').draw()
    }

    let { Data, missingInputs, wrongNumberInputs, doublonRef } = getDataFromModalReferenceArticle(modal);

    // si tout va bien on envoie la requête ajax...
    if (missingInputs.length == 0 && wrongNumberInputs.length == 0 && !doublonRef) {
        if (close == true) modal.find('.close').click();
        $.post(path, JSON.stringify(Data), function(data) {
            if (!data) {
                $('#cannotDelete').click();
            }
            if (data.new) {
                tableRefArticle.row.add(data.new).draw(false);
            } else if (data.delete) {
                tableRefArticle.row($('#delete' + data.delete).parents('div').parents('td').parents('tr')).remove().draw(false);
            } else if (data.edit) {
                tableRefArticle.row($('#edit' + data.id).parents('div').parents('td').parents('tr')).remove().draw(false);
                tableRefArticle.row.add(data.edit).draw(false);
            }
            if (callback !== null) callback(data, modal);

            initRemove();
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
    if (typeof(data.msg) == 'undefined') {
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
        if (typeof(data.codeError) != 'undefined') {
            switch(data.codeError) {
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

let modalRefArticleNew = $("#modalNewRefArticle");
let submitNewRefArticle = $("#submitNewRefArticle");
let urlRefArticleNew = Routing.generate('reference_article_new', true);
InitialiserModalRefArticle(modalRefArticleNew, submitNewRefArticle, urlRefArticleNew, displayErrorRA, false);

let modalDeleteRefArticle = $("#modalDeleteRefArticle");
let SubmitDeleteRefArticle = $("#submitDeleteRefArticle");
let urlDeleteRefArticle = Routing.generate('reference_article_delete', true);
InitialiserModalRefArticle(modalDeleteRefArticle, SubmitDeleteRefArticle, urlDeleteRefArticle);

let modalModifyRefArticle = $('#modalEditRefArticle');
let submitModifyRefArticle = $('#submitEditRefArticle');
let urlModifyRefArticle = Routing.generate('reference_article_edit', true);
InitialiserModalRefArticle(modalModifyRefArticle, submitModifyRefArticle, urlModifyRefArticle, displayErrorRA, false);

let modalPlusDemande = $('#modalPlusDemande');
let submitPlusDemande = $('#submitPlusDemande');
let urlPlusDemande = Routing.generate('plus_demande', true);
InitialiserModalRefArticle(modalPlusDemande, submitPlusDemande, urlPlusDemande);

let modalColumnVisible = $('#modalColumnVisible');
let submitColumnVisible = $('#submitColumnVisible');
let urlColumnVisible = Routing.generate('save_column_visible', true);
InitialiserModalRefArticle(modalColumnVisible, submitColumnVisible, urlColumnVisible);

let modalNewFilter = $('#modalNewFilter');
let submitNewFilter = $('#submitNewFilter');
let urlNewFilter = Routing.generate('filter_ref_new', true);
InitialiserModalRefArticle(modalNewFilter, submitNewFilter, urlNewFilter, displayNewFilter, true);

let url = Routing.generate('ref_article_api', true);

$(function () {
    initTableRefArticle();
});

function initTableRefArticle() {
    $.post(Routing.generate('ref_article_api_columns'), function (columns) {
        tableRefArticle = $('#tableRefArticle_id')
            .on('error.dt', (a,b,c,d) => {
                console.log(a,b,c,d)
            })
            .DataTable({
                processing: true,
                serverSide: true,
                paging: true,
                scrollX: true,
                order: [[1, 'asc']],
                ajax: {
                    'url': url,
                    'type': 'POST',
                    'dataSrc': function (json) {
                        return json.data;
                    }
                },
                initComplete: function() {
                    hideSpinner($('#spinner'));
                    initRemove();
                    hideAndShowColumns(columns);
                    overrideSearch($('#tableRefArticle_id_filter input'), tableRefArticle, function($input) {
                        let $printBtn = $('.justify-content-end').find('#printTag');

                        if ($input.val() === '') {
                            $printBtn.addClass('btn-disabled');
                            $printBtn.removeClass('btn-primary');
                        } else {
                            $printBtn.removeClass('btn-disabled');
                            $printBtn.addClass('btn-primary');
                        }
                    });
                },
                length: 10,
                columns: columns.map((column) => ({
                    ...column,
                    class: undefined
                })),
                columnDefs: [
                    {
                        orderable: false,
                        targets: 0
                    }
                ],
                language: {
                    url: "/js/i18n/dataTableLanguage.json",
                },
                "drawCallback": function(settings) {
                    resizeTable();
                },
            });
    });
}


function resizeTable() {
    tableRefArticle
        .columns.adjust()
        .responsive.recalc();
}

//COLUMN VISIBLE
let tableColumnVisible = $('#tableColumnVisible_id').DataTable({
    language: {
        url: "/js/i18n/dataTableLanguage.json",
    },
    "paging": false,
    "info": false,
    "searching": false
});

function showOrHideColumn(check) {

    let columnName = check.data('name');

    let column = tableRefArticle.column(columnName + ':name');

    column.visible(!column.visible());

    let tableRefArticleColumn = $('#tableRefArticle_id_wrapper');
    tableRefArticleColumn.find('th, td').removeClass('hide');
    tableRefArticleColumn.find('th, td').addClass('display');
    check.toggleClass('data');
}

function hideAndShowColumns(columns) {
    tableRefArticle.columns().every(function(index) {
        this.visible(columns[index].class !== 'hide');
    });
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
    $('.justify-content-end').find('.printButton').removeClass('btn-disabled');
    tableRefArticle.clear();
    tableRefArticle.ajax.reload();
    managePrintButtonTooltip(false, $printTag);
}

// suppression du filtre au clic dessus
function initRemove() {
    // $('.filter-bloc').on('click', removeFilter); //TODO filtres et/ou
    $('.filter').on('click', removeFilter);
    tableRefArticle.on('responsive-resize', function (e, datatable) {
        resizeTable();
    });
}

function removeFilter() {
    $(this).remove();
    let params = JSON.stringify({ 'filterId': $(this).find('.filter-id').val() });
    $.post(Routing.generate('filter_ref_delete', true), params, function () {
        tableRefArticle.clear();
        tableRefArticle.ajax.reload();
    });
    if($('#filters').find('.filter').length <= 0){
        managePrintButtonTooltip(true, $printTag);
        $('.justify-content-end').find('.printButton').addClass('btn-disabled');
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
        case 'boolean':
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
        modalBody.find('.input-group').html('<input type="' + type + '" class="form-control cursor-default data ' + type + '" id="value" name="value">');
        if (datetimepicker) initDateTimePicker('#modalNewFilter .text');
    }

    elem.closest('.modal-body').find('.valueLabel').text(label);
}

function displayErrorRA(data, modal) {
    if (data.success === true) {
        modal.find('.close').click();
    } else {
        modal.find('.error-msg').html(data.msg);
    }
}

let recupIdRefArticle = function (div) {
    let id = div.data('id');
    $('#submitPlusDemande').val(id);
    $('.editChampLibre').html('');
}

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
            } if (dataReponse.temp || dataReponse.byRef) {
                modalFooter.removeClass('d-none');
            }
            else {
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

let ajaxEditArticle = function (select) {
    let modalFooter = select.closest('.modal').find('.modal-footer');
    let path = Routing.generate('article_api_edit', true);
    let params = { id: select.val(), isADemand: 1 };

    $.post(path, JSON.stringify(params), function(data) {
        if (data) {
            $('.editChampLibre').html(data);
            ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement-edit'));
            toggleRequiredChampsLibres(select.closest('.modal').find('#type'), 'edit');
            $('#livraisonShow').find('#quantityToTake').removeClass('d-none').addClass('data');
            modalFooter.removeClass('d-none');
            setMaxQuantityByArtRef($('#livraisonShow').find('#quantity-to-deliver'));
        }
    }, 'json');
    modalFooter.addClass('d-none');
}

//initialisation editeur de texte une seule fois
let editorNewReferenceArticleAlreadyDone = false;
function initNewReferenceArticleEditor(modal) {
    if (!editorNewReferenceArticleAlreadyDone) {
        initEditor('.editor-container-new');
        editorNewReferenceArticleAlreadyDone = true;
    }
    ajaxAutoFournisseurInit($('.ajax-autocompleteFournisseur'));
    ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement'));
    clearModal(modal);
};

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
    $.post(path, params, function(response) {
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

function addFournisseurEdit(button) {
    let $modal = button.closest('.modal-body');

    xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            dataReponse = JSON.parse(this.responseText);
            $modal.find('#articleFournisseursEdit').parent().append(dataReponse);
            ajaxAutoFournisseurInit($('.ajax-autocompleteFournisseur'));
        }
    };
    let path = Routing.generate('ajax_render_add_fournisseur', true);
    xhttp.open("POST", path, true);
    xhttp.send();
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

    $.post(path, JSON.stringify(params), function(data) {
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

    window.location.href = Routing.generate(demandeType + '_show', { 'id': demandeId });
}

function saveRapidSearch() {
    let searchesWanted = [];
    $('#rapidSearch tbody td').each(function() {
        searchesWanted.push($(this).html());
    });
    let params = {
        recherches: searchesWanted
    };
    let json = JSON.stringify(params);
    $.post(Routing.generate('update_user_searches', true), json, function(data) {
        $("#modalRapidSearch").find('.close').click();
        tableRefArticle.search(tableRefArticle.search()).draw();
    });
}

function printReferenceArticleBarCode() {
    window.location.href = Routing.generate(
        'reference_article_bar_codes_print',
        {length: tableRefArticle.page.info().length, start: tableRefArticle.page.info().start, search: $('#tableRefArticle_id_filter input').val()},
        true
    );
}

function displayActifOrInactif(select){
    let donnees;
    if (select.is(':checked')) {
        donnees = 'actif';
    } else {
        donnees = 'inactif';
    }

    let params = {donnees: donnees};
    let path = Routing.generate('reference_article_actif_inactif');

    $.post(path, JSON.stringify(params), function(){
        tableRefArticle.ajax.reload();
    });
}

function initDatatableMovements(id) {
    let pathRefMouvements = Routing.generate('ref_mouvements_api', { 'id': id }, true);
    let tableRefMouvements = $('#tableMouvements').DataTable({
        "language": {
            url: "/js/i18n/dataTableLanguage.json",
        },
        ajax: {
            "url": pathRefMouvements,
            "type": "POST"
        },
        columns: [
            {"data": 'Date', 'title': 'Date'},
            {"data": 'Quantity', 'title': 'Quantité'},
            {"data": 'Origin', 'title': 'Origine'},
            {"data": 'Destination', 'title': 'Destination'},
            {"data": 'Type', 'title': 'Type'},
            {"data": 'Operator', 'title': 'Opérateur'}
        ],
    });
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
