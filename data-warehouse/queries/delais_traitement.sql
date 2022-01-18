DROP TABLE IF EXISTS TEMP_non_worked_days;
DROP TABLE IF EXISTS TEMP_worked_days;

CREATE TABLE TEMP_worked_days SELECT IF(days_worked.day = 'monday', 'lundi',
                                        IF(days_worked.day = 'tuesday', 'mardi',
                                           IF(days_worked.day = 'wednesday', 'mercredi',
                                              IF(days_worked.day = 'thursday', 'jeudi',
                                                 IF(days_worked.day = 'friday', 'vendredi',
                                                    IF(days_worked.day = 'saturday', 'samedi',
                                                       IF(days_worked.day = 'sunday', 'dimanche', NULL))))))) AS jour,
                                     IF(days_worked.worked = 1, 'oui', 'non')                                 AS travaille,
                                     IF(days_worked.times != '',
                                        IF(LOCATE(';', days_worked.times) > 0, SUBSTRING_INDEX(SUBSTRING_INDEX(days_worked.times, ';', 1), '-', 1),
                                           SUBSTRING_INDEX(days_worked.times, '-', 1)), NULL)                 AS horaire1,
                                     IF(days_worked.times != '',
                                        IF(LOCATE(';', days_worked.times) > 0, SUBSTRING_INDEX(SUBSTRING_INDEX(days_worked.times, ';', 1), '-', -1),
                                           SUBSTRING_INDEX(days_worked.times, '-', -1)), NULL)                AS horaire2,
                                     IF(LOCATE(';', days_worked.times) > 0, SUBSTRING_INDEX(SUBSTRING_INDEX(days_worked.times, ';', -1), '-', 1),
                                        NULL)                                                                 AS horaire3,
                                     IF(LOCATE(';', days_worked.times) > 0, SUBSTRING_INDEX(SUBSTRING_INDEX(days_worked.times, ';', -1), '-', -1),
                                        NULL)                                                                 AS horaire4

                              FROM days_worked;

CREATE TABLE TEMP_non_worked_days SELECT day AS jour
                                  FROM work_free_day;

SELECT demandeId,
       (((NBLUN*DURLUN)-CORR_DEB_LUN-CORR_FIN_LUN)
           + ((NBMAR*DURMAR)-CORR_DEB_MAR-CORR_FIN_MAR)
           + ((NBMER*DURMER)-CORR_DEB_MER-CORR_FIN_MER)
           + ((NBJEU*DURJEU)-CORR_DEB_JEU-CORR_FIN_JEU)
           + ((NBVEN*DURVEN)-CORR_DEB_VEN-CORR_FIN_VEN)
           + ((NBSAM*DURSAM)-CORR_DEB_SAM-CORR_FIN_SAM)
           + ((NBDIM*DURDIM)-CORR_DEB_DIM-CORR_FIN_DIM))/3600 AS delais_traitement
