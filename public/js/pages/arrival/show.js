$('.select2').select2();
let tableHistoLitige;
let tablePacks;

let tableArrivageLitiges
$(function () {
    extendsDateSort('customDate');

    const arrivalId = Number($('#arrivageId').val());
    const query = GetRequestQuery();
    let addPacks = $('#addPacks').val();
    if (addPacks) {
        $('#btnModalAddPacks').click();
    }

    let printPacks = Number(Boolean(Number($('#printPacks').val())));
    let printArrivage = Number(Boolean(Number($('#printArrivage').val())));

    if (printPacks || printArrivage) {
        printArrival({
            arrivalId: arrivalId,
            printPacks: printPacks,
            printArrivage: printArrivage
        });
    }
    SetRequestQuery({});

    const $modalNewDispatch = $("#modalNewDispatch");
    const $buttonNewDispatch = $(`.dispatch-button`);
    $($buttonNewDispatch).on(`click`, function () {
        $modalNewDispatch.modal(`show`);
    });

    initDispatchCreateForm($modalNewDispatch, 'arrivals', [arrivalId]);

    AJAX
        .route(AJAX.GET, 'arrival_list_packs_api_columns', {})
        .json()
        .then((columns) => {
            const packDatatable = initPackDatatable(arrivalId, columns);
            initPackModals(arrivalId, packDatatable);
        });

    const disputeDatatable = initDisputeDatatable(arrivalId);
    initDisputeModals(arrivalId, disputeDatatable);

    initArrivalModals();

    if (query.reserve) {
        $('.new-dispute-modal').click();
    }
});

function openTableHisto(dispute = undefined) {
    dispute = dispute || $('[name="disputeId"]').val();
    let pathHistoLitige = Routing.generate('dispute_histo_api', {dispute: dispute}, true);
    let tableHistoLitigeConfig = {
        ajax: {
            "url": pathHistoLitige,
            "type": AJAX.POST
        },
        serverSide: true,
        order: [['date', 'asc']],
        columns: [
            {data: 'user', name: 'Utilisateur', title: Translation.of('Traçabilité', 'Général', 'Utilisateur')},
            {data: 'date', name: 'date', title: Translation.of('Traçabilité', 'Général', 'Date')},
            {data: 'comment', name: 'commentaire', title: Translation.of('Général', '', 'Modale', 'Commentaire')},
            {data: 'statusLabel', name: 'status', title: Translation.of('Qualité', 'Litiges', 'Statut')},
            {data: 'typeLabel', name: 'type', title: Translation.of('Qualité', 'Litiges', 'Type')},
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

                Camera.init(
                    modal.find(`.take-picture-modal-button`),
                    modal.find(`[name="files[]"]`)
                );

                modal.modal('show');
            }, 'json');
        }
    );
    modal.find(submit).attr('value', id);
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
    let path = Routing.generate('dispute_add_comment', {dispute: $('[name="disputeId"]').val()}, true);
    let commentLitige = $('#modalEditLitige').find('#litige-edit-commentaire');
    let dataComment = commentLitige.val();

    $.post(path, JSON.stringify(dataComment), function (response) {
        tableHistoLitige.ajax.reload();
        commentLitige.val('');
    });
}

function initPackDatatable(arrivalId, columns) {
    return initDataTable('tablePacks', {
        ajax: {
            "url": Routing.generate('packs_api', {arrivage: arrivalId}, true),
            "type": AJAX.POST,
        },
        domConfig: {
            removeInfo: true
        },
        processing: true,
        rowConfig: {
            needsRowClickAction: true
        },
        columns,
        order: [['code', 'asc']]
    });
}

