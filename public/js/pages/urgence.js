$(function() {
    Select2Old.user();
    Select2Old.provider($('.ajax-autocomplete-fournisseur'));
    Select2Old.carrier($('.ajax-autocomplete-transporteur'));
    initPage();
    const $userFormat = $('#userDateFormat');
    const format = $userFormat.val() ? $userFormat.val() : 'd/m/Y';

    initDateTimePicker('#dateMin, #dateMax', DATE_FORMATS_TO_DISPLAY[format]);

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_URGENCES);
    $.post(path, params, function(data) {
        displayFiltersSup(data, true);
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
        order: [['start', "desc"]],
        columns:[
            { "data": 'actions', 'title': '', 'orderable': false, className: 'noVis'},
            { "data": 'start', 'name' : 'start', 'title' : Translation.of('Traçabilité', 'Urgences', 'Date de début', false) },
            { "data": 'end', 'name' : 'end', 'title' : Translation.of('Traçabilité', 'Urgences', 'Date de fin', false) },
            { "data": 'commande', 'name' : 'commande', 'title' : Translation.of('Traçabilité', 'Urgences', 'N° de commande', false) },
            { "data": 'postNb', 'name' : 'postNb', 'title' : Translation.of('Traçabilité', 'Urgences', 'N° poste', false) },
            { "data": 'buyer', 'name' : 'buyer', 'title' : Translation.of('Traçabilité', 'Urgences', 'Acheteur', false) },
            { "data": 'provider', 'name' : 'provider', 'title' : Translation.of('Traçabilité', 'Urgences', 'Fournisseur', false) },
            { "data": 'carrier', 'name' : 'carrier', 'title' : Translation.of('Traçabilité', 'Urgences', 'Transporteur', false) },
            { "data": 'trackingNb', 'name' : 'trackingNb', 'title' : Translation.of('Traçabilité', 'Urgences', 'N° tracking transporteur', false) },
            { "data": 'arrivalDate', 'name' : 'arrivalDate', 'title' : Translation.of('Traçabilité', 'Urgences', 'Date arrivage', false) },
            {"data": 'arrivalNb', 'name' : 'arrivalNb', 'title' : Translation.of('Traçabilité', 'Urgences', 'Numéro d\'arrivage', false)},
            {"data": 'createdAt', 'name': 'createdAt', 'title': Translation.of('Traçabilité', 'Urgences', 'Date de création', false)},
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
    const $userFormat = $('#userDateFormat');
    const format = $userFormat.val() ? $userFormat.val() : 'd/m/Y';
    initDateTimePicker('.datetime-field', DATE_FORMATS_TO_DISPLAY[format] + ' HH:mm');
    fillDatePickers('.datetime-field', 'YYYY-MM-DD', true);
}

function callbackEditFormLoading($modal, buyerId, buyerName) {
    Select2Old.user($modal.find('.ajax-autocomplete-user'));
    Select2Old.provider($modal.find('.ajax-autocomplete-fournisseur'));
    Select2Old.carrier($modal.find('.ajax-autocomplete-transporteur'));

    if (buyerId && buyerName) {
        let option = new Option(buyerName, buyerId, true, true);
        const $selectBuyer = $modal.find('.ajax-autocomplete-user[name="acheteur"]');
        $selectBuyer.append(option).trigger('change');
    }
    const $userFormat = $('#userDateFormat');
    const format = $userFormat.val() ? $userFormat.val() : 'd/m/Y';
    initDateTimePicker('.datetime-field', DATE_FORMATS_TO_DISPLAY[format] + ' HH:mm');
    fillDatePickers('.datetime-field', 'YYYY-MM-DD', true);
}

function callbackUrgenceAction({success, message}, $modal = undefined, resetDate = false) {
    if (success) {
        showBSAlert(message, 'success');
        if ($modal) {
            clearModal($modal);
            if (resetDate) {
                $('#dateStart').val(moment().hours(0).minutes(0).format('YYYY-MM-DD\\THH:mm'));
                $('#dateEnd').val(moment().hours(23).minutes(59).format('YYYY-MM-DD\\THH:mm'));
            }
            $modal.modal('hide');
        }
    }
    else {
        showBSAlert(message, 'danger');
    }
}
