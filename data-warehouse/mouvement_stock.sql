SELECT
    mouvement_stock.id,
    demande_collecte.id AS demande_collecte_id,
    ordre_livraison_demande.id AS demande_livraison_id,
    mouvement_stock.type     AS type_mouvement, -- CEA

    IF(ordre_collecte.id IS NOT NULL, type_collecte.label,
       IF(ordre_livraison.id IS NOT NULL, ordre_livraison_type.label,
          IF(ordre_preparation.id IS NOT NULL, ordre_livraison_type.label, NULL)))
        AS type_flux, -- CEA

    emplacement_prise.label  AS emplacement_prise, -- CEA
    emplacement_depose.label AS emplacement_depose, -- CEA

    IF(ordre_collecte.id IS NOT NULL, demandeur_collecte.username,
       IF(ordre_transfert.id IS NOT NULL, demandeur_transfert.username,
          IF(ordre_livraison.id IS NOT NULL, ordre_livraison_demandeur.username,
             IF(ordre_preparation.id IS NOT NULL, ordre_preparation_demandeur.username,
                IF(ordre_reception.id IS NOT NULL, reception_utilisateur.username,
                   IF(import.id IS NOT NULL, import_utilisateur.username, NULL))))))
        AS demandeur, -- CEA

    IF(ordre_collecte.id IS NOT NULL, demande_collecte.cleaned_comment,
       IF(ordre_transfert.id IS NOT NULL, demande_transfert.cleaned_comment,
          IF(ordre_livraison.id IS NOT NULL, ordre_livraison_demande.cleaned_comment,
             IF(ordre_preparation.id IS NOT NULL, ordre_preparation_demande.cleaned_comment, NULL))))
        AS commentaire_demande, -- CEA

    IF(ordre_collecte.id IS NOT NULL, demande_collecte.numero,
       IF(ordre_transfert.id IS NOT NULL, demande_transfert.number,
          IF(ordre_livraison.id IS NOT NULL, ordre_livraison_demande.numero,
             IF(ordre_preparation.id IS NOT NULL, ordre_preparation_demande.numero, NULL))))
        AS numero_demande, -- CEA

    IF(ordre_collecte.id IS NOT NULL, ordre_collecte.numero,
       IF(ordre_transfert.id IS NOT NULL, ordre_transfert.number,
          IF(ordre_livraison.id IS NOT NULL, ordre_livraison.numero,
             IF(ordre_preparation.id IS NOT NULL, ordre_preparation.numero,
                IF(ordre_transfert.id IS NOT NULL, ordre_transfert.number,
                   IF(ordre_reception.id IS NOT NULL, ordre_reception.numero_reception, NULL))))))
        AS numero_ordre, -- CEA

    IF(reference_article.id IS NOT NULL, reference_article.bar_code,
       IF(article.id IS NOT NULL, article_reference_article.bar_code, NULL)) AS code_barre_reference, -- CEA
    article.bar_code AS code_barre_article, -- CEA

    IF(reference_article.id IS NOT NULL, reference_article.reference,
       IF(article_id IS NOT NULL, article_reference_article.reference, NULL))
        AS reference, -- CEA

    IF(reference_article.id IS NOT NULL, reference_article.libelle,
       IF(article.id IS NOT NULL, article_reference_article.libelle, NULL))
        AS libelle, -- CEA

    mouvement_stock.quantity AS quantite_livree, -- CEA

    operateur.username AS operateur, -- CEA

    IF(ordre_livraison.id IS NOT NULL, emplacement_transfert.label, NULL) AS emplacement_transfert, -- CEA

    mouvement_stock.date AS date, -- CEA

    IF(reference_article.id IS NOT NULL, emplacement_stock_reference_article.label,
       IF(article.id IS NOT NULL, emplacement_stock_article.label, NULL))
        AS emplacement_stock, -- CEA

    IF(demande_collecte.validation_date IS NOT NULL AND ordre_collecte.date IS NOT NULL,
       ROUND(TIME_FORMAT(TIMEDIFF(ordre_collecte.date, demande_collecte.validation_date), '%H')
                 + TIME_FORMAT(TIMEDIFF(ordre_collecte.date, demande_collecte.validation_date), '%i') / 60
                 + TIME_FORMAT(TIMEDIFF(ordre_collecte.date, demande_collecte.validation_date), '%s') / 3600, 4),
       IF(ordre_livraison_demande.date IS NOT NULL AND ordre_livraison.date IS NOT NULL,
          ROUND(TIME_FORMAT(TIMEDIFF(ordre_livraison.date, ordre_livraison_demande.date), '%H')
                    + TIME_FORMAT(TIMEDIFF(ordre_livraison.date, ordre_livraison_demande.date), '%i') / 60
                    + TIME_FORMAT(TIMEDIFF(ordre_livraison.date, ordre_livraison_demande.date), '%s') / 3600, 4),
          IF(demande_transfert.validation_date IS NOT NULL AND ordre_transfert.transfer_date IS NOT NULL,
             ROUND(TIME_FORMAT(TIMEDIFF(ordre_transfert.transfer_date, demande_transfert.validation_date), '%H')
                       + TIME_FORMAT(TIMEDIFF(ordre_transfert.transfer_date, demande_transfert.validation_date), '%i') / 60
                       + TIME_FORMAT(TIMEDIFF(ordre_transfert.transfer_date, demande_transfert.validation_date), '%s') / 3600, 4),
             NULL))) AS delta_date

