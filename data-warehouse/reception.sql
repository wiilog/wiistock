SELECT

    IF(reception_reference_article.id IS NOT NULL, reception_reference_article.id, 0) AS reception_reference_article_id,
    IF(article.id IS NOT NULL, article.id, 0) AS article_id,

    reception.id AS reception_id,

    reception.order_number AS no_commande, -- CEA

    statut.nom AS statut, -- CEA

    reception.cleaned_comment AS commentaire, -- CEA

    reception.date AS date, -- CEA

    reception.numero_reception AS numero, -- CEA

    fournisseur.nom AS fournisseur, -- CEA

    IF(reference_article.id IS NOT NULL, reference_article.reference,
       IF(article.id IS NOT NULL, article_reference_article.reference, NULL)) AS reference, -- CEA

    IF(reference_article.id IS NOT NULL, reference_article.libelle,
       IF(article.id IS NOT NULL, article_reference_article.libelle, NULL)) AS libelle, -- CEA

    reception_reference_article.quantite AS quantite_recue, -- CEA

    reception_reference_article.quantite_ar AS quantite_a_recevoir, -- CEA

    IF(reference_article.id IS NOT NULL, reference_article.quantite_stock,
       IF(article.id IS NOT NULL, article_reference_article.quantite_stock, NULL)) AS quantite_reference, -- CEA

    article.quantite AS quantite_article_associe, -- CEA

    IF(reference_article.id IS NOT NULL, reference_article.bar_code,
       IF(article.id IS NOT NULL, article_reference_article.bar_code, NULL)) AS code_barre_reference, -- CEA

    article.bar_code AS code_barre_article, -- CEA

    IF(reference_article.id IS NOT NULL, type_reference_article.label,
       IF(article.id IS NOT NULL, type_article.label, NULL)) AS type_flux

FROM reception

         LEFT JOIN statut ON reception.statut_id = statut.id
         LEFT JOIN fournisseur ON reception.fournisseur_id = fournisseur.id

    -- Récupère uniquement les réceptions qui contiennent des ref/articles
         INNER JOIN reception_reference_article ON reception.id = reception_reference_article.reception_id

         LEFT JOIN reference_article ON reception_reference_article.reference_article_id = reference_article.id

         LEFT JOIN article ON reception_reference_article.id = article.reception_reference_article_id
         LEFT JOIN article_fournisseur ON article.article_fournisseur_id = article_fournisseur.id
         LEFT JOIN reference_article AS article_reference_article
                   ON article_reference_article.id = article_fournisseur.reference_article_id

         LEFT JOIN type AS type_article ON article.type_id = type_article.id
         LEFT JOIN type AS type_reference_article ON reference_article.type_id = type_reference_article.id

WHERE (article.id IS NOT NULL
    OR reference_article.type_quantite = 'reference'
    OR reception_reference_article.quantite = 0
    OR reception_reference_article.quantite IS NULL)
