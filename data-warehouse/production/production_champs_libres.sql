SELECT *
FROM (
         SELECT production_free_field_tmp.production_id,
                production_free_field_tmp.libelle,
                IF(production_free_field_tmp.typage = 'datetime',
                   DATE_FORMAT(REPLACE(production_free_field_tmp.valeur, 'T', ' '),
                               '%d/%m/%Y %T'),
                   IF(production_free_field_tmp.typage = 'date',
                      DATE_FORMAT(production_free_field_tmp.valeur, '%d/%m/%Y'),
                      IF((production_free_field_tmp.typage = 'booleen' AND production_free_field_tmp.valeur = 1), 'oui',
                         IF((production_free_field_tmp.typage = 'booleen' AND production_free_field_tmp.valeur = 0), 'non',
                            production_free_field_tmp.valeur)))) AS valeur
         FROM (
                  SELECT production_free_field.production_id                            AS production_id,
                         free_field.label                                                             AS libelle,
                         free_field.typage,
                         JSON_UNQUOTE(JSON_EXTRACT(production_request.free_fields,
                                                   CONCAT('$."', production_free_field.free_field_id, '"'))) AS valeur
                  FROM production_request
                           INNER JOIN (
                      -- associate production_request.id to free_field_id
                      SELECT production_request.id AS production_id,
                             res.*
                      FROM production_request,
                           JSON_TABLE(
                                   JSON_KEYS(production_request.free_fields),
                                   '$[*]' COLUMNS (free_field_id INT path '$')) AS res

                      WHERE production_request.free_fields IS NOT NULL
                        AND JSON_LENGTH(production_request.free_fields) <> 0
                  ) AS production_free_field
                                      ON production_request.id = production_free_field.production_id
                           INNER JOIN free_field on free_field.id = production_free_field.free_field_id
              ) AS production_free_field_tmp
     ) AS production_free_field
WHERE production_free_field.valeur IS NOT NULL
  AND production_free_field.valeur <> ''
