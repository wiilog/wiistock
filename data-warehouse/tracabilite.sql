SELECT
    tracking_movement.id AS mouvement_traca_id,
    tracking_movement.datetime AS date_mouvement,
    tracking_movement_pack.code AS code_colis,
    type_mouvement.nom AS type_mouvement,
    tracking_movement.quantity AS quantite_mouvement,
    emplacement_mouvement.label AS emplacement_mouvement,
    operateur.username AS operateur,
    arrivage.id AS arrivage_id,
    tracking_movement.dispatch_id AS acheminement_id

FROM tracking_movement

    LEFT JOIN pack AS tracking_movement_pack ON tracking_movement.pack_id = tracking_movement_pack.id
    LEFT JOIN nature AS nature_pack ON tracking_movement_pack.nature_id = nature_pack.id
    LEFT JOIN statut AS type_mouvement ON tracking_movement.type_id = type_mouvement.id
    LEFT JOIN emplacement AS emplacement_mouvement on tracking_movement.emplacement_id = emplacement_mouvement.id
    LEFT JOIN utilisateur AS operateur ON tracking_movement.operateur_id = operateur.id

    LEFT JOIN arrivage ON tracking_movement_pack.arrivage_id = arrivage.id
