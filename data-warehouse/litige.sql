SELECT dispute.id                                                     AS litige_id,
       dispute.number                                                 AS numero,
       type.label                                                     AS type,
       dispute.creation_date                                          AS date_creation,
       statut.nom                                                     AS dernier_statut,
       last_dispute_record.comment                                    AS dernier_commentaire,
       GROUP_CONCAT(acheteurs.username SEPARATOR ', ')                AS acheteurs,
       declarant.username                                             AS declarant,
       IF(dispute.emergency_triggered = 1, 'oui', 'non')              AS urgence,
       arrivage.numero_arrivage                                       AS numero_arrivage,
       reception.number                                               AS numero_reception,
       IF(arrivage.id, IF(JSON_LENGTH(arrivage.numero_commande_list) > 0,
                          REPLACE(REPLACE(REPLACE(arrivage.numero_commande_list, '"', ''), '[', ''), ']', ''), NULL),
          IF(reception.id, reception.order_number, NULL))             AS numero_commande_bl,
       reception.order_number                                         AS numero_ligne,
       IF(arrivage.id, fournisseur_arrivage.nom,
          IF(reception.id, fournisseur_reception.nom, NULL))          AS fournisseur,
       reference_article.reference                                    AS reference,
       IF(arrivage.id, transporteur_arrivage.label,
          IF(reception.id, transporteur_reception.label, NULL))       AS transporteur,
       IF(article.id, article.bar_code, IF(pack.id, pack.code, NULL)) AS colis_article,
       article.label                                                  AS libelle_article,
       article_reference_article.reference                            AS reference_article,
       article.quantite                                               AS quantite_article

FROM dispute

         LEFT JOIN type ON dispute.type_id = type.id
         LEFT JOIN dispute_history_record AS last_dispute_record ON dispute.last_history_record_id = last_dispute_record.id
         LEFT JOIN dispute_utilisateur ON dispute.id = dispute_utilisateur.dispute_id
         LEFT JOIN utilisateur AS acheteurs ON dispute_utilisateur.utilisateur_id = acheteurs.id
         LEFT JOIN utilisateur AS declarant ON dispute.reporter_id = declarant.id
         LEFT JOIN dispute_pack ON dispute.id = dispute_pack.dispute_id
         LEFT JOIN dispute_article ON dispute.id = dispute_article.dispute_id
         LEFT JOIN article ON dispute_article.article_id = article.id
         LEFT JOIN article_fournisseur ON article.article_fournisseur_id = article_fournisseur.id
         LEFT JOIN reference_article AS article_reference_article
                   ON article_fournisseur.reference_article_id = article_reference_article.id
         LEFT JOIN reception_reference_article
                   ON article.reception_reference_article_id = reception_reference_article.id
         LEFT JOIN reference_article ON reception_reference_article.reference_article_id = reference_article.id
         LEFT JOIN reception_line ON reception_reference_article.reception_line_id = reception_line.id
         LEFT JOIN reception ON reception_line.reception_id = reception.id
         LEFT JOIN fournisseur AS fournisseur_reception ON reception.fournisseur_id = fournisseur_reception.id
         LEFT JOIN transporteur AS transporteur_reception ON reception.transporteur_id = transporteur_reception.id
         LEFT JOIN pack ON dispute_pack.pack_id = pack.id
         LEFT JOIN arrivage ON pack.arrivage_id = arrivage.id
         LEFT JOIN fournisseur AS fournisseur_arrivage ON arrivage.fournisseur_id = fournisseur_arrivage.id
         LEFT JOIN transporteur AS transporteur_arrivage ON arrivage.transporteur_id = transporteur_arrivage.id
         LEFT JOIN statut ON dispute.status_id = statut.id

GROUP BY litige_id,
         numero,
         type,
         date_creation,
         dernier_statut,
         dernier_commentaire,
         declarant,
         urgence,
         arrivage.numero_arrivage,
         reception.number,
         numero_commande_bl,
         numero_ligne,
         fournisseur,
         reference,
         transporteur,
         colis_article,
         libelle_article,
         reference_article,
         quantite_article
