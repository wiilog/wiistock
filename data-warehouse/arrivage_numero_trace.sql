-- Creates a temporary table named DATES, which contains all the days starting from tomorrow up until one years time
WITH RECURSIVE DATES AS
(
   SELECT (SELECT truck_arrival.creation_date FROM truck_arrival ORDER BY truck_arrival.creation_date LIMIT 1) as next
   UNION ALL
   SELECT DATE_ADD(next, INTERVAL 1 DAY)
   from DATES
   where next < DATE_ADD(NOW(), INTERVAL 1 YEAR)
)

SELECT truck_arrival_line.number as no_tracking,
       truck_arrival.number as no_arrivage_camion,
       IF(reserve.type = 'qualite', 'Oui', 'Non') as reserve_qualite,
       IF(
           (
               (SELECT COUNT(*) FROM truck_arrival_line_arrivage WHERE truck_arrival_line_arrivage.truck_arrival_line_id = truck_arrival_line.id) = 0
               AND TIME(truck_arrival.creation_date) <= (SELECT setting.value FROM setting WHERE setting.label = 'TRUCK_ARRIVALS_PROCESSING_HOUR_CREATE_BEFORE_START')
               AND (
                    DATE(NOW()) > DATE(truck_arrival.creation_date)
                    OR TIME(NOW()) > (SELECT setting.value FROM setting WHERE setting.label = 'TRUCK_ARRIVALS_PROCESSING_HOUR_CREATE_BEFORE_END')
               )
           )
           OR (
               (SELECT COUNT(*) FROM truck_arrival_line_arrivage WHERE truck_arrival_line_arrivage.truck_arrival_line_id = truck_arrival_line.id) = 0
               AND TIME(truck_arrival.creation_date) >= (SELECT setting.value FROM setting WHERE setting.label = 'TRUCK_ARRIVALS_PROCESSING_HOUR_CREATE_AFTER_START')
               AND (
                    DATE(NOW()) > (
                                    SELECT DATE(next)
                                    FROM DATES
                                    WHERE (SELECT COUNT(day) FROM work_free_day WHERE day = DATE_FORMAT(next, '%Y-%m-%d') LIMIT 1) = 0
                                    AND (SELECT day FROM days_worked WHERE worked = 1 AND LCASE(days_worked.day) = LCASE(DAYNAME(next))) IS NOT NULL
                                    AND next > truck_arrival.creation_date
                                    ORDER BY next
                                    LIMIT 1
                                   )
                    OR (
                        DATE(NOW()) = (
                                        SELECT DATE(next)
                                        FROM DATES
                                        WHERE (SELECT COUNT(day) FROM work_free_day WHERE day = DATE_FORMAT(next, '%Y-%m-%d') LIMIT 1) = 0
                                        AND (SELECT day FROM days_worked WHERE worked = 1 AND LCASE(days_worked.day) = LCASE(DAYNAME(next))) IS NOT NULL
                                        AND next > truck_arrival.creation_date
                                        ORDER BY next
                                        LIMIT 1
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
