SELECT *
FROM (
         SELECT dispatch_free_field_tmp.acheminement_id,
                dispatch_free_field_tmp.libelle,
                IF(dispatch_free_field_tmp.typage = 'datetime',
                   DATE_FORMAT(REPLACE(dispatch_free_field_tmp.valeur, 'T', ' '),
                               '%d/%m/%Y %T'),
                   IF(dispatch_free_field_tmp.typage = 'date',
                      DATE_FORMAT(dispatch_free_field_tmp.valeur, '%d/%m/%Y'),
                      IF((dispatch_free_field_tmp.typage = 'booleen' AND dispatch_free_field_tmp.valeur = 1), 'oui',
                         IF((dispatch_free_field_tmp.typage = 'booleen' AND dispatch_free_field_tmp.valeur = 0), 'non',
                            dispatch_free_field_tmp.valeur)))) AS valeur
         FROM (
                  SELECT dispatch_free_field.dispatch_id                                                   AS acheminement_id,
                         free_field.label                                                                  AS libelle,
                         free_field.typage,
                         JSON_UNQUOTE(JSON_EXTRACT(dispatch.free_fields,
                                                   CONCAT('$."', dispatch_free_field.free_field_id, '"'))) AS valeur
                  FROM dispatch
                           INNER JOIN (
                      -- associate dispatch.id to free_field_id
                      SELECT dispatch.id AS dispatch_id,
                             res.*
                      FROM dispatch,
                           JSON_TABLE(
                                   JSON_KEYS(dispatch.free_fields),
                                   '$[*]' COLUMNS (free_field_id INT path '$')) AS res

                      WHERE dispatch.free_fields IS NOT NULL
                        AND JSON_LENGTH(dispatch.free_fields) <> 0
                  ) AS dispatch_free_field
                                      ON dispatch.id = dispatch_free_field.dispatch_id
                           INNER JOIN free_field on free_field.id = dispatch_free_field.free_field_id
              ) AS dispatch_free_field_tmp
     ) AS dispatch_free_field
WHERE dispatch_free_field.valeur IS NOT NULL
  AND dispatch_free_field.valeur <> ''
