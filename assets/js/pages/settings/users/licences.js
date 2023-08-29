import {POST} from "@app/ajax";

export function initializeLicencesPage($container) {
    const tableSessionHistoryRecords = initDataTable('tableSessionHistoryRecords', {
        order: [['openedAt', 'desc']],
        serverSide: true,
        ajax: {
            "url": Routing.generate('session_history_record_api', true),
            "type": "POST"
        },
        columns: [
            { data: 'user', title: 'Nom utilisateur' },
            { data: 'userEmail', title: 'Email' },
            { data: 'type', title: 'Type de connexion' },
            { data: 'openedAt', title: 'Date de connexion' },
            { data: 'closedAt', title: 'Date de déconnexion' },
            { data: 'sessionId', title: 'Identifiant de la session' },
        ],
        rowConfig: {
            needsRowClickAction: false
        },
    });
}
