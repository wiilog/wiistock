SELECT *
FROM (
         SELECT capteur_free_field_tmp.sensor_code              AS code_capteur,
                capteur_free_field_tmp.libelle                  AS libelle_champ_libre,
                IF(capteur_free_field_tmp.typage = 'datetime',
                   DATE_FORMAT(REPLACE(capteur_free_field_tmp.valeur, 'T', ' '),
                               '%d/%m/%Y %T'),
                   IF(capteur_free_field_tmp.typage = 'date',
                      DATE_FORMAT(capteur_free_field_tmp.valeur, '%d/%m/%Y'),
                      IF((capteur_free_field_tmp.typage = 'booleen' AND capteur_free_field_tmp.valeur = 1), 'oui',
                         IF((capteur_free_field_tmp.typage = 'booleen' AND capteur_free_field_tmp.valeur = 0), 'non',
                            capteur_free_field_tmp.valeur))))   AS valeur_champ_libre
         FROM (
                  SELECT sensor.code                                                                       AS sensor_code,
                         free_field.label                                                                  AS libelle,
                         free_field.typage,
                         JSON_UNQUOTE(JSON_EXTRACT(sensor_wrapper.free_fields,
                                                   CONCAT('$."', dispatch_free_field.free_field_id, '"'))) AS valeur
                  FROM sensor_wrapper
                      INNER JOIN (
                          -- associate dispatch.id to free_field_id
                          SELECT sensor_wrapper.id AS sensor_wrapper_id,
                                 res.*
                          FROM sensor_wrapper,
                               JSON_TABLE(
                                   JSON_KEYS(sensor_wrapper.free_fields),
                                   '$[*]' COLUMNS (free_field_id INT path '$')) AS res
                          WHERE sensor_wrapper.free_fields IS NOT NULL
                            AND JSON_LENGTH(sensor_wrapper.free_fields) <> 0
                      ) AS dispatch_free_field
                          ON sensor_wrapper.id = dispatch_free_field.sensor_wrapper_id
                      INNER JOIN free_field on free_field.id = dispatch_free_field.free_field_id
                      INNER JOIN sensor ON sensor_wrapper.sensor_id = sensor.id
                  ) AS capteur_free_field_tmp
         ) AS capteur_free_field
WHERE capteur_free_field.valeur_champ_libre IS NOT NULL
  AND capteur_free_field.valeur_champ_libre <> ''
