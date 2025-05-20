SELECT emergency.id AS id_urgence,
       arrivage_tracking_emergency.arrivage_id AS id_arrivage,
       reception_line.reception_id AS id_reception,
       reception_reference_article.reference_article_id AS id_reference_article
FROM emergency
         LEFT JOIN arrivage_tracking_emergency
                   ON arrivage_tracking_emergency.tracking_emergency_id = emergency.id
         LEFT JOIN reception_reference_article_stock_emergency
                   ON reception_reference_article_stock_emergency.stock_emergency_id = emergency.id
         LEFT JOIN reception_reference_article
                   ON reception_reference_article.id = reception_reference_article_stock_emergency.reception_reference_article_id
         LEFT JOIN reception_line
                   ON reception_line.id = reception_reference_article.reception_line_id
