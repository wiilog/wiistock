SELECT *
FROM (
         SELECT demande_free_field_tmp.livraison_id,
                demande_free_field_tmp.libelle,
                IF(demande_free_field_tmp.typage = 'datetime',
                   DATE_FORMAT(REPLACE(demande_free_field_tmp.valeur, 'T', ' '),
                               '%d/%m/%Y %T'),
                   IF(demande_free_field_tmp.typage = 'date',
                      DATE_FORMAT(demande_free_field_tmp.valeur, '%d/%m/%Y'),
                      IF((demande_free_field_tmp.typage = 'booleen' AND demande_free_field_tmp.valeur = 1), 'oui',
                         IF((demande_free_field_tmp.typage = 'booleen' AND demande_free_field_tmp.valeur = 0), 'non',
                            demande_free_field_tmp.valeur)))) AS valeur
         FROM (
                  SELECT demande_free_field.demande_id                            AS livraison_id,
                         free_field.label                                                             AS libelle,
                         free_field.typage,
                         JSON_UNQUOTE(JSON_EXTRACT(demande.free_fields,
                                                   CONCAT('$."', demande_free_field.free_field_id, '"'))) AS valeur
                  FROM demande
                           INNER JOIN (
                      -- associate demande.id to free_field_id
                      SELECT demande.id AS demande_id,
                             res.*
                      FROM demande,
                           JSON_TABLE(
                                   JSON_KEYS(demande.free_fields),
                                   '$[*]' COLUMNS (free_field_id INT path '$')) AS res

                      WHERE demande.free_fields IS NOT NULL
                        AND JSON_LENGTH(demande.free_fields) <> 0
                  ) AS demande_free_field
                                      ON demande.id = demande_free_field.demande_id
                           INNER JOIN free_field on free_field.id = demande_free_field.free_field_id
              ) AS demande_free_field_tmp
     ) AS demande_free_field
WHERE demande_free_field.valeur IS NOT NULL
  AND demande_free_field.valeur <> ''
