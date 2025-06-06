SELECT
    emergency.id,
    type.label AS type,
    (CASE
         WHEN stock_emergency.emergency_trigger = 'supplier' THEN 'Fournisseur'
         WHEN stock_emergency.emergency_trigger = 'reference' THEN 'Référence'
    END) AS declencheur_urgence,
    (CASE
         WHEN emergency.end_emergency_criteria = 'end_date' THEN 'Quantité restante'
         WHEN emergency.end_emergency_criteria = 'remaining_quantity' THEN 'Durée de validité'
         WHEN emergency.end_emergency_criteria = 'manual' THEN 'Manuelle'
    END) AS critere_fin_urgence,
    emergency.comment AS commentaire,
    expected_location.label AS emplacement_urgence,
    emergency.date_start AS debut_delais_livraison,
    emergency.date_end AS fin_delais_livraison,
    emergency.order_number AS no_commande,
    tracking_emergency.internal_article_code AS code_article_interne,
    tracking_emergency.supplier_article_code AS code_article_fournisseur,
    emergency.last_triggered_at AS date_dernier_declenchement,
    stock_emergency.expected_quantity AS quantite,
    emergency.carrier_tracking_number AS no_tracking,
    supplier.code_reference AS fournisseur,
    carrier.code AS transporteur,
    stock_emergency.reference_article_id as reference_article_id,
    CONCAT_WS('',(
            SELECT reception.number
            FROM reception
            INNER JOIN reception_line ON reception.id = reception_line.reception_id
            INNER JOIN reception_reference_article ON reception_line.id = reception_reference_article.reception_line_id
            INNER JOIN reception_reference_article_stock_emergency ON reception_reference_article.id = reception_reference_article_stock_emergency.reception_reference_article_id
            INNER JOIN stock_emergency ON reception_reference_article_stock_emergency.stock_emergency_id = stock_emergency.id
            WHERE stock_emergency.id = emergency.id
            ORDER BY reception.date DESC
            LIMIT 1
        ), (
            SELECT arrivage.numero_arrivage
            FROM arrivage
            INNER JOIN arrivage_tracking_emergency ON arrivage.id = arrivage_tracking_emergency.arrivage_id
            INNER JOIN tracking_emergency ON arrivage_tracking_emergency.tracking_emergency_id = tracking_emergency.id
            WHERE tracking_emergency.id = emergency.id
            ORDER BY arrivage.date DESC
            LIMIT 1
        )
    ) AS dernier_numero_arrivage_reception

FROM emergency
     LEFT JOIN type ON emergency.type_id = type.id
     LEFT JOIN stock_emergency ON emergency.id = stock_emergency.id
     LEFT JOIN tracking_emergency ON emergency.id = tracking_emergency.id
     LEFT JOIN emplacement expected_location ON stock_emergency.expected_location_id = expected_location.id
     LEFT JOIN fournisseur supplier ON emergency.supplier_id = supplier.id
     LEFT JOIN transporteur carrier ON emergency.carrier_id = carrier.id
