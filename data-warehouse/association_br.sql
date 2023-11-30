SELECT receipt_association.creation_date    AS date,
       TRIM(pack.code)                      AS code_ul,
       receipt_association.reception_number AS reception,
       utilisateur.username                 AS utilisateur

FROM receipt_association

         LEFT JOIN receipt_association_logistic_unit
                   ON receipt_association.id = receipt_association_logistic_unit.receipt_association_id
         LEFT JOIN pack ON receipt_association_logistic_unit.logistic_unit_id = pack.id
         LEFT JOIN utilisateur ON receipt_association.user_id = utilisateur.id
