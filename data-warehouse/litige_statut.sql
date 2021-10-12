SELECT dispute_history_record.dispute_id AS litige_id,
       dispute.number                    AS numero,
       type.label                        AS type,
       statut.nom                        AS statut,
       dispute_history_record.date       AS date_statut,
       utilisateur.username              AS utilisateur

FROM dispute_history_record

         LEFT JOIN dispute ON dispute_history_record.dispute_id = dispute.id
         LEFT JOIN type ON dispute.type_id = type.id
         LEFT JOIN statut ON dispute.status_id = statut.id
         LEFT JOIN utilisateur ON dispute_history_record.user_id = utilisateur.id
