SELECT dispatch.id                                        AS id,
       dispatch.number                                    AS numero,
       dispatch.creation_date                             AS date_creation,
       dispatch.validation_date                           AS date_validation,
       dispatch.treatment_date                            AS date_traitement,
       dispatch.start_date                                AS date_echeance_debut,
       dispatch.end_date                                  AS date_echeance_fin,
       type.label                                         AS type,
       transporteur.label                                 AS transporteur,
       dispatch.carrier_tracking_number                   AS numero_tracking_transporteur,
       dispatch.command_number                            AS numero_commande,
       demandeur.username                                 AS demandeur,
       GROUP_CONCAT(destinataire.username SEPARATOR ', ') AS destinataire,
       pack.code                                          AS code_colis,
       pack.quantity                                      AS quantite_colis,
       dispatch_pack.quantity                             AS quantite_a_acheminer,
       nature.label                                       AS nature_colis,
       emplacement_prise.label                            AS emplacement_prise,
       emplacement_depose.label                           AS emplacement_depose,
       dispatch.destination                               AS destination,
       (SELECT COUNT(dispatch_pack_count.id)
        FROM dispatch AS sub_dispatch
                 LEFT JOIN dispatch_pack AS dispatch_pack_count ON sub_dispatch.id = dispatch_pack_count.dispatch_id
        WHERE sub_dispatch.id = dispatch.id)
                                                          AS nb_colis,
       statut.nom                                         AS statut,
       operateur.username                                 AS operateur,
       treate_par.username                                AS traite_par,
       dernier_emplacement_colis.label                    AS dernier_emplacement,
       dernier_mouvement.datetime                         AS date_dernier_mouvement,
       dispatch.emergency                                 AS urgence,
       dispatch.project_number                            AS numero_projet,
       dispatch.business_unit                             AS business_unit,
       reference_article.reference                        AS reference,
       reference_article.quantite_stock                   AS quantite_reference,
       dispatch_reference_article.batch_number            AS numero_lot,
       dispatch_reference_article.serial_number           AS numero_serie,
       dispatch_reference_article.sealing_number          AS numero_plombage_scelle,
       dispatch_reference_article.adr                     AS adr

FROM dispatch

         LEFT JOIN type ON dispatch.type_id = type.id
         LEFT JOIN utilisateur AS demandeur ON dispatch.requester_id = demandeur.id
         LEFT JOIN dispatch_receiver AS destinataire_acheminement ON dispatch.id = destinataire_acheminement.dispatch_id
         LEFT JOIN utilisateur AS destinataire ON destinataire_acheminement.utilisateur_id = destinataire.id
         LEFT JOIN utilisateur AS treate_par ON dispatch.treated_by_id = treate_par.id
         LEFT JOIN emplacement AS emplacement_prise ON dispatch.location_from_id = emplacement_prise.id
         LEFT JOIN emplacement AS emplacement_depose ON dispatch.location_to_id = emplacement_depose.id
         LEFT JOIN statut ON dispatch.statut_id = statut.id
         LEFT JOIN transporteur ON dispatch.carrier_id = transporteur.id
         LEFT JOIN dispatch_pack ON dispatch.id = dispatch_pack.dispatch_id
         LEFT JOIN pack ON dispatch_pack.pack_id = pack.id
         LEFT JOIN nature ON pack.nature_id = nature.id
         LEFT JOIN tracking_movement AS dernier_emplacement ON pack.last_drop_id = dernier_emplacement.id
         LEFT JOIN emplacement AS dernier_emplacement_colis
                   ON dernier_emplacement.emplacement_id = dernier_emplacement_colis.id
         LEFT JOIN tracking_movement AS dernier_mouvement ON pack.last_tracking_id = dernier_mouvement.id
         LEFT JOIN utilisateur AS operateur ON dernier_mouvement.operateur_id = operateur.id
         LEFT JOIN dispatch_reference_article ON dispatch_pack.id = dispatch_reference_article.dispatch_pack_id
         LEFT JOIN reference_article ON dispatch_reference_article.reference_article_id = reference_article.id

GROUP BY id,
         numero,
         date_creation,
         date_validation,
         date_traitement,
         date_echeance_debut,
         date_echeance_fin,
         type,
         transporteur,
         numero_tracking_transporteur,
         numero_commande,
         demandeur,
         code_colis,
         quantite_colis,
         quantite_a_acheminer,
         nature_colis,
         emplacement_prise,
         emplacement_depose,
         destination,
         nb_colis,
         statut,
         operateur,
         traite_par,
         dernier_emplacement,
         date_dernier_mouvement,
         urgence,
         numero_projet,
         business_unit,
         reference
