import '@styles/details-page.scss';
import '@styles/pages/pack/timeline.scss';
import {POST} from "@app/ajax";
import Routing from "@app/fos-routing";

$(function() {
    const logisticUnitId = $(`[name="logisticUnitId"]`).val();
    getTrackingHistory(logisticUnitId, true);
});

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

export function initializeHistoryTables(packId){
    initializeGroupHistoryTable(packId);
    initializeProjectHistoryTable(packId);
}

export function initializeGroupHistoryTable(packId) {
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

export function initializeProjectHistoryTable(packId) {
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

