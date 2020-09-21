$(function() {
    ajaxAutoUserInit($('.ajax-autocomplete-user'));
    ajaxAutoFournisseurInit($('.ajax-autocomplete-fournisseur'));
    ajaxAutoCompleteTransporteurInit($('.ajax-autocomplete-transporteur'));
    initPage();
    initDateTimePicker('#dateMin, #dateMax');
    initDateTimePicker('#dateStart', 'DD/MM/YYYY HH:mm', true, 0, 0);
    initDateTimePicker('#dateEnd', 'DD/MM/YYYY HH:mm', false,23, 59);

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_URGENCES);
    $.post(path, params, function(data) {
        displayFiltersSup(data);
    }, 'json');
});

function initPage() {
    let pathUrgences = Routing.generate('urgence_api', true);
    let tableUrgenceConfig = {
        processing: true,
        serverSide: true,
        ajax:{
            "url": pathUrgences,
            "type": "POST"
        },
        order: [[1, "desc"]],
        columns:[
            { "data": 'actions', 'title': '', 'orderable': false, className: 'noVis'},
            { "data": 'start', 'name' : 'start', 'title' : 'urgences.date de début', translated: true },
            { "data": 'end', 'name' : 'end', 'title' : 'urgences.date de fin', translated: true },
            { "data": 'commande', 'name' : 'commande', 'title' : 'urgences.numéro de commande', translated: true },
            { "data": 'postNb', 'name' : 'postNb', 'title' : 'N° poste' },
            { "data": 'buyer', 'name' : 'buyer', 'title' : 'urgences.acheteur', translated: true },
            { "data": 'provider', 'name' : 'provider', 'title' : 'Fournisseur' },
            { "data": 'carrier', 'name' : 'carrier', 'title' : 'Transporteur' },
            { "data": 'trackingNb', 'name' : 'trackingNb', 'title' : 'N° tracking transporteur' },
            { "data": 'arrivalDate', 'name' : 'arrivalDate', 'title' : 'urgences.Date arrivage', translated: true },
            {"data": 'arrivalNb', 'name' : 'arrivalNb', 'title' : "Numero d'arrivage"},
            {"data": 'createdAt', 'name': 'createdAt', 'title': 'Date de création'},
        ],
        drawConfig: {
            needsSearchOverride: true,
        },
        rowConfig: {
            needsRowClickAction: true,
        },
        columnDefs: [
            {
                "type": "customDate",
                "targets": [1, 2]
            }
        ],
    };
    let tableUrgence = initDataTable('tableUrgences', tableUrgenceConfig);

    let modalNewUrgence = $('#modalNewUrgence');
    let submitNewUrgence = $('#submitNewUrgence');
    let urlNewUrgence = Routing.generate('urgence_new');
    InitModal(modalNewUrgence, submitNewUrgence, urlNewUrgence, {
        tables: [tableUrgence],
        success : (data) => callbackUrgenceAction(data, modalNewUrgence, true)
    });

    let modalDeleteUrgence = $('#modalDeleteUrgence');
    let submitDeleteUrgence = $('#submitDeleteUrgence');
    let urlDeleteUrgence = Routing.generate('urgence_delete', true);
    InitModal(modalDeleteUrgence, submitDeleteUrgence, urlDeleteUrgence, {tables: [tableUrgence]});

    let modalModifyUrgence = $('#modalEditUrgence');
    let submitModifyUrgence = $('#submitEditUrgence');
    let urlModifyUrgence = Routing.generate('urgence_edit', true);
    InitModal(modalModifyUrgence, submitModifyUrgence, urlModifyUrgence, {
        tables: [tableUrgence],
        success : (data) => callbackUrgenceAction(data, modalModifyUrgence, true)
    });
}

function callbackEditFormLoading($modal, buyerId, buyerName) {
    initDateTimePicker('#modalEditUrgence .datepicker.dateStart', 'DD/MM/YYYY HH:mm', false, 0, 0);
    initDateTimePicker('#modalEditUrgence .datepicker.dateEnd', 'DD/MM/YYYY HH:mm', false, 23, 59);
    let $dateStartInput = $('#modalEditUrgence').find('.dateStart');
    let dateStart = $dateStartInput.attr('data-date');

    let $dateEndInput = $('#modalEditUrgence').find('.dateEnd');
    let dateEnd = $dateEndInput.attr('data-date');

    $dateStartInput.val(moment(dateStart, 'YYYY-MM-DD HH:mm').format('DD/MM/YYYY HH:mm'));
    $dateEndInput.val(moment(dateEnd, 'YYYY-MM-DD HH:mm').format('DD/MM/YYYY HH:mm'));

    ajaxAutoUserInit($modal.find('.ajax-autocomplete-user'));
    ajaxAutoFournisseurInit($modal.find('.ajax-autocomplete-fournisseur'));
    ajaxAutoCompleteTransporteurInit($modal.find('.ajax-autocomplete-transporteur'));

    if (buyerId && buyerName) {
        let option = new Option(buyerName, buyerId, true, true);
        const $selectBuyer = $modal.find('.ajax-autocomplete-user[name="acheteur"]');
        $selectBuyer.append(option).trigger('change');
    }
}

function callbackUrgenceAction({success, message}, $modal = undefined, resetDate = false) {
    if (success) {
        showBSAlert(message, 'success');
        if ($modal) {
            clearModal($modal);
            if (resetDate) {
                $('#dateStart').val(moment().hours(0).minutes(0).format('DD/MM/YYYY HH:mm'));
                $('#dateEnd').val(moment().hours(23).minutes(59).format('DD/MM/YYYY HH:mm'));
            }
            $modal.modal('hide');
        }
    }
    else {
        showBSAlert(message, 'danger');
    }
}
