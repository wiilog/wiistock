DROP FUNCTION IF EXISTS get_validation_date;
DROP FUNCTION IF EXISTS get_treatment_date;

CREATE FUNCTION get_validation_date(demande_id INTEGER)
    RETURNS DATETIME
BEGIN
    RETURN (SELECT MIN(preparation.date)
            FROM demande AS sub_demande
                     LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
            WHERE sub_demande.id = demande_id);
END;

CREATE FUNCTION get_treatment_date(demande_id INTEGER)
    RETURNS DATETIME
BEGIN
    RETURN (SELECT MAX(livraison.date_fin)
            FROM demande AS sub_demande
                     LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                     LEFT JOIN livraison ON preparation.id = livraison.preparation_id
            WHERE sub_demande.id = demande_id);
END;

SELECT id,
       numero,
       date_creation,
       date_validation,
       date_traitement,
       demandeur,
       type,
       statut,
       codes_preparations,
       codes_livraisons,
       destination,
       commentaire,
       reference_article,
       libelle,
       code_barre,
       quantite_disponible,
       quantite_a_prelever,
       delais_traitement
FROM (
         SELECT demande.id                                     AS id,
                demande.numero                                 AS numero,
                demande.date                                   AS date_creation,
                get_validation_date(demande.id)                AS date_validation,
                get_treatment_date(demande.id)                AS date_traitement,
                demandeur.username                             AS demandeur,
                type.label                                     AS type,
                statut.nom                                     AS statut,

                (SELECT GROUP_CONCAT(preparation.numero SEPARATOR ' / ')
                 FROM demande AS sub_demande
                          LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                 WHERE sub_demande.id = demande.id)            AS codes_preparations,

                (SELECT GROUP_CONCAT(livraison.numero SEPARATOR ' / ')
                 FROM demande AS sub_demande
                          LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                          LEFT JOIN livraison on preparation.id = livraison.preparation_id
                 WHERE sub_demande.id = demande.id)            AS codes_livraisons,

                destination.label                              AS destination,
                demande.cleaned_comment                        AS commentaire,
                reference_article.reference                    AS reference_article,
                article.label                                  AS libelle,
                article.bar_code                               AS code_barre,
                article.quantite                               AS quantite_disponible,
                delivery_request_article_line.quantity_to_pick AS quantite_a_prelever,
                IF(get_treatment_date(demande.id) IS NOT NULL AND get_validation_date(demande.id) IS NOT NULL,
                   ROUND(TIME_FORMAT(TIMEDIFF(get_treatment_date(demande.id), get_validation_date(demande.id)), '%H')
                             +
                         TIME_FORMAT(TIMEDIFF(get_treatment_date(demande.id), get_validation_date(demande.id)), '%i') /
                         60
                             +
                         TIME_FORMAT(TIMEDIFF(get_treatment_date(demande.id), get_validation_date(demande.id)), '%s') /
                         3600, 4),
                   NULL)                                       AS delais_traitement

         FROM demande

                  LEFT JOIN utilisateur AS demandeur ON demande.utilisateur_id = demandeur.id
                  LEFT JOIN statut ON demande.statut_id = statut.id
                  LEFT JOIN emplacement AS destination ON demande.destination_id = destination.id
                  LEFT JOIN type ON demande.type_id = type.id
                  INNER JOIN delivery_request_article_line
                             ON demande.id = delivery_request_article_line.request_id
                  INNER JOIN article ON delivery_request_article_line.article_id = article.id
                  LEFT JOIN article_fournisseur ON article.article_fournisseur_id = article_fournisseur.id
                  LEFT JOIN reference_article ON article_fournisseur.reference_article_id = reference_article.id

         WHERE demande.numero

         UNION
         SELECT demande.id                                       AS id,
                demande.numero                                   AS numero,
                demande.date                                     AS date_creation,
                get_validation_date(demande.id)                  AS date_validation,
                get_treatment_date(demande.id)                AS date_traitement,
                demandeur.username                               AS demandeur,
                type.label                                       AS type,
                statut.nom                                       AS statut,

                (SELECT GROUP_CONCAT(preparation.numero SEPARATOR ' / ')
                 FROM demande AS sub_demande
                          LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                 WHERE sub_demande.id IN (demande.id))           AS codes_preparations,

                (SELECT GROUP_CONCAT(livraison.numero SEPARATOR ' / ')
                 FROM demande AS sub_demande
                          LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                          LEFT JOIN livraison on preparation.id = livraison.preparation_id
                 WHERE sub_demande.id IN (demande.id))           AS codes_livraisons,

                destination.label                                AS destination,
                demande.cleaned_comment                          AS commentaire,
                reference_article.reference                      AS reference_article,
                reference_article.libelle                        AS libelle,
                reference_article.bar_code                       AS code_barre,
                reference_article.quantite_disponible            AS quantite_disponible,
                delivery_request_reference_line.quantity_to_pick AS quantite_a_prelever,
                IF(get_treatment_date(demande.id) IS NOT NULL AND get_validation_date(demande.id) IS NOT NULL,
                   ROUND(TIME_FORMAT(TIMEDIFF(get_treatment_date(demande.id), get_validation_date(demande.id)), '%H')
                             +
                         TIME_FORMAT(TIMEDIFF(get_treatment_date(demande.id), get_validation_date(demande.id)), '%i') /
                         60
                             +
                         TIME_FORMAT(TIMEDIFF(get_treatment_date(demande.id), get_validation_date(demande.id)), '%s') /
                         3600, 4),
                   NULL)                                         AS delais_traitement

         FROM demande

                  LEFT JOIN utilisateur AS demandeur ON demande.utilisateur_id = demandeur.id
                  LEFT JOIN statut ON demande.statut_id = statut.id
                  LEFT JOIN emplacement AS destination ON demande.destination_id = destination.id
                  LEFT JOIN type ON demande.type_id = type.id
                  INNER JOIN delivery_request_reference_line
                             ON demande.id = delivery_request_reference_line.request_id
                  LEFT JOIN reference_article ON delivery_request_reference_line.reference_id = reference_article.id
     ) AS requests
