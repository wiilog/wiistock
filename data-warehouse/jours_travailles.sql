SELECT IF(days_worked.day = 'monday', 'lundi',
          IF(days_worked.day = 'tuesday', 'mardi',
             IF(days_worked.day = 'wednesday', 'mercredi',
                IF(days_worked.day = 'thursday', 'jeudi',
                   IF(days_worked.day = 'friday', 'vendredi',
                      IF(days_worked.day = 'saturday', 'samedi',
                         IF(days_worked.day = 'sunday', 'dimanche', NULL))))))) AS jour,
       IF(days_worked.worked = 1, 'oui', 'non')                                 AS travaille,
       IF(LOCATE(';', days_worked.times) > 0, SUBSTRING_INDEX(SUBSTRING_INDEX(days_worked.times, ';', 1), '-', 1),
          SUBSTRING_INDEX(days_worked.times, '-', 1))                           AS horaire1,
       IF(LOCATE(';', days_worked.times) > 0, SUBSTRING_INDEX(SUBSTRING_INDEX(days_worked.times, ';', 1), '-', -1),
          SUBSTRING_INDEX(days_worked.times, '-', -1))                          AS horaire2,
       IF(LOCATE(';', days_worked.times) > 0, SUBSTRING_INDEX(SUBSTRING_INDEX(days_worked.times, ';', -1), '-', 1),
          NULL)                                                                 AS horaire3,
       IF(LOCATE(';', days_worked.times) > 0, SUBSTRING_INDEX(SUBSTRING_INDEX(days_worked.times, ';', -1), '-', -1),
          NULL)                                                                 AS horaire4

FROM days_worked
