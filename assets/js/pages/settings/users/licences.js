import {POST} from "@app/ajax";

export function initializeLicencesPage() {
    const tableSessionHistoryRecords = initDataTable('tableSessionHistoryRecords', {
        order: [['openedAt', 'desc']],
        serverSide: true,
        ajax:{
            "url": Routing.generate('session_history_record_api', true),
            "type": POST
        },
        columns:[
            { data: 'user', title: 'Nom utilisateur'},
            { data: 'userEmail', title : 'Email'},
            { data: 'type', title : 'Type de connexion'},
            { data: 'openedAt', title : 'Type de connexion'},
            { data: 'closedAt', title : 'Type de connexion'},
            { data: 'sessionId', title : 'Identifiant de la session'},
        ],
        rowConfig: {
            needsRowClickAction: false
        },
    });
}
