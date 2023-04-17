SELECT id,
       numero,
       date_creation,
       date_validation,
       date_traitement,
       date_attendue,
       projet,
       demandeur,
       destinataire,
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
       code_UL,
       project_article,
       commentaire_article

FROM (SELECT demande.id                                         AS id,
             demande.numero                                     AS numero,
             demande.created_at                                 AS date_creation,
             demande.validated_at                               AS date_validation,
             (SELECT MAX(livraison.date_fin)
              FROM demande AS sub_demande
                       LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                       LEFT JOIN livraison ON preparation.id = livraison.preparation_id
              WHERE sub_demande.id = demande.id)                AS date_traitement,
             demande.expected_at                                AS date_attendue,
             COALESCE(projet_demande.code, projet_article.code) AS projet,
             demandeur.username                                 AS demandeur,
             type.label                                         AS type,
             statut.nom                                         AS statut,

             (SELECT GROUP_CONCAT(preparation.numero SEPARATOR ' / ')
              FROM demande AS sub_demande
                       LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
              WHERE sub_demande.id = demande.id)                AS codes_preparations,

             (SELECT GROUP_CONCAT(livraison.numero SEPARATOR ' / ')
              FROM demande AS sub_demande
                       LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                       LEFT JOIN livraison on preparation.id = livraison.preparation_id
              WHERE sub_demande.id = demande.id)                AS codes_livraisons,

             destination.label                                  AS destination,
             demande.destinataire_id                            AS destinataire,
             demande.cleaned_comment                            AS commentaire,
             reference_article.reference                        AS reference_article,
             article.label                                      AS libelle,
             article.bar_code                                   AS code_barre,
             article.quantite                                   AS quantite_disponible,
             delivery_request_article_line.quantity_to_pick     AS quantite_a_prelever,
             pack.code                                          AS code_UL,
             request_line_project.code                          AS project_article,
             article.commentaire                                AS commentaire_article

      FROM demande

               LEFT JOIN utilisateur AS demandeur ON demande.utilisateur_id = demandeur.id
               LEFT JOIN project AS projet_demande ON demande.project_id = projet_demande.id
               LEFT JOIN statut ON demande.statut_id = statut.id
               LEFT JOIN emplacement AS destination ON demande.destination_id = destination.id
               LEFT JOIN type ON demande.type_id = type.id
               INNER JOIN delivery_request_article_line
                          ON demande.id = delivery_request_article_line.request_id
               INNER JOIN pack AS request_line_pack ON delivery_request_article_line.pack_id = request_line_pack.id
               INNER JOIN project AS request_line_project ON request_line_pack.project_id = request_line_project.id
               INNER JOIN article ON delivery_request_article_line.article_id = article.id
               LEFT JOIN article_fournisseur ON article.article_fournisseur_id = article_fournisseur.id
               LEFT JOIN reference_article ON article_fournisseur.reference_article_id = reference_article.id
               LEFT JOIN pack ON article.current_logistic_unit_id = pack.id
               LEFT JOIN project AS projet_article ON pack.project_id = projet_article.id

      UNION
      SELECT demande.id                                       AS id,
             demande.numero                                   AS numero,
             demande.created_at                               AS date_creation,
             demande.validated_at                             AS date_validation,
             (SELECT MAX(livraison.date_fin)
              FROM demande AS sub_demande
                       LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                       LEFT JOIN livraison ON preparation.id = livraison.preparation_id
              WHERE sub_demande.id = demande.id)              AS date_traitement,
             demande.expected_at                              AS date_attendue,
             project.code                                     AS projet,
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
             demande.destinataire_id                            AS destinataire,
             demande.cleaned_comment                          AS commentaire,
             reference_article.reference                      AS reference_article,
             reference_article.libelle                        AS libelle,
             reference_article.bar_code                       AS code_barre,
             reference_article.quantite_disponible            AS quantite_disponible,
             delivery_request_reference_line.quantity_to_pick AS quantite_a_prelever,
             NULL                                             AS code_UL,
             NULL                                             AS project_article,
             NULL                                             AS commentaire_article

      FROM demande

               LEFT JOIN utilisateur AS demandeur ON demande.utilisateur_id = demandeur.id
               LEFT JOIN statut ON demande.statut_id = statut.id
               LEFT JOIN emplacement AS destination ON demande.destination_id = destination.id
               LEFT JOIN type ON demande.type_id = type.id
               INNER JOIN delivery_request_reference_line
                          ON demande.id = delivery_request_reference_line.request_id
               LEFT JOIN reference_article ON delivery_request_reference_line.reference_id = reference_article.id
               LEFT JOIN project ON demande.project_id = project.id
      WHERE demande.manual = 0) AS requests
