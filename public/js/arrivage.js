let onFlyFormOpened = {};
let clicked = false;
$('.select2').select2();
let arrivageUrgentLoading = false;

$(function() {
    initDateTimePicker('#dateMin, #dateMax, .date-cl');
    initSelect2('#statut', 'Statut');
    initSelect2('#carriers', 'Transporteurs');

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_ARRIVAGE);

    $.post(path, params, function (data) {
        displayFiltersSup(data);
        initFilterDateToday();
    }, 'json');

    ajaxAutoUserInit($('.filters .ajax-autocomplete-user'), 'Destinataires');
    ajaxAutoFournisseurInit($('.ajax-autocomplete-fournisseur'), 'Fournisseurs');
    $('select[name="tableArrivages_length"]').on('change', function() {
        $.post(Routing.generate('update_user_page_length_for_arrivage'), JSON.stringify($(this).val()));
    });
});

let pathArrivage = Routing.generate('arrivage_api', true);
let tableArrivage = $('#tableArrivages').DataTable({
    responsive: true,
    serverSide: true,
    processing: true,
    pageLength: $('#pageLengthForArrivage').val(),
    language: {
        url: "/js/i18n/dataTableLanguage.json",
    },
    order: [[1, "desc"]],
    scrollX: true,
    ajax: {
        "url": pathArrivage,
        "type": "POST",
        'data': {
            'clicked': () => clicked,
        }
    },
    drawCallback: function(resp) {
        overrideSearch($('#tableArrivages_filter input'), tableArrivage);
        hideColumns(tableArrivage, resp.json.columnsToHide);
    },
    columns: [
        {"data": 'Actions', 'name': 'actions', 'title': 'Actions'},
        {"data": 'Date', 'name': 'date', 'title': 'Date'},
        {"data": "NumeroArrivage", 'name': 'numeroArrivage', 'title': $('#noArrTranslation').val()},
        {"data": 'Transporteur', 'name': 'transporteur', 'title': 'Transporteur'},
        {"data": 'Chauffeur', 'name': 'chauffeur', 'title': 'Chauffeur'},
        {"data": 'NoTracking', 'name': 'noTracking', 'title': 'N° tracking transporteur'},
        {"data": 'NumeroBL', 'name': 'numeroBL', 'title': 'N° commande / BL'},
        {"data": 'Fournisseur', 'name': 'fournisseur', 'title': 'Fournisseur'},
        {"data": 'Destinataire', 'name': 'destinataire', 'title': 'Destinataire'},
        {"data": 'Acheteurs', 'name': 'acheteurs', 'title': 'Acheteurs'},
        {"data": 'NbUM', 'name': 'NbUM', 'title': 'Nb UM'},
        {"data": 'Statut', 'name': 'Statut', 'title': 'Statut'},
        {"data": 'Utilisateur', 'name': 'Utilisateur', 'title': 'Utilisateur'},
    ],
    columnDefs: [
        {
            targets: 0,
            className: 'noVis'
        },
        {
            orderable: false,
            targets: [0]
        }
    ],
    headerCallback: function(thead) {
        $(thead).find('th').eq(2).attr('title', "n° d'arrivage");
    },
    "rowCallback" : function(row, data) {
        if (data.urgent === true) $(row).addClass('table-danger');
    },
    dom: '<"row"<"col-4"B><"col-4"l><"col-4"f>>t<"bottom"ip>r',
    buttons: [
        {
            extend: 'colvis',
            columns: ':not(.noVis)',
            className: 'dt-btn'
        },
        // {
        //     extend: 'csv',
        //     className: 'dt-btn'
        // }
    ],
    "lengthMenu": [10, 25, 50, 100],
});

function listColis(elem) {
    let arrivageId = elem.data('id');
    let path = Routing.generate('arrivage_list_colis_api', true);
    let modal = $('#modalListColis');
    let params = { id: arrivageId };

    $.post(path, JSON.stringify(params), function(data) {
        modal.find('.modal-body').html(data);
    }, 'json');
}

tableArrivage.on('responsive-resize', function (e, datatable) {
    datatable.columns.adjust().responsive.recalc();
});

let $modalNewArrivage = $("#modalNewArrivage");
let submitNewArrivage = $("#submitNewArrivage");
let urlNewArrivage = Routing.generate('arrivage_new', true);
let redirectAfterArrival = $('#redirect').val();
initModalWithAttachments($modalNewArrivage, submitNewArrivage, urlNewArrivage, tableArrivage, arrivalCreationCallback, redirectAfterArrival === 1);

let editorNewArrivageAlreadyDone = false;
let quillNew;

