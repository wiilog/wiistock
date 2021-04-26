SELECT reception_traca.date_creation AS date,
       reception_traca.arrivage AS arrivage,
       reception_traca.number AS reception,
       utilisateur.username AS utilisateur

FROM reception_traca

LEFT JOIN utilisateur ON reception_traca.user_id = utilisateur.id
