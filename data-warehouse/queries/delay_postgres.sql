SELECT (NBLUN * DURLUN
    + NBMAR * DURMAR
    + NBMER * DURMER
    + NBJEU * DURJEU
    + NBVEN * DURVEN
    + NBSAM * DURSAM
    + NBDIM * DURDIM)/3600 as delais_traitement
FROM (SELECT EXTRACT(day FROM (DATE_TRUNC('week',date_traitement)-DATE_TRUNC('week',date_validation)))/7 + 1
                 - (CASE WHEN DATE_PART('dow',date_validation)<> 1 THEN 1 ELSE 0 END)
                 - (SELECT count(*) FROM dw_jours_non_travailles WHERE jour BETWEEN date_validation AND date_traitement AND DATE_PART('dow',jour) = 1) AS NBLUN,
             (SELECT (
                         SELECT
                             CASE WHEN horaire1 IS NOT NULL AND horaire2 IS NOT NULL
                                      THEN EXTRACT(EPOCH FROM CAST(horaire2 AS TIME)-CAST(horaire1 AS TIME))
                                  ELSE 0
                                 END
                         FROM dw_jours_horaires_travailles
                         WHERE jour = 'lundi') +
                     (SELECT
                          CASE WHEN horaire3 IS NOT NULL AND horaire4 IS NOT NULL
                                   THEN EXTRACT(EPOCH FROM (CAST(horaire4 AS TIME)-CAST(horaire3 AS TIME)))
                               ELSE 0
                              END
                      FROM dw_jours_horaires_travailles
                      WHERE jour = 'lundi')) DURLUN,
             EXTRACT(day FROM (DATE_TRUNC('week',date_traitement)-DATE_TRUNC('week',date_validation)))/7 + 1
                 - (CASE WHEN DATE_PART('dow',date_validation) NOT IN (1 , 2) THEN 1 ELSE 0 END)
                 - (CASE WHEN DATE_PART('dow',date_traitement) = 1 THEN 1 ELSE 0 END)
                 - (SELECT count(*) FROM dw_jours_non_travailles WHERE jour BETWEEN date_validation AND date_traitement AND DATE_PART('dow',jour) = 2) AS NBMAR,
             (SELECT (
                         SELECT
                             CASE WHEN horaire1 IS NOT NULL AND horaire2 IS NOT NULL
                                      THEN EXTRACT(EPOCH FROM CAST(horaire2 AS TIME)-CAST(horaire1 AS TIME))
                                  ELSE 0
                                 END
                         FROM dw_jours_horaires_travailles
                         WHERE jour = 'mardi') +
                     (SELECT
                          CASE WHEN horaire3 IS NOT NULL AND horaire4 IS NOT NULL
                                   THEN EXTRACT(EPOCH FROM (CAST(horaire4 AS TIME)-CAST(horaire3 AS TIME)))
                               ELSE 0
                              END
                      FROM dw_jours_horaires_travailles
                      WHERE jour = 'mardi')) DURMAR,
             EXTRACT(day FROM (DATE_TRUNC('week',date_traitement)-DATE_TRUNC('week',date_validation)))/7 + 1
                 - (CASE WHEN DATE_PART('dow',date_validation) NOT IN (1 , 2 , 3) THEN 1 ELSE 0 END)
                 - (CASE WHEN DATE_PART('dow',date_traitement) IN (1 , 2) THEN 1 ELSE 0 END)
                 - (SELECT count(*) FROM dw_jours_non_travailles WHERE jour BETWEEN date_validation AND date_traitement AND DATE_PART('dow',jour) = 3) AS NBMER,
             (SELECT (
                         SELECT
                             CASE WHEN horaire1 IS NOT NULL AND horaire2 IS NOT NULL
                                      THEN EXTRACT(EPOCH FROM CAST(horaire2 AS TIME)-CAST(horaire1 AS TIME))
                                  ELSE 0
                                 END
                         FROM dw_jours_horaires_travailles
                         WHERE jour = 'mercredi') +
                     (SELECT
                          CASE WHEN horaire3 IS NOT NULL AND horaire4 IS NOT NULL
                                   THEN EXTRACT(EPOCH FROM (CAST(horaire4 AS TIME)-CAST(horaire3 AS TIME)))
                               ELSE 0
                              END
                      FROM dw_jours_horaires_travailles
                      WHERE jour = 'mercredi')) DURMER,
             EXTRACT(day FROM (DATE_TRUNC('week',date_traitement)-DATE_TRUNC('week',date_validation)))/7 + 1
                 - (CASE WHEN DATE_PART('dow',date_validation) IN (5 , 6 , 0) THEN 1 ELSE 0 END)
                 - (CASE WHEN DATE_PART('dow',date_traitement) IN (1 , 2 , 3) THEN 1 ELSE 0 END)
                 - (SELECT count(*) FROM dw_jours_non_travailles WHERE jour BETWEEN date_validation AND date_traitement AND DATE_PART('dow',jour) = 4) AS NBJEU,
             (SELECT (
                         SELECT
                             CASE WHEN horaire1 IS NOT NULL AND horaire2 IS NOT NULL
                                      THEN EXTRACT(EPOCH FROM CAST(horaire2 AS TIME)-CAST(horaire1 AS TIME))
                                  ELSE 0
                                 END
                         FROM dw_jours_horaires_travailles
                         WHERE jour = 'jeudi') +
                     (SELECT
                          CASE WHEN horaire3 IS NOT NULL AND horaire4 IS NOT NULL
                                   THEN EXTRACT(EPOCH FROM (CAST(horaire4 AS TIME)-CAST(horaire3 AS TIME)))
                               ELSE 0
                              END
                      FROM dw_jours_horaires_travailles
                      WHERE jour = 'jeudi')) DURJEU,
             EXTRACT(day FROM (DATE_TRUNC('week',date_traitement)-DATE_TRUNC('week',date_validation)))/7 + 1
                 - (CASE WHEN DATE_PART('dow',date_validation) IN (6 , 0) THEN 1 ELSE 0 END)
                 - (CASE WHEN DATE_PART('dow',date_traitement) NOT IN (5 , 6 , 0) THEN 1 ELSE 0 END)
                 - (SELECT count(*) FROM dw_jours_non_travailles WHERE jour BETWEEN date_validation AND date_traitement AND DATE_PART('dow',jour) = 5) AS NBVEN,
             (SELECT (
                         SELECT
                             CASE WHEN horaire1 IS NOT NULL AND horaire2 IS NOT NULL
                                      THEN EXTRACT(EPOCH FROM CAST(horaire2 AS TIME)-CAST(horaire1 AS TIME))
                                  ELSE 0
                                 END
                         FROM dw_jours_horaires_travailles
                         WHERE jour = 'vendredi') +
                     (SELECT
                          CASE WHEN horaire3 IS NOT NULL AND horaire4 IS NOT NULL
                                   THEN EXTRACT(EPOCH FROM (CAST(horaire4 AS TIME)-CAST(horaire3 AS TIME)))
                               ELSE 0
                              END
                      FROM dw_jours_horaires_travailles
                      WHERE jour = 'vendredi')) DURVEN,
             EXTRACT(day FROM (DATE_TRUNC('week',date_traitement)-DATE_TRUNC('week',date_validation)))/7 + 1
                 - (CASE WHEN DATE_PART('dow',date_validation) = (0) THEN 1 ELSE 0 END)
                 - (CASE WHEN DATE_PART('dow',date_traitement) NOT IN (6 , 0) THEN 1 ELSE 0 END)
                 - (SELECT count(*) FROM dw_jours_non_travailles WHERE jour BETWEEN date_validation AND date_traitement AND DATE_PART('dow',jour) = 6) AS NBSAM,
             (SELECT (
                         SELECT
                             CASE WHEN horaire1 IS NOT NULL AND horaire2 IS NOT NULL
                                      THEN EXTRACT(EPOCH FROM CAST(horaire2 AS TIME)-CAST(horaire1 AS TIME))
                                  ELSE 0
                                 END
                         FROM dw_jours_horaires_travailles
                         WHERE jour = 'samedi') +
                     (SELECT
                          CASE WHEN horaire3 IS NOT NULL AND horaire4 IS NOT NULL
                                   THEN EXTRACT(EPOCH FROM (CAST(horaire4 AS TIME)-CAST(horaire3 AS TIME)))
                               ELSE 0
                              END
                      FROM dw_jours_horaires_travailles
                      WHERE jour = 'samedi')) DURSAM,
             EXTRACT(day FROM (DATE_TRUNC('week',date_traitement)-DATE_TRUNC('week',date_validation)))/7 + 1
                 - (CASE WHEN DATE_PART('dow',date_traitement) <> 0 THEN 1 ELSE 0 END)
                 - (SELECT count(*) FROM dw_jours_non_travailles WHERE jour BETWEEN date_validation AND date_traitement AND DATE_PART('dow',jour) = 0) AS NBDIM,
             (SELECT (
                         SELECT
                             CASE WHEN horaire1 IS NOT NULL AND horaire2 IS NOT NULL
                                      THEN EXTRACT(EPOCH FROM CAST(horaire2 AS TIME)-CAST(horaire1 AS TIME))
                                  ELSE 0
                                 END
                         FROM dw_jours_horaires_travailles
                         WHERE jour = 'dimanche') +
                     (SELECT
                          CASE WHEN horaire3 IS NOT NULL AND horaire4 IS NOT NULL
                                   THEN EXTRACT(EPOCH FROM (CAST(horaire4 AS TIME)-CAST(horaire3 AS TIME)))
                               ELSE 0
                              END
                      FROM dw_jours_horaires_travailles
                      WHERE jour = 'dimanche')) DURDIM
      FROM dw_demande_livraison) AS data
