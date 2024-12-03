import {POST, DELETE} from "@app/ajax";
import Form from "@app/form";
import Modal from "@app/modal";
import Routing from "@app/fos-routing";


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

export function deletePack(params, table){
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
