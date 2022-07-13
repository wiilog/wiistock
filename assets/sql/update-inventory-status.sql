UPDATE reference_article ra
    LEFT JOIN inventory_category ic on ra.category_id = ic.id
    LEFT JOIN inventory_frequency f on ic.frequency_id = f.id

SET up_to_date_inventory =
        IF(
            f.id IS NULL OR
            ra.type_quantite = :referenceQuantityManagement OR
            (
                SELECT 1
                WHERE NOT EXISTS (
                    SELECT a.id
                    FROM (SELECT * FROM reference_article) as reference_articles_count
                             INNER JOIN article_fournisseur af on reference_articles_count.id = af.reference_article_id
                             INNER JOIN article a on af.id = a.article_fournisseur_id
                             INNER JOIN statut s on a.statut_id = s.id
                    WHERE reference_articles_count.id = ra.id
                      AND s.code IN (:articleStatuses)
                )
            ) OR
            (
                (
                    SELECT 1
                    WHERE EXISTS (
                        SELECT a.id
                        FROM (SELECT * FROM reference_article) as reference_articles_count_with_date
                            INNER JOIN article_fournisseur af on reference_articles_count_with_date.id = af.reference_article_id
                            INNER JOIN article a on af.id = a.article_fournisseur_id
                            INNER JOIN statut s on a.statut_id = s.id
                        WHERE reference_articles_count_with_date.id = ra.id
                          AND s.code IN (:articleStatuses)
                          AND a.date_last_inventory IS NOT NULL
                    )
                ) AND
                (
                    DATEDIFF(
                        NOW(),
                        (
                            SELECT a.date_last_inventory
                            FROM (SELECT * FROM reference_article) as reference_articles_with_date
                                INNER JOIN article_fournisseur af on reference_articles_with_date.id = af.reference_article_id
                                INNER JOIN article a on af.id = a.article_fournisseur_id
                                INNER JOIN statut s on a.statut_id = s.id
                            WHERE reference_articles_with_date.id = ra.id
                              AND s.code IN (:articleStatuses)
                              AND a.date_last_inventory IS NOT NULL
                            ORDER BY a.date_last_inventory DESC
                            LIMIT 1
                        )
                    )/30 < f.nb_months
                )
            ), 1, 0
        )
WHERE 1;
