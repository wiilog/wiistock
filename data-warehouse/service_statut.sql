SELECT handling.id as service_id,
       handling.number as numero,
       statut.nom as statut,
       status_history.date as date_statut,
       demandeur.username as utilisateur
FROM status_history
         LEFT JOIN handling ON handling.id = status_history.handling_id
         LEFT JOIN utilisateur AS demandeur ON handling.requester_id = demandeur.id
         LEFT JOIN statut ON status_history.status_id = statut.id
WHERE status_history.handling_id IS NOT NULL;
