SELECT reception_reference_article_stock_emergency.stock_emergency_id AS urgence_id,
       reception_line.reception_id AS reception_id
FROM reception_reference_article_stock_emergency
         LEFT JOIN reception_reference_article
                   ON reception_reference_article.id = reception_reference_article_stock_emergency.reception_reference_article_id
         LEFT JOIN reception_line
                   ON reception_line.id = reception_reference_article.reception_line_id
