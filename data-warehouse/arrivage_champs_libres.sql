SELECT *
FROM (
         SELECT arrivage_free_field_tmp.arrivage_id,
                arrivage_free_field_tmp.libelle,
                IF(arrivage_free_field_tmp.typage = 'datetime',
                   DATE_FORMAT(REPLACE(arrivage_free_field_tmp.valeur, 'T', ' '),
                               '%d/%m/%Y %T'),
                   IF(arrivage_free_field_tmp.typage = 'date',
                      DATE_FORMAT(arrivage_free_field_tmp.valeur, '%d/%m/%Y'),
                      IF((arrivage_free_field_tmp.typage = 'booleen' AND arrivage_free_field_tmp.valeur = 1), 'oui',
                         IF((arrivage_free_field_tmp.typage = 'booleen' AND arrivage_free_field_tmp.valeur = 0), 'non',
                            arrivage_free_field_tmp.valeur)))) AS valeur
         FROM (
                  SELECT arrivage_free_field.arrivage_id                            AS arrivage_id,
                         free_field.label                                                             AS libelle,
                         free_field.typage,
                         JSON_UNQUOTE(JSON_EXTRACT(arrivage.free_fields,
                                      CONCAT('$."', arrivage_free_field.free_field_id, '"'))) AS valeur
                  FROM arrivage
                           INNER JOIN (
                      -- associate arrivage.id to free_field_id
                      SELECT arrivage.id AS arrivage_id,
                             res.*
                      FROM arrivage,
                           JSON_TABLE(
                                   JSON_KEYS(arrivage.free_fields),
                                   '$[*]' COLUMNS (free_field_id INT path '$')) AS res

                      WHERE arrivage.free_fields IS NOT NULL
                        AND JSON_LENGTH(arrivage.free_fields) <> 0
                  ) AS arrivage_free_field
                                      ON arrivage.id = arrivage_free_field.arrivage_id
                           INNER JOIN free_field on free_field.id = arrivage_free_field.free_field_id
              ) AS arrivage_free_field_tmp
     ) AS arrivage_free_field
WHERE arrivage_free_field.valeur IS NOT NULL
  AND arrivage_free_field.valeur <> ''
