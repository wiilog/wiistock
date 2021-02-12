SELECT
    inventory_entry.id,

    IF(reference_article.id IS NOT NULL, reference_article.bar_code,
       IF(article.id IS NOT NULL, reference_article_article.bar_code, NULL))
                     AS code_barre_reference,

    article.bar_code AS code_barre_article,

    IF(reference_article.id IS NOT NULL, reference_article.reference,
       IF(article.id IS NOT NULL, reference_article_article.reference, NULL))
                     AS reference,

    IF(reference_article.id IS NOT NULL, reference_article.libelle,
       IF(article.id IS NOT NULL, reference_article_article.libelle, NULL))
                     AS libelle,

    IF(reference_article.id IS NOT NULL, type_reference_article.label,
       IF(article.id IS NOT NULL, type_reference_article_article.label, NULL))
                     AS type_flux,

    inventory_entry.date AS date,

    inventory_entry.quantity AS quantite_comptee

FROM inventory_entry
         LEFT JOIN reference_article ON inventory_entry.ref_article_id = reference_article.id
         LEFT JOIN type AS type_reference_article ON reference_article.type_id = type_reference_article.id
         LEFT JOIN article ON inventory_entry.article_id = article.id
         LEFT JOIN article_fournisseur on article.article_fournisseur_id = article_fournisseur.id
         LEFT JOIN reference_article AS reference_article_article
                   ON article_fournisseur.reference_article_id = reference_article_article.id
         LEFT JOIN type AS type_reference_article_article
                   ON reference_article_article.type_id = type_reference_article_article.id
