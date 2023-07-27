const $modalNewTriggerAction = $('#modalNewTriggerAction');
const $modalEditTriggerAction = $('#modalEditTriggerAction');
const $sensorSelect = $modalNewTriggerAction.find('[name=sensorWrapper]');
const $sensorInput = $modalNewTriggerAction.find('[name=sensorCode]');
const $sensorDetailsContainer = $modalNewTriggerAction.find('.sensor-details-container');

$(function() {
    let pathTriggerAction = Routing.generate('trigger_action_api', true);
    let purchaseTriggerActionConfig = {
        processing: true,
        serverSide: true,
        order: [['sensorWrapper', 'desc']],
        ajax: {
            "url": pathTriggerAction,
            'data': {
                'filterDemand': $('#filterDemandId').val()
            },
            "type": "POST",
        },
        rowConfig: {
            needsRowClickAction: true,
        },
        drawConfig: {
            needsSearchOverride: true,
        },
        columns: [
            {"data": 'actions', 'name': 'Actions', 'title': '', className: 'noVis', orderable: false, width: '10px'},
            {"data": 'sensorWrapper', 'name': 'Nom', 'title': 'Nom du capteur', width: '50%'},
            {"data": 'template', 'name': 'Modèle', 'title': 'Modèle', width: '50%'},
        ]
    };

    const table = initDataTable('tableTriggerAction', purchaseTriggerActionConfig);

    let $submitNewTriggerAction = $modalNewTriggerAction.find('.submit-button');
    let urlNewTriggerAction = Routing.generate('trigger_action_new', true);
    InitModal($modalNewTriggerAction, $submitNewTriggerAction, urlNewTriggerAction, {tables: [table]});

    let $modalDeleteTriggerAction = $('#modalDeleteTriggerAction');
    let $submitDeleteTriggerAction = $('#submitDeleteTriggerAction');
    let urlDeleteTriggerAction = Routing.generate('trigger_action_delete', true);
    InitModal($modalDeleteTriggerAction, $submitDeleteTriggerAction, urlDeleteTriggerAction, {tables: [table]});

    let $modalEditTriggerAction = $('#modalEditTriggerAction');
    let $submitEditTriggerAction = $modalEditTriggerAction.find('.submit-button');
    let urlEditTriggerAction = Routing.generate('trigger_action_edit', true);
    InitModal($modalEditTriggerAction, $submitEditTriggerAction, urlEditTriggerAction, {tables: [table]});
});

function deleteRowLine(button, $submit) {
    let id = button.data('id');
    $submit.attr('value', id);
}

function submitSensor(val = null) {
    if(val) {
        const route = Routing.generate("get_sensor_by_name", {name: $sensorSelect.val() || $sensorInput.val()});

        $.get(route).then((html) => {
            const $sensorType = $modalNewTriggerAction.find('.sensor-type');
            $sensorType.empty();
            $sensorDetailsContainer.removeClass('d-none');
            const templatesSelect = $modalNewTriggerAction.find("select[name=templates]");
            templatesSelect.val(null).trigger('change');
            templatesSelect.attr('disabled', true);
            $sensorType.append(html);
        });
    }
}

function generateGetTemplatesRoute($select, selectName, route) {
    const templatesSelect = $modalNewTriggerAction.find(`select[name=${selectName}]`);
    $.post(route).then(({results}) => {
        templatesSelect.empty();
        for(let option of results) {
            templatesSelect.append(`<option value="${option['id']}">${option['text']}</option>`)
        }
        templatesSelect.attr('disabled', $select.val() === '');
    });
}

function onTemplateTypeChange($select){
    const route = Routing.generate(`get_templates`, {type: $select.val()});

    if($select.attr('name') === 'templateTypeHigherTemp' && $select.val() !== ''){
        generateGetTemplatesRoute($select, 'templatesForHigherTemp', route);
    } else if($select.attr('name') === 'templateTypeLowerTemp' && $select.val() !== ''){
        generateGetTemplatesRoute($select, 'templatesForLowerTemp', route);
    } else if($select.attr('name') === 'templateTypeHigherHygro' && $select.val() !== ''){
        generateGetTemplatesRoute($select, 'templatesForHigherHygro', route);
    } else if($select.attr('name') === 'templateTypeLowerHygro' && $select.val() !== ''){
        generateGetTemplatesRoute($select, 'templatesForLowerHygro', route);
    } else if($select.attr('name') === 'templateType' && $select.val() !== ''){
        generateGetTemplatesRoute($select, 'templates', route)
    }
}

function clearNewModal(clearReferenceInput = false){
    clearModal($modalNewTriggerAction);
    $modalNewTriggerAction.find('.sensor-details-container').addClass('d-none');
}

function initEditTriggerActionForm(){
    onTemplateTypeChange($modalEditTriggerAction.find('[name=templateType]'), true);
}

