SELECT
    pack.code AS code_unite_logistique,
    nature.label AS nature,
    pack.quantity AS quantite,
    project.code AS projet,
    dispatch_pack.dispatch_id AS acheminement_id,
    pack.arrivage_id AS arrivage_id,
    (SELECT COUNT(article.id)
     FROM article
     WHERE article.current_logistic_unit_id = pack.id) AS nb_articles_contenus,
    article.bar_code AS code_barre_article

FROM pack
         LEFT JOIN article ON pack.id = article.current_logistic_unit_id
         LEFT JOIN nature ON pack.nature_id = nature.id
         LEFT JOIN project ON pack.project_id = project.id
         LEFT JOIN dispatch_pack on pack.id = dispatch_pack.pack_id
