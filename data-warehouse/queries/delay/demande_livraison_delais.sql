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

SELECT NBLUN, DURLUN
     ,NBMAR, DURMAR
     ,NBMER, DURMER
     ,NBJEU, DURJEU
     ,NBVEN, DURVEN
     ,NBSAM, DURSAM
     ,NBDIM, DURDIM, ((NBLUN) * DURLUN
    + (NBMAR) * DURMAR
    + (NBMER) * DURMER
    + (NBJEU) * DURJEU
    + (NBVEN) * DURVEN
    + (NBSAM) * DURSAM
    + (NBDIM) * DURDIM)/3600 as delais_traitement
FROM (SELECT ((TIMESTAMPDIFF(SECOND, (SELECT MAX(livraison.date_fin)
                                      FROM demande AS sub_demande
                                               LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                               LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                      WHERE sub_demande.id = demande.id), validated_at)/86400)/7 + 1
    - (IF(DAYOFWEEK(validated_at) <> 2, 1, 0))
    - (SELECT count(*) FROM TEMP_non_worked_days WHERE jour BETWEEN validated_at AND (SELECT MAX(livraison.date_fin)
                                                                                         FROM demande AS sub_demande
                                                                                                  LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                                                                                  LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                                                                         WHERE sub_demande.id = demande.id) AND DAYOFWEEK(jour) = 2)) AS NBLUN,
             (SELECT (
                         SELECT IF(horaire1 IS NOT NULL AND horaire2 IS NOT NULL,
                                   (TIME_TO_SEC(CAST(horaire2 AS TIME)) - TIME_TO_SEC(CAST(horaire1 AS TIME))), 0)
                         FROM TEMP_worked_days
                         WHERE jour = 'lundi') +
                     (SELECT IF(horaire3 IS NOT NULL AND horaire4 IS NOT NULL,
                                (TIME_TO_SEC(CAST(horaire4 AS TIME)) - TIME_TO_SEC(CAST(horaire3 AS TIME))), 0)
                      FROM TEMP_worked_days
                      WHERE jour = 'lundi')) DURLUN,
             ((TIMESTAMPDIFF(SECOND, (SELECT MAX(livraison.date_fin)
                                      FROM demande AS sub_demande
                                               LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                               LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                      WHERE sub_demande.id = demande.id), validated_at)/86400)/7 + 1
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
             (SELECT (
                         SELECT IF(horaire1 IS NOT NULL AND horaire2 IS NOT NULL,
                                   (TIME_TO_SEC(CAST(horaire2 AS TIME)) - TIME_TO_SEC(CAST(horaire1 AS TIME))), 0)
                         FROM TEMP_worked_days
                         WHERE jour = 'mardi') +
                     (SELECT IF(horaire3 IS NOT NULL AND horaire4 IS NOT NULL,
                                (TIME_TO_SEC(CAST(horaire4 AS TIME)) - TIME_TO_SEC(CAST(horaire3 AS TIME))), 0)
                      FROM TEMP_worked_days
                      WHERE jour = 'mardi')) DURMAR,
             ((TIMESTAMPDIFF(SECOND, (SELECT MAX(livraison.date_fin)
                                      FROM demande AS sub_demande
                                               LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                               LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                      WHERE sub_demande.id = demande.id), validated_at)/86400)/7 + 1
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
             (SELECT (
                         SELECT IF(horaire1 IS NOT NULL AND horaire2 IS NOT NULL,
                                   (TIME_TO_SEC(CAST(horaire2 AS TIME)) - TIME_TO_SEC(CAST(horaire1 AS TIME))), 0)
                         FROM TEMP_worked_days
                         WHERE jour = 'mercredi') +
                     (SELECT IF(horaire3 IS NOT NULL AND horaire4 IS NOT NULL,
                                (TIME_TO_SEC(CAST(horaire4 AS TIME)) - TIME_TO_SEC(CAST(horaire3 AS TIME))), 0)
                      FROM TEMP_worked_days
                      WHERE jour = 'mercredi')) DURMER,
             ((TIMESTAMPDIFF(SECOND, (SELECT MAX(livraison.date_fin)
                                      FROM demande AS sub_demande
                                               LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                               LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                      WHERE sub_demande.id = demande.id), validated_at)/86400)/7 + 1
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
             (SELECT (
                         SELECT IF(horaire1 IS NOT NULL AND horaire2 IS NOT NULL,
                                   (TIME_TO_SEC(CAST(horaire2 AS TIME)) - TIME_TO_SEC(CAST(horaire1 AS TIME))), 0)
                         FROM TEMP_worked_days
                         WHERE jour = 'jeudi') +
                     (SELECT IF(horaire3 IS NOT NULL AND horaire4 IS NOT NULL,
                                (TIME_TO_SEC(CAST(horaire4 AS TIME)) - TIME_TO_SEC(CAST(horaire3 AS TIME))), 0)
                      FROM TEMP_worked_days
                      WHERE jour = 'jeudi')) DURJEU,
             ((TIMESTAMPDIFF(SECOND, (SELECT MAX(livraison.date_fin)
                                      FROM demande AS sub_demande
                                               LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                               LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                      WHERE sub_demande.id = demande.id), validated_at)/86400)/7 + 1
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
             (SELECT (
                         SELECT IF(horaire1 IS NOT NULL AND horaire2 IS NOT NULL,
                                   (TIME_TO_SEC(CAST(horaire2 AS TIME)) - TIME_TO_SEC(CAST(horaire1 AS TIME))), 0)
                         FROM TEMP_worked_days
                         WHERE jour = 'vendredi') +
                     (SELECT IF(horaire3 IS NOT NULL AND horaire4 IS NOT NULL,
                                (TIME_TO_SEC(CAST(horaire4 AS TIME)) - TIME_TO_SEC(CAST(horaire3 AS TIME))), 0)
                      FROM TEMP_worked_days
                      WHERE jour = 'vendredi')) DURVEN,
             ((TIMESTAMPDIFF(SECOND, (SELECT MAX(livraison.date_fin)
                                      FROM demande AS sub_demande
                                               LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                               LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                      WHERE sub_demande.id = demande.id), validated_at)/86400)/7 + 1
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
             (SELECT (
                         SELECT IF(horaire1 IS NOT NULL AND horaire2 IS NOT NULL,
                                   (TIME_TO_SEC(CAST(horaire2 AS TIME)) - TIME_TO_SEC(CAST(horaire1 AS TIME))), 0)
                         FROM TEMP_worked_days
                         WHERE jour = 'samedi') +
                     (SELECT IF(horaire3 IS NOT NULL AND horaire4 IS NOT NULL,
                                (TIME_TO_SEC(CAST(horaire4 AS TIME)) - TIME_TO_SEC(CAST(horaire3 AS TIME))), 0)
                      FROM TEMP_worked_days
                      WHERE jour = 'samedi')) DURSAM,
             ((TIMESTAMPDIFF(SECOND, (SELECT MAX(livraison.date_fin)
                                      FROM demande AS sub_demande
                                               LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
                                               LEFT JOIN livraison ON preparation.id = livraison.preparation_id
                                      WHERE sub_demande.id = demande.id), validated_at)/86400)/7 + 1
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
             (SELECT (
                         SELECT IF(horaire1 IS NOT NULL AND horaire2 IS NOT NULL,
                                   (TIME_TO_SEC(CAST(horaire2 AS TIME)) - TIME_TO_SEC(CAST(horaire1 AS TIME))), 0)
                         FROM TEMP_worked_days
                         WHERE jour = 'dimanche') +
                     (SELECT IF(horaire3 IS NOT NULL AND horaire4 IS NOT NULL,
                                (TIME_TO_SEC(CAST(horaire4 AS TIME)) - TIME_TO_SEC(CAST(horaire3 AS TIME))), 0)
                      FROM TEMP_worked_days
                      WHERE jour = 'dimanche')) DURDIM
      FROM demande) AS data;

DROP TABLE IF EXISTS TEMP_non_worked_days;
DROP TABLE IF EXISTS TEMP_worked_days;
