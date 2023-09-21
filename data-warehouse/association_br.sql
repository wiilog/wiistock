SELECT receipt_association.creation_date AS date,
       receipt_association.pack_code AS codes_ul,
       receipt_association.reception_number AS reception,
       utilisateur.username AS utilisateur

FROM receipt_association

LEFT JOIN utilisateur ON receipt_association.user_id = utilisateur.id
