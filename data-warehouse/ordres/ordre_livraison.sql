SELECT id, numero, statut, date_creation, date_livraison, date_demande, demandeur, operateur, type, commentaire, reference, libelle, emplacement, quantite_a_livrer, quantite_en_stock, code_barre, delta_date

FROM (
    SELECT
           ordre_livraison.id                 AS id,
           ordre_livraison.numero             AS numero,
           statut.nom                         AS statut,
           ordre_livraison.date               AS date_creation,
           ordre_livraison.date_fin           AS date_livraison,
           demande_livraison.date             AS date_demande,
           demandeur.username                 AS demandeur,
           operateur.username                 AS operateur,
           type.label                         AS type,
           demande_livraison.cleaned_comment  AS commentaire,
           reference_article.reference        AS reference,
           reference_article.libelle          AS libelle,
           destination.label                  AS emplacement,
           ligne_article_preparation.quantite AS quantite_a_livrer,
           reference_article.quantite_stock   AS quantite_en_stock,
           reference_article.bar_code         AS code_barre,

           IF(ordre_livraison.date_fin IS NOT NULL AND preparation.date IS NOT NULL,
              ROUND(TIME_FORMAT(TIMEDIFF(ordre_livraison.date_fin, preparation.date), '%H')
                        + TIME_FORMAT(TIMEDIFF(ordre_livraison.date_fin, preparation.date), '%i') / 60
                        + TIME_FORMAT(TIMEDIFF(ordre_livraison.date_fin, preparation.date), '%s') / 3600, 4), NULL) AS delta_date

    FROM livraison AS ordre_livraison
        LEFT JOIN statut ON ordre_livraison.statut_id = statut.id
        LEFT JOIN preparation ON ordre_livraison.preparation_id = preparation.id
        LEFT JOIN utilisateur AS operateur ON ordre_livraison.utilisateur_id = operateur.id
            LEFT JOIN ligne_article_preparation ON preparation.id = ligne_article_preparation.preparation_id
            LEFT JOIN demande AS demande_livraison ON preparation.demande_id = demande_livraison.id
                LEFT JOIN utilisateur AS demandeur ON demande_livraison.utilisateur_id = demandeur.id
                LEFT JOIN type ON demande_livraison.type_id = type.id
                LEFT JOIN reference_article ON ligne_article_preparation.reference_id = reference_article.id
                LEFT JOIN emplacement AS destination ON demande_livraison.destination_id = destination.id

    WHERE ligne_article_preparation.quantite_prelevee > 0

    UNION
    SELECT
        ordre_livraison.id                 AS id,
        ordre_livraison.numero             AS numero,
        statut.nom                         AS statut,
        ordre_livraison.date               AS date_creation,
        ordre_livraison.date_fin           AS date_livraison,
        demande_livraison.date             AS date_demande,
        demandeur.username                 AS demandeur,
        operateur.username                 AS operateur,
        type.label                         AS type,
        demande_livraison.cleaned_comment  AS commentaire,
        reference_article.reference        AS reference,
        article.label                      AS libelle,
        destination.label                  AS emplacement,
        article.quantite_aprelever         AS quantite_a_livrer,
        article.quantite                   AS quantite_en_stock,
        article.bar_code                   AS code_barre,

        IF(ordre_livraison.date_fin IS NOT NULL AND preparation.date IS NOT NULL,
           ROUND(TIME_FORMAT(TIMEDIFF(ordre_livraison.date_fin, preparation.date), '%H')
                     + TIME_FORMAT(TIMEDIFF(ordre_livraison.date_fin, preparation.date), '%i') / 60
                     + TIME_FORMAT(TIMEDIFF(ordre_livraison.date_fin, preparation.date), '%s') / 3600, 4), NULL) AS delta_date

    FROM livraison AS ordre_livraison
        LEFT JOIN statut ON ordre_livraison.statut_id = statut.id
        LEFT JOIN preparation ON ordre_livraison.preparation_id = preparation.id
            LEFT JOIN utilisateur AS operateur ON ordre_livraison.utilisateur_id = operateur.id
            LEFT JOIN demande AS demande_livraison ON preparation.demande_id = demande_livraison.id
            LEFT JOIN article ON preparation.id = article.preparation_id
                LEFT JOIN type ON demande_livraison.type_id = type.id
                LEFT JOIN emplacement AS destination ON demande_livraison.destination_id = destination.id
                LEFT JOIN utilisateur AS demandeur ON demande_livraison.utilisateur_id = demandeur.id
                LEFT JOIN article_fournisseur ON article.article_fournisseur_id = article_fournisseur.id
                    LEFT JOIN reference_article ON article_fournisseur.reference_article_id = reference_article.id

    WHERE article.quantite > 0
) AS orders
