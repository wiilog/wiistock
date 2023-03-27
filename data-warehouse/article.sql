SELECT article.id                            AS id,
       reference_article.reference           AS reference,
       article.label                         AS libelle,
       article.bar_code                      AS code_barre,
       statut.nom                            AS statut,
       article.quantite                      AS quantite,
       article.commentaire                   AS commentaire,
       emplacement.label                     AS emplacement,
       article.date_last_inventory           AS date_dernier_inventaire,
       article.batch                         AS lot,
       article.stock_entry_date              AS date_entree_stock,
       article.expiry_date                   AS date_peremption,
       fournisseur.code_reference            AS code_fournisseur,
       article_fournisseur.reference         AS reference_fournisseur,
       article_fournisseur.label             AS label_fournisseur,
       fournisseur.nom                       AS label_reference_fournisseur,
       project.code                          AS projet,
       project_history_record.created_at     AS date_assignation_projet,
       pack.code                             AS code_UL,
       article.prix_unitaire                 AS prix_unitaire,
       IF(article.conform = 1, 'oui', 'non') AS anomalie

FROM article
         LEFT JOIN statut ON article.statut_id = statut.id
         LEFT JOIN emplacement ON article.emplacement_id = emplacement.id
         LEFT JOIN article_fournisseur ON article.article_fournisseur_id = article_fournisseur.id
         LEFT JOIN fournisseur ON article_fournisseur.fournisseur_id = fournisseur.id
         LEFT JOIN reference_article ON article_fournisseur.reference = reference_article.reference
         LEFT JOIN pack ON article.current_logistic_unit_id = pack.id
         LEFT JOIN project ON pack.project_id = project.id
         LEFT JOIN project_history_record ON pack.id = project_history_record.pack_id AND project.id = project_history_record.project_id

