SELECT
    handling.id,
    type.label AS type,
    handling.subject AS objet,
    demandeur.username AS demandeur,
    handling.creation_date AS date_creation,
    handling.desired_date AS date_attendue,
    handling.validation_date AS date_realisation,
    operateur.username AS operateur,
    handling.source AS emplacement_prise,
    handling.destination AS emplacement_depose,
    handling.number AS numero,
    statut.nom AS statut,
    handling.emergency AS urgence,
    IF(handling.validation_date IS NOT NULL AND handling.desired_date IS NOT NULL,
       ROUND(TIME_FORMAT(TIMEDIFF(handling.validation_date, handling.desired_date), '%H')
                 + TIME_FORMAT(TIMEDIFF(handling.validation_date, handling.desired_date), '%i') / 60
                 + TIME_FORMAT(TIMEDIFF(handling.validation_date, handling.desired_date), '%s') / 3600, 4),
       NULL) AS delta_date

FROM handling

         LEFT JOIN type ON handling.type_id = type.id
         LEFT JOIN utilisateur AS demandeur ON handling.requester_id = demandeur.id
         LEFT JOIN utilisateur AS operateur ON handling.treated_by_handling_id = operateur.id
         LEFT JOIN statut ON handling.status_id = statut.id
