$(function() {
    $('.select2').select2();

    const tableHandling = initDatatable();
    initModals(tableHandling);

    initDateTimePicker();
    Select2Old.user($('.filter-select2[name="utilisateurs"]'), 'Demandeurs');
    Select2Old.user($('.filter-select2[name="receivers"]'), 'Destinataires');
    Select2Old.init($('.filter-select2[name="emergencyMultiple"]'), 'Urgences');

    // applique les filtres si pré-remplis
    let val = $('#filterStatus').val();

    if (val && val.length > 0) {
        let valuesStr = val.split(',');
        let valuesInt = [];
        valuesStr.forEach((value) => {
            valuesInt.push(parseInt(value));
        })
        $('#statut').val(valuesInt).select2();
    } else {
        // sinon, filtres enregistrés en base pour chaque utilisateur
        let path = Routing.generate('filter_get_by_page');
        let params = JSON.stringify(PAGE_HAND);
        $.post(path, params, function (data) {
            displayFiltersSup(data);
        }, 'json');
    }

    const $modalNewHandling = $("#modalNewHandling");
    $modalNewHandling.on('show.bs.modal', function () {
        initNewHandlingEditor("#modalNewHandling");
    });
});


function initNewHandlingEditor(modal) {
    Select2Old.location($('.ajax-autocomplete-location'));
    onTypeChange($(modal).find('select[name="type"]'));
}

function changeStatus(button) {
    let sel = $(button).data('title');
    let tog = $(button).data('toggle');
    let $statusHandling = $("#statusHandling");

    if ($(button).hasClass('not-active')) {
        if ($statusHandling.val() === "0") {
            $statusHandling.val("1");
        } else {
            $statusHandling.val("0");
        }
    }

    $('span[data-toggle="' + tog + '"]')
        .not('[data-title="' + sel + '"]')
        .removeClass('active')
        .addClass('not-active');

    $('span[data-toggle="' + tog + '"][data-title="' + sel + '"]')
        .removeClass('not-active')
        .addClass('active');
}

function callbackSaveFilter() {
    // supprime le filtre de l'url
    let str = window.location.href.split('/');
    if (str[5]) {
        window.location.href = Routing.generate('handling_index');
    }
}

function initDatatable() {
    const showReceiversColumn = Number($('#showReceiversColumn').val()) === 1;
    const receiversColumn = showReceiversColumn
        ? [{ "data":  'receivers', 'name': 'receivers', 'title': 'Destinataires', orderable: false}]
        : [];

    let pathHandling = Routing.generate('handling_api', true);
    let tableHandlingConfig = {
        serverSide: true,
        processing: true,
        order: [['creationDate', 'desc']],
        rowConfig: {
            needsRowClickAction: true,
            needsColor: true,
            color: 'danger',
            dataToCheck: 'emergency'
        },
        drawConfig: {
            needsSearchOverride: true,
        },
        ajax: {
            "url": pathHandling,
            "type": "POST",
            'data' : {
                'filterStatus': $('#filterStatus').val()
            },
        },
        columns: [
            { "data": 'Actions', 'name': 'Actions', 'title': '', className: 'noVis', orderable: false},
            { "data": 'number', 'name': 'number', 'title': 'Numéro de demande' },
            { "data": 'creationDate', 'name': 'creationDate', 'title': 'Date demande' },
            { "data": 'type', 'name': 'type', 'title': 'Type' },
            { "data": 'requester', 'name': 'requester', 'title': 'Demandeur' },
            { "data": 'subject', 'name': 'subject', 'title': 'services.Objet', translated: true },
            { "data": 'desiredDate', 'name': 'desiredDate', 'title': 'Date attendue' },
            { "data": 'validationDate', 'name': 'validationDate', 'title': 'Date de réalisation' },
            { "data": 'status', 'name': 'status', 'title': 'Statut' },
            { "data": 'emergency', 'name': 'emergency', 'title': 'Urgence' },
            // {
            //     "data": 'treatmentDelay',
            //     'name': 'treatmentDelay',
            //     'title': 'Temps de traitement opérateur',
            //     'tooltip': "Temps entre l’ouverture de la demande sur la nomade et la validation de cette dernière."
            // },
            { "data": 'carriedOutOperationCount', 'name': 'carriedOutOperationCount', 'title': 'services.Nombre d\'opération(s) réalisée(s)', translated: true },
            { "data": 'treatedBy', 'name': 'treatedBy', 'title': 'Traité par' },
            ...receiversColumn
        ]
    };
    return initDataTable('tableHandling_id', tableHandlingConfig);
}

function initModals(tableHandling) {
    let $modalNewHandling = $("#modalNewHandling");
    let $submitNewHandling = $("#submitNewHandling");
    let urlNewHandling = Routing.generate('handling_new', true);
    InitModal($modalNewHandling, $submitNewHandling, urlNewHandling, {tables: [tableHandling]});

    let $modalModifyHandling = $('#modalEditHandling');
    let $submitModifyHandling = $('#submitEditHandling');
    let urlModifyHandling = Routing.generate('handling_edit', true);
    InitModal($modalModifyHandling, $submitModifyHandling, urlModifyHandling, {tables: [tableHandling]});

    let $modalDeleteHandling = $('#modalDeleteHandling');
    let $submitDeleteHandling = $('#submitDeleteHandling');
    let urlDeleteHandling = Routing.generate('handling_delete', true);
    InitModal($modalDeleteHandling, $submitDeleteHandling, urlDeleteHandling, {tables: [tableHandling]});

    Select2Old.user($modalNewHandling.find('.ajax-autocomplete-user[name=receivers]'))
}
