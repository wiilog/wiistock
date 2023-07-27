SELECT truck_arrival.id,
       truck_arrival.number                                       AS no_arrivage_camion,
       truck_arrival_line.number                                  AS no_tracking,
       truck_arrival.creation_date                                AS date_creation,
       transporteur.label                                         AS transporteur,
       CONCAT(chauffeur.prenom, ' ', chauffeur.nom)               AS chauffeur,
       truck_arrival.registration_number                          AS immatriculation,
       emplacement.label                                          AS emplacement,
       utilisateur.username                                       AS operateur,
       (SELECT COUNT(sub_line.id)
        FROM truck_arrival_line AS sub_line
        WHERE sub_line.truck_arrival_id = truck_arrival.id)       AS nb_tracking_total,
       IF((SELECT COUNT(sub_reserve.id)
           FROM reserve AS sub_reserve
           WHERE sub_reserve.truck_arrival_id = truck_arrival.id
             AND sub_reserve.kind = 'general') > 0, 'Oui', 'Non') AS reserve_generale,
       (SELECT IF(sub_reserve.id IS NOT NULL,
                  IF(sub_reserve.quantity_type = 'MINUS', CONCAT('-', sub_reserve.quantity),
                     CONCAT('+', sub_reserve.quantity)),
                  'Non')
        FROM truck_arrival AS sub_truck_arrival
                 LEFT JOIN reserve AS sub_reserve ON sub_reserve.id = sub_truck_arrival.id
            AND reserve.kind = 'quantity'
        LIMIT 1)                                                  AS reserve_quantite,
       reserve_type.label                                         AS reserve_tracking_type

FROM truck_arrival_line

         LEFT JOIN truck_arrival ON truck_arrival.id = truck_arrival_line.truck_arrival_id
         LEFT JOIN chauffeur ON chauffeur.id = truck_arrival.driver_id
         INNER JOIN transporteur ON transporteur.id = truck_arrival.carrier_id
         LEFT JOIN emplacement ON emplacement.id = truck_arrival.unloading_location_id
         INNER JOIN utilisateur ON utilisateur.id = truck_arrival.operator_id
         LEFT JOIN reserve ON truck_arrival_line.id = reserve.line_id AND reserve.kind = 'line'
         LEFT JOIN reserve_type ON reserve.reserve_type_id = reserve_type.id

GROUP BY truck_arrival_line.id
