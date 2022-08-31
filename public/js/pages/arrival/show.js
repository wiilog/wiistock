$('.select2').select2();
let tableHistoLitige;

$(function () {
    let addColis = $('#addColis').val();
    if (addColis) {
        $('#btnModalAddColis').click();
    }

    let printColis = Number(Boolean($('#printColis').val()));
    let printArrivage = Number(Boolean($('#printArrivage').val()));

    if (printColis || printArrivage) {
        let params = {
            arrivage: Number($('#arrivageId').val()),
            printColis: printColis,
            printArrivage: printArrivage
        };

        Wiistock.download(Routing.generate('print_arrivage_bar_codes', params, true));
    }

    $(`.dispatch-button`).on(`click`, function () {
        $(this).pushLoader(`black`);
        $.post(Routing.generate(`create_from_arrival_template`, {arrival: $(this).data(`id`)}, true))
            .then(({content}) => {
                $(this).popLoader();
                $(`body`).append(content);

                let $modalNewDispatch = $("#modalNewDispatch");
                $modalNewDispatch.modal(`show`);

                let $submitNewDispatch = $("#submitNewDispatch");
                let urlDispatchNew = Routing.generate('dispatch_new', true);
                InitModal($modalNewDispatch, $submitNewDispatch, urlDispatchNew);

                initNewDispatchEditor('#modalNewDispatch');
            });
    });

    let pathColis = Routing.generate('colis_api', {arrivage: $('#arrivageId').val()}, true);
    let tableColisConfig = {
        ajax: {
            "url": pathColis,
            "type": "POST"
        },
        domConfig: {
            removeInfo: true
        },
        processing: true,
        rowConfig: {
            needsRowClickAction: true
        },
        columns: [
            {"data": 'actions', 'name': 'actions',  'title': '', className: 'noVis', orderable: true},
            {"data": 'nature', 'name': 'nature',    'title': Translation.of('Traçabilité', 'Général', 'Nature')},
            {"data": 'code', 'name': 'code',        'title': Translation.of('Traçabilité', 'Général', 'Unités logistiques')},
            {"data": 'lastMvtDate', 'name': 'lastMvtDate',      'title':  Translation.of('Traçabilité', 'Général', 'Date dernier mouvement')},
            {"data": 'lastLocation', 'name': 'lastLocation',    'title':  Translation.of('Traçabilité', 'Général', 'Dernier emplacement')},
            {"data": 'operator', 'name': 'operator',            'title': Translation.of('Traçabilité', 'Général', 'Opérateur')},
        ],
        order: [['code', 'asc']]
    };
    let tableColis = initDataTable('tableColis', tableColisConfig);

    let modalAddColis = $('#modalAddColis');
    let submitAddColis = $('#submitAddColis');
    let urlAddColis = Routing.generate('arrivage_add_colis', true);
    InitModal(modalAddColis, submitAddColis, urlAddColis, {
        tables: [tableColis],
        waitDatatable: true,
        success: (data) => {
            if (data.packs && data.packs.length > 0) {
                window.location.href = Routing.generate(
                    'print_arrivage_bar_codes',
                    {
                        packs: data.packs.map(({id}) => id),
                        arrivage: data.arrivageId,
                        printColis: 1
                    },
                    true);
            }
        }
    });

    let pathArrivageLitiges = Routing.generate('arrivageLitiges_api', {arrivage: $('#arrivageId').val()}, true);
    let tableArrivageLitigesConfig = {
        domConfig: {
            removeInfo: true
        },
        ajax: {
            "url": pathArrivageLitiges,
            "type": "POST"
        },
        columns: [
            {"data": 'Actions', 'name': 'actions', 'title': '', orderable: false, className: 'noVis'},
            {"data": 'firstDate', 'name': 'firstDate', 'title': Translation.of('Traçabilité', 'Général', 'Date de création')},
            {"data": 'status', 'name': 'status', 'title': Translation.of('Traçabilité','Flux - Arrivages', 'Champs fixes', 'Statut')},
            {"data": 'type', 'name': 'type', 'title': Translation.of('Traçabilité','Flux - Arrivages', 'Champs fixes', 'Type')},
            {"data": 'updateDate', 'name': 'updateDate', 'title': Translation.of('Traçabilité','Flux - Arrivages', 'Détails arrivage - Liste des litiges', 'Date de modification')},
            {"data": 'urgence', 'name': 'urgence', 'title': 'urgence', visible: false},
        ],
        rowConfig: {
            needsColor: true,
            color: 'danger',
            needsRowClickAction: true,
            dataToCheck: 'urgence'
        },
        order: [['firstDate', 'desc']]
    };
    let tableArrivageLitiges = initDataTable('tableArrivageLitiges', tableArrivageLitigesConfig);
    extendsDateSort('customDate');

    let modalNewLitige = $('#modalNewLitige');
    let submitNewLitige = $('#submitNewLitige');
    let urlNewLitige = Routing.generate('dispute_new', {reloadArrivage: $('#arrivageId').val()}, true);
    InitModal(modalNewLitige, submitNewLitige, urlNewLitige, {tables: [tableArrivageLitiges]});

    let modalEditLitige = $('#modalEditLitige');
    let submitEditLitige = $('#submitEditLitige');
    let urlEditLitige = Routing.generate('litige_edit_arrivage', {reloadArrivage: $('#arrivageId').val()}, true);
    InitModal(modalEditLitige, submitEditLitige, urlEditLitige, {tables: [tableArrivageLitiges]});

    let ModalDeleteLitige = $("#modalDeleteLitige");
    let SubmitDeleteLitige = $("#submitDeleteLitige");
    let urlDeleteLitige = Routing.generate('litige_delete_arrivage', true);
    InitModal(ModalDeleteLitige, SubmitDeleteLitige, urlDeleteLitige, {tables: [tableArrivageLitiges]});

    let modalModifyArrivage = $('#modalEditArrivage');
    let submitModifyArrivage = $('#submitEditArrivage');
    let urlModifyArrivage = Routing.generate('arrivage_edit', true);
    InitModal(modalModifyArrivage, submitModifyArrivage, urlModifyArrivage, {success: (params) => arrivalCallback(false, params)});

    let modalDeleteArrivage = $('#modalDeleteArrivage');
    let submitDeleteArrivage = $('#submitDeleteArrivage');
    let urlDeleteArrivage = Routing.generate('arrivage_delete', true);
    InitModal(modalDeleteArrivage, submitDeleteArrivage, urlDeleteArrivage);

    let $modalNewDispatch = $("#modalNewDispatch");
    let $submitNewDispatch = $("#submitNewDispatch");
    let $submitNewDispatchWithBL = $("#submitNewDispatchWithBL");
    let urlDispatchNew = Routing.generate('dispatch_new', true);
    let urlDispatchNewWithBL = Routing.generate('dispatch_new', {printDeliveryNote: 1}, true);
    InitModal($modalNewDispatch, $submitNewDispatch, urlDispatchNew);
    InitModal($modalNewDispatch, $submitNewDispatchWithBL, urlDispatchNewWithBL);

    //édition de colis
    const $modalEditPack = $('#modalEditPack');
    const $submitEditPack = $('#submitEditPack');
    const urlEditPack = Routing.generate('pack_edit', true);
    InitModal($modalEditPack, $submitEditPack, urlEditPack, {
        tables: [tableColis],
        waitForUserAction: () => {
            return checkPossibleCustoms($modalEditPack);
        },
    });

    //suppression de colis
    let modalDeletePack = $("#modalDeletePack");
    let SubmitDeletePack = $("#submitDeletePack");
    let urlDeletePack = Routing.generate('pack_delete', true);
    InitModal(modalDeletePack, SubmitDeletePack, urlDeletePack, {tables: [tableColis], clearOnClose: true});

    $(`.new-dispute-modal`).on(`click`, function () {
        getNewDisputeModalContent($(this));
    });
});

