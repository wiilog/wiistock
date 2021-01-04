SELECT *
FROM (
         SELECT reception_free_field_tmp.reception_id,
                reception_free_field_tmp.libelle,
                IF(reception_free_field_tmp.typage = 'datetime',
                   DATE_FORMAT(REPLACE(reception_free_field_tmp.valeur, 'T', ' '),
                               '%d/%m/%Y %T'),
                   IF(reception_free_field_tmp.typage = 'date',
                      DATE_FORMAT(reception_free_field_tmp.valeur, '%d/%m/%Y'),
                      IF((reception_free_field_tmp.typage = 'booleen' AND reception_free_field_tmp.valeur = 1), 'oui',
                         IF((reception_free_field_tmp.typage = 'booleen' AND reception_free_field_tmp.valeur = 0), 'non',
                            reception_free_field_tmp.valeur)))) AS valeur
         FROM (
                  SELECT reception_free_field.reception_id                            AS reception_id,
                         free_field.label                                                             AS libelle,
                         free_field.typage,
                         JSON_UNQUOTE(JSON_EXTRACT(reception.free_fields,
                                      CONCAT('$."', reception_free_field.free_field_id, '"'))) AS valeur
                  FROM reception
                           INNER JOIN (
                      -- associate reception.id to free_field_id
                      SELECT reception.id AS reception_id,
                             res.*
                      FROM reception,
                           JSON_TABLE(
                                   JSON_KEYS(reception.free_fields),
                                   '$[*]' COLUMNS (free_field_id INT path '$')) AS res

                      WHERE reception.free_fields IS NOT NULL
                        AND JSON_LENGTH(reception.free_fields) <> 0
                  ) AS reception_free_field
                                      ON reception.id = reception_free_field.reception_id
                           INNER JOIN free_field on free_field.id = reception_free_field.free_field_id
              ) AS reception_free_field_tmp
     ) AS reception_free_field
WHERE reception_free_field.valeur IS NOT NULL
  AND reception_free_field.valeur <> ''
