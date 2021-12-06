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

SELECT serviceId,
       (((NBLUN*DURLUN)-CORR_DEB_LUN-CORR_FIN_LUN)
           + ((NBMAR*DURMAR)-CORR_DEB_MAR-CORR_FIN_MAR)
           + ((NBMER*DURMER)-CORR_DEB_MER-CORR_FIN_MER)
           + ((NBJEU*DURJEU)-CORR_DEB_JEU-CORR_FIN_JEU)
           + ((NBVEN*DURVEN)-CORR_DEB_VEN-CORR_FIN_VEN)
           + ((NBSAM*DURSAM)-CORR_DEB_SAM-CORR_FIN_SAM)
           + ((NBDIM*DURDIM)-CORR_DEB_DIM-CORR_FIN_DIM))/3600 AS delais_traitement_attendu
FROM (SELECT
          ((DATEDIFF((validation_date - INTERVAL WEEKDAY(validation_date) DAY), (desired_date - INTERVAL WEEKDAY(desired_date) DAY)))/7 + 1
              - (IF(DAYOFWEEK(desired_date) <> 2, 1, 0))
              - (SELECT count(*) FROM TEMP_non_worked_days WHERE jour BETWEEN desired_date AND validation_date AND DAYOFWEEK(jour) = 2)) AS NBLUN,
          (SELECT (SELECT(IF(horaire1 IS NOT NULL AND horaire2 IS NOT NULL, TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME)), 0)) FROM TEMP_worked_days WHERE jour = 'lundi')+
                  (SELECT(IF(horaire3 IS NOT NULL AND horaire4 IS NOT NULL, TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME)), 0)) FROM TEMP_worked_days WHERE jour = 'lundi')) DURLUN,
          COALESCE((SELECT CASE
                               WHEN (CAST(desired_date AS TIME))<CAST(horaire1 AS TIME)
                                   THEN 0
                               WHEN (CAST(desired_date AS TIME))<CAST(horaire2 AS TIME)
                                   THEN TIME_TO_SEC(CAST(desired_date AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME))
                               WHEN (CAST(desired_date AS TIME))<CAST(horaire3 AS TIME)
                                   THEN TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME))
                               WHEN (CAST(desired_date AS TIME))<CAST(horaire4 AS TIME)
                                   THEN (TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME)))+(TIME_TO_SEC(CAST(desired_date AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME)))
                               ELSE ((TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME)))+(TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME))))
                               END FROM TEMP_worked_days WHERE jour = 'lundi' AND DAYOFWEEK(desired_date) = 2), 0) CORR_DEB_LUN,
          COALESCE((SELECT CASE
                               WHEN CAST(validation_date AS TIME)>CAST(horaire4 AS TIME)
                                   THEN 0
                               WHEN CAST(validation_date AS TIME)>CAST(horaire3 AS TIME)
                                   THEN TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(validation_date AS TIME))
                               WHEN CAST(validation_date AS TIME)>CAST(horaire2 AS TIME)
                                   THEN TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME))
                               WHEN CAST(validation_date AS TIME)>CAST(horaire1 AS TIME)
                                   THEN (TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME)))+(TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(validation_date AS TIME)))
                               ELSE ((TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME)))+(TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME))))
                               END FROM TEMP_worked_days WHERE jour = 'lundi' AND DAYOFWEEK(validation_date) = 2), 0) CORR_FIN_LUN,


          ((DATEDIFF((validation_date - INTERVAL WEEKDAY(validation_date) DAY), (desired_date - INTERVAL WEEKDAY(desired_date) DAY)))/7 + 1
              - (IF(DAYOFWEEK(desired_date) NOT IN (2, 3), 1, 0))
              - (IF(DAYOFWEEK(validation_date) = 2, 1, 0))
              - (SELECT count(*) FROM TEMP_non_worked_days WHERE jour BETWEEN desired_date AND validation_date AND DAYOFWEEK(jour) = 3)) AS NBMAR,
          (SELECT (SELECT(IF(horaire1 IS NOT NULL AND horaire2 IS NOT NULL, TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME)), 0)) FROM TEMP_worked_days WHERE jour = 'mardi')+
                  (SELECT(IF(horaire3 IS NOT NULL AND horaire4 IS NOT NULL, TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME)), 0)) FROM TEMP_worked_days WHERE jour = 'mardi')) DURMAR,
          COALESCE((SELECT CASE
                               WHEN (CAST(desired_date AS TIME))<CAST(horaire1 AS TIME)
                                   THEN 0
                               WHEN (CAST(desired_date AS TIME))<CAST(horaire2 AS TIME)
                                   THEN TIME_TO_SEC(CAST(desired_date AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME))
                               WHEN (CAST(desired_date AS TIME))<CAST(horaire3 AS TIME)
                                   THEN TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME))
                               WHEN (CAST(desired_date AS TIME))<CAST(horaire4 AS TIME)
                                   THEN (TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME)))+(TIME_TO_SEC(CAST(desired_date AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME)))
                               ELSE ((TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME)))+(TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME))))
                               END FROM TEMP_worked_days WHERE jour = 'mardi' AND DAYOFWEEK(desired_date) = 3), 0) CORR_DEB_MAR,
          COALESCE((SELECT CASE
                               WHEN CAST(validation_date AS TIME)>CAST(horaire4 AS TIME)
                                   THEN 0
                               WHEN CAST(validation_date AS TIME)>CAST(horaire3 AS TIME)
                                   THEN TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(validation_date AS TIME))
                               WHEN CAST(validation_date AS TIME)>CAST(horaire2 AS TIME)
                                   THEN TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME))
                               WHEN CAST(validation_date AS TIME)>CAST(horaire1 AS TIME)
                                   THEN (TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME)))+(TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(validation_date AS TIME)))
                               ELSE ((TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME)))+(TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME))))
                               END FROM TEMP_worked_days WHERE jour = 'mardi' AND DAYOFWEEK(validation_date) = 3), 0) CORR_FIN_MAR,


          ((DATEDIFF((validation_date - INTERVAL WEEKDAY(validation_date) DAY), (desired_date - INTERVAL WEEKDAY(desired_date) DAY)))/7 + 1
              - (IF(DAYOFWEEK(desired_date) NOT IN (2, 3, 4), 1, 0))
              - (IF(DAYOFWEEK(validation_date) IN (2, 3), 1, 0))
              - (SELECT count(*) FROM TEMP_non_worked_days WHERE jour BETWEEN desired_date AND validation_date AND DAYOFWEEK(jour) = 4)) AS NBMER,
          (SELECT (SELECT(IF(horaire1 IS NOT NULL AND horaire2 IS NOT NULL, TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME)), 0)) FROM TEMP_worked_days WHERE jour = 'mercredi')+
                  (SELECT(IF(horaire3 IS NOT NULL AND horaire4 IS NOT NULL, TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME)), 0)) FROM TEMP_worked_days WHERE jour = 'mercredi')) DURMER,
          COALESCE((SELECT CASE
                               WHEN (CAST(desired_date AS TIME))<CAST(horaire1 AS TIME)
                                   THEN 0
                               WHEN (CAST(desired_date AS TIME))<CAST(horaire2 AS TIME)
                                   THEN TIME_TO_SEC(CAST(desired_date AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME))
                               WHEN (CAST(desired_date AS TIME))<CAST(horaire3 AS TIME)
                                   THEN TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME))
                               WHEN (CAST(desired_date AS TIME))<CAST(horaire4 AS TIME)
                                   THEN (TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME)))+(TIME_TO_SEC(CAST(desired_date AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME)))
                               ELSE ((TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME)))+(TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME))))
                               END FROM TEMP_worked_days WHERE jour = 'mercredi' AND DAYOFWEEK(desired_date) = 4), 0) CORR_DEB_MER,
          COALESCE((SELECT CASE
                               WHEN CAST(validation_date AS TIME)>CAST(horaire4 AS TIME)
                                   THEN 0
                               WHEN CAST(validation_date AS TIME)>CAST(horaire3 AS TIME)
                                   THEN TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(validation_date AS TIME))
                               WHEN CAST(validation_date AS TIME)>CAST(horaire2 AS TIME)
                                   THEN TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME))
                               WHEN CAST(validation_date AS TIME)>CAST(horaire1 AS TIME)
                                   THEN (TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME)))+(TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(validation_date AS TIME)))
                               ELSE ((TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME)))+(TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME))))
                               END FROM TEMP_worked_days WHERE jour = 'mercredi' AND DAYOFWEEK(validation_date) = 4),0) CORR_FIN_MER,


          ((DATEDIFF((validation_date - INTERVAL WEEKDAY(validation_date) DAY), (desired_date - INTERVAL WEEKDAY(desired_date) DAY)))/7 + 1
              - (IF(DAYOFWEEK(desired_date) IN (6, 7, 1), 1, 0))
              - (IF(DAYOFWEEK(validation_date) IN (2, 3, 4), 1, 0))
              - (SELECT count(*) FROM TEMP_non_worked_days WHERE jour BETWEEN desired_date AND validation_date AND DAYOFWEEK(jour) = 5)) AS NBJEU,
          (SELECT (SELECT(IF(horaire1 IS NOT NULL AND horaire2 IS NOT NULL, TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME)), 0)) FROM TEMP_worked_days WHERE jour = 'jeudi')+
                  (SELECT(IF(horaire3 IS NOT NULL AND horaire4 IS NOT NULL, TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME)), 0)) FROM TEMP_worked_days WHERE jour = 'jeudi')) DURJEU,
          COALESCE((SELECT CASE
                               WHEN (CAST(desired_date AS TIME))<CAST(horaire1 AS TIME)
                                   THEN 0
                               WHEN (CAST(desired_date AS TIME))<CAST(horaire2 AS TIME)
                                   THEN TIME_TO_SEC(CAST(desired_date AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME))
                               WHEN (CAST(desired_date AS TIME))<CAST(horaire3 AS TIME)
                                   THEN TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME))
                               WHEN (CAST(desired_date AS TIME))<CAST(horaire4 AS TIME)
                                   THEN (TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME)))+(TIME_TO_SEC(CAST(desired_date AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME)))
                               ELSE ((TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME)))+(TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME))))
                               END FROM TEMP_worked_days WHERE jour = 'jeudi' AND DAYOFWEEK(desired_date) = 5), 0) CORR_DEB_JEU,
          COALESCE((SELECT CASE
                               WHEN CAST(validation_date AS TIME)>CAST(horaire4 AS TIME)
                                   THEN 0
                               WHEN CAST(validation_date AS TIME)>CAST(horaire3 AS TIME)
                                   THEN TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(validation_date AS TIME))
                               WHEN CAST(validation_date AS TIME)>CAST(horaire2 AS TIME)
                                   THEN TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME))
                               WHEN CAST(validation_date AS TIME)>CAST(horaire1 AS TIME)
                                   THEN (TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME)))+(TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(validation_date AS TIME)))
                               ELSE ((TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME)))+(TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME))))
                               END FROM TEMP_worked_days WHERE jour = 'jeudi' AND DAYOFWEEK(validation_date) = 5), 0) CORR_FIN_JEU,


          ((DATEDIFF((validation_date - INTERVAL WEEKDAY(validation_date) DAY), (desired_date - INTERVAL WEEKDAY(desired_date) DAY)))/7 + 1
              - (IF(DAYOFWEEK(desired_date) IN (7, 1), 1, 0))
              - (IF(DAYOFWEEK(validation_date) NOT IN (6, 7, 1), 1, 0))
              - (SELECT count(*) FROM TEMP_non_worked_days WHERE jour BETWEEN desired_date AND validation_date AND DAYOFWEEK(jour) = 6)) AS NBVEN,
          (SELECT (SELECT(IF(horaire1 IS NOT NULL AND horaire2 IS NOT NULL, TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME)), 0)) FROM TEMP_worked_days WHERE jour = 'vendredi')+
                  (SELECT(IF(horaire3 IS NOT NULL AND horaire4 IS NOT NULL, TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME)), 0)) FROM TEMP_worked_days WHERE jour = 'vendredi')) DURVEN,
          COALESCE((SELECT CASE
                               WHEN (CAST(desired_date AS TIME))<CAST(horaire1 AS TIME)
                                   THEN 0
                               WHEN (CAST(desired_date AS TIME))<CAST(horaire2 AS TIME)
                                   THEN TIME_TO_SEC(CAST(desired_date AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME))
                               WHEN (CAST(desired_date AS TIME))<CAST(horaire3 AS TIME)
                                   THEN TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME))
                               WHEN (CAST(desired_date AS TIME))<CAST(horaire4 AS TIME)
                                   THEN (TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME)))+(TIME_TO_SEC(CAST(desired_date AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME)))
                               ELSE ((TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME)))+(TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME))))
                               END FROM TEMP_worked_days WHERE jour = 'vendredi' AND DAYOFWEEK(desired_date) = 6), 0) CORR_DEB_VEN,
          COALESCE((SELECT CASE
                               WHEN CAST(validation_date AS TIME)>CAST(horaire4 AS TIME)
                                   THEN 0
                               WHEN CAST(validation_date AS TIME)>CAST(horaire3 AS TIME)
                                   THEN TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(validation_date AS TIME))
                               WHEN CAST(validation_date AS TIME)>CAST(horaire2 AS TIME)
                                   THEN TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME))
                               WHEN CAST(validation_date AS TIME)>CAST(horaire1 AS TIME)
                                   THEN (TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME)))+(TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(validation_date AS TIME)))
                               ELSE ((TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME)))+(TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME))))
                               END FROM TEMP_worked_days WHERE jour = 'vendredi' AND DAYOFWEEK(validation_date) = 6), 0) CORR_FIN_VEN,


          ((DATEDIFF((validation_date - INTERVAL WEEKDAY(validation_date) DAY), (desired_date - INTERVAL WEEKDAY(desired_date) DAY)))/7 + 1
              - (IF(DAYOFWEEK(desired_date) = 1, 1, 0))
              - (IF(DAYOFWEEK(validation_date) NOT IN (7, 1), 1, 0))
              - (SELECT count(*) FROM TEMP_non_worked_days WHERE jour BETWEEN desired_date AND validation_date AND DAYOFWEEK(jour) = 7)) AS NBSAM,
          (SELECT (SELECT(IF(horaire1 IS NOT NULL AND horaire2 IS NOT NULL, TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME)), 0)) FROM TEMP_worked_days WHERE jour = 'samedi')+
                  (SELECT(IF(horaire3 IS NOT NULL AND horaire4 IS NOT NULL, TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME)), 0)) FROM TEMP_worked_days WHERE jour = 'samedi')) DURSAM,
          COALESCE((SELECT CASE
                               WHEN (CAST(desired_date AS TIME))<CAST(horaire1 AS TIME)
                                   THEN 0
                               WHEN (CAST(desired_date AS TIME))<CAST(horaire2 AS TIME)
                                   THEN TIME_TO_SEC(CAST(desired_date AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME))
                               WHEN (CAST(desired_date AS TIME))<CAST(horaire3 AS TIME)
                                   THEN TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME))
                               WHEN (CAST(desired_date AS TIME))<CAST(horaire4 AS TIME)
                                   THEN (TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME)))+(TIME_TO_SEC(CAST(desired_date AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME)))
                               ELSE ((TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME)))+(TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME))))
                               END FROM TEMP_worked_days WHERE jour = 'samedi' AND DAYOFWEEK(desired_date) = 7), 0) CORR_DEB_SAM,
          COALESCE((SELECT CASE
                               WHEN CAST(validation_date AS TIME)>CAST(horaire4 AS TIME)
                                   THEN 0
                               WHEN CAST(validation_date AS TIME)>CAST(horaire3 AS TIME)
                                   THEN TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(validation_date AS TIME))
                               WHEN CAST(validation_date AS TIME)>CAST(horaire2 AS TIME)
                                   THEN TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME))
                               WHEN CAST(validation_date AS TIME)>CAST(horaire1 AS TIME)
                                   THEN (TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME)))+(TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(validation_date AS TIME)))
                               ELSE ((TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME)))+(TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME))))
                               END FROM TEMP_worked_days WHERE jour = 'samedi' AND DAYOFWEEK(validation_date) = 7), 0) CORR_FIN_SAM,


          ((DATEDIFF((validation_date - INTERVAL WEEKDAY(validation_date) DAY), (desired_date - INTERVAL WEEKDAY(desired_date) DAY)))/7 + 1
              - (IF(DAYOFWEEK(validation_date) <> 1, 1, 0))
              - (SELECT count(*) FROM TEMP_non_worked_days WHERE jour BETWEEN desired_date AND validation_date AND DAYOFWEEK(jour) = 1)) AS NBDIM,
          (SELECT (SELECT(IF(horaire1 IS NOT NULL AND horaire2 IS NOT NULL, TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME)), 0)) FROM TEMP_worked_days WHERE jour = 'dimanche')+
                  (SELECT(IF(horaire3 IS NOT NULL AND horaire4 IS NOT NULL, TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME)), 0)) FROM TEMP_worked_days WHERE jour = 'dimanche')) DURDIM,
          COALESCE((SELECT CASE
                               WHEN (CAST(desired_date AS TIME))<CAST(horaire1 AS TIME)
                                   THEN 0
                               WHEN (CAST(desired_date AS TIME))<CAST(horaire2 AS TIME)
                                   THEN TIME_TO_SEC(CAST(desired_date AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME))
                               WHEN (CAST(desired_date AS TIME))<CAST(horaire3 AS TIME)
                                   THEN TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME))
                               WHEN (CAST(desired_date AS TIME))<CAST(horaire4 AS TIME)
                                   THEN (TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME)))+(TIME_TO_SEC(CAST(desired_date AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME)))
                               ELSE ((TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME)))+(TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME))))
                               END FROM TEMP_worked_days WHERE jour = 'dimanche' AND DAYOFWEEK(desired_date) = 1), 0) CORR_DEB_DIM,
          COALESCE((SELECT CASE
                               WHEN CAST(validation_date AS TIME)>CAST(horaire4 AS TIME)
                                   THEN 0
                               WHEN CAST(validation_date AS TIME)>CAST(horaire3 AS TIME)
                                   THEN TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(validation_date AS TIME))
                               WHEN CAST(validation_date AS TIME)>CAST(horaire2 AS TIME)
                                   THEN TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME))
                               WHEN CAST(validation_date AS TIME)>CAST(horaire1 AS TIME)
                                   THEN (TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME)))+(TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(validation_date AS TIME)))
                               ELSE ((TIME_TO_SEC(CAST(horaire2 AS TIME))-TIME_TO_SEC(CAST(horaire1 AS TIME)))+(TIME_TO_SEC(CAST(horaire4 AS TIME))-TIME_TO_SEC(CAST(horaire3 AS TIME))))
                               END FROM TEMP_worked_days WHERE jour = 'dimanche' AND DAYOFWEEK(validation_date) = 1), 0) CORR_FIN_DIM,
          handling.id AS serviceId
      FROM handling) AS data;

DROP TABLE IF EXISTS TEMP_non_worked_days;
DROP TABLE IF EXISTS TEMP_worked_days;
