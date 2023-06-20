SELECT
    sensor.code             AS code_capteur,
    sensor_message.date     AS date_message,
    sensor_message.event    AS type_message,
    sensor_message.content  AS donnee_principale

FROM sensor_message

    INNER JOIN sensor ON sensor_message.sensor_id = sensor.id

