INSERT INTO inventory_mission_reference_article (reference_article_id, inventory_mission_id)
SELECT DISTINCT reference_article.id, :mission
FROM reference_article
         INNER JOIN inventory_category ic on reference_article.category_id = ic.id
         INNER JOIN statut s on reference_article.statut_id = s.id
         INNER JOIN inventory_frequency i on ic.frequency_id = i.id
         LEFT JOIN inventory_mission_reference_article imra ON reference_article.id = imra.reference_article_id
         LEFT JOIN inventory_mission im on imra.inventory_mission_id = im.id
WHERE (
    im.id IS NULL OR
    (
        (im.start_prev_date > :start AND im.start_prev_date > :end) OR
        (im.end_prev_date < :start AND im.end_prev_date < :end)
    ) AND
    i.id = :frequency AND
    s.nom = :activeStatus AND
    reference_article.type_quantite = :referenceQuantityManagement AND
    reference_article.date_last_inventory IS NOT NULL AND
    TIMESTAMPDIFF(
        MONTH,
        reference_article.date_last_inventory,
        NOW()
    ) >= i.nb_months
);