FROM (SELECT
          ((DATEDIFF(((SELECT MAX(livraison.date_fin)
                       FROM demande AS sub_demande
                                LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                       WHERE sub_demande.id = demande.id) - INTERVAL WEEKDAY((SELECT MAX(livraison.date_fin)
                                                                              FROM demande AS sub_demande
                                                                                       LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                                                       LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                                                              WHERE sub_demande.id = demande.id)) DAY), (validated_at - INTERVAL WEEKDAY(validated_at) DAY)))/7 + 1
              - (IF(DAYOFWEEK(validated_at) <> 2, 1, 0))
              - (SELECT count(*) FROM TEMP_non_worked_days WHERE jour BETWEEN validated_at AND (SELECT MAX(livraison.date_fin)
                                                                                                FROM demande AS sub_demande
                                                                                                         LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                                                                         LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                                                                                WHERE sub_demande.id = demande.id) AND DAYOFWEEK(jour) = 2)) AS NBLUN,
          (SELECT (SELECT(IF(horaire1 IS NOT NULL AND horaire2 IS NOT NULL, TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME)), 0)) FROM TEMP_worked_days WHERE jour = 'lundi')+
                  (SELECT(IF(horaire3 IS NOT NULL AND horaire4 IS NOT NULL, TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME)), 0)) FROM TEMP_worked_days WHERE jour = 'lundi')) DURLUN,
          COALESCE((SELECT CASE
                               WHEN (CAST(validated_at AS TIME))<IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 0)
                                   THEN 0
                               WHEN (CAST(validated_at AS TIME))<IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 0)
                                   THEN TIME_TO_SEC(CAST(validated_at AS TIME))-TIME_TO_SEC(IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 0))
                               WHEN (CAST(validated_at AS TIME))<IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 0)
                                   THEN TIME_TO_SEC(IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 0))-TIME_TO_SEC(IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 0))
                               WHEN (CAST(validated_at AS TIME))<IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 0)
                                   THEN (TIME_TO_SEC(IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 0))-TIME_TO_SEC(IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 0)))+(TIME_TO_SEC(CAST(validated_at AS TIME))-TIME_TO_SEC(IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 0)))
                               ELSE ((TIME_TO_SEC(IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 0))-TIME_TO_SEC(IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 0)))+(TIME_TO_SEC(IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 0))-TIME_TO_SEC(IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 0))))
                               END FROM TEMP_worked_days WHERE jour = 'lundi' AND DAYOFWEEK(validated_at) = 2), 0) CORR_DEB_LUN,
          COALESCE((SELECT CASE
                               WHEN CAST((SELECT MAX(livraison.date_fin)
                                          FROM demande AS sub_demande
                                                   LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                   LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                          WHERE sub_demande.id = demande.id) AS TIME)>IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 99)
                                   THEN 0
                               WHEN CAST((SELECT MAX(livraison.date_fin)
                                          FROM demande AS sub_demande
                                                   LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                   LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                          WHERE sub_demande.id = demande.id) AS TIME)>IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 99)
                                   THEN TIME_TO_SEC(IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 0))-TIME_TO_SEC(CAST((SELECT MAX(livraison.date_fin)
                                                                                                                           FROM demande AS sub_demande
                                                                                                                                    LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                                                                                                    LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                                                                                                           WHERE sub_demande.id = demande.id) AS TIME))
                               WHEN CAST((SELECT MAX(livraison.date_fin)
                                          FROM demande AS sub_demande
                                                   LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                   LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                          WHERE sub_demande.id = demande.id) AS TIME)>IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 99)
                                   THEN TIME_TO_SEC(IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 0))-TIME_TO_SEC(IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 0))
                               WHEN CAST((SELECT MAX(livraison.date_fin)
                                          FROM demande AS sub_demande
                                                   LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                   LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                          WHERE sub_demande.id = demande.id) AS TIME)>IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 99)
                                   THEN (TIME_TO_SEC(IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 0))-TIME_TO_SEC(IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 0)))+(TIME_TO_SEC(IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 0))-TIME_TO_SEC(CAST((SELECT MAX(livraison.date_fin)
                                                                                                                                                                                                                                                                FROM demande AS sub_demande
                                                                                                                                                                                                                                                                         LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                                                                                                                                                                                                                                         LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                                                                                                                                                                                                                                                WHERE sub_demande.id = demande.id) AS TIME)))
                               ELSE ((TIME_TO_SEC(IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 0))-TIME_TO_SEC(IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 0)))+(TIME_TO_SEC(IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 0))-TIME_TO_SEC(IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 0))))
                               END FROM TEMP_worked_days WHERE jour = 'lundi' AND DAYOFWEEK((SELECT MAX(livraison.date_fin)
                                                                                             FROM demande AS sub_demande
                                                                                                      LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                                                                      LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                                                                             WHERE sub_demande.id = demande.id)) = 2), 0) CORR_FIN_LUN,


          ((DATEDIFF(((SELECT MAX(livraison.date_fin)
                       FROM demande AS sub_demande
                                LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                       WHERE sub_demande.id = demande.id) - INTERVAL WEEKDAY((SELECT MAX(livraison.date_fin)
                                                                              FROM demande AS sub_demande
                                                                                       LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                                                       LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                                                              WHERE sub_demande.id = demande.id)) DAY), (validated_at - INTERVAL WEEKDAY(validated_at) DAY)))/7 + 1
              - (IF(DAYOFWEEK(validated_at) NOT IN (2, 3), 1, 0))
              - (IF(DAYOFWEEK((SELECT MAX(livraison.date_fin)
                               FROM demande AS sub_demande
                                        LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                        LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                               WHERE sub_demande.id = demande.id)) = 2, 1, 0))
              - (SELECT count(*) FROM TEMP_non_worked_days WHERE jour BETWEEN validated_at AND (SELECT MAX(livraison.date_fin)
                                                                                                FROM demande AS sub_demande
                                                                                                         LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                                                                         LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                                                                                WHERE sub_demande.id = demande.id) AND DAYOFWEEK(jour) = 3)) AS NBMAR,
          (SELECT (SELECT(IF(horaire1 IS NOT NULL AND horaire2 IS NOT NULL, TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME)), 0)) FROM TEMP_worked_days WHERE jour = 'mardi')+
                  (SELECT(IF(horaire3 IS NOT NULL AND horaire4 IS NOT NULL, TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME)), 0)) FROM TEMP_worked_days WHERE jour = 'mardi')) DURMAR,
          COALESCE((SELECT CASE
                               WHEN (CAST(validated_at AS TIME))<IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 0)
                                   THEN 0
                               WHEN (CAST(validated_at AS TIME))<IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 0)
                                   THEN TIME_TO_SEC(CAST(validated_at AS TIME))-TIME_TO_SEC(IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 0))
                               WHEN (CAST(validated_at AS TIME))<IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 0)
                                   THEN TIME_TO_SEC(IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 0))-TIME_TO_SEC(IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 0))
                               WHEN (CAST(validated_at AS TIME))<IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 0)
                                   THEN (TIME_TO_SEC(IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 0))-TIME_TO_SEC(IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 0)))+(TIME_TO_SEC(CAST(validated_at AS TIME))-TIME_TO_SEC(IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 0)))
                               ELSE ((TIME_TO_SEC(IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 0))-TIME_TO_SEC(IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 0)))+(TIME_TO_SEC(IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 0))-TIME_TO_SEC(IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 0))))
                               END FROM TEMP_worked_days WHERE jour = 'mardi' AND DAYOFWEEK(validated_at) = 3), 0) CORR_DEB_MAR,
          COALESCE((SELECT CASE
                               WHEN CAST((SELECT MAX(livraison.date_fin)
                                          FROM demande AS sub_demande
                                                   LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                   LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                          WHERE sub_demande.id = demande.id) AS TIME)>IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 99)
                                   THEN 0
                               WHEN CAST((SELECT MAX(livraison.date_fin)
                                          FROM demande AS sub_demande
                                                   LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                   LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                          WHERE sub_demande.id = demande.id) AS TIME)>IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 99)
                                   THEN TIME_TO_SEC(IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 0))-TIME_TO_SEC(CAST((SELECT MAX(livraison.date_fin)
                                                                                                                           FROM demande AS sub_demande
                                                                                                                                    LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                                                                                                    LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                                                                                                           WHERE sub_demande.id = demande.id) AS TIME))
                               WHEN CAST((SELECT MAX(livraison.date_fin)
                                          FROM demande AS sub_demande
                                                   LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                   LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                          WHERE sub_demande.id = demande.id) AS TIME)>IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 99)
                                   THEN TIME_TO_SEC(IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 0))-TIME_TO_SEC(IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 0))
                               WHEN CAST((SELECT MAX(livraison.date_fin)
                                          FROM demande AS sub_demande
                                                   LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                   LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                          WHERE sub_demande.id = demande.id) AS TIME)>IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 99)
                                   THEN (TIME_TO_SEC(IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 0))-TIME_TO_SEC(IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 0)))+(TIME_TO_SEC(IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 0))-TIME_TO_SEC(CAST((SELECT MAX(livraison.date_fin)
                                                                                                                                                                                                                                                                FROM demande AS sub_demande
                                                                                                                                                                                                                                                                         LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                                                                                                                                                                                                                                         LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                                                                                                                                                                                                                                                WHERE sub_demande.id = demande.id) AS TIME)))
                               ELSE ((TIME_TO_SEC(IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 0))-TIME_TO_SEC(IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 0)))+(TIME_TO_SEC(IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 0))-TIME_TO_SEC(IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 0))))
                               END FROM TEMP_worked_days WHERE jour = 'mardi' AND DAYOFWEEK((SELECT MAX(livraison.date_fin)
                                                                                             FROM demande AS sub_demande
                                                                                                      LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                                                                      LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                                                                             WHERE sub_demande.id = demande.id)) = 3), 0) CORR_FIN_MAR,


          ((DATEDIFF(((SELECT MAX(livraison.date_fin)
                       FROM demande AS sub_demande
                                LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                       WHERE sub_demande.id = demande.id) - INTERVAL WEEKDAY((SELECT MAX(livraison.date_fin)
                                                                              FROM demande AS sub_demande
                                                                                       LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                                                       LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                                                              WHERE sub_demande.id = demande.id)) DAY), (validated_at - INTERVAL WEEKDAY(validated_at) DAY)))/7 + 1
              - (IF(DAYOFWEEK(validated_at) NOT IN (2, 3, 4), 1, 0))
              - (IF(DAYOFWEEK((SELECT MAX(livraison.date_fin)
                               FROM demande AS sub_demande
                                        LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                        LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                               WHERE sub_demande.id = demande.id)) IN (2, 3), 1, 0))
              - (SELECT count(*) FROM TEMP_non_worked_days WHERE jour BETWEEN validated_at AND (SELECT MAX(livraison.date_fin)
                                                                                                FROM demande AS sub_demande
                                                                                                         LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                                                                         LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                                                                                WHERE sub_demande.id = demande.id) AND DAYOFWEEK(jour) = 4)) AS NBMER,
          (SELECT (SELECT(IF(horaire1 IS NOT NULL AND horaire2 IS NOT NULL, TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME)), 0)) FROM TEMP_worked_days WHERE jour = 'mercredi')+
                  (SELECT(IF(horaire3 IS NOT NULL AND horaire4 IS NOT NULL, TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME)), 0)) FROM TEMP_worked_days WHERE jour = 'mercredi')) DURMER,
          COALESCE((SELECT CASE
                               WHEN (CAST(validated_at AS TIME))<IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 0)
                                   THEN 0
                               WHEN (CAST(validated_at AS TIME))<IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 0)
                                   THEN TIME_TO_SEC(CAST(validated_at AS TIME))-TIME_TO_SEC(IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 0))
                               WHEN (CAST(validated_at AS TIME))<IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 0)
                                   THEN TIME_TO_SEC(IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 0))-TIME_TO_SEC(IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 0))
                               WHEN (CAST(validated_at AS TIME))<IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 0)
                                   THEN (TIME_TO_SEC(IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 0))-TIME_TO_SEC(IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 0)))+(TIME_TO_SEC(CAST(validated_at AS TIME))-TIME_TO_SEC(IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 0)))
                               ELSE ((TIME_TO_SEC(IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 0))-TIME_TO_SEC(IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 0)))+(TIME_TO_SEC(IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 0))-TIME_TO_SEC(IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 0))))
                               END FROM TEMP_worked_days WHERE jour = 'mercredi' AND DAYOFWEEK(validated_at) = 4), 0) CORR_DEB_MER,
          COALESCE((SELECT CASE
                               WHEN CAST((SELECT MAX(livraison.date_fin)
                                          FROM demande AS sub_demande
                                                   LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                   LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                          WHERE sub_demande.id = demande.id) AS TIME)>IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 99)
                                   THEN 0
                               WHEN CAST((SELECT MAX(livraison.date_fin)
                                          FROM demande AS sub_demande
                                                   LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                   LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                          WHERE sub_demande.id = demande.id) AS TIME)>IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 99)
                                   THEN TIME_TO_SEC(IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 0))-TIME_TO_SEC(CAST((SELECT MAX(livraison.date_fin)
                                                                                                                           FROM demande AS sub_demande
                                                                                                                                    LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                                                                                                    LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                                                                                                           WHERE sub_demande.id = demande.id) AS TIME))
                               WHEN CAST((SELECT MAX(livraison.date_fin)
                                          FROM demande AS sub_demande
                                                   LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                   LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                          WHERE sub_demande.id = demande.id) AS TIME)>IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 99)
                                   THEN TIME_TO_SEC(IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 0))-TIME_TO_SEC(IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 0))
                               WHEN CAST((SELECT MAX(livraison.date_fin)
                                          FROM demande AS sub_demande
                                                   LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                   LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                          WHERE sub_demande.id = demande.id) AS TIME)>IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 99)
                                   THEN (TIME_TO_SEC(IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 0))-TIME_TO_SEC(IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 0)))+(TIME_TO_SEC(IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 0))-TIME_TO_SEC(CAST((SELECT MAX(livraison.date_fin)
                                                                                                                                                                                                                                                                FROM demande AS sub_demande
                                                                                                                                                                                                                                                                         LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                                                                                                                                                                                                                                         LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                                                                                                                                                                                                                                                WHERE sub_demande.id = demande.id) AS TIME)))
                               ELSE ((TIME_TO_SEC(IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 0))-TIME_TO_SEC(IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 0)))+(TIME_TO_SEC(IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 0))-TIME_TO_SEC(IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 0))))
                               END FROM TEMP_worked_days WHERE jour = 'mercredi' AND DAYOFWEEK((SELECT MAX(livraison.date_fin)
                                                                                                FROM demande AS sub_demande
                                                                                                         LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                                                                         LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                                                                                WHERE sub_demande.id = demande.id)) = 4), 0) CORR_FIN_MER,


          ((DATEDIFF(((SELECT MAX(livraison.date_fin)
                       FROM demande AS sub_demande
                                LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                       WHERE sub_demande.id = demande.id) - INTERVAL WEEKDAY((SELECT MAX(livraison.date_fin)
                                                                              FROM demande AS sub_demande
                                                                                       LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                                                       LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                                                              WHERE sub_demande.id = demande.id)) DAY), (validated_at - INTERVAL WEEKDAY(validated_at) DAY)))/7 + 1
              - (IF(DAYOFWEEK(validated_at) IN (6, 7, 1), 1, 0))
              - (IF(DAYOFWEEK((SELECT MAX(livraison.date_fin)
                               FROM demande AS sub_demande
                                        LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                        LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                               WHERE sub_demande.id = demande.id)) IN (2, 3, 4), 1, 0))
              - (SELECT count(*) FROM TEMP_non_worked_days WHERE jour BETWEEN validated_at AND (SELECT MAX(livraison.date_fin)
                                                                                                FROM demande AS sub_demande
                                                                                                         LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                                                                         LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                                                                                WHERE sub_demande.id = demande.id) AND DAYOFWEEK(jour) = 5)) AS NBJEU,
          (SELECT (SELECT(IF(horaire1 IS NOT NULL AND horaire2 IS NOT NULL, TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME)), 0)) FROM TEMP_worked_days WHERE jour = 'jeudi')+
                  (SELECT(IF(horaire3 IS NOT NULL AND horaire4 IS NOT NULL, TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME)), 0)) FROM TEMP_worked_days WHERE jour = 'jeudi')) DURJEU,
          COALESCE((SELECT CASE
                               WHEN (CAST(validated_at AS TIME))<IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 0)
                                   THEN 0
                               WHEN (CAST(validated_at AS TIME))<IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 0)
                                   THEN TIME_TO_SEC(CAST(validated_at AS TIME))-TIME_TO_SEC(IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 0))
                               WHEN (CAST(validated_at AS TIME))<IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 0)
                                   THEN TIME_TO_SEC(IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 0))-TIME_TO_SEC(IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 0))
                               WHEN (CAST(validated_at AS TIME))<IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 0)
                                   THEN (TIME_TO_SEC(IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 0))-TIME_TO_SEC(IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 0)))+(TIME_TO_SEC(CAST(validated_at AS TIME))-TIME_TO_SEC(IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 0)))
                               ELSE ((TIME_TO_SEC(IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 0))-TIME_TO_SEC(IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 0)))+(TIME_TO_SEC(IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 0))-TIME_TO_SEC(IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 0))))
                               END FROM TEMP_worked_days WHERE jour = 'jeudi' AND DAYOFWEEK(validated_at) = 5), 0) CORR_DEB_JEU,
          COALESCE((SELECT CASE
                               WHEN CAST((SELECT MAX(livraison.date_fin)
                                          FROM demande AS sub_demande
                                                   LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                   LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                          WHERE sub_demande.id = demande.id) AS TIME)>IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 99)
                                   THEN 0
                               WHEN CAST((SELECT MAX(livraison.date_fin)
                                          FROM demande AS sub_demande
                                                   LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                   LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                          WHERE sub_demande.id = demande.id) AS TIME)>IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 99)
                                   THEN TIME_TO_SEC(IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 0))-TIME_TO_SEC(CAST((SELECT MAX(livraison.date_fin)
                                                                                                                           FROM demande AS sub_demande
                                                                                                                                    LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                                                                                                    LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                                                                                                           WHERE sub_demande.id = demande.id) AS TIME))
                               WHEN CAST((SELECT MAX(livraison.date_fin)
                                          FROM demande AS sub_demande
                                                   LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                   LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                          WHERE sub_demande.id = demande.id) AS TIME)>IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 99)
                                   THEN TIME_TO_SEC(IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 0))-TIME_TO_SEC(IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 0))
                               WHEN CAST((SELECT MAX(livraison.date_fin)
                                          FROM demande AS sub_demande
                                                   LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                   LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                          WHERE sub_demande.id = demande.id) AS TIME)>IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 99)
                                   THEN (TIME_TO_SEC(IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 0))-TIME_TO_SEC(IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 0)))+(TIME_TO_SEC(IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 0))-TIME_TO_SEC(CAST((SELECT MAX(livraison.date_fin)
                                                                                                                                                                                                                                                                FROM demande AS sub_demande
                                                                                                                                                                                                                                                                         LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                                                                                                                                                                                                                                         LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                                                                                                                                                                                                                                                WHERE sub_demande.id = demande.id) AS TIME)))
                               ELSE ((TIME_TO_SEC(IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 0))-TIME_TO_SEC(IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 0)))+(TIME_TO_SEC(IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 0))-TIME_TO_SEC(IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 0))))
                               END FROM TEMP_worked_days WHERE jour = 'jeudi' AND DAYOFWEEK((SELECT MAX(livraison.date_fin)
                                                                                             FROM demande AS sub_demande
                                                                                                      LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                                                                      LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                                                                             WHERE sub_demande.id = demande.id)) = 5), 0) CORR_FIN_JEU,


          ((DATEDIFF(((SELECT MAX(livraison.date_fin)
                       FROM demande AS sub_demande
                                LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                       WHERE sub_demande.id = demande.id) - INTERVAL WEEKDAY((SELECT MAX(livraison.date_fin)
                                                                              FROM demande AS sub_demande
                                                                                       LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                                                       LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                                                              WHERE sub_demande.id = demande.id)) DAY), (validated_at - INTERVAL WEEKDAY(validated_at) DAY)))/7 + 1
              - (IF(DAYOFWEEK(validated_at) IN (7, 1), 1, 0))
              - (IF(DAYOFWEEK((SELECT MAX(livraison.date_fin)
                               FROM demande AS sub_demande
                                        LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                        LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                               WHERE sub_demande.id = demande.id)) NOT IN (6, 7, 1), 1, 0))
              - (SELECT count(*) FROM TEMP_non_worked_days WHERE jour BETWEEN validated_at AND (SELECT MAX(livraison.date_fin)
                                                                                                FROM demande AS sub_demande
                                                                                                         LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                                                                         LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                                                                                WHERE sub_demande.id = demande.id) AND DAYOFWEEK(jour) = 6)) AS NBVEN,
          (SELECT (SELECT(IF(horaire1 IS NOT NULL AND horaire2 IS NOT NULL, TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME)), 0)) FROM TEMP_worked_days WHERE jour = 'vendredi')+
                  (SELECT(IF(horaire3 IS NOT NULL AND horaire4 IS NOT NULL, TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME)), 0)) FROM TEMP_worked_days WHERE jour = 'vendredi')) DURVEN,
          COALESCE((SELECT CASE
                               WHEN (CAST(validated_at AS TIME))<IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 0)
                                   THEN 0
                               WHEN (CAST(validated_at AS TIME))<IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 0)
                                   THEN TIME_TO_SEC(CAST(validated_at AS TIME))-TIME_TO_SEC(IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 0))
                               WHEN (CAST(validated_at AS TIME))<IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 0)
                                   THEN TIME_TO_SEC(IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 0))-TIME_TO_SEC(IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 0))
                               WHEN (CAST(validated_at AS TIME))<IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 0)
                                   THEN (TIME_TO_SEC(IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 0))-TIME_TO_SEC(IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 0)))+(TIME_TO_SEC(CAST(validated_at AS TIME))-TIME_TO_SEC(IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 0)))
                               ELSE ((TIME_TO_SEC(IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 0))-TIME_TO_SEC(IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 0)))+(TIME_TO_SEC(IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 0))-TIME_TO_SEC(IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 0))))
                               END FROM TEMP_worked_days WHERE jour = 'vendredi' AND DAYOFWEEK(validated_at) = 6), 0) CORR_DEB_VEN,
          COALESCE((SELECT CASE
                               WHEN CAST((SELECT MAX(livraison.date_fin)
                                          FROM demande AS sub_demande
                                                   LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                   LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                          WHERE sub_demande.id = demande.id) AS TIME)>IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 99)
                                   THEN 0
                               WHEN CAST((SELECT MAX(livraison.date_fin)
                                          FROM demande AS sub_demande
                                                   LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                   LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                          WHERE sub_demande.id = demande.id) AS TIME)>IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 99)
                                   THEN TIME_TO_SEC(IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 0))-TIME_TO_SEC(CAST((SELECT MAX(livraison.date_fin)
                                                                                                                           FROM demande AS sub_demande
                                                                                                                                    LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                                                                                                    LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                                                                                                           WHERE sub_demande.id = demande.id) AS TIME))
                               WHEN CAST((SELECT MAX(livraison.date_fin)
                                          FROM demande AS sub_demande
                                                   LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                   LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                          WHERE sub_demande.id = demande.id) AS TIME)>IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 99)
                                   THEN TIME_TO_SEC(IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 0))-TIME_TO_SEC(IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 0))
                               WHEN CAST((SELECT MAX(livraison.date_fin)
                                          FROM demande AS sub_demande
                                                   LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                   LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                          WHERE sub_demande.id = demande.id) AS TIME)>IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 99)
                                   THEN (TIME_TO_SEC(IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 0))-TIME_TO_SEC(IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 0)))+(TIME_TO_SEC(IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 0))-TIME_TO_SEC(CAST((SELECT MAX(livraison.date_fin)
                                                                                                                                                                                                                                                                FROM demande AS sub_demande
                                                                                                                                                                                                                                                                         LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                                                                                                                                                                                                                                         LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                                                                                                                                                                                                                                                WHERE sub_demande.id = demande.id) AS TIME)))
                               ELSE ((TIME_TO_SEC(IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 0))-TIME_TO_SEC(IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 0)))+(TIME_TO_SEC(IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 0))-TIME_TO_SEC(IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 0))))
                               END FROM TEMP_worked_days WHERE jour = 'vendredi' AND DAYOFWEEK((SELECT MAX(livraison.date_fin)
                                                                                                FROM demande AS sub_demande
                                                                                                         LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                                                                         LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                                                                                WHERE sub_demande.id = demande.id)) = 6), 0) CORR_FIN_VEN,


          ((DATEDIFF(((SELECT MAX(livraison.date_fin)
                       FROM demande AS sub_demande
                                LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                       WHERE sub_demande.id = demande.id) - INTERVAL WEEKDAY((SELECT MAX(livraison.date_fin)
                                                                              FROM demande AS sub_demande
                                                                                       LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                                                       LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                                                              WHERE sub_demande.id = demande.id)) DAY), (validated_at - INTERVAL WEEKDAY(validated_at) DAY)))/7 + 1
              - (IF(DAYOFWEEK(validated_at) = 1, 1, 0))
              - (IF(DAYOFWEEK((SELECT MAX(livraison.date_fin)
                               FROM demande AS sub_demande
                                        LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                        LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                               WHERE sub_demande.id = demande.id)) NOT IN (7, 1), 1, 0))
              - (SELECT count(*) FROM TEMP_non_worked_days WHERE jour BETWEEN validated_at AND (SELECT MAX(livraison.date_fin)
                                                                                                FROM demande AS sub_demande
                                                                                                         LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                                                                         LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                                                                                WHERE sub_demande.id = demande.id) AND DAYOFWEEK(jour) = 7)) AS NBSAM,
          (SELECT (SELECT(IF(horaire1 IS NOT NULL AND horaire2 IS NOT NULL, TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME)), 0)) FROM TEMP_worked_days WHERE jour = 'samedi')+
                  (SELECT(IF(horaire3 IS NOT NULL AND horaire4 IS NOT NULL, TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME)), 0)) FROM TEMP_worked_days WHERE jour = 'samedi')) DURSAM,
          COALESCE((SELECT CASE
                               WHEN (CAST(validated_at AS TIME))<IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 0)
                                   THEN 0
                               WHEN (CAST(validated_at AS TIME))<IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 0)
                                   THEN TIME_TO_SEC(CAST(validated_at AS TIME))-TIME_TO_SEC(IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 0))
                               WHEN (CAST(validated_at AS TIME))<IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 0)
                                   THEN TIME_TO_SEC(IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 0))-TIME_TO_SEC(IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 0))
                               WHEN (CAST(validated_at AS TIME))<IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 0)
                                   THEN (TIME_TO_SEC(IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 0))-TIME_TO_SEC(IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 0)))+(TIME_TO_SEC(CAST(validated_at AS TIME))-TIME_TO_SEC(IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 0)))
                               ELSE ((TIME_TO_SEC(IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 0))-TIME_TO_SEC(IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 0)))+(TIME_TO_SEC(IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 0))-TIME_TO_SEC(IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 0))))
                               END FROM TEMP_worked_days WHERE jour = 'samedi' AND DAYOFWEEK(validated_at) = 7), 0) CORR_DEB_SAM,
          COALESCE((SELECT CASE
                               WHEN CAST((SELECT MAX(livraison.date_fin)
                                          FROM demande AS sub_demande
                                                   LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                   LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                          WHERE sub_demande.id = demande.id) AS TIME)>IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 99)
                                   THEN 0
                               WHEN CAST((SELECT MAX(livraison.date_fin)
                                          FROM demande AS sub_demande
                                                   LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                   LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                          WHERE sub_demande.id = demande.id) AS TIME)>IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 99)
                                   THEN TIME_TO_SEC(IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 0))-TIME_TO_SEC(CAST((SELECT MAX(livraison.date_fin)
                                                                                                                           FROM demande AS sub_demande
                                                                                                                                    LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                                                                                                    LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                                                                                                           WHERE sub_demande.id = demande.id) AS TIME))
                               WHEN CAST((SELECT MAX(livraison.date_fin)
                                          FROM demande AS sub_demande
                                                   LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                   LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                          WHERE sub_demande.id = demande.id) AS TIME)>IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 99)
                                   THEN TIME_TO_SEC(IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 0))-TIME_TO_SEC(IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 0))
                               WHEN CAST((SELECT MAX(livraison.date_fin)
                                          FROM demande AS sub_demande
                                                   LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                   LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                          WHERE sub_demande.id = demande.id) AS TIME)>IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 99)
                                   THEN (TIME_TO_SEC(IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 0))-TIME_TO_SEC(IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 0)))+(TIME_TO_SEC(IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 0))-TIME_TO_SEC(CAST((SELECT MAX(livraison.date_fin)
                                                                                                                                                                                                                                                                FROM demande AS sub_demande
                                                                                                                                                                                                                                                                         LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                                                                                                                                                                                                                                         LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                                                                                                                                                                                                                                                WHERE sub_demande.id = demande.id) AS TIME)))
                               ELSE ((TIME_TO_SEC(IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 0))-TIME_TO_SEC(IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 0)))+(TIME_TO_SEC(IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 0))-TIME_TO_SEC(IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 0))))
                               END FROM TEMP_worked_days WHERE jour = 'samedi' AND DAYOFWEEK((SELECT MAX(livraison.date_fin)
                                                                                              FROM demande AS sub_demande
                                                                                                       LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                                                                       LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                                                                              WHERE sub_demande.id = demande.id)) = 7), 0) CORR_FIN_SAM,


          ((DATEDIFF(((SELECT MAX(livraison.date_fin)
                       FROM demande AS sub_demande
                                LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                       WHERE sub_demande.id = demande.id) - INTERVAL WEEKDAY((SELECT MAX(livraison.date_fin)
                                                                              FROM demande AS sub_demande
                                                                                       LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                                                       LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                                                              WHERE sub_demande.id = demande.id)) DAY), (validated_at - INTERVAL WEEKDAY(validated_at) DAY)))/7 + 1
              - (IF(DAYOFWEEK((SELECT MAX(livraison.date_fin)
                               FROM demande AS sub_demande
                                        LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                        LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                               WHERE sub_demande.id = demande.id)) <> 1, 1, 0))
              - (SELECT count(*) FROM TEMP_non_worked_days WHERE jour BETWEEN validated_at AND (SELECT MAX(livraison.date_fin)
                                                                                                FROM demande AS sub_demande
                                                                                                         LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                                                                         LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                                                                                WHERE sub_demande.id = demande.id) AND DAYOFWEEK(jour) = 1)) AS NBDIM,
          (SELECT (SELECT(IF(horaire1 IS NOT NULL AND horaire2 IS NOT NULL, TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME)), 0)) FROM TEMP_worked_days WHERE jour = 'dimanche')+
                  (SELECT(IF(horaire3 IS NOT NULL AND horaire4 IS NOT NULL, TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME)), 0)) FROM TEMP_worked_days WHERE jour = 'dimanche')) DURDIM,
          COALESCE((SELECT CASE
                               WHEN (CAST(validated_at AS TIME))<IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 0)
                                   THEN 0
                               WHEN (CAST(validated_at AS TIME))<IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 0)
                                   THEN TIME_TO_SEC(CAST(validated_at AS TIME))-TIME_TO_SEC(IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 0))
                               WHEN (CAST(validated_at AS TIME))<IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 0)
                                   THEN TIME_TO_SEC(IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 0))-TIME_TO_SEC(IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 0))
                               WHEN (CAST(validated_at AS TIME))<IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 0)
                                   THEN (TIME_TO_SEC(IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 0))-TIME_TO_SEC(IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 0)))+(TIME_TO_SEC(CAST(validated_at AS TIME))-TIME_TO_SEC(IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 0)))
                               ELSE ((TIME_TO_SEC(IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 0))-TIME_TO_SEC(IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 0)))+(TIME_TO_SEC(IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 0))-TIME_TO_SEC(IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 0))))
                               END FROM TEMP_worked_days WHERE jour = 'dimanche' AND DAYOFWEEK(validated_at) = 1), 0) CORR_DEB_DIM,
          COALESCE((SELECT CASE
                               WHEN CAST((SELECT MAX(livraison.date_fin)
                                          FROM demande AS sub_demande
                                                   LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                   LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                          WHERE sub_demande.id = demande.id) AS TIME)>IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 99)
                                   THEN 0
                               WHEN CAST((SELECT MAX(livraison.date_fin)
                                          FROM demande AS sub_demande
                                                   LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                   LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                          WHERE sub_demande.id = demande.id) AS TIME)>IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 99)
                                   THEN TIME_TO_SEC(IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 0))-TIME_TO_SEC(CAST((SELECT MAX(livraison.date_fin)
                                                                                                                           FROM demande AS sub_demande
                                                                                                                                    LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                                                                                                    LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                                                                                                           WHERE sub_demande.id = demande.id) AS TIME))
                               WHEN CAST((SELECT MAX(livraison.date_fin)
                                          FROM demande AS sub_demande
                                                   LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                   LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                          WHERE sub_demande.id = demande.id) AS TIME)>IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 99)
                                   THEN TIME_TO_SEC(IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 0))-TIME_TO_SEC(IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 0))
                               WHEN CAST((SELECT MAX(livraison.date_fin)
                                          FROM demande AS sub_demande
                                                   LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                   LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                          WHERE sub_demande.id = demande.id) AS TIME)>IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 99)
                                   THEN (TIME_TO_SEC(IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 0))-TIME_TO_SEC(IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 0)))+(TIME_TO_SEC(IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 0))-TIME_TO_SEC(CAST((SELECT MAX(livraison.date_fin)
                                                                                                                                                                                                                                                                FROM demande AS sub_demande
                                                                                                                                                                                                                                                                         LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                                                                                                                                                                                                                                         LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                                                                                                                                                                                                                                                WHERE sub_demande.id = demande.id) AS TIME)))
                               ELSE ((TIME_TO_SEC(IF(horaire2 IS NOT NULL, CAST(horaire2 AS TIME), 0))-TIME_TO_SEC(IF(horaire1 IS NOT NULL, CAST(horaire1 AS TIME), 0)))+(TIME_TO_SEC(IF(horaire4 IS NOT NULL, CAST(horaire4 AS TIME), 0))-TIME_TO_SEC(IF(horaire3 IS NOT NULL, CAST(horaire3 AS TIME), 0))))
                               END FROM TEMP_worked_days WHERE jour = 'dimanche' AND DAYOFWEEK((SELECT MAX(livraison.date_fin)
                                                                                                FROM demande AS sub_demande
                                                                                                         LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                                                                         LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                                                                                WHERE sub_demande.id = demande.id)) = 1), 0) CORR_FIN_DIM,
          demande.id AS demandeId
      FROM demande) AS data;

DROP TABLE IF EXISTS TEMP_non_worked_days;
DROP TABLE IF EXISTS TEMP_worked_days;
