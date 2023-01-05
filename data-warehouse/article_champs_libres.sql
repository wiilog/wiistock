SELECT *
FROM (
         SELECT article_free_field_tmp.article_id,
                article_free_field_tmp.libelle,
                IF(article_free_field_tmp.typage = 'datetime',
                   DATE_FORMAT(REPLACE(article_free_field_tmp.valeur, 'T', ' '),
                               '%d/%m/%Y %T'),
                   IF(article_free_field_tmp.typage = 'date',
                      DATE_FORMAT(article_free_field_tmp.valeur, '%d/%m/%Y'),
                      IF((article_free_field_tmp.typage = 'booleen' AND article_free_field_tmp.valeur = 1), 'oui',
                         IF((article_free_field_tmp.typage = 'booleen' AND article_free_field_tmp.valeur = 0), 'non',
                            article_free_field_tmp.valeur)))) AS valeur
         FROM (
                  SELECT article_free_field.article_id                            AS article_id,
                         free_field.label                                         AS libelle,
                         free_field.typage,
                         JSON_UNQUOTE(JSON_EXTRACT(article.free_fields,
                                                   CONCAT('$."', article_free_field.free_field_id, '"'))) AS valeur
                  FROM article
                           INNER JOIN (
                      -- associate article.id to free_field_id
                      SELECT article.id AS article_id,
                             res.*
                      FROM article,
                           JSON_TABLE(
                               JSON_KEYS(article.free_fields),
                               '$[*]' COLUMNS (free_field_id INT path '$')) AS res

                      WHERE article.free_fields IS NOT NULL
                        AND JSON_LENGTH(article.free_fields) <> 0
                  ) AS article_free_field
                                      ON article.id = article_free_field.article_id
                           INNER JOIN free_field on free_field.id = article_free_field.free_field_id
              ) AS article_free_field_tmp
     ) AS article_free_field
WHERE article_free_field.valeur IS NOT NULL
  AND article_free_field.valeur <> ''
