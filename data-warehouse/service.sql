SELECT
    handling.id,
    type.label AS type, -- CEA
    handling.subject AS objet, -- CEA
    demandeur.username AS demandeur, -- CEA
    handling.creation_date AS date_creation, -- CEA
    handling.desired_date AS date_attendue, -- CEA
    handling.validation_date AS date_realisation, -- CEA
    operateur.username AS operateur, -- CEA
    handling.source AS emplacement_prise, -- CEA
    handling.destination AS emplacement_depose, -- CEA
    handling.number AS numero, -- CEA
    statut.nom AS statut, -- CEA
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
