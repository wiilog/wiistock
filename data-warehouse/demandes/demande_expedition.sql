SELECT shipping_request.id,
       shipping_request.number                                                          AS numero,
       statut.code                                                                      AS statut,
       shipping_request.created_at                                                      AS date_creation,
       shipping_request.validated_at                                                    AS date_validation,
       shipping_request.planned_at                                                      AS date_planification,
       shipping_request.expected_picked_at                                              AS date_enlevement_prevu,
       shipping_request.treated_at                                                      AS date_expedition,
       shipping_request.request_cared_at                                                AS date_prise_en_charge_souhaitee,
       GROUP_CONCAT(DISTINCT requester.username SEPARATOR ', ')                         AS demandeur,
       shipping_request.customer_order_number                                           AS numero_commande_client,
       IF(shipping_request.free_delivery = 1, 'oui', 'non')                             AS livraison_titre_gracieux,
       IF(shipping_request.compliant_articles = 1, 'oui', 'non')                        AS articles_conformes,
       shipping_request.customer_name                                                   AS client,
       shipping_request.customer_recipient                                              AS destinataire_client,
       shipping_request.customer_phone                                                  AS telephone_client,
       shipping_request.customer_address                                                AS adresse_livraison,
       reference_article.reference                                                      AS code_reference,
       shipping_request_expected_line.quantity                                          AS quantite_reference,
       IF(SUM(shipping_request_line.id) = 0, null, pack.code)                           AS code_UL,
       IF(SUM(shipping_request_line.id) = 0, null, nature.code)                         AS nature_UL,
       IF(SUM(shipping_request_line.id) = 0, null, article.bar_code)                    AS code_article,
       IF(SUM(shipping_request_line.id) = 0, null, shipping_request_line.quantity)      AS quantite_article,
       reference_article.prix_unitaire                                                  AS prix_unitaire_reference,
       shipping_request_expected_line.weight                                            AS poids_net_reference,
       IF(SUM(shipping_request_line.id) = 0, null, (shipping_request_line.quantity*reference_article.prix_unitaire))    AS montant_total_reference,
       shipping_request.shipment                                                        AS envoi,
       shipping_request.carrying                                                        AS port,
       shipping_request.net_weight                                                      AS poids_net_transport,
       IF(SUM(shipping_request_line.id) = 0, null, shipping_request_pack.size)          AS dimension_colis,
       shipping_request.total_value                                                     AS valeur_total_transport,
       COUNT(shipping_request_pack.id)                                                  AS nombre_colis,
       shipping_request.gross_weight                                                    AS poids_brut_transport

FROM shipping_request

    LEFT JOIN statut ON shipping_request.status_id = statut.id
    LEFT JOIN shipping_request_requester ON shipping_request.id = shipping_request_requester.shipping_request_id
    LEFT JOIN utilisateur requester ON shipping_request_requester.utilisateur_id = requester.id
    LEFT JOIN shipping_request_expected_line ON shipping_request.id = shipping_request_expected_line.request_id
    LEFT JOIN reference_article ON shipping_request_expected_line.reference_article_id = reference_article.id
    LEFT JOIN shipping_request_line ON shipping_request_expected_line.id = shipping_request_line.expected_line_id
    LEFT JOIN article ON shipping_request_line.article_id = article.id
    LEFT JOIN shipping_request_pack ON shipping_request_line.shipping_pack_id = shipping_request_pack.id
    LEFT JOIN pack ON shipping_request_pack.pack_id = pack.id
    LEFT JOIN nature ON pack.nature_id = nature.id

GROUP BY shipping_request.id, reference_article.reference, shipping_request_expected_line.quantity, pack.code,
         nature.code, article.bar_code, shipping_request_line.quantity, shipping_request_expected_line.weight,
         shipping_request_pack.size

