SELECT IF(days_worked.day = 'monday', 'lundi',
          IF(days_worked.day = 'tuesday', 'mardi',
             IF(days_worked.day = 'wednesday', 'mercredi',
                IF(days_worked.day = 'thursday', 'jeudi',
                   IF(days_worked.day = 'friday', 'vendredi',
                      IF(days_worked.day = 'saturday', 'samedi',
                         IF(days_worked.day = 'sunday', 'dimanche', NULL))))))) AS jour,
       IF(days_worked.worked = 1, 'oui', 'non')                                 AS travaille,
       days_worked.times                                                        AS horaires

FROM days_worked
