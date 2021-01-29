SELECT id, numero, date_creation, date_validation, point_collecte, demandeur, objet, destination, statut, type, commentaire, code_barre, quantite
FROM (
     SELECT
        demande_collecte.id                                                        AS id,
        demande_collecte.numero                                                    AS numero,
        demande_collecte.date                                                      AS date_creation,
        demande_collecte.validation_date                                           AS date_validation,
        emplacement.label                                                          AS point_collecte,
        demandeur.username                                                         AS demandeur,
        demande_collecte.objet                                                     AS objet,
        IF(demande_collecte.stock_or_destruct = 1, 'Mise en stock', 'Destruction') AS destination,
        statut.nom                                                                 AS statut,
        type.label                                                                 AS type,
        demande_collecte.cleaned_comment                                           AS commentaire,
        article.bar_code                                                           AS code_barre,
        article.quantite                                                           AS quantite

     FROM collecte AS demande_collecte

         LEFT JOIN utilisateur AS demandeur ON demande_collecte.demandeur_id = demandeur.id
         LEFT JOIN statut ON demande_collecte.statut_id = statut.id
         LEFT JOIN type ON demande_collecte.type_id = type.id
         LEFT JOIN emplacement ON demande_collecte.point_collecte_id = emplacement.id

         LEFT JOIN collecte_article ON demande_collecte.id = collecte_article.collecte_id
            INNER JOIN article ON collecte_article.article_id = article.id

     UNION
     SELECT
        demande_collecte.id                                                        AS id,
        demande_collecte.numero                                                    AS numero,
        demande_collecte.date                                                      AS date_creation,
        demande_collecte.validation_date                                           AS date_validation,
        emplacement.label                                                          AS point_collecte,
        demandeur.username                                                         AS demandeur,
        demande_collecte.objet                                                     AS objet,
        IF(demande_collecte.stock_or_destruct = 1, 'Mise en stock', 'Destruction') AS destination,
        statut.nom                                                                 AS statut,
        type.label                                                                 AS type,
        demande_collecte.cleaned_comment                                           AS commentaire,
        reference_article.bar_code                                                 AS code_barre,
        collecte_reference.quantite                                                AS quantite

     FROM collecte AS demande_collecte

         LEFT JOIN utilisateur AS demandeur ON demande_collecte.demandeur_id = demandeur.id
         LEFT JOIN statut ON demande_collecte.statut_id = statut.id
         LEFT JOIN type ON demande_collecte.type_id = type.id
         LEFT JOIN emplacement ON demande_collecte.point_collecte_id = emplacement.id

         LEFT JOIN collecte_reference ON demande_collecte.id = collecte_reference.collecte_id
            INNER JOIN reference_article ON collecte_reference.reference_article_id = reference_article.id
) AS requests