FROM mouvement_stock
         LEFT JOIN emplacement AS emplacement_prise ON mouvement_stock.emplacement_from_id = emplacement_prise.id
         LEFT JOIN emplacement AS emplacement_depose ON mouvement_stock.emplacement_to_id = emplacement_depose.id

         LEFT JOIN utilisateur AS operateur ON mouvement_stock.user_id = operateur.id

         LEFT JOIN preparation AS ordre_preparation ON mouvement_stock.preparation_order_id = ordre_preparation.id
         LEFT JOIN demande AS ordre_preparation_demande ON ordre_preparation.demande_id = ordre_preparation_demande.id
         LEFT JOIN type AS ordre_preparation_type ON ordre_preparation_demande.type_id = ordre_preparation_type.id
         LEFT JOIN utilisateur AS ordre_preparation_demandeur ON ordre_preparation_demande.utilisateur_id = ordre_preparation_demandeur.id

         LEFT JOIN livraison AS ordre_livraison ON mouvement_stock.livraison_order_id = ordre_livraison.id
         LEFT JOIN preparation AS ordre_livraison_preparation ON ordre_livraison.preparation_id = ordre_livraison_preparation.id
         LEFT JOIN emplacement AS emplacement_transfert ON ordre_livraison_preparation.end_location_id = emplacement_transfert.id
         LEFT JOIN demande AS ordre_livraison_demande ON ordre_livraison_preparation.demande_id = ordre_livraison_demande.id
         LEFT JOIN type AS ordre_livraison_type ON ordre_livraison_demande.type_id = ordre_livraison_type.id
         LEFT JOIN utilisateur AS ordre_livraison_demandeur ON ordre_livraison_demande.utilisateur_id = ordre_livraison_demandeur.id

         LEFT JOIN ordre_collecte ON mouvement_stock.collecte_order_id = ordre_collecte.id
         LEFT JOIN collecte AS demande_collecte ON ordre_collecte.demande_collecte_id = demande_collecte.id
         LEFT JOIN type AS type_collecte ON demande_collecte.type_id = type_collecte.id
         LEFT JOIN utilisateur AS demandeur_collecte ON demande_collecte.demandeur_id = demandeur_collecte.id

         LEFT JOIN transfer_order AS ordre_transfert ON mouvement_stock.transfer_order_id = ordre_transfert.id
         LEFT JOIN transfer_request AS demande_transfert ON ordre_transfert.request_id = demande_transfert.id
         LEFT JOIN utilisateur AS demandeur_transfert ON demande_transfert.requester_id = demandeur_transfert.id

         LEFT JOIN reception AS ordre_reception ON mouvement_stock.reception_order_id = ordre_reception.id
         LEFT JOIN utilisateur AS reception_utilisateur ON ordre_reception.utilisateur_id = reception_utilisateur.id
         LEFT JOIN import ON mouvement_stock.import_id = import.id
         LEFT JOIN utilisateur AS import_utilisateur ON import.user_id = import_utilisateur.id

         LEFT JOIN reference_article ON mouvement_stock.ref_article_id = reference_article.id
         LEFT JOIN emplacement AS emplacement_stock_reference_article ON reference_article.emplacement_id = emplacement_stock_reference_article.id
         LEFT JOIN article ON mouvement_stock.article_id = article.id
         LEFT JOIN emplacement AS emplacement_stock_article ON article.emplacement_id = emplacement_stock_article.id
         LEFT JOIN article_fournisseur AS article_article_fournisseur ON article.article_fournisseur_id = article_article_fournisseur.id
         LEFT JOIN reference_article AS article_reference_article ON article_article_fournisseur.reference_article_id = article_reference_article.id
