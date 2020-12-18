SELECT
    -- Mouvements traça
    tracking_movement.id AS mouvement_traca_id,
    tracking_movement.datetime AS date_mouvement, -- CEA
    tracking_movement_pack.code AS code_colis, -- CEA
    type_mouvement.nom AS type_mouvement, -- CEA
    tracking_movement.quantity AS quantite_mouvement, -- CEA
    emplacement_mouvement.label AS emplacement_mouvement, -- CEA
    operateur.username AS operateur, -- CEA
#     tracking_movement.commentaire AS commentaire_mouvement_traca, -- SED
#     piece_jointe.original_name AS piece_jointe, -- SED => Sous requête ?

    -- Arrivages
    arrivage.id AS arrivage_id,
    arrivage.numero_arrivage AS no_arrivage, -- CEA
    destinataire_arrivage.username AS destinataire_arrivage, -- CEA
    fournisseur.nom AS fournisseur, -- CEA
    transporteur.label AS transporteur, -- CEA
    chauffeur.nom AS chauffeur, -- CEA
    arrivage.no_tracking AS no_tracking, -- CEA
    arrivage.numero_commande_list AS no_commande, -- CEA
    type_arrivage.label AS type_arrivage, -- CEA
    acheteur_arrivage.username AS acheteur_arrivage, -- CEA
    statut_arrivage.nom AS statut_arrivage, -- CEA
    arrivage.cleaned_comment AS commentaire, -- CEA
    arrivage.date AS date_arrivage, -- CEA
    (SELECT COUNT(pack.id)
    FROM arrivage AS sub_arrivage
        LEFT JOIN pack
            ON sub_arrivage.id = pack.arrivage_id
    WHERE sub_arrivage.id = arrivage.id)
        AS nb_colis_arrivage, -- CEA
    IF(arrivage.customs = 1, 'oui', 'non') AS douane, -- SED
    IF(arrivage.is_urgent = 1, 'oui', 'non') AS urgence, -- SED
    arrivage.project_number AS numero_projet, -- SED
    arrivage.business_unit AS business_unit_arrivage, -- CEA

    -- Acheminements
    dispatch.id AS acheminement_id,
    dispatch.number AS no_acheminement, -- CEA
    dispatch.creation_date AS date_creation, -- CEA
    dispatch.validation_date AS date_traitement, -- CEA
    type_acheminement.label AS type_acheminement, -- CEA
    demandeur_acheminement.username AS demandeur_acheminement, -- CEA
    destinataire_acheminement.username AS destinataire_acheminement, -- CEA
    statut_acheminement.nom AS statut_acheminement, -- CEA
    nature_pack.label AS nature, -- CEA
    dispatch.business_unit AS business_unit_acheminement, -- CEA
    IF(dispatch.validation_date IS NOT NULL AND dispatch.end_date IS NOT NULL,
       ROUND(
                   TIME_FORMAT(TIMEDIFF(dispatch.validation_date, dispatch.end_date), '%H')
                   + TIME_FORMAT(TIMEDIFF(dispatch.validation_date, dispatch.end_date), '%i') / 60
                   + TIME_FORMAT(TIMEDIFF(dispatch.validation_date, dispatch.end_date), '%s') / 3600, 4),
       NULL) AS delta_date_acheminement -- CEA

FROM tracking_movement

         LEFT JOIN pack AS tracking_movement_pack ON tracking_movement.pack_id = tracking_movement_pack.id
         LEFT JOIN nature AS nature_pack ON tracking_movement_pack.nature_id = nature_pack.id
         LEFT JOIN statut AS type_mouvement ON tracking_movement.type_id = type_mouvement.id
         LEFT JOIN emplacement AS emplacement_mouvement on tracking_movement.emplacement_id = emplacement_mouvement.id
         LEFT JOIN utilisateur AS operateur on emplacement_mouvement.id = operateur.dropzone_id

         LEFT JOIN arrivage ON tracking_movement_pack.arrivage_id = arrivage.id
         LEFT JOIN utilisateur AS destinataire_arrivage ON arrivage.destinataire_id = destinataire_arrivage.id
         LEFT JOIN fournisseur ON arrivage.fournisseur_id = fournisseur.id
         LEFT JOIN transporteur ON arrivage.transporteur_id = transporteur.id
         LEFT JOIN chauffeur ON arrivage.chauffeur_id = chauffeur.id
         LEFT JOIN type AS type_arrivage ON arrivage.type_id = type_arrivage.id
         LEFT JOIN utilisateur AS acheteur_arrivage ON arrivage.utilisateur_id = acheteur_arrivage.id
         LEFT JOIN statut AS statut_arrivage ON arrivage.statut_id = statut_arrivage.id

         LEFT JOIN dispatch ON tracking_movement.dispatch_id = dispatch.id
         LEFT JOIN type AS type_acheminement ON dispatch.type_id = type_acheminement.id
         LEFT JOIN utilisateur AS demandeur_acheminement ON dispatch.requester_id = demandeur_acheminement.id
         LEFT JOIN utilisateur AS destinataire_acheminement ON dispatch.receiver_id = destinataire_acheminement.id
         LEFT JOIN statut AS statut_acheminement ON dispatch.statut_id = statut_acheminement.id
