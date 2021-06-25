function onDeviceChanged($select, needsSubmit = false) {
    const $modal = $select.closest(".modal");
    const $sensorCode = $modal.find('select[name=sensor]');
    const $sensor = $modal.find('select[name=sensorWrapper]');
    const [val] = $select.select2("data");

    if ($sensorCode.val() !== $sensor.val()) {
        if ($select.attr('name') === "sensor") {
            if (val) {
                let option = new Option(val.name, val.id, true, true);
                $sensor.append(option).trigger('change');
            } else {
                $sensor.val(null);
                $sensor.trigger('change');
            }
        } else if (($select.attr('name') === "sensorWrapper")) {
            if (val) {
                let option = new Option(val.code, val.id, true, true);
                $sensorCode.append(option).trigger('change');
            } else {
                $sensorCode.val(null);
                $sensorCode.trigger('change');
            }
        }
    }

    if (needsSubmit) {
        submitSensor(val);
    }
}
