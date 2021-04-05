SELECT *
FROM (
         SELECT tracking_movement_free_field_tmp.mouvement_traca_id,
                tracking_movement_free_field_tmp.libelle,
                IF(tracking_movement_free_field_tmp.typage = 'datetime',
                   DATE_FORMAT(REPLACE(tracking_movement_free_field_tmp.valeur, 'T', ' '),
                               '%d/%m/%Y %T'),
                   IF(tracking_movement_free_field_tmp.typage = 'date',
                      DATE_FORMAT(tracking_movement_free_field_tmp.valeur, '%d/%m/%Y'),
                      IF((tracking_movement_free_field_tmp.typage = 'booleen' AND tracking_movement_free_field_tmp.valeur = 1), 'oui',
                         IF((tracking_movement_free_field_tmp.typage = 'booleen' AND tracking_movement_free_field_tmp.valeur = 0), 'non',
                            tracking_movement_free_field_tmp.valeur)))) AS valeur
         FROM (
                  SELECT tracking_movement_free_field.tracking_movement_id                                          AS mouvement_traca_id,
                         free_field.label                                                                           AS libelle,
                         free_field.typage,
                         JSON_UNQUOTE(JSON_EXTRACT(tracking_movement.free_fields,
                                                   CONCAT('$."', tracking_movement_free_field.free_field_id, '"'))) AS valeur
                  FROM tracking_movement
                           INNER JOIN (
                      -- associate tracking_movement.id to free_field_id
                      SELECT tracking_movement.id AS tracking_movement_id,
                             res.*
                      FROM tracking_movement,
                           JSON_TABLE(
                                   JSON_KEYS(tracking_movement.free_fields),
                                   '$[*]' COLUMNS (free_field_id INT path '$')) AS res

                      WHERE tracking_movement.free_fields IS NOT NULL
                        AND JSON_LENGTH(tracking_movement.free_fields) <> 0
                  ) AS tracking_movement_free_field
                                      ON tracking_movement.id = tracking_movement_free_field.tracking_movement_id
                           INNER JOIN free_field on free_field.id = tracking_movement_free_field.free_field_id
              ) AS tracking_movement_free_field_tmp
     ) AS tracking_movement_free_field
WHERE tracking_movement_free_field.valeur IS NOT NULL
  AND tracking_movement_free_field.valeur <> ''