function openTableHisto() {
    let pathHistoLitige = Routing.generate('histo_dispute_api', {dispute: $('#disputeId').val()}, true);
    let tableHistoLitigeConfig = {
        ajax: {
            "url": pathHistoLitige,
            "type": "POST"
        },
        order: [['date', 'asc']],
        columns: [
            {data: 'user', name: 'Utilisateur', title: 'Utilisateur'},
            {data: 'date', name: 'date', title: 'Date', "type": "customDate"},
            {data: 'commentaire', name: 'commentaire', title: 'Commentaire'},
            {data: 'status', name: 'status', title: 'Statut'},
            {data: 'type', name: 'type', title: 'Type'},
        ],
        domConfig: {
            needsPartialDomOverride: true,
        }
    };
    tableHistoLitige = initDataTable('tableHistoLitige', tableHistoLitigeConfig);
}

function editRowArrivage($button) {
    let path = Routing.generate('arrivage_edit_api', true);
    let modal = $('#modalEditArrivage');
    let submit = $('#submitEditArrivage');
    let id = $button.data('id');
    let params = {id: id};

    $.post(path, JSON.stringify(params), function (data) {
        modal.find('.error-msg').html('');
        modal.find('.modal-body').html(data.html);

        modal.find('#acheteursEdit').val(data.acheteurs).select2();
        modal.find('.select2').select2();
        initDateTimePicker('.date-cl');
        Select2Old.initFree($('.select2-free'));
        Select2Old.location(modal.find('.ajax-autocomplete-location'));
        Select2Old.user(modal.find('.ajax-autocomplete-user'));
        const $userFormat = $('#userDateFormat');
        const format = $userFormat.val() ? $userFormat.val() : 'd/m/Y';

        initDateTimePicker('.free-field-date', DATE_FORMATS_TO_DISPLAY[format]);
        initDateTimePicker('.free-field-datetime', DATE_FORMATS_TO_DISPLAY[format] + ' HH:mm');

        fillDatePickers('.free-field-date');
        fillDatePickers('.free-field-datetime', 'YYYY-MM-DD', true);
    }, 'json');

    modal.find(submit).attr('value', id);
}

