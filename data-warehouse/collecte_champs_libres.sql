SELECT *
FROM (
         SELECT collecte_free_field_tmp.collecte_id,
                collecte_free_field_tmp.libelle,
                IF(collecte_free_field_tmp.typage = 'datetime',
                   DATE_FORMAT(REPLACE(collecte_free_field_tmp.valeur, 'T', ' '),
                               '%d/%m/%Y %T'),
                   IF(collecte_free_field_tmp.typage = 'date',
                      DATE_FORMAT(collecte_free_field_tmp.valeur, '%d/%m/%Y'),
                      IF((collecte_free_field_tmp.typage = 'booleen' AND collecte_free_field_tmp.valeur = 1), 'oui',
                         IF((collecte_free_field_tmp.typage = 'booleen' AND collecte_free_field_tmp.valeur = 0), 'non',
                            collecte_free_field_tmp.valeur)))) AS valeur
         FROM (
                  SELECT collecte_free_field.collecte_id                            AS collecte_id,
                         free_field.label                                                             AS libelle,
                         free_field.typage,
                         JSON_UNQUOTE(JSON_EXTRACT(collecte.free_fields,
                                      CONCAT('$."', collecte_free_field.free_field_id, '"'))) AS valeur
                  FROM collecte
                           INNER JOIN (
                      -- associate collecte.id to free_field_id
                      SELECT collecte.id AS collecte_id,
                             res.*
                      FROM collecte,
                           JSON_TABLE(
                                   JSON_KEYS(collecte.free_fields),
                                   '$[*]' COLUMNS (free_field_id INT path '$')) AS res

                      WHERE collecte.free_fields IS NOT NULL
                        AND JSON_LENGTH(collecte.free_fields) <> 0
                  ) AS collecte_free_field
                                      ON collecte.id = collecte_free_field.collecte_id
                           INNER JOIN free_field on free_field.id = collecte_free_field.free_field_id
              ) AS collecte_free_field_tmp
     ) AS collecte_free_field
WHERE collecte_free_field.valeur IS NOT NULL
  AND collecte_free_field.valeur <> ''
