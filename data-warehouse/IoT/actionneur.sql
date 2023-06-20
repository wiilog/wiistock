SELECT
    sensor.code             AS code_capteur,
    IF(request_template.id,
        'Demande',
        IF(alert_template.id,
            'Alerte',
            null))          AS type_modele,
    IF(request_template.id,
       request_template.name,
       IF(alert_template.id,
          alert_template.name,
          null))            AS nom_modele,
    IF(JSON_UNQUOTE(JSON_EXTRACT(trigger_action.config, '$."temperature"')) = 'null',
       null,
       CONCAT(JSON_UNQUOTE(JSON_EXTRACT(trigger_action.config, '$."temperature"')), '°C'))
                            AS seuil_capteur,
    IF(JSON_UNQUOTE(JSON_EXTRACT(trigger_action.config, '$."limit"')) = 'null',
       null,
       IF(JSON_UNQUOTE(JSON_EXTRACT(trigger_action.config, '$."limit"')) = 'lower',
           'Inférieur',
           'Supérieur'))
                            AS type_seuil

FROM trigger_action

    INNER JOIN sensor_wrapper ON trigger_action.sensor_wrapper_id = sensor_wrapper.id
    INNER JOIN sensor ON sensor_wrapper.sensor_id = sensor.id
    LEFT JOIN alert_template ON trigger_action.alert_template_id = alert_template.id
    LEFT JOIN request_template ON trigger_action.request_template_id = request_template.id

WHERE JSON_UNQUOTE(JSON_EXTRACT(trigger_action.config, '$."limit"')) != 'null'
