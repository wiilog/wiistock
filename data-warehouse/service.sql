SELECT
    handling.id,
    type.label AS type,
    handling.object AS objet,
    demandeur.username AS demandeur,
    handling.creation_date AS date_creation,
    handling.desired_date AS date_attendue,
    handling.validation_date AS date_realisation,
    operateur.username AS operateur,
    handling.source AS emplacement_prise,
    handling.destination AS emplacement_depose,
    handling.number AS numero,
    statut.nom AS statut,
    handling.emergency AS urgence

FROM handling

         LEFT JOIN type ON handling.type_id = type.id
         LEFT JOIN utilisateur AS demandeur ON handling.requester_id = demandeur.id
         LEFT JOIN utilisateur AS operateur ON handling.treated_by_handling_id = operateur.id
         LEFT JOIN statut ON handling.status_id = statut.id
