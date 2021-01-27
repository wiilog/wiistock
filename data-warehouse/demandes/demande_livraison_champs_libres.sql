SELECT *
FROM (
         SELECT demande_livraison_free_field_tmp.demande_livraison_id,
                demande_livraison_free_field_tmp.libelle,
                IF(demande_livraison_free_field_tmp.typage = 'datetime',
                   DATE_FORMAT(REPLACE(demande_livraison_free_field_tmp.valeur, 'T', ' '),
                               '%d/%m/%Y %T'),
                   IF(demande_livraison_free_field_tmp.typage = 'date',
                      DATE_FORMAT(demande_livraison_free_field_tmp.valeur, '%d/%m/%Y'),
                      IF((demande_livraison_free_field_tmp.typage = 'booleen' AND demande_livraison_free_field_tmp.valeur = 1), 'oui',
                         IF((demande_livraison_free_field_tmp.typage = 'booleen' AND demande_livraison_free_field_tmp.valeur = 0), 'non',
                            demande_livraison_free_field_tmp.valeur)))) AS valeur
         FROM (
                  SELECT demande_livraison_free_field.demande_livraison_id                                          AS demande_livraison_id,
                         free_field.label                                                                           AS libelle,
                         free_field.typage,
                         JSON_UNQUOTE(JSON_EXTRACT(demande.free_fields,
                                                   CONCAT('$."', demande_livraison_free_field.free_field_id, '"'))) AS valeur
                  FROM demande
                           INNER JOIN (
                      -- associate demande_livraison.id to free_field_id
                      SELECT demande.id AS demande_livraison_id,
                             res.*
                      FROM demande,
                           JSON_TABLE(
                                   JSON_KEYS(demande.free_fields),
                                   '$[*]' COLUMNS (free_field_id INT path '$')) AS res

                      WHERE demande.free_fields IS NOT NULL
                        AND JSON_LENGTH(demande.free_fields) <> 0
                  ) AS demande_livraison_free_field
                                      ON demande.id = demande_livraison_free_field.demande_livraison_id
                           INNER JOIN free_field on free_field.id = demande_livraison_free_field.free_field_id
              ) AS demande_livraison_free_field_tmp
     ) AS demande_livraison_free_field
WHERE demande_livraison_free_field.valeur IS NOT NULL
  AND demande_livraison_free_field.valeur <> ''
