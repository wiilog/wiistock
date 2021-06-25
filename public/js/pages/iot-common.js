function onDeviceChanged($select, needsSubmit = false) {
    const $modal = $select.closest(".modal");
    const $sensorCode = $modal.find('select[name=sensorCode]');
    const $sensor = $modal.find('select[name=sensor], select[name=sensorWrapper]');
    const [val] = $select.select2("data");
    if($sensorCode.val() !== $sensor.val()){
        if(val){
            if ($select.attr('name') === "sensorCode") {
                let option = new Option(val.name, val.id, true, true);
                $sensor.append(option).trigger('change');
            } else if (($select.attr('name') === "sensorWrapper" || $select.attr('name') === "sensor")) {
                let option = new Option(val.code, val.id, true, true);
                $sensorCode.append(option).trigger('change');
            }
        }else{
            $sensorCode.val(null);
            $sensor.val(null);
            $sensorCode.trigger('change');
            $sensor.trigger('change');
        }
    }
    if (needsSubmit) {
        submitSensor(val);
    }
}

function refreshSelectWithVal($select, val) {
    $select.select2('destroy');
    $select.val(val).select2();
}
