SELECT dispute_history_record.dispute_id   AS litige_id,
       dispute.number                      AS numero,
       dispute_history_record.type_label   AS type,
       dispute_history_record.status_label AS statut,
       dispute_history_record.date         AS date_statut,
       utilisateur.username                AS utilisateur

FROM dispute_history_record

         LEFT JOIN dispute ON dispute_history_record.dispute_id = dispute.id
         LEFT JOIN utilisateur ON dispute_history_record.user_id = utilisateur.id
