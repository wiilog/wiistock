SELECT arrivage.id,
       arrivage.numero_arrivage                                                                                                                      AS no_arrivage,
       arrivage.date,
       (SELECT COUNT(pack_count.id)
        FROM arrivage AS sub_arrivage
                 LEFT JOIN pack
            AS pack_count ON sub_arrivage.id = pack_count.arrivage_id
        WHERE sub_arrivage.id = arrivage.id)
                                                                                                                                                     AS nb_ul,
       GROUP_CONCAT(receivers.username SEPARATOR ', ')                                                                                               AS destinataires,
       fournisseur.nom                                                                                                                               AS fournisseur,
       transporteur.label                                                                                                                            AS transporteur,
       chauffeur.nom                                                                                                                                 AS chauffeur,
       arrivage.no_tracking                                                                                                                          AS no_tracking_transporteur,
       IF(JSON_LENGTH(arrivage.numero_commande_list) > 0, REPLACE(REPLACE(REPLACE(arrivage.numero_commande_list, '"', ''), '[', ''), ']', ''), NULL) AS no_commande_bl,
       type.label                                                                                                                                    AS type,
       GROUP_CONCAT(acheteurs.username SEPARATOR ', ')                                                                                               AS acheteurs,
       IF(arrivage.is_urgent = 1, 'oui', 'non')                                                                                                      AS urgence,
       IF(arrivage.customs = 1, 'oui', 'non')                                                                                                        AS douane,
       IF(arrivage.frozen = 1, 'oui', 'non')                                                                                                         AS congele,
       statut.nom                                                                                                                                    AS statut,
       arrivage.cleaned_comment                                                                                                                      AS commentaire,
       utilisateur.username                                                                                                                          AS utilisateur,
       arrivage.project_number                                                                                                                       AS numero_projet,
       arrivage.business_unit                                                                                                                        AS business_unit,
       reception.id                                                                                                                                  AS reception_id

FROM arrivage
         LEFT JOIN utilisateur ON arrivage.utilisateur_id = utilisateur.id
         LEFT JOIN fournisseur ON arrivage.fournisseur_id = fournisseur.id
         LEFT JOIN transporteur ON arrivage.transporteur_id = transporteur.id
         LEFT JOIN chauffeur ON arrivage.chauffeur_id = chauffeur.id
         LEFT JOIN type ON arrivage.type_id = type.id
         LEFT JOIN statut ON arrivage.statut_id = statut.id
         LEFT JOIN arrivage_utilisateur ON arrivage.id = arrivage_utilisateur.arrivage_id
         INNER JOIN utilisateur AS acheteurs ON arrivage_utilisateur.utilisateur_id = acheteurs.id
         LEFT JOIN reception ON arrivage.id = reception.arrival_id
         LEFT JOIN arrival_receiver ON arrivage.id = arrival_receiver.arrival_id
         INNER JOIN utilisateur AS receivers ON arrival_receiver.user_id = receivers.id

GROUP BY id,
         no_arrivage,
         nb_ul,
         fournisseur,
         transporteur,
         chauffeur,
         no_tracking_transporteur,
         no_commande_bl,
         type,
         urgence,
         douane,
         congele,
         statut,
         commentaire,
         utilisateur,
         numero_projet,
         business_unit,
         reception_id
