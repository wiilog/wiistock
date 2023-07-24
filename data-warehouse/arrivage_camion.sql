SELECT truck_arrival.id,
       truck_arrival.number as no_arrivage_camion,
       truck_arrival.creation_date as date_creation,
       transporteur.label as transporteur,
       CONCAT(chauffeur.prenom, ' ', chauffeur.nom) as chauffeur,
       truck_arrival.registration_number as immatriculation,
       emplacement.label as emplacement,
       utilisateur.username as operateur,
       COUNT(truck_arrival_line.id) as nb_tracking_total,
       IF(COUNT(reserve.id) > 0 , 'Oui', 'Non') as reserve_general,
       MAX(
           IF(
                   reserve.kind = 'quantity',
                   IF(
                           reserve.quantity_type = 'MINUS',
                           CONCAT('-', reserve.quantity),
                           CONCAT('+', reserve.quantity)
                       ),
                   'Non'
               )
           ) as reserve_quantite,
       GROUP_CONCAT(DISTINCT reserve_type.label) as reserve_types

FROM truck_arrival_line

         INNER JOIN truck_arrival on truck_arrival.id = truck_arrival_line.truck_arrival_id
         LEFT JOIN chauffeur on chauffeur.id = truck_arrival.driver_id
         INNER JOIN transporteur on transporteur.id = truck_arrival.carrier_id
         LEFT JOIN emplacement on emplacement.id = truck_arrival.unloading_location_id
         INNER JOIN utilisateur on utilisateur.id = truck_arrival.operator_id
         LEFT JOIN reserve on truck_arrival.id = reserve.truck_arrival_id
         LEFT JOIN reserve_type on reserve.reserve_type_id = reserve_type.id

GROUP BY truck_arrival.id
