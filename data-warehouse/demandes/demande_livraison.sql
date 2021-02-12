SELECT id,
       numero,
       date_creation,
       date_validation,
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
       quantite_a_prelever
FROM (
         SELECT demande.id                          AS id,
                demande.numero                      AS numero,
                demande.date                        AS date_creation,
                (SELECT MIN(preparation.date)
                 FROM demande AS sub_demande
                          LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                 WHERE sub_demande.id = demande.id) AS date_validation,
                demandeur.username                  AS demandeur,
                type.label                          AS type,
                statut.nom                          AS statut,

                (SELECT GROUP_CONCAT(preparation.numero SEPARATOR ' / ')
                 FROM demande AS sub_demande
                          LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                 WHERE sub_demande.id = demande.id) AS codes_preparations,

                (SELECT GROUP_CONCAT(livraison.numero SEPARATOR ' / ')
                 FROM demande AS sub_demande
                          LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                          LEFT JOIN livraison on preparation.id = livraison.preparation_id
                 WHERE sub_demande.id = demande.id) AS codes_livraisons,

                destination.label                   AS destination,
                demande.cleaned_comment             AS commentaire,
                reference_article.reference         AS reference_article,
                article.label                       AS libelle,
                article.bar_code                    AS code_barre,
                article.quantite                    AS quantite_disponible,
                article.quantite_aprelever          AS quantite_a_prelever

         FROM demande

                  LEFT JOIN utilisateur AS demandeur ON demande.utilisateur_id = demandeur.id
                  LEFT JOIN statut ON demande.statut_id = statut.id
                  LEFT JOIN emplacement AS destination ON demande.destination_id = destination.id
                  LEFT JOIN type ON demande.type_id = type.id
                  INNER JOIN article ON demande.id = article.demande_id
                  LEFT JOIN article_fournisseur ON article.article_fournisseur_id = article_fournisseur.id
                  LEFT JOIN reference_article ON article_fournisseur.reference_article_id = reference_article.id

         WHERE demande.numero

         UNION
         SELECT demande.id                             AS id,
                demande.numero                         AS numero,
                demande.date                           AS date_creation,
                (SELECT MIN(preparation.date)
                 FROM demande AS sub_demande
                          LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                 WHERE sub_demande.id = demande.id)    AS date_validation,
                demandeur.username                     AS demandeur,
                type.label                             AS type,
                statut.nom                             AS statut,

                (SELECT GROUP_CONCAT(preparation.numero SEPARATOR ' / ')
                 FROM demande AS sub_demande
                          LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                 WHERE sub_demande.id IN (demande.id)) AS codes_preparations,

                (SELECT GROUP_CONCAT(livraison.numero SEPARATOR ' / ')
                 FROM demande AS sub_demande
                          LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                          LEFT JOIN livraison on preparation.id = livraison.preparation_id
                 WHERE sub_demande.id IN (demande.id)) AS codes_livraisons,

                destination.label                      AS destination,
                demande.cleaned_comment                AS commentaire,
                reference_article.reference            AS reference_article,
                reference_article.libelle              AS libelle,
                reference_article.bar_code             AS code_barre,
                reference_article.quantite_disponible  AS quantite_disponible,
                ligne_article.quantite                 AS quantite_a_prelever

         FROM demande

                  LEFT JOIN utilisateur AS demandeur ON demande.utilisateur_id = demandeur.id
                  LEFT JOIN statut ON demande.statut_id = statut.id
                  LEFT JOIN emplacement AS destination ON demande.destination_id = destination.id
                  LEFT JOIN type ON demande.type_id = type.id

                  INNER JOIN ligne_article ON demande.id = ligne_article.demande_id
                  LEFT JOIN reference_article ON ligne_article.reference_id = reference_article.id
     ) AS requests
