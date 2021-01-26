SELECT
    transfer_order.id AS id,
    transfer_order.number AS numero_ordre,
    transfer_request.number AS numero_demande,
    statut.nom AS statut,
    demandeur.username AS demandeur,
    operateur.username AS operateur,
    origine.label AS origine,
    destination.label AS destination,
    transfer_order.creation_date AS date_creation,
    transfer_order.transfer_date AS date_transfert,
    transfer_request.cleaned_comment AS commentaire,

       IF(article.id IS NOT NULL, reference_article_transfer_request_article.reference,
           IF(reference_article_transfer_request_reference_article.id IS NOT NULL, reference_article_transfer_request_reference_article.reference, NULL))
                                     AS reference,

    IF(article.id IS NOT NULL, article.bar_code,
       IF(reference_article_transfer_request_reference_article.id IS NOT NULL, reference_article_transfer_request_reference_article.bar_code, NULL))
                                     AS code_barre

FROM transfer_order

LEFT JOIN transfer_request ON transfer_order.request_id = transfer_request.id
LEFT JOIN statut ON transfer_order.status_id = statut.id
LEFT JOIN utilisateur AS demandeur ON transfer_request.requester_id = demandeur.id
LEFT JOIN utilisateur AS operateur ON transfer_order.operator_id = operateur.id
LEFT JOIN emplacement AS origine ON transfer_request.origin_id = origine.id
LEFT JOIN emplacement AS destination ON transfer_request.destination_id = destination.id

LEFT JOIN transfer_request_article ON transfer_request.id = transfer_request_article.transfer_request_id
    LEFT JOIN article ON transfer_request_article.article_id = article.id
        LEFT JOIN article_fournisseur ON article.article_fournisseur_id = article_fournisseur.id
            LEFT JOIN reference_article AS reference_article_transfer_request_article
                ON article_fournisseur.reference_article_id = reference_article_transfer_request_article.id

LEFT JOIN transfer_request_reference_article ON transfer_request.id = transfer_request_reference_article.transfer_request_id
    LEFT JOIN reference_article AS reference_article_transfer_request_reference_article
        ON transfer_request_reference_article.reference_article_id = reference_article_transfer_request_reference_article.id
