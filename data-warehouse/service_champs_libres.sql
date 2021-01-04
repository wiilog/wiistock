SELECT *
FROM (
         SELECT handling_free_field_tmp.service_id,
                handling_free_field_tmp.libelle,
                IF(handling_free_field_tmp.typage = 'datetime',
                   DATE_FORMAT(REPLACE(handling_free_field_tmp.valeur, 'T', ' '),
                               '%d/%m/%Y %T'),
                   IF(handling_free_field_tmp.typage = 'date',
                      DATE_FORMAT(handling_free_field_tmp.valeur, '%d/%m/%Y'),
                      IF((handling_free_field_tmp.typage = 'booleen' AND handling_free_field_tmp.valeur = 1), 'oui',
                         IF((handling_free_field_tmp.typage = 'booleen' AND handling_free_field_tmp.valeur = 0), 'non',
                            handling_free_field_tmp.valeur)))) AS valeur
         FROM (
                  SELECT handling_free_field.handling_id                            AS service_id,
                         free_field.label                                                             AS libelle,
                         free_field.typage,
                         JSON_UNQUOTE(JSON_EXTRACT(handling.free_fields,
                                      CONCAT('$."', handling_free_field.free_field_id, '"'))) AS valeur
                  FROM handling
                           INNER JOIN (
                      -- associate handling.id to free_field_id
                      SELECT handling.id AS handling_id,
                             res.*
                      FROM handling,
                           JSON_TABLE(
                                   JSON_KEYS(handling.free_fields),
                                   '$[*]' COLUMNS (free_field_id INT path '$')) AS res

                      WHERE handling.free_fields IS NOT NULL
                        AND JSON_LENGTH(handling.free_fields) <> 0
                  ) AS handling_free_field
                                      ON handling.id = handling_free_field.handling_id
                           INNER JOIN free_field on free_field.id = handling_free_field.free_field_id
              ) AS handling_free_field_tmp
     ) AS handling_free_field
WHERE handling_free_field.valeur IS NOT NULL
  AND handling_free_field.valeur <> ''
