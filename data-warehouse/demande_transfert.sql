SELECT
    transfer_request.id AS id,
    transfer_request.number AS numero,
    transfer_request.creation_date AS date_creation,
    transfer_request.validation_date AS date_valication,
    statut.nom AS statut,
    demandeur.username AS demandeur,
    origine.label AS origine,
    destination.label AS destination,
    transfer_request.cleaned_comment AS commentaire,
    IF(article.id IS NOT NULL, article_reference_article.reference,
        IF(reference_article.id IS NOT NULL, reference_article.reference, NULL)) AS reference,
    IF(article.id IS NOT NULL, article.bar_code,
       IF(reference_article.id IS NOT NULL, reference_article.bar_code, NULL)) AS code_barre

FROM transfer_request

    LEFT JOIN statut ON transfer_request.status_id = statut.id
    LEFT JOIN utilisateur AS demandeur ON transfer_request.requester_id = demandeur.id
    LEFT JOIN emplacement AS origine ON transfer_request.origin_id = origine.id
    LEFT JOIN emplacement AS destination ON transfer_request.destination_id = destination.id

    LEFT JOIN transfer_request_article ON transfer_request.id = transfer_request_article.transfer_request_id
        LEFT JOIN article ON transfer_request_article.article_id = article.id
            LEFT JOIN article_fournisseur ON article.article_fournisseur_id = article_fournisseur.id
                LEFT JOIN reference_article AS article_reference_article ON article_fournisseur.reference_article_id = article_reference_article.id

    LEFT JOIN transfer_request_reference_article ON transfer_request.id = transfer_request_reference_article.transfer_request_id
        LEFT JOIN reference_article ON transfer_request_reference_article.reference_article_id = reference_article.id
