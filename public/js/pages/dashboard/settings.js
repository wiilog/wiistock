const $modalComponentTypeFirstStep = $('#modalComponentTypeFistStep');
const $modalComponentTypeSecondStep = $('#modalComponentTypeSecondStep');

function openModalComponentTypeFirstStep() {
    $modalComponentTypeFirstStep.modal('show');
}

function openModalComponentTypeNextStep($button) {
    const firstStepIsShown = $modalComponentTypeFirstStep.hasClass('show');
    if (firstStepIsShown) {
        const componentTypeId = $button.data('component-type-id');

        const $componentTypeInput = $modalComponentTypeSecondStep.find('input[name="componentType"]');
        $componentTypeInput.val(componentTypeId);

        const $modalComponentTypeSecondStepContent = $modalComponentTypeSecondStep.find('.content');
        $modalComponentTypeSecondStepContent.html('');

        const apiRoute = Routing.generate('dashboard_component_type_form', {componentType: componentTypeId});

        wrapLoadingOnActionButton($button, () => $.get(
            apiRoute,
            {
                value: []
            },
            function (data) {
                $modalComponentTypeSecondStepContent.html(data.html);
                $modalComponentTypeFirstStep.modal('hide');
                $modalComponentTypeSecondStep.modal('show');
            },
            'json'
        ), false);
    }
}
