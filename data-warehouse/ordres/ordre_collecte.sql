SELECT id, numero, statut, date_creation, date_traitement, operateur, type, reference, libelle, emplacement, quantite_a_collecter, code_barre, destination, delta_date
FROM (
     SELECT
         ordre_collecte.id AS id,
         ordre_collecte.numero AS numero,
         statut.nom AS statut,
         ordre_collecte.date AS date_creation,
         ordre_collecte.treating_date AS date_traitement,
         utilisateur.username AS operateur,
         type.label AS type,
         reference_article_ordre_collecte_article.reference AS reference,
         article.label AS libelle,
         emplacement_article.label AS emplacement,
         article.quantite AS quantite_a_collecter,
         article.bar_code AS code_barre,
         IF(demande_collecte.stock_or_destruct = 1, 'Mise en stock', 'Destruction') AS destination,

         IF(demande_collecte.validation_date IS NOT NULL AND ordre_collecte.date IS NOT NULL,
            ROUND(TIME_FORMAT(TIMEDIFF(ordre_collecte.date, demande_collecte.validation_date), '%H')
                      + TIME_FORMAT(TIMEDIFF(ordre_collecte.date, demande_collecte.validation_date), '%i') / 60
                      + TIME_FORMAT(TIMEDIFF(ordre_collecte.date, demande_collecte.validation_date), '%s') / 3600, 4), NULL) AS delta_date

     FROM article_ordre_collecte
         LEFT JOIN ordre_collecte on article_ordre_collecte.ordre_collecte_id = ordre_collecte.id
             LEFT JOIN statut ON ordre_collecte.statut_id = statut.id
             LEFT JOIN utilisateur ON ordre_collecte.utilisateur_id = utilisateur.id
                LEFT JOIN collecte AS demande_collecte ON ordre_collecte.demande_collecte_id = demande_collecte.id
                    LEFT JOIN type ON demande_collecte.type_id = type.id

         LEFT JOIN article ON article_ordre_collecte.article_id = article.id
            LEFT JOIN emplacement AS emplacement_article ON article.emplacement_id = emplacement_article.id
            LEFT JOIN article_fournisseur ON article.article_fournisseur_id = article_fournisseur.id
                LEFT JOIN reference_article AS reference_article_ordre_collecte_article ON article_fournisseur.reference_article_id = reference_article_ordre_collecte_article.id

     UNION
     SELECT
         ordre_collecte.id AS id,
         ordre_collecte.numero AS numero,
         statut.nom AS statut,
         ordre_collecte.date AS date_creation,
         ordre_collecte.treating_date AS date_traitement,
         utilisateur.username AS operateur,
         type.label AS type,
         reference_article_ordre_collecte_reference.reference AS reference,
         reference_article_ordre_collecte_reference.libelle AS libelle,
         emplacement_reference_article.label AS emplacement,

         IF(reference_article_ordre_collecte_reference.id IS NOT NULL, ordre_collecte_reference.quantite, 0) AS quantite_a_collecter,

         reference_article_ordre_collecte_reference.bar_code AS code_barre,

         IF(demande_collecte.stock_or_destruct = 1, 'Mise en stock', 'Destruction') AS destination,

         IF(demande_collecte.validation_date IS NOT NULL AND ordre_collecte.date IS NOT NULL,
            ROUND(TIME_FORMAT(TIMEDIFF(ordre_collecte.date, demande_collecte.validation_date), '%H')
                      + TIME_FORMAT(TIMEDIFF(ordre_collecte.date, demande_collecte.validation_date), '%i') / 60
                      + TIME_FORMAT(TIMEDIFF(ordre_collecte.date, demande_collecte.validation_date), '%s') / 3600, 4), NULL) AS delta_date


     FROM ordre_collecte_reference
              LEFT JOIN ordre_collecte on ordre_collecte_reference.ordre_collecte_id = ordre_collecte.id
                LEFT JOIN statut ON ordre_collecte.statut_id = statut.id
                LEFT JOIN utilisateur ON ordre_collecte.utilisateur_id = utilisateur.id
                LEFT JOIN collecte AS demande_collecte ON ordre_collecte.demande_collecte_id = demande_collecte.id
                    LEFT JOIN type ON demande_collecte.type_id = type.id

              LEFT JOIN reference_article AS reference_article_ordre_collecte_reference
                        ON ordre_collecte_reference.reference_article_id = reference_article_ordre_collecte_reference.id
                  LEFT JOIN emplacement AS emplacement_reference_article
                            ON reference_article_ordre_collecte_reference.emplacement_id = emplacement_reference_article.id
) AS orders
