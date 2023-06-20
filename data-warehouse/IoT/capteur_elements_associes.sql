SELECT
    sensor.code     AS code_capteur,
    IF(article.id,
        article.label,
        IF(preparation.id,
            preparation.numero,
            IF(ordre_collecte.id,
                ordre_collecte.numero,
                IF(pack.id,
                    pack.code,
                    IF(emplacement.id,
                        emplacement.label,
                        IF(location_group.id,
                            location_group.label,
                            IF(vehicle.id,
                                vehicle.registration_number,
                                '')
                            )
                        )
                    )
                )
            )
    )               AS element_associe,
    pairing.start   AS date_association,
    pairing.end     AS date_fin_association

FROM pairing

    INNER JOIN sensor_wrapper ON pairing.sensor_wrapper_id = sensor_wrapper.id
    INNER JOIN sensor ON sensor_wrapper.sensor_id = sensor.id
    LEFT JOIN article ON pairing.article_id = article.id
    LEFT JOIN pack ON pairing.pack_id = pack.id
    LEFT JOIN emplacement ON pairing.location_id = emplacement.id
    LEFT JOIN location_group ON pairing.location_group_id = location_group.id
    LEFT JOIN vehicle ON pairing.vehicle_id = vehicle.id
    LEFT JOIN preparation ON pairing.preparation_order_id = preparation.id
    LEFT JOIN ordre_collecte ON pairing.collect_order_id = ordre_collecte.id

WHERE pairing.active = true

