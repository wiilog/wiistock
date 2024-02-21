SELECT production_request.id AS production_id,
       statut.nom            AS statut,
       status_history.date   AS date_statut,
       operator.username     AS utilisateur

FROM status_history
         INNER JOIN production_request ON status_history.production_request_id = production_request.id
         INNER JOIN statut ON status_history.status_id = statut.id
         LEFT JOIN utilisateur operator ON status_history.initiated_by_id = operator.id
