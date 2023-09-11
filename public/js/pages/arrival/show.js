$('.select2').select2();
let tableHistoLitige;
let tablePacks;

$(function () {
    const query = GetRequestQuery();
    let addPacks = $('#addPacks').val();
    if (addPacks) {
        $('#btnModalAddPacks').click();
    }

    let printPacks = Number(Boolean(Number($('#printPacks').val())));
    let printArrivage = Number(Boolean(Number($('#printArrivage').val())));

    if (printPacks || printArrivage) {
        let params = {
            arrivageId: Number($('#arrivageId').val()),
            printPacks: printPacks,
            printArrivage: printArrivage
        };
        printArrival(params);
    }
    SetRequestQuery({});

    const $modalNewDispatch = $("#modalNewDispatch");
    const $buttonNewDispatch = $(`.dispatch-button`);
    $($buttonNewDispatch).on(`click`, function () {
        $modalNewDispatch.modal(`show`);
    });

    Form
        .create($modalNewDispatch)
        .on('change', '[name=customerName]', (event) => {
            const $customers = $(event.target)
            // pre-filling customer information according to the customer
            const [customer] = $customers.select2('data');
            $modalNewDispatch.find('[name=customerPhone]')?.val(customer?.phoneNumber);
            $modalNewDispatch.find('[name=customerRecipient]')?.val(customer?.recipient);
            $modalNewDispatch.find('[name=customerAddress]')?.val(customer?.address);
        })
        .onOpen(() => {
            initNewDispatchEditor('#modalNewDispatch');
            Modal
                .load(
                    'create_from_arrival_template',
                    {arrival: $buttonNewDispatch.data(`id`)},
                    $modalNewDispatch,
                    $modalNewDispatch.find(`.modal-body`)
                )
        })
        .submitTo(
            AJAX.POST,
            'dispatch_new',
            {
                success: ({redirect}) => window.location.href = redirect,
            }
        )

    $.post(Routing.generate('arrival_list_packs_api_columns'), function(columns){
        let pathPacks = Routing.generate('packs_api', {arrivage: $('#arrivageId').val()}, true);
        let tablePacksConfig = {
            ajax: {
                "url": pathPacks,
                "type": "POST"
            },
            domConfig: {
                removeInfo: true
            },
            processing: true,
            rowConfig: {
                needsRowClickAction: true
            },
            columns: columns,
            hideColumnConfig: {
                columns,
                tableFilter: 'tablePacks'
            },
            order: [['code', 'asc']]
        };
        tablePacks = initDataTable('tablePacks', tablePacksConfig);

        let modalAddPacks = $('#modalAddPacks');
        let submitAddPacks = $('#submitAddPacks');
        let urlAddPacks = Routing.generate('arrivage_add_pack', true);
        InitModal(modalAddPacks, submitAddPacks, urlAddPacks, {
            tables: [tablePacks],
            waitDatatable: true,
            success: (data) => {
                if (data.packs && data.packs.length > 0) {
                    printArrival({
                        arrivageId: data.arrivageId,
                        printPacks: true,
                        printArrivage: false,
                        packs: data.packs.map(({id}) => id)
                    });
                }
            }
        });

        //édition d'UL
        const $modalEditPack = $('#modalEditPack');
        const $submitEditPack = $('#submitEditPack');
        const urlEditPack = Routing.generate('pack_edit', true);
        InitModal($modalEditPack, $submitEditPack, urlEditPack, {
            tables: [tablePacks],
            waitForUserAction: () => {
                return checkPossibleCustoms($modalEditPack);
            },
        });

        //suppression d'UL
        let modalDeletePack = $("#modalDeletePack");
        let SubmitDeletePack = $("#submitDeletePack");
        let urlDeletePack = Routing.generate('pack_delete', true);
        InitModal(modalDeletePack, SubmitDeletePack, urlDeletePack, {tables: [tablePacks], clearOnClose: true});
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
            {data: 'Actions', name: 'actions', title: '', orderable: false, className: 'noVis'},
            {data: 'firstDate', name: 'firstDate', title: Translation.of('Général', null, 'Zone liste', 'Date de création')},
            {data: 'status', name: 'status', title: Translation.of('Qualité', 'Litiges', 'Statut')},
            {data: 'type', name: 'type', title: Translation.of('Qualité', 'Litiges', 'Type')},
            {data: 'updateDate', name: 'updateDate', title: Translation.of('Traçabilité', 'Arrivages UL', 'Détails arrivage UL - Liste des litiges', 'Date de modification')},
            {data: 'urgence', name: 'urgence', title: Translation.of('Traçabilité', 'Arrivages UL', 'Divers', 'Urgence'), visible: false},
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

    $(`.new-dispute-modal`).on(`click`, function () {
        getNewDisputeModalContent($(this));
    });

    if (query.reserve) {
        $('.new-dispute-modal').click();
    }
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
            {data: 'user', name: 'Utilisateur', title: Translation.of('Traçabilité', 'Général', 'Utilisateur')},
            {data: 'date', name: 'date', title: Translation.of('Traçabilité', 'Général', 'Date')},
            {data: 'commentaire', name: 'commentaire', title: Translation.of('Général', '', 'Modale', 'Commentaire')},
            {data: 'status', name: 'status', title: Translation.of('Qualité', 'Litiges', 'Statut')},
            {data: 'type', name: 'type', title: Translation.of('Qualité', 'Litiges', 'Type')},
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

    wrapLoadingOnActionButton(
        $button,
        () => {
            return $.post(path, JSON.stringify(params), function (data) {
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

                modal.modal('show');
            }, 'json');
        }
    );

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
        modal.find('#packEditLitige').val(data.packs).select2();
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
