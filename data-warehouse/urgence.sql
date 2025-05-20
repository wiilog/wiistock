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
    emplacement.label AS emplacement_urgence,
    emergency.date_start AS debut_delais_livraison,
    emergency.date_end AS fin_delais_livraison,
    emergency.order_number AS no_commande,
    tracking_emergency.internal_article_code AS code_article_interne,
    tracking_emergency.supplier_article_code AS code_article_fournisseur,
    emergency.last_triggered_at AS date_dernier_declenchement,
    stock_emergency.expected_quantity AS quantite,
    emergency.carrier_tracking_number AS no_tracking,
    fournisseur.code_reference AS fournisseur,
    transporteur.code AS transporteur

FROM emergency
     LEFT JOIN type ON emergency.type_id = type.id
     LEFT JOIN stock_emergency ON emergency.id = stock_emergency.id
     LEFT JOIN tracking_emergency ON emergency.id = tracking_emergency.id
     LEFT JOIN emplacement ON stock_emergency.expected_location_id = emplacement.id
     LEFT JOIN fournisseur ON emergency.supplier_id = fournisseur.id
     LEFT JOIN transporteur ON emergency.carrier_id = transporteur.id
