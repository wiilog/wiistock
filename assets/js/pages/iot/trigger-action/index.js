const $modalNewTriggerAction = $('#modalNewTriggerAction');
const $modalEditTriggerAction = $('#modalEditTriggerAction');
const $sensorSelect = $modalNewTriggerAction.find('[name=sensorWrapper]');
const $sensorInput = $modalNewTriggerAction.find('[name=sensorCode]');
const $sensorDetailsContainer = $modalNewTriggerAction.find('.sensor-details-container');

global.submitSensor = submitSensor;
global.onTemplateTypeChange = onTemplateTypeChange;
global.clearNewModal = clearNewModal;

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
            {"data": 'sensorWrapper', 'name': 'Nom', 'title': 'Nom du capteur'},
            {"data": 'template', 'name': 'Modèle', 'title': 'Modèle'},
            {"data": 'templateType', 'name': 'Type de modèle', 'title': 'Type de modèle', orderable: false},
            {"data": 'threshold', 'name': 'Type de seuil', 'title': 'Type de seuil', orderable: false},
            {"data": 'lastTrigger', 'name': 'Date du dernier déclenchement', 'title': 'Date du dernier déclenchement'},
        ]
    };

    const table = initDataTable('tableTriggerAction', purchaseTriggerActionConfig);

    let $submitNewTriggerAction = $modalNewTriggerAction.find('.submit-button');
    let urlNewTriggerAction = Routing.generate('trigger_action_new', true);
    InitModal($modalNewTriggerAction, $submitNewTriggerAction, urlNewTriggerAction, {tables: [table]});
    $modalNewTriggerAction.on('change', '.trigger-action-data [name^=sensor]' , function () {
        const $dataInput = $(this);
        const $templateType = $dataInput.parents('.trigger-action-data').find('[name^=templateType]');
        $templateType.attr('disabled', !$dataInput.val());
        if (!$dataInput.val()) {
            $templateType.val(null).trigger('change');
        }
    });


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

function onTemplateTypeChange($select) {
    const $templatesSelect = $select.parents('.trigger-action-data').find('select[name^=templatesFor]');

    if ($select.val()) {
        AJAX
            .route(AJAX.GET, 'get_templates', {type: $select.val()})
            .json()
            .then(({results}) => {
                $templatesSelect.empty();
                for (let option of results) {
                    $templatesSelect.append(`<option value="${option['id']}">${option['text']}</option>`)
                }
            });
    } else {
        $templatesSelect.empty();
    }

    $templatesSelect.attr('disabled', !$select.val());
}

function clearNewModal(clearReferenceInput = false){
    clearModal($modalNewTriggerAction);
    $modalNewTriggerAction.find('.sensor-details-container').addClass('d-none');
}

