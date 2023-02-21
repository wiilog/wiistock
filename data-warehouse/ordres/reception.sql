SELECT reception.id                                                                   AS id,
       (SELECT GROUP_CONCAT(item)
        FROM JSON_TABLE(
                 reception.order_number,
                 '$[*]' COLUMNS (rowid FOR ORDINALITY, item VARCHAR(100) PATH '$')
                 ) AS json_parsed)                                                    AS no_commande,
       statut.nom                                                                     AS statut,
       reception.cleaned_comment                                                      AS commentaire,
       reception.date                                                                 AS date,
       reception.date_attendue                                                        AS date_attendue,
       reception.number                                                               AS numero,
       fournisseur.nom                                                                AS fournisseur,
       emplacement.label                                                              AS emplacement,
       IF(reference_article.id IS NOT NULL, reference_article.reference,
          IF(article.id IS NOT NULL, article_reference_article.reference, NULL))      AS reference,

       IF(reference_article.id IS NOT NULL, reference_article.libelle,
          IF(article.id IS NOT NULL, article_reference_article.libelle, NULL))        AS libelle,

       reception_reference_article.quantite                                           AS quantite_recue,
       reception_reference_article.quantite_ar                                        AS quantite_a_recevoir,

       IF(reference_article.id IS NOT NULL, reference_article.quantite_stock,
          IF(article.id IS NOT NULL, article_reference_article.quantite_stock, NULL)) AS quantite_reference,

       article.quantite                                                               AS quantite_article_associe,

       IF(reference_article.id IS NOT NULL, reference_article.bar_code,
          IF(article.id IS NOT NULL, article_reference_article.bar_code, NULL))       AS code_barre_reference,

       article.bar_code                                                               AS code_barre_article,
       pack.code                                                                      AS code_UL,
       IF(reference_article.id IS NOT NULL, type_reference_article.label,
          IF(article.id IS NOT NULL, type_article.label, NULL))                       AS type_flux,

       IF(reception.urgent_articles = 1, 'oui', 'non')                                AS urgence_reference,

       IF(reception.manual_urgent = 1, 'oui', 'non')                                  AS urgence_reception,
       purchase_request.number                                                        AS numero_demande_achat,
       reception.arrival_id                                                           AS arrivage_id

FROM reception
         LEFT JOIN statut ON reception.statut_id = statut.id
         LEFT JOIN fournisseur ON reception.fournisseur_id = fournisseur.id
         LEFT JOIN emplacement ON reception.location_id = emplacement.id
         LEFT JOIN purchase_request_line ON reception.id = purchase_request_line.reception_id
         LEFT JOIN purchase_request ON purchase_request_line.purchase_request_id = purchase_request.id
         LEFT JOIN reception_line ON reception.id = reception_line.reception_id
         LEFT JOIN reception_reference_article ON reception_line.id = reception_reference_article.reception_line_id
         LEFT JOIN reference_article ON reception_reference_article.reference_article_id = reference_article.id
         LEFT JOIN article ON reception_reference_article.id = article.reception_reference_article_id
         LEFT JOIN pack ON article.current_logistic_unit_id = pack.id
         LEFT JOIN article_fournisseur ON article.article_fournisseur_id = article_fournisseur.id
         LEFT JOIN reference_article AS article_reference_article
                   ON article_reference_article.id = article_fournisseur.reference_article_id
         LEFT JOIN type AS type_article ON article.type_id = type_article.id
         LEFT JOIN type AS type_reference_article ON reference_article.type_id = type_reference_article.id

WHERE (article.id IS NOT NULL
    OR reference_article.type_quantite = 'reference'
    OR reception_reference_article.quantite = 0
    OR reception_reference_article.quantite IS NULL)
