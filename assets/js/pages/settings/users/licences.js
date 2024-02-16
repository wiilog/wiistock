import Routing from '@app/fos-routing';

export function initializeLicencesPage() {
    initDataTable('tableSessionHistoryRecords', {
        order: [['openedAt', 'desc']],
        serverSide: true,
        ajax: {
            url: Routing.generate('session_history_record_api', true),
            type: AJAX.POST
        },
        columns: [
            {data: 'user', title: 'Nom utilisateur'},
            {data: 'userEmail', title: 'Email'},
            {data: 'type', title: 'Type de connexion'},
            {data: 'openedAt', title: 'Date de connexion'},
            {data: 'closedAt', title: 'Date de déconnexion'},
            {data: 'sessionId', title: 'Identifiant de la session'},
        ],
        rowConfig: {
            needsRowClickAction: false
        },
    });
}
