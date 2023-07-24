SELECT  dispatch.id,
        statut.nom                                                                  AS statut,
        status_history.date                                                         AS date_statut,
        IF(statut.grouped_signature_type = 'Enlèvement', signatory.username, null)  AS signataire_enlevement,
        IF(statut.grouped_signature_type = 'Livraison', signatory.username, null)   AS signataire_livraison,
        operator.username                                                        AS utilisateur

FROM status_history
         INNER JOIN dispatch ON status_history.dispatch_id = dispatch.id
         INNER JOIN statut ON status_history.status_id = statut.id
         LEFT JOIN utilisateur signatory ON status_history.validated_by_id = signatory.id
         LEFT JOIN utilisateur operator ON status_history.initiated_by_id = operator.id

GROUP BY dispatch.id,
         statut.nom,
         status_history.date,
         statut.grouped_signature_type,
         signatory.username,
         operator.username
