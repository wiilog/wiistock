SELECT *
FROM (
         SELECT emergency_free_field_tmp.emergency_id AS urgence_id,
                emergency_free_field_tmp.libelle,
                IF(emergency_free_field_tmp.typage = 'datetime',
                   DATE_FORMAT(REPLACE(emergency_free_field_tmp.valeur, 'T', ' '),
                               '%d/%m/%Y %T'),
                   IF(emergency_free_field_tmp.typage = 'date',
                      DATE_FORMAT(emergency_free_field_tmp.valeur, '%d/%m/%Y'),
                      IF((emergency_free_field_tmp.typage = 'booleen' AND emergency_free_field_tmp.valeur = 1), 'oui',
                         IF((emergency_free_field_tmp.typage = 'booleen' AND emergency_free_field_tmp.valeur = 0), 'non',
                            emergency_free_field_tmp.valeur)))) AS valeur
         FROM (
                  SELECT emergency_free_field.emergency_id                                                          AS emergency_id,
                         free_field.label                                                                           AS libelle,
                         free_field.typage,
                         JSON_UNQUOTE(JSON_EXTRACT(emergency.free_fields,
                                                   CONCAT('$."', emergency_free_field.free_field_id, '"'))) AS valeur
                  FROM emergency
                           INNER JOIN (
                      -- associate emergency.id to free_field_id
                      SELECT emergency.id AS emergency_id,
                             res.*
                      FROM emergency,
                           JSON_TABLE(
                                   JSON_KEYS(emergency.free_fields),
                                   '$[*]' COLUMNS (free_field_id INT path '$')) AS res

                      WHERE emergency.free_fields IS NOT NULL
                        AND JSON_LENGTH(emergency.free_fields) <> 0
                  ) AS emergency_free_field
                                      ON emergency.id = emergency_free_field.emergency_id
                           INNER JOIN free_field on free_field.id = emergency_free_field.free_field_id
              ) AS emergency_free_field_tmp
     ) AS emergency_free_field
WHERE emergency_free_field.valeur IS NOT NULL
  AND emergency_free_field.valeur <> ''
