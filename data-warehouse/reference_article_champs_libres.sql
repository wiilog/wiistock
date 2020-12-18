SELECT *
FROM (
         SELECT reference_article_free_field_tmp.reference_article_id,
                reference_article_free_field_tmp.libelle,
                IF(reference_article_free_field_tmp.typage = 'datetime',
                   DATE_FORMAT(REPLACE(reference_article_free_field_tmp.valeur, 'T', ' '),
                               '%d/%m/%Y %T'),
                   IF(reference_article_free_field_tmp.typage = 'date',
                      DATE_FORMAT(reference_article_free_field_tmp.valeur, '%d/%m/%Y'),
                      IF((reference_article_free_field_tmp.typage = 'booleen' AND reference_article_free_field_tmp.valeur = 1), 'oui',
                         IF((reference_article_free_field_tmp.typage = 'booleen' AND reference_article_free_field_tmp.valeur = 0), 'non',
                            reference_article_free_field_tmp.valeur)))) AS valeur
         FROM (
                  SELECT reference_article_free_field.reference_article_id                            AS reference_article_id,
                         free_field.label                                                             AS libelle,
                         free_field.typage,
                         JSON_UNQUOTE(JSON_EXTRACT(reference_article.free_fields,
                                      CONCAT('$."', reference_article_free_field.free_field_id, '"'))) AS valeur
                  FROM reference_article
                           INNER JOIN (
                      -- associate reference_article.id to free_field_id
                      SELECT reference_article.id AS reference_article_id,
                             res.*
                      FROM reference_article,
                           JSON_TABLE(
                                   JSON_KEYS(reference_article.free_fields),
                                   '$[*]' COLUMNS (free_field_id INT path '$')) AS res

                      WHERE reference_article.free_fields IS NOT NULL
                        AND JSON_LENGTH(reference_article.free_fields) <> 0
                  ) AS reference_article_free_field
                                      ON reference_article.id = reference_article_free_field.reference_article_id
                           INNER JOIN free_field on free_field.id = reference_article_free_field.free_field_id
              ) AS reference_article_free_field_tmp
     ) AS reference_article_free_field
WHERE reference_article_free_field.valeur IS NOT NULL
  AND reference_article_free_field.valeur <> ''
