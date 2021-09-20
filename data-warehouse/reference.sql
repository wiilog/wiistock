SELECT
    reference_article.id,
    reference_article.bar_code AS code_barre,
    reference_article.reference AS reference,
    reference_article.libelle AS libelle,
    reference_article.quantite_stock AS quantite_stock,
    type.label AS type,
    statut.nom AS statut,
    reference_article.cleaned_comment AS commentaire,
    emplacement.label AS emplacement,
    reference_article.limit_security AS seuil_securite,
    reference_article.limit_warning AS seuil_alerte,
    IF(alerte_stock.reference_id IS NOT NULL AND alerte_stock.type = 1,
       alerte_stock.date,
       NULL) AS date_securite_stock, -- Alert::SECURITY
    IF(alerte_stock.reference_id IS NOT NULL AND alerte_stock.type = 2,
       alerte_stock.date,
       NULL) AS date_alerte_stock, -- Alert::WARNING
    reference_article.prix_unitaire AS prix_unitaire,
    inventory_category.label AS categorie_inventaire,
    reference_article.date_last_inventory AS date_dernier_inventaire,
    IF(reference_article.needs_mobile_sync = 1, 'oui', 'non') AS synchronisation_nomade,
    reference_article.stock_management AS gestion_stock,
    GROUP_CONCAT(gestionnaires.username SEPARATOR ', ') AS gestionnaires,
    visibility_group.label AS groupe_visibilite

FROM reference_article

         LEFT JOIN type ON reference_article.type_id = type.id
         LEFT JOIN statut ON reference_article.statut_id = statut.id
         LEFT JOIN emplacement ON reference_article.emplacement_id = emplacement.id
         LEFT JOIN inventory_category ON reference_article.category_id = inventory_category.id
         LEFT JOIN reference_article_utilisateur ON reference_article.id = reference_article_utilisateur.reference_article_id
         LEFT JOIN utilisateur AS gestionnaires ON reference_article_utilisateur.utilisateur_id = gestionnaires.id
         LEFT JOIN visibility_group ON reference_article.visibility_group_id = visibility_group.id

         LEFT JOIN alert AS alerte_stock ON reference_article.id = alerte_stock.reference_id

GROUP BY
    reference_article.id,
    code_barre,
    reference,
    libelle,
    quantite_stock,
    type,
    statut,
    commentaire,
    emplacement,
    seuil_securite,
    seuil_alerte,
    date_securite_stock,
    date_alerte_stock,
    prix_unitaire,
    categorie_inventaire,
    date_dernier_inventaire,
    synchronisation_nomade,
    gestion_stock,
    groupe_visibilite