function initDisputeDatatable(arrivalId) {
    let pathArrivageLitiges = Routing.generate('arrival_diputes_api', {arrivage: arrivalId}, true);
    let tableArrivageLitigesConfig = {
        domConfig: {
            removeInfo: true
        },
        ajax: {
            "url": pathArrivageLitiges,
            "type": AJAX.POST
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
    return initDataTable('tableArrivageLitiges', tableArrivageLitigesConfig);
}

function initPackModals(arrivalId, packDatatable) {
    let $modalAddPacks = $('#modalAddPacks');
    Form
        .create($modalAddPacks, {clearOnOpen: true})
        .addProcessor((data) => {
            data.append('arrivalId', arrivalId);
        })
        .addProcessor((data) => {
            const $packs = $modalAddPacks.find('[name="pack"]');
            const packs = $packs.toArray()
                .map((pack) => $(pack))
                .reduce((carry, $pack) => ({
                    ...carry,
                    [$pack.data('id')]: $pack.val()
                }), {});
            data.append('packs', JSON.stringify(packs));
        })
        .submitTo(
            AJAX.POST,
            'arrivage_add_pack',
            {
                tables: [packDatatable],
                success: (data) => {
                    if (data.packs && data.packs.length > 0) {
                        printArrival({
                            arrivalId: data.arrivageId,
                            printPacks: true,
                            printArrivage: false,
                            packs: data.packs.map(({id}) => id)
                        });
                    }
                }
            }
        );

    //édition d'UL
    const $modalEditPack = $('#modalEditPack');
    const $submitEditPack = $('#submitEditPack');
    const urlEditPack = Routing.generate('pack_edit', true);
    InitModal($modalEditPack, $submitEditPack, urlEditPack, {
        tables: [packDatatable],
        waitForUserAction: () => {
            return checkPossibleCustoms($modalEditPack);
        },
    });

    //suppression d'UL
    let modalDeletePack = $("#modalDeletePack");
    let SubmitDeletePack = $("#submitDeletePack");
    let urlDeletePack = Routing.generate('pack_delete', true);
    InitModal(modalDeletePack, SubmitDeletePack, urlDeletePack, {tables: [packDatatable], clearOnClose: true});
}

function initDisputeModals(arrivalId, disputeDatatable) {

    let $modalNewLitige = $('#modalNewLitige');
    Form
        .create($modalNewLitige, {clearOnOpen: true})
        .addProcessor((data) => {
            data.append('reloadArrivage', arrivalId);
        })
        .submitTo(
            AJAX.POST,
            "dispute_new",
            {
                tables: [disputeDatatable],
            }
        )
        .onOpen((event) => {
            const $button = $(event.relatedTarget)
            Modal.load('new_dispute_template', {id: arrivalId}, $modalNewLitige, $modalNewLitige.find('.modal-body'), {
                onOpen: () => {
                    Camera.init(
                        $modalNewLitige.find(`.take-picture-modal-button`),
                        $modalNewLitige.find(`[name="files[]"]`)
                    );
                }
            })
        });

    let $modalEditLitige = $('#modalEditLitige');
    Form
        .create($modalEditLitige, {clearOnOpen: true})
        .addProcessor((data) => {
            data.append('reloadArrivage', arrivalId);
        })
        .onOpen((event) => {
            const disputeId = $(event.relatedTarget).data('dispute');
            Modal.load('arrival_dispute_api_edit', {dispute: disputeId}, $modalEditLitige, $modalEditLitige.find('.modal-body'), {
                onOpen: () => {
                    Camera.init(
                        $modalEditLitige.find(`.take-picture-modal-button`),
                        $modalEditLitige.find(`[name="files[]"]`)
                    );
                    openTableHisto(disputeId)
                }
            })
        })
        .submitTo(
            AJAX.POST,
            'arrival_edit_dispute',
            {
                tables: [disputeDatatable],
            }
        );

    let ModalDeleteLitige = $("#modalDeleteLitige");
    let SubmitDeleteLitige = $("#submitDeleteLitige");
    let urlDeleteLitige = Routing.generate('litige_delete_arrivage', true);
    InitModal(ModalDeleteLitige, SubmitDeleteLitige, urlDeleteLitige, {tables: [disputeDatatable]});
}

function initArrivalModals() {
    let modalModifyArrivage = $('#modalEditArrivage');
    let submitModifyArrivage = $('#submitEditArrivage');
    let urlModifyArrivage = Routing.generate('arrivage_edit', true);
    InitModal(modalModifyArrivage, submitModifyArrivage, urlModifyArrivage, {success: (params) => arrivalCallback(false, params)});

    let modalDeleteArrivage = $('#modalDeleteArrivage');
    let submitDeleteArrivage = $('#submitDeleteArrivage');
    let urlDeleteArrivage = Routing.generate('arrivage_delete', true);
    InitModal(modalDeleteArrivage, submitDeleteArrivage, urlDeleteArrivage);
}
