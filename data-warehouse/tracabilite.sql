SELECT tracking_movement.id                                        AS mouvement_traca_id,
       tracking_movement.datetime                                  AS date_mouvement,
       IF(tracking_movement.logistic_unit_parent_id IS NOT NULL,
          IF(type_mouvement.nom IN ('prise', 'depose'), '', tracking_movement_logistic_unit_parent.code),
          COALESCE(article.bar_code, tracking_movement_pack.code)) AS code_colis,
       article.bar_code                                            AS code_barre_article,
       type_mouvement.nom                                          AS type_mouvement,
       IF(tracking_movement_parent_pack.id, CONCAT(tracking_movement_parent_pack.code,
                                                   IF(tracking_movement.group_iteration,
                                                      CONCAT('-', tracking_movement.group_iteration), '')),
          NULL)                                                    AS groupe,
       tracking_movement.quantity                                  AS quantite_mouvement,
       emplacement_mouvement.label                                 AS emplacement_mouvement,
       operateur.username                                          AS operateur,
       arrivage.id                                                 AS arrivage_id,
       tracking_movement.dispatch_id                               AS acheminement_id,
       tracking_movement.reception_id                              AS reception_id

FROM tracking_movement

         LEFT JOIN pack AS tracking_movement_pack ON tracking_movement.pack_id = tracking_movement_pack.id
         LEFT JOIN pack AS tracking_movement_parent_pack
                   ON tracking_movement.pack_parent_id = tracking_movement_parent_pack.id
         LEFT JOIN article ON tracking_movement_pack.article_id = article.id
         LEFT JOIN nature AS nature_pack ON tracking_movement_pack.nature_id = nature_pack.id
         LEFT JOIN statut AS type_mouvement ON tracking_movement.type_id = type_mouvement.id
         LEFT JOIN emplacement AS emplacement_mouvement ON tracking_movement.emplacement_id = emplacement_mouvement.id
         LEFT JOIN utilisateur AS operateur ON tracking_movement.operateur_id = operateur.id
         LEFT JOIN arrivage ON tracking_movement_pack.arrivage_id = arrivage.id
         LEFT JOIN pack AS tracking_movement_logistic_unit_parent
                   ON tracking_movement.logistic_unit_parent_id = tracking_movement_logistic_unit_parent.id