function editRowLitigeArrivage(button, afterLoadingEditModal = () => {}, arrivageId, disputeId, disputeNumber) {
    let path = Routing.generate('litige_api_edit', true);
    let modal = $('#modalEditLitige');
    let submit = $('#submitEditLitige');

    let params = {
        disputeId,
        arrivageId: arrivageId
    };

    $.post(path, JSON.stringify(params), function (data) {
        modal.find('.error-msg').html('');
        modal.find('.modal-body').html(data.html);
        modal.find('#colisEditLitige').val(data.colis).select2();
        fillDemandeurField(modal);
        afterLoadingEditModal()
    }, 'json');

    modal.find(submit).attr('value', disputeId);
    $('#disputeNumberArrival').text(disputeNumber);
}

function deleteRowArrivage(button, modal, submit, hasLitige) {
    deleteRow(button, modal, submit);
    let hasLitigeText = modal.find('.hasLitige');
    if (hasLitige) {
        hasLitigeText.removeClass('d-none');
    } else {
        hasLitigeText.addClass('d-none');
    }
}

function getCommentAndAddHisto() {
    let path = Routing.generate('add_comment', {dispute: $('#disputeId').val()}, true);
    let commentLitige = $('#modalEditLitige').find('#litige-edit-commentaire');
    let dataComment = commentLitige.val();

    $.post(path, JSON.stringify(dataComment), function (response) {
        tableHistoLitige.ajax.reload();
        commentLitige.val('');
    });
}

function getNewDisputeModalContent($button) {
    $button.pushLoader(`white`);
    $.get(Routing.generate(`new_dispute_template`, {id: $button.data(`id`)}, true))
        .then(({content}) => {
            $button.popLoader();
            const $modalNewDispute = $(`#modalNewLitige`);
            $modalNewDispute.find(`.modal-body`).html(content);
            $modalNewDispute.modal(`show`);

            const buyers = $modalNewDispute
                .find(`input[name=disputeBuyersValues]`)
                .val()
                .split(',')
                .filter((buyer) => buyer);

            const $disputeBuyersSelect = $modalNewDispute.find(`select[name=acheteursLitige]`);
            buyers.forEach((value) => {
                $disputeBuyersSelect.append(new Option(value, value, false, false));
            });
            $disputeBuyersSelect.val(buyers).select2();

            const orderNumbersValues = $modalNewDispute
                .find(`input[name=orderNumbersValues]`)
                .val()
                .split(`,`)
                .filter((orderNumber) => orderNumber);

            const $orderNumbersSelect = $modalNewDispute.find(`select[name=numeroCommandeListLitige]`);
            orderNumbersValues.forEach((value) => {
                $orderNumbersSelect.append(new Option(value, value, false, false));
            });
            $orderNumbersSelect.val(orderNumbersValues).select2();

            const $operatorSelect = $modalNewDispute.find(`select[name=disputeReporter]`);
            const $loggedUserInput = $modalNewDispute.find('input[hidden][name="logged-user"]');
            let option = new Option($loggedUserInput.data('username'), $loggedUserInput.data('id'), true, true);
            $operatorSelect
                .val(null)
                .trigger('change')
                .append(option)
                .trigger('change');
        });
}