function arrivalCreationCallback({alertConfig = {}, ...response}) {
    const {autoHide, message, modalType, arrivalId} = alertConfig;

    const buttonConfigs = [
        {
            class: 'btn btn-success m-0 btn-action-on-hide',
            text: (modalType === 'yes-no-question' ? 'Oui' : 'Continuer'),
            action: ($modal) => {
                if (modalType === 'yes-no-question') {
                    if (!arrivageUrgentLoading) {
                        arrivageUrgentLoading = true;
                        $modal.find('.modal-footer-wrapper').addClass('d-none');
                        loadSpinner($modal.find('.spinner'));
                        setArrivalUrgent(arrivalId, response);
                    }
                }
                else {
                    treatArrivalCreation(response);
                    $modal.modal('hide')
                }
            }
        }
    ];

    if (modalType === 'yes-no-question') {
        buttonConfigs.unshift({
            class: 'btn btn-secondary m-0',
            text: 'Non',
            action: () => {
                arrivalCreationCallback({
                    alertConfig: {
                        autoHide: false,
                        message: 'Arrivage enregistré avec succès',
                        modalType: 'info',
                        arrivalId
                    },
                    ...response
                });
            }
        });
    }

    displayAlertModal(
        undefined,
        $('<div/>', {
            class: 'text-center',
            text: message
        }),
        buttonConfigs,
        (modalType === 'info') ? 'success' : undefined,
        autoHide
    );
}

function setArrivalUrgent(newArrivalId, arrivalResponseCreation) {
    const patchArrivalUrgentUrl = Routing.generate('patch_arrivage_urgent', {arrival: newArrivalId});
    $.ajax({
        type: 'PATCH',
        url: patchArrivalUrgentUrl,
        success: (secondResponse) => {
            arrivageUrgentLoading = false;
            if (secondResponse.success) {
                arrivalCreationCallback({
                    alertConfig: secondResponse.alertConfig,
                    ...arrivalResponseCreation
                });
            }
            else {
                displayAlertModal(
                    undefined,
                    $('<div/>', {
                        class: 'text-center',
                        text: 'Erreur dans la mise en urgence de l\'arrivage'
                    }),
                    [{
                        class: 'btn btn-secondary m-0',
                        text: 'OK',
                        action: ($modal) => {
                            $modal.modal('hide')
                        }
                    }],
                    'error'
                );
            }
        }
    });
}

function initNewArrivageEditor(modal) {
    let $modal = $(modal);
    onFlyFormOpened = {};
    onFlyFormToggle('fournisseurDisplay', 'addFournisseur', true);
    onFlyFormToggle('transporteurDisplay', 'addTransporteur', true);
    onFlyFormToggle('chauffeurDisplay', 'addChauffeur', true);
    if (!editorNewArrivageAlreadyDone) {
        quillNew = initEditor(modal + ' .editor-container-new');
        editorNewArrivageAlreadyDone = true;
    }
    ajaxAutoFournisseurInit($modal.find('.ajax-autocomplete-fournisseur'));
    ajaxAutoUserInit($modal.find('.ajax-autocomplete-user'));
    ajaxAutoCompleteTransporteurInit($modal.find('.ajax-autocomplete-transporteur'));
    ajaxAutoChauffeurInit($modal.find('.ajax-autocomplete-chauffeur'));
    $modal.find('.list-multiple').select2();
}

function treatArrivalCreation({redirectAfterAlert, printColis, printArrivage, statutConformeId, }) {
    if (!redirectAfterAlert) {
        $modalNewArrivage.find('.champsLibresBlock').html(response.champsLibresBlock);
        $('.list-multiple').select2();
        $modalNewArrivage.find('#statut').val(statutConformeId);
        let isPrintColisChecked = $modalNewArrivage.find('#printColisChecked').val();
        $modalNewArrivage.find('#printColis').prop('checked', isPrintColisChecked);

        if (printColis) {
            let path = Routing.generate('print_arrivage_colis_bar_codes', { arrivage: arrivageId }, true);
            window.open(path, '_blank');
        }
        if (printArrivage) {
            setTimeout(function() {
                let path = Routing.generate('print_arrivage_bar_code', { arrivage: arrivageId }, true);
                window.open(path, '_blank');
            }, 500);
        }
    }
    else {
        const arrivalShowUrl = createArrivageShowUrl(redirectAfterAlert, printColis, printArrivage);
        window.location.href = arrivalShowUrl;
    }
}

function createArrivageShowUrl(arrivageShowUrl, printColis, printArrivage) {
    const printColisNumber = (printColis === true) ? '1' : '0';
    const printArrivageNumber = (printArrivage === true) ? '1' : '0';
    return `${arrivageShowUrl}/${printColisNumber}/${printArrivageNumber}`;
}

