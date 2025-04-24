SELECT article.id                                                         AS id,
       reference_article.reference                                        AS reference,
       article.label                                                      AS libelle,
       article.bar_code                                                   AS code_barre,
       statut.nom                                                         AS statut,
       article.quantite                                                   AS quantite,
       IF(article.commentaire = '<p><br></p>', null, article.commentaire) AS commentaire,
       emplacement.label                                                  AS emplacement,
       article.date_last_inventory                                        AS date_dernier_inventaire,
       article.batch                                                      AS lot,
       article.stock_entry_date                                           AS date_entree_stock,
       article.expiry_date                                                AS date_peremption,
       fournisseur.code_reference                                         AS code_fournisseur,
       article_fournisseur.reference                                      AS reference_fournisseur,
       article_fournisseur.label                                          AS label_fournisseur,
       fournisseur.nom                                                    AS label_reference_fournisseur,
       project.code                                                       AS projet,
       project_history_record.created_at                                  AS date_assignation_projet,
       pack.code                                                          AS code_ul,
       article.prix_unitaire                                              AS prix_unitaire,
       IF(article.conform = 1, 'non', 'oui')                              AS anomalie,
       article.rfidTag                                                    AS tag_rfid,
       type.label                                                         AS type,
       article.purchase_order                                             AS numero_commande,
       article.delivery_note                                              AS numero_bon_livraison,
       native_country.label                                               AS pays_origine,
       article.manufactured_at                                            AS date_fabrication,
       reference_article.last_sleeping_stock_alert_answer                 AS derniere_reponse_stockage,
       FLOOR(sleeping_stock_plan.max_storage_time/60/60/24)               AS duree_stockage_maximale,
       FLOOR(sleeping_stock_plan.max_stationary_time/60/60/24)            AS duree_immobilisation_maximale

FROM article
         LEFT JOIN statut ON article.statut_id = statut.id
         LEFT JOIN emplacement ON article.emplacement_id = emplacement.id
         LEFT JOIN article_fournisseur ON article.article_fournisseur_id = article_fournisseur.id
         LEFT JOIN fournisseur ON article_fournisseur.fournisseur_id = fournisseur.id
         LEFT JOIN reference_article ON article_fournisseur.reference_article_id = reference_article.id
         LEFT JOIN pack ON article.current_logistic_unit_id = pack.id
         LEFT JOIN project ON pack.project_id = project.id
         LEFT JOIN project_history_record
                   ON pack.id = project_history_record.pack_id AND project.id = project_history_record.project_id
         INNER JOIN type ON article.type_id = type.id
         LEFT JOIN sleeping_stock_plan ON sleeping_stock_plan.type_id = type.id
         LEFT JOIN native_country ON article.native_country_id = native_country.id

