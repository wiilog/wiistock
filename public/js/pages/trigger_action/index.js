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
        const route = Routing.generate("get_sensor_by_name", {name: $sensorSelect.val() || $sensorInput.val()} );

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
    const type = $select.val();
    const $modal = $select.closest('.modal');
    const templatesSelect = $modal.find("select[name=templates]");

    templatesSelect.val(null).trigger(`change`);
    templatesSelect.attr('disabled', type === "");
    Select2Old.init(templatesSelect, "Sélectionner un modèle ...", 0, {
        route: "get_templates",
        param: {
            type: type,
        }
    });
}

function clearNewModal(clearReferenceInput = false){
    clearModal($modalNewTriggerAction);
    $modalNewTriggerAction.find('.sensor-details-container').addClass('d-none');
}

function initEditTriggerActionForm(){
    onTemplateTypeChange($modalEditTriggerAction.find('[name=templateType]'));
}

