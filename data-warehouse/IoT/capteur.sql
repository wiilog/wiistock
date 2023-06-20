SELECT
    sensor.code                     AS code_capteur,
    sensor_wrapper.name             AS nom_capteur,
    type.label                      AS type_capteur,
    sensor_profile.name             AS profil_capteur,
    max(sensor_message.date)        AS derniere_remontee,
    CONCAT(sensor.battery, '%')     AS niveau_batterie,
    utilisateur.username            AS gestionnaire

FROM sensor_wrapper

    INNER JOIN sensor ON sensor_wrapper.sensor_id = sensor.id
    INNER JOIN type ON sensor.type_id = type.id
    INNER JOIN sensor_profile ON sensor.profile_id = sensor_profile.id
    INNER JOIN sensor_message ON sensor.id = sensor_message.sensor_id
    INNER JOIN utilisateur ON sensor_wrapper.manager_id = utilisateur.id

WHERE sensor_wrapper.deleted = false
GROUP BY sensor.code, sensor_wrapper.name, utilisateur.username
