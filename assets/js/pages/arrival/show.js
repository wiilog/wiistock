import {initEditPackModal, deletePack} from "@app/pages/pack/common";
import AJAX, {POST, GET, DELETE} from "@app/ajax";
import Modal from "@app/modal";
import Routing from '@app/fos-routing';
import {initDataTable} from "@app/datatable";
import Form from "@app/form";
import {initCommentHistoryForm} from "@app/pages/dispute/common";
import {initDispatchCreateForm, onDispatchTypeChange} from "@app/pages/dispatch/common";
import {printArrival, checkPossibleCustoms, arrivalCallback} from "@app/pages/arrival/common";

global.printArrival = printArrival;
global.onDispatchTypeChange = onDispatchTypeChange

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
        .route(GET, 'arrival_list_packs_api_columns', {})
        .json()
        .then((columns) => {
            const packDatatable = initPackDatatable(arrivalId, columns);
            initPackModals(arrivalId, packDatatable);
        });

    const disputeDatatable = initDisputeDatatable(arrivalId);
    initDisputeModals(arrivalId, disputeDatatable);

    if (query.reserve) {
        $('.new-dispute-modal').click();
    }

    let $modalEditArrivage = $('#modalEditArrivage');
    Form
        .create($modalEditArrivage)
        .submitTo(
            POST,
            "arrivage_edit",
            {
                success: (response) => {
                    arrivalCallback(false, response);
                    if (response.entete) {
                        $('.zone-entete').html(response.entete);
                    }
                }
            }
        )
        .onOpen((event) => {
            Modal.load('arrivage_edit_api', {id: arrivalId}, $modalEditArrivage, $modalEditArrivage.find('.modal-body'), {
                onOpen: (data) => {
                    $modalEditArrivage.find('.error-msg').html('');

                    $modalEditArrivage.find('#acheteursEdit').val(data.acheteurs).select2();
                    $modalEditArrivage.find('.select2').select2();
                    initDateTimePicker('.date-cl');
                    Select2Old.initFree($('.select2-free'));
                    Select2Old.location($modalEditArrivage.find('.ajax-autocomplete-location'));
                    Select2Old.user($modalEditArrivage.find('.ajax-autocomplete-user'));
                    const $userFormat = $('#userDateFormat');
                    const format = $userFormat.val() ? $userFormat.val() : 'd/m/Y';

                    initDateTimePicker('.free-field-date', DATE_FORMATS_TO_DISPLAY[format]);
                    initDateTimePicker('.free-field-datetime', DATE_FORMATS_TO_DISPLAY[format] + ' HH:mm');

                    fillDatePickers('.free-field-date');
                    fillDatePickers('.free-field-datetime', 'YYYY-MM-DD', true);

                    Camera.init(
                        $modalEditArrivage.find(`.take-picture-modal-button`),
                        $modalEditArrivage.find(`[name="files[]"]`)
                    );
                }
            })
        });

    $('.delete-arrival').on('click', function () {
        Modal.confirm({
            ajax: {
                method: DELETE,
                route: 'arrivage_delete',
                params: {
                    arrival: arrivalId
                },
            },
            message: Translation.of('Traçabilité', 'Arrivages UL', 'Divers', 'Voulez-vous réellement supprimer cet arrivage UL ?'),
            title: 'Supprimer l\'arrivage UL',
            validateButton: {
                color: 'danger',
                label: 'Supprimer',
            },
            cancelButton: {
                label: 'Annuler',
            },
        });
    });
});

function openTableHisto($modalEditLitige, dispute = undefined) {
    dispute = dispute || $('[name="disputeId"]').val();
    let pathHistoLitige = Routing.generate('dispute_histo_api', {dispute: dispute}, true);
    let tableHistoLitigeConfig = {
        ajax: {
            "url": pathHistoLitige,
            "type": POST
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
    tableHistoLitige = initDataTable($modalEditLitige.find('#tableHistoLitige'), tableHistoLitigeConfig);
    initCommentHistoryForm($modalEditLitige, tableHistoLitige);
}

function initPackDatatable(arrivalId, columns) {
    return initDataTable('tablePacks', {
        ajax: {
            "url": Routing.generate('packs_api', {arrivage: arrivalId}),
            "type": POST,
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
    let pathArrivageLitiges = Routing.generate('arrival_diputes_api', {arrivage: arrivalId});
    let tableArrivageLitigesConfig = {
        domConfig: {
            removeInfo: true
        },
        ajax: {
            "url": pathArrivageLitiges,
            "type": POST
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
        .create($modalAddPacks, {resetView: ['open', 'close']})
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
            POST,
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

    const $modalEditPack = $('#modalEditPack');
    initEditPackModal({
        tables: [packDatatable],
        waitForUserAction: () => {
            return checkPossibleCustoms($modalEditPack);
        },
    })

    $(document).on('click', '.delete-pack', function () {
        deletePack({ pack: $(this).data('id'), arrivage: arrivalId }, tablePacks);
    });
}

function initDisputeModals(arrivalId, disputeDatatable) {

    let $modalNewLitige = $('#modalNewLitige');
    Form
        .create($modalNewLitige, {resetView: ['open', 'close']})
        .addProcessor((data) => {
            data.append('reloadArrivage', arrivalId);
        })
        .submitTo(
            POST,
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
        .create($modalEditLitige, {resetView: ['open', 'close']})
        .addProcessor((data) => {
            data.append('reloadArrivage', arrivalId);
            data.append('comment', $modalEditLitige.find('[name="comment"]').val());
        })
        .onOpen((event) => {
            const disputeId = $(event.relatedTarget).data('dispute');
            Modal.load('arrival_dispute_api_edit', {dispute: disputeId}, $modalEditLitige, $modalEditLitige.find('.modal-body'), {
                onOpen: () => {
                    Camera.init(
                        $modalEditLitige.find(`.take-picture-modal-button`),
                        $modalEditLitige.find(`[name="files[]"]`)
                    );
                    openTableHisto($modalEditLitige, disputeId)
                }
            })
        })
        .submitTo(
            POST,
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
