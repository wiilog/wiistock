SELECT
    collecte.id AS id,
    collecte.numero AS numero,
    collecte.date AS date_creation,
    collecte.validation_date AS date_validation,
    emplacement.label AS point_collecte,
    demandeur.username AS demandeur,
    collecte.objet AS objet,
    IF(collecte.stock_or_destruct, 'Mise en stock', 'Destruction') AS destination,
    statut.nom AS statut,
    type.label AS type,
    collecte.cleaned_comment AS commentaire,
    IF(article.id IS NOT NULL, article.bar_code,
       IF(reference_article.id IS NOT NULL, reference_article.bar_code, NULL)) AS code_barre,
    IF(article.id, article.quantite,
       IF(collecte_reference.id, collecte_reference.quantite, NULL)) AS quantite

FROM collecte

    LEFT JOIN utilisateur AS demandeur ON collecte.demandeur_id = demandeur.id
    LEFT JOIN statut ON collecte.statut_id = statut.id
    LEFT JOIN type ON collecte.type_id = type.id
    LEFT JOIN emplacement ON collecte.point_collecte_id = emplacement.id

    LEFT JOIN collecte_article ON collecte.id = collecte_article.collecte_id
        LEFT JOIN article ON collecte_article.article_id = article.id
    LEFT JOIN collecte_reference ON collecte.id = collecte_reference.collecte_id
        LEFT JOIN reference_article ON collecte_reference.reference_article_id = reference_article.id
