SELECT sensor.code            AS code_capteur,
       sensor_message.date    AS date_message,
       IF(sensor_message.content_type = 1, 'Température',
          IF(sensor_message.content_type = 2, 'Hygrométrie',
             IF(sensor_message.content_type = 3, 'Action',
                IF(sensor_message.content_type = 4, 'GPS', NULL)
             )
          )
       )                  AS type_donnee,
       sensor_message.content AS donnee_principale

FROM sensor_message

         INNER JOIN sensor ON sensor_message.sensor_id = sensor.id
