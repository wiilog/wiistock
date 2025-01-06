SELECT truck_arrival_line.number                  as no_tracking,
       truck_arrival.number                       as no_arrivage_camion,
       IF(reserve.kind = 'qualite', 'Oui', 'Non') as reserve_qualite,
       IF(
           -- Line is marked as late condition 1 AND condition 2 AND condition 3 AND (condition 4.1 OR condition 4.2)
           -- condition 1: IF there is at least one worked days un settings
           (SELECT COUNT(*) FROM worked_day WHERE worked = 1) = 1
               -- condition 2: AND IF the current truck_arrival_line is not treated
               AND (SELECT COUNT(*) FROM truck_arrival_line_arrivage WHERE truck_arrival_line_arrivage.truck_arrival_line_id = truck_arrival_line.id) = 0
               -- condition 3: AND IF current truck_arrival_line is not treated
               AND (
                    -- condition 4.1: First truck arrival setting "Créé avant XX:XX à traiter avant XX:XX"
                    (
                        TIME(truck_arrival.creation_date) <= (SELECT setting.value FROM setting WHERE setting.label = 'TRUCK_ARRIVALS_PROCESSING_HOUR_CREATE_BEFORE_START')
                            AND (
                            DATE(NOW()) > DATE(truck_arrival.creation_date)
                                OR TIME(NOW()) > (SELECT setting.value FROM setting WHERE setting.label = 'TRUCK_ARRIVALS_PROCESSING_HOUR_CREATE_BEFORE_END')
                            )
                        )
                        -- condition 4.2: Second truck arrival setting "Créé après XX:XX à traiter avant XX:XX"
                        OR (
                        TIME(truck_arrival.creation_date) >= (SELECT setting.value FROM setting WHERE setting.label = 'TRUCK_ARRIVALS_PROCESSING_HOUR_CREATE_AFTER_START')
                            AND (
                            DATE(NOW()) > IF(truck_arrival.creation_date < DATE_ADD(NOW(), INTERVAL 1 YEAR)
                                                 AND (SELECT COUNT(day) FROM work_free_day WHERE day = DATE_FORMAT(truck_arrival.creation_date, '%Y-%m-%d') LIMIT 1) = 0
                                                 AND (SELECT day FROM worked_day WHERE worked = 1 AND LCASE(worked_day.day) = LCASE(DAYNAME(truck_arrival.creation_date))) IS NOT NULL
                                                 AND DATE_ADD(truck_arrival.creation_date, INTERVAL 1 DAY) > truck_arrival.creation_date,
                                             truck_arrival.creation_date,
                                             NULL
                                          )
                                OR (
                                DATE(NOW()) = IF(truck_arrival.creation_date < DATE_ADD(NOW(), INTERVAL 1 YEAR)
                                                     AND (SELECT COUNT(day) FROM work_free_day WHERE day = DATE_FORMAT(truck_arrival.creation_date, '%Y-%m-%d') LIMIT 1) = 0
                                                     AND (SELECT day FROM worked_day WHERE worked = 1 AND LCASE(worked_day.day) = LCASE(DAYNAME(truck_arrival.creation_date))) IS NOT NULL
                                                     AND DATE_ADD(truck_arrival.creation_date, INTERVAL 1 DAY) > truck_arrival.creation_date,
                                                 truck_arrival.creation_date,
                                                 NULL
                                              )
                                    AND TIME(NOW()) > (SELECT setting.value FROM setting WHERE setting.label = 'TRUCK_ARRIVALS_PROCESSING_HOUR_CREATE_AFTER_END')
                                )
                            )
                        ),
                    'Oui',
                    'Non'
               ) as retard,
           arrivage.numero_arrivage as no_arrivage_UL
           FROM truck_arrival_line
           INNER JOIN truck_arrival on truck_arrival_line.truck_arrival_id = truck_arrival.id
           LEFT JOIN reserve on truck_arrival_line.id = reserve.line_id
           LEFT JOIN truck_arrival_line_arrivage on truck_arrival_line.id = truck_arrival_line_arrivage.truck_arrival_line_id
           LEFT JOIN arrivage on truck_arrival_line_arrivage.arrivage_id = arrivage.id
