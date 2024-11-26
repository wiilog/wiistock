SELECT production_request.id                                                     AS production_id,
       production_request.manufacturing_order_number                             AS numero_of,
       production_request.emergency                                              AS urgence,
       production_request.expected_at                                            AS date_attendue,
       production_request.project_number                                         AS numero_projet,
       production_request.product_article_code                                   AS code_produit_article,
       emplacement.label                                                         AS emplacement_depose,
       production_request.cleaned_comment                                        AS commentaire,
       IF(production_request_attachment.attachment_id IS NOT NULL, 'Oui', 'Non') AS piece_jointe,
       production_request.quantity                                               AS quantite,
       production_request.line_count                                             AS nombre_lignes,
       destination_location.label                                                AS emplacement_destination
FROM production_request
         LEFT JOIN emplacement ON production_request.drop_location_id = emplacement.id
         LEFT JOIN production_request_attachment
                   ON production_request.id = production_request_attachment.production_request_id
         LEFT JOIN emplacement destination_location ON production_request.destination_location_id = destination_location.id
