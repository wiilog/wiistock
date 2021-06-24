function onDeviceChanged($select, needsSubmit = false) {
    const val = $select.find('option:selected').data('value');
    if ($select.attr('name') === "sensorCode") {
        refreshSelectWithVal($('select[name=sensorWrapper]'), val);
        refreshSelectWithVal($('select[name=sensor]'), val);
    } else if (($select.attr('name') === "sensorWrapper" || $select.attr('name') === "sensor")) {
        refreshSelectWithVal($('select[name=sensorCode]'), val);
    }
    if (needsSubmit) {
        submitSensor(val);
    }
}

function refreshSelectWithVal($select, val) {
    $select.select2('destroy');
    $select.val(val).select2();
}
