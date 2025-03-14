import AJAX, {POST, DELETE} from "@app/ajax";
import Form from "@app/form";
import Modal from "@app/modal";
import Routing from "@app/fos-routing";
import Flash, {ERROR, SUCCESS} from "@app/flash";

export function initEditPackModal(options) {
    const $modalEditPack = $('#modalEditPack');
    Form
        .create($modalEditPack)
        .onOpen((event) => {
            Modal.load('pack_edit_api', {pack: $(event.relatedTarget).data('id')}, $modalEditPack, $modalEditPack.find('.modal-body'), {
                onOpen: () => {
                    initializeEntryTimeIntervals($modalEditPack, true);
                }
            })
        })
        .submitTo(
            POST,
            'pack_edit',
            options
        );
}

export function initUngroupModal(options) {
    const $modalUngroup = $('#modalUngroup');
    Form
        .create($modalUngroup)
        .onOpen((event) => {
            Modal.load('group_ungroup_api', {group: $(event.relatedTarget).data('id')}, $modalUngroup, $modalUngroup.find('.modal-body'), {
                onOpen: () => {
                    initializeEntryTimeIntervals($modalUngroup, true);
                }
            })
        })
        .submitTo(
            POST,
            'group_ungroup',
            options
        );
}

export function deletePack(params, table, onSuccess = null) {
    Modal.confirm({
        ajax: {
            method: DELETE,
            route: 'pack_delete',
            params: params,
        },
        message: Translation.of('Traçabilité', 'Unités logistiques', 'Onglet "Unités logistiques"', 'Voulez-vous réellement supprimer cette UL ?'),
        title:  Translation.of('Traçabilité', 'Unités logistiques', 'Onglet "Unités logistiques"', 'Supprimer l\'UL', false),
        validateButton: {
            color: 'danger',
            label: Translation.of('Général', null, 'Modale', 'Supprimer'),
        },
        table: table,
        onSuccess: onSuccess,
    });
}


export function getTrackingHistory(logisticUnitId, searchable = true) {
    const tableLuhistoryConfig = {
        processing: true,
        serverSide: true,
        paging: true,
        searching: searchable,
        ajax: {
            url: Routing.generate(`pack_tracking_history_api`, {id: logisticUnitId}, true),
            type: POST,
        },
        columns: [
            {data: `history`, title: ``, orderable: false},
        ],
    };
    initDataTable($('#table-LU-history'), tableLuhistoryConfig);

    initializeHistoryTables(logisticUnitId);
}

function initializeHistoryTables(packId){
    initializeGroupHistoryTable(packId);
    initializeProjectHistoryTable(packId);
}

function initializeGroupHistoryTable(packId) {
    initDataTable('groupHistoryTable', {
        serverSide: true,
        processing: true,
        order: [['date', "desc"]],
        ajax: {
            "url": Routing.generate('pack_group_history_api', {pack: packId}, true),
            "type": "POST"
        },
        columns: [
            {data: 'group', name: 'group', title: Translation.of('Traçabilité', 'Mouvements', 'Groupe')},
            {data: 'date', name: 'date', title: Translation.of('Traçabilité', 'Général', 'Date')},
            {data: 'type', name: 'type', title: Translation.of('Traçabilité', 'Mouvements', 'Type')},
        ],
        domConfig: {
            needsPartialDomOverride: true,
        }
    });
}
export function initializeGroupContentTable(logisticUnit, showPageMode = true) {
    initDataTable('groupContentTable', {
        serverSide: true,
        processing: true,
        paging: true,
        searching: showPageMode,
        ajax: {
            "url": Routing.generate('pack_group_content_api', {
                logisticUnit,
                showPageMode: Number(showPageMode || false),
            }, true),
            "type": "POST"
        },
        columns: [
            {data: `content`, title: ``, orderable: false},
        ],
        drawCallback: () => {
            let $lastElement = false;
            $(".pack-details-element").each(function() {
                if ($lastElement && $lastElement.offset().top !== $(this).offset().top) {
                    $lastElement.addClass("border-right-0");
                }
                $lastElement = $(this);
            })
        },
    });
}

function initializeProjectHistoryTable(packId) {
    initDataTable('projectHistoryTable', {
        serverSide: true,
        processing: true,
        order: [['createdAt', "desc"]],
        ajax: {
            "url": Routing.generate('pack_project_history_api', {pack: packId}, true),
            "type": "POST"
        },
        columns: [
            {data: 'project', name: 'group', title: Translation.of('Référentiel', 'Projet', 'Projet', false)},
            {data: 'createdAt', name: 'type', title: 'Assigné le'},
        ],
        domConfig: {
            needsPartialDomOverride: true,
        }
    });
}

export function reloadLogisticUnitTrackingDelay(logisticUnitId) {
    AJAX
        .route(POST, "pack_force_tracking_delay_calculation", {logisticUnit: logisticUnitId})
        .json()
        .then(({success}) => {
            if (success) {
                Flash.add(SUCCESS, `Le calcul du ${Translation.of('Traçabilité', 'Unités logistiques', 'Divers', 'Délai de traitement')} a bien été relancé. Veuillez patienter...`, true, true);
            } else {
                Flash.add(ERROR, `Une erreur est survenu lors du calcul du ${Translation.of('Traçabilité', 'Unités logistiques', 'Divers', 'Délai de traitement')} de l'unité logistique. Veuillez réessayer ultérieurement.`, true, true);
            }
        });
}

export function addToCart(ids) {
    AJAX.route(POST, `cart_add_logistic_units`, {ids: ids.join(`,`)})
        .json()
        .then(({messages, cartQuantity}) => {
            messages.forEach(({success, msg}) => {
                Flash.add(success ? `success` : `danger`, msg);
            });

            if (cartQuantity !== undefined) {
                $('.header-icon.cart .icon-figure.small').removeClass(`d-none`).text(cartQuantity);
            }
        });
}

export function clearPackListSearching() {
    const $logisticUnitsContainer = $('.logistic-units-container');
    const $searchInput = $logisticUnitsContainer
        .closest('.content')
        .find('input[type=search]');
    $searchInput.val(null);
}
